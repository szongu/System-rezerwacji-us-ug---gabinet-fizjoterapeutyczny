<?php
require_once __DIR__ . '/_init.php';
$pdo = getDb();
$adminId = currentUser()['id'];

$errors = [];
$formValues = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    requireValidCsrf();

    $formValues['first_name']  = trim($_POST['first_name'] ?? '');
    $formValues['last_name']   = trim($_POST['last_name'] ?? '');
    $formValues['email']       = trim($_POST['email'] ?? '');
    $formValues['phone']       = trim($_POST['phone'] ?? '');
    $formValues['description'] = trim($_POST['description'] ?? '');

    if ($formValues['first_name'] === '' || $formValues['last_name'] === '') {
        $errors[] = 'Podaj imię i nazwisko pracownika.';
    }
    if (!isValidEmail($formValues['email'])) {
        $errors[] = 'Podaj poprawny adres e-mail.';
    }
    if (!isValidPhone($formValues['phone'])) {
        $errors[] = 'Podaj poprawny numer telefonu.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $formValues['email']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'Ten adres e-mail jest już zajęty.';
        }
    }

    if (empty($errors)) {
        $tempPassword = bin2hex(random_bytes(4)); // losowe hasło startowe, pracownik powinien je zmienić
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, phone, role, active)
                 VALUES (:fn, :ln, :email, :hash, :phone, "employee", 1)'
            );
            $stmt->execute([
                'fn' => $formValues['first_name'], 'ln' => $formValues['last_name'],
                'email' => $formValues['email'], 'hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
                'phone' => $formValues['phone'],
            ]);
            $newUserId = (int) $pdo->lastInsertId();

            $stmt2 = $pdo->prepare('INSERT INTO employees (user_id, description, active) VALUES (:uid, :desc, 1)');
            $stmt2->execute(['uid' => $newUserId, 'desc' => $formValues['description']]);

            $pdo->commit();
            logAdminAction($adminId, 'Dodano pracownika: ' . $formValues['first_name'] . ' ' . $formValues['last_name']);
            setFlash('success', 'Pracownik został dodany. Tymczasowe hasło logowania: ' . $tempPassword . ' (przekaż je pracownikowi, powinien je zmienić po zalogowaniu).');
            redirect(basePath('admin/employees.php'));
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Nie udało się utworzyć pracownika. Spróbuj ponownie.';
        }
    }
}

$employees = $pdo->query(
    "SELECT e.id, e.active, u.first_name, u.last_name, u.email, u.phone,
            (SELECT COUNT(*) FROM employee_services es WHERE es.employee_id = e.id) AS services_count
     FROM employees e JOIN users u ON u.id = e.user_id
     ORDER BY u.first_name, u.last_name"
)->fetchAll();

$pageTitle = 'Pracownicy';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<h1 class="h3 mb-4">Pracownicy</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Dodaj nowego pracownika</div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-3">
                <label class="form-label">Imię</label>
                <input type="text" name="first_name" class="form-control" required value="<?= e($formValues['first_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Nazwisko</label>
                <input type="text" name="last_name" class="form-control" required value="<?= e($formValues['last_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" required value="<?= e($formValues['email']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" required value="<?= e($formValues['phone']) ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label">Opis / specjalizacja</label>
                <input type="text" name="description" class="form-control" value="<?= e($formValues['description']) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Dodaj pracownika</button>
            </div>
        </form>
        <p class="form-text mb-0 mt-2">System wygeneruje tymczasowe hasło logowania, które zostanie wyświetlone po dodaniu.</p>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead><tr><th>Imię i nazwisko</th><th>Kontakt</th><th>Przypisane usługi</th><th>Status</th><th class="text-end">Akcje</th></tr></thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
            <tr>
                <td><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                <td class="small text-muted"><?= e($emp['email']) ?><br><?= e($emp['phone']) ?></td>
                <td><?= (int) $emp['services_count'] ?></td>
                <td><span class="badge <?= $emp['active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $emp['active'] ? 'Aktywny' : 'Nieaktywny' ?></span></td>
                <td class="text-end">
                    <a href="<?= e(basePath('admin/employee_edit.php?id=' . $emp['id'])) ?>" class="btn btn-sm btn-outline-primary">Zarządzaj</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($employees)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Brak pracowników.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/_nav_end.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
