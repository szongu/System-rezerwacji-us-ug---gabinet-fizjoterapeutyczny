<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();
$adminId = currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    requireValidCsrf();
    $id = (int) $_POST['id'];
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];

    if (in_array($newStatus, $validStatuses, true)) {
        $current = $pdo->prepare('SELECT status FROM reservations WHERE id = :id');
        $current->execute(['id' => $id]);
        $oldStatus = $current->fetchColumn();

        if ($oldStatus !== false) {
            $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id')
                ->execute(['status' => $newStatus, 'id' => $id]);
            logStatusChange($id, $oldStatus, $newStatus, $adminId);
            logAdminAction($adminId, "Zmieniono status rezerwacji #$id na $newStatus");
            setFlash('success', 'Status rezerwacji został zaktualizowany.');
        }
    }
    redirect(basePath('admin/reservations.php') . '?' . http_build_query($_GET));
}

// --- Filtry ---
$filterDate   = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterEmp    = (int) ($_GET['employee_id'] ?? 0);
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 15;

$where = ['1=1'];
$params = [];

if ($filterDate !== '') {
    $where[] = 'r.reservation_date = :date';
    $params['date'] = $filterDate;
}
if (in_array($filterStatus, ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'], true)) {
    $where[] = 'r.status = :status';
    $params['status'] = $filterStatus;
}
if ($filterEmp > 0) {
    $where[] = 'r.employee_id = :employee_id';
    $params['employee_id'] = $filterEmp;
}
if ($search !== '') {
    $where[] = '(u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations r
     JOIN users u ON u.id = r.user_id
     WHERE $whereSql"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT r.*, s.name AS service_name, s.price AS service_price, u.first_name AS client_first, u.last_name AS client_last, u.email AS client_email,
                   eu.first_name AS emp_first, eu.last_name AS emp_last
            FROM reservations r
            JOIN services s ON s.id = r.service_id
            JOIN users u ON u.id = r.user_id
            JOIN employees e ON e.id = r.employee_id
            JOIN users eu ON eu.id = e.user_id
            WHERE $whereSql
            ORDER BY r.reservation_date DESC, r.start_time DESC
            LIMIT $perPage OFFSET $offset";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$reservations = $listStmt->fetchAll();

$employees = $pdo->query(
    "SELECT e.id, u.first_name, u.last_name FROM employees e JOIN users u ON u.id = e.user_id ORDER BY u.first_name"
)->fetchAll();

$statuses = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];

$pageTitle = 'Rezerwacje';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Rezerwacje</h1>

<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
        <label class="form-label small mb-0">Data</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">Wszystkie</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= statusLabel($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Pracownik</label>
        <select name="employee_id" class="form-select form-select-sm">
            <option value="0">Wszyscy</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>" <?= $filterEmp === (int) $emp['id'] ? 'selected' : '' ?>>
                    <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">Klient</label>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Imię, nazwisko, e-mail" value="<?= e($search) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filtruj</button>
        <a href="<?= e(basePath('admin/reservations.php')) ?>" class="btn btn-sm btn-outline-secondary">Wyczyść</a>
    </div>
</form>

<p class="text-muted small">Znaleziono <?= $totalRows ?> rezerwacji.</p>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Data</th><th>Godzina</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th class="text-end">Zmień status</th></tr></thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= e(formatDate($r['reservation_date'])) ?></td>
                <td><?= e(formatTime($r['start_time'])) ?>-<?= e(formatTime($r['end_time'])) ?></td>
                <td><?= e($r['client_first'] . ' ' . $r['client_last']) ?><br><span class="small text-muted"><?= e($r['client_email']) ?></span></td>
                <td><?= e($r['service_name']) ?></td>
                <td><?= e($r['emp_first'] . ' ' . $r['emp_last']) ?></td>
                <td><?= formatPrice((float) $r['service_price']) ?></td>
                <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= statusLabel($r['status']) ?></span></td>
                <td class="text-end">
                    <form method="post" class="d-flex gap-1 justify-content-end">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <select name="status" class="form-select form-select-sm" style="width:auto;">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= statusLabel($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Zapisz</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($reservations)): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">Brak rezerwacji spełniających kryteria.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php $q = array_merge($_GET, ['page' => $p]); ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= e(http_build_query($q)) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
