<?php
require_once __DIR__ . '/_init.php';
$employeeId = (int) $currentEmployee['id'];
$adminOrSelfId = currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    requireValidCsrf();
    $reservationId = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];

    // Kontrola własności: pracownik może zmieniać WYŁĄCZNIE status wizyt, które sam obsługuje.
    $stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = :id AND employee_id = :employee_id');
    $stmt->execute(['id' => $reservationId, 'employee_id' => $employeeId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        setFlash('danger', 'Nie znaleziono wizyty lub nie masz do niej dostępu.');
    } elseif (!in_array($newStatus, $validStatuses, true)) {
        setFlash('danger', 'Nieprawidłowy status.');
    } else {
        $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id')
            ->execute(['status' => $newStatus, 'id' => $reservationId]);
        logStatusChange($reservationId, $reservation['status'], $newStatus, $adminOrSelfId);
        setFlash('success', 'Status wizyty został zaktualizowany.');
    }
    redirect(basePath('employee/dashboard.php?tab=' . urlencode($_GET['tab'] ?? 'today')));
}

$tab = $_GET['tab'] ?? 'today';
$today = date('Y-m-d');

$baseSql = "SELECT r.*, s.name AS service_name, s.price, u.first_name AS client_first, u.last_name AS client_last, u.phone AS client_phone
            FROM reservations r
            JOIN services s ON s.id = r.service_id
            JOIN users u ON u.id = r.user_id
            WHERE r.employee_id = :employee_id";

if ($tab === 'today') {
    $sql = $baseSql . " AND r.reservation_date = :today AND r.status != 'anulowana' ORDER BY r.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employeeId, 'today' => $today]);
} elseif ($tab === 'upcoming') {
    $sql = $baseSql . " AND r.reservation_date > :today AND r.status != 'anulowana' ORDER BY r.reservation_date, r.start_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employeeId, 'today' => $today]);
} else { // history
    $sql = $baseSql . " AND (r.reservation_date < :today OR r.status IN ('zrealizowana','anulowana')) ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employeeId, 'today' => $today]);
}
$reservations = $stmt->fetchAll();
$statuses = ['oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana'];

$pageTitle = 'Panel pracownika';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Panel pracownika - <?= e(currentUser()['first_name']) ?></h1>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'today' ? 'active' : '' ?>" href="?tab=today">Dzisiaj</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'upcoming' ? 'active' : '' ?>" href="?tab=upcoming">Przyszłe wizyty</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" href="?tab=history">Historia</a></li>
</ul>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Data</th><th>Godzina</th><th>Klient</th><th>Usługa</th><th>Cena</th><th>Status</th><th class="text-end">Zmień status</th></tr></thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
            <tr>
                <td><?= e(formatDate($r['reservation_date'])) ?></td>
                <td><?= e(formatTime($r['start_time'])) ?>-<?= e(formatTime($r['end_time'])) ?></td>
                <td><?= e($r['client_first'] . ' ' . $r['client_last']) ?><br><span class="small text-muted"><?= e($r['client_phone']) ?></span></td>
                <td><?= e($r['service_name']) ?></td>
                <td><?= formatPrice((float) $r['price']) ?></td>
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
                    <?php if (!empty($r['comment'])): ?>
                        <div class="small text-muted mt-1"><i class="bi bi-chat-left-text"></i> <?= e($r['comment']) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($reservations)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Brak wizyt w tej kategorii.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
