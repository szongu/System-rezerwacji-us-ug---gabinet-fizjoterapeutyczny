<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();
$adminId = currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    requireValidCsrf();
    $id = (int) $_POST['id'];

    if ($id === $adminId) {
        setFlash('danger', 'Nie możesz dezaktywować własnego konta.');
    } else {
        $pdo->prepare('UPDATE users SET active = NOT active WHERE id = :id')->execute(['id' => $id]);
        logAdminAction($adminId, "Zmieniono status aktywności użytkownika #$id");
        setFlash('success', 'Status konta został zmieniony.');
    }
    redirect(basePath('admin/users.php' . (isset($_GET['role']) ? '?role=' . urlencode($_GET['role']) : '')));
}

$roleFilter = $_GET['role'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT id, first_name, last_name, email, phone, role, active, created_at FROM users WHERE 1=1';
$params = [];

if (in_array($roleFilter, ['client', 'employee', 'admin'], true)) {
    $sql .= ' AND role = :role';
    $params['role'] = $roleFilter;
}
if ($search !== '') {
    $sql .= ' AND (first_name LIKE :q OR last_name LIKE :q OR email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Użytkownicy';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Użytkownicy</h1>

<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Wszystkie role</option>
            <option value="client" <?= $roleFilter === 'client' ? 'selected' : '' ?>>Klient</option>
            <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Pracownik</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>
    </div>
    <div class="col-auto">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj po imieniu/e-mailu" value="<?= e($search) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filtruj</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Imię i nazwisko</th><th>E-mail</th><th>Telefon</th><th>Rola</th><th>Utworzono</th><th>Status</th><th class="text-end">Akcje</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['phone']) ?></td>
                <td><span class="badge bg-primary badge-role"><?= e($u['role']) ?></span></td>
                <td class="small text-muted"><?= e(formatDate($u['created_at'])) ?></td>
                <td><span class="badge <?= $u['active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $u['active'] ? 'Aktywny' : 'Nieaktywny' ?></span></td>
                <td class="text-end">
                    <form method="post" class="d-inline" onsubmit="return confirm('Zmienić status tego konta?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $u['id'] === $adminId ? 'disabled' : '' ?>>
                            <?= $u['active'] ? 'Dezaktywuj' : 'Aktywuj' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Brak wyników.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
