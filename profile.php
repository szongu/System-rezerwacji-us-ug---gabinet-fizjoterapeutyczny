<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$pdo = getDb();
$userId = currentUser()['id'];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();



$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if ($firstName === '' || mb_strlen($firstName) > 60) {
            $errors[] = 'Podaj poprawne imię.';
        }
        if ($lastName === '' || mb_strlen($lastName) > 60) {
            $errors[] = 'Podaj poprawne nazwisko.';
        }
        if (!isValidPhone($phone)) {
            $errors[] = 'Podaj poprawny numer telefonu (9 cyfr).';
        }

        if (empty($errors)) {
            $update = $pdo->prepare(
                'UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone WHERE id = :id'
            );
            $update->execute(['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone, 'id' => $userId]);

            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name']  = $lastName;

            $success = 'Dane profilu zostały zaktualizowane.';
            $user['first_name'] = $firstName;
            $user['last_name'] = $lastName;
            $user['phone'] = $phone;
        }
    }

    if ($formType === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $new2    = (string) ($_POST['new_password_confirm'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Aktualne hasło jest nieprawidłowe.';
        } elseif (!isStrongPassword($new)) {
            $errors[] = 'Nowe hasło musi mieć min. 8 znaków oraz zawierać literę i cyfrę.';
        } elseif ($new !== $new2) {
            $errors[] = 'Nowe hasła nie są identyczne.';
        } else {
            $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]);
            $success = 'Hasło zostało zmienione.';
        }
    }
}

$pageTitle = 'Moje konto';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4">Moje konto</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Dane profilu</div>
            <div class="card-body">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_type" value="profile">
                    <div class="mb-3">
                        <label class="form-label">Imię</label>
                        <input type="text" name="first_name" class="form-control" required maxlength="60"
                               value="<?= e($user['first_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nazwisko</label>
                        <input type="text" name="last_name" class="form-control" required maxlength="60"
                               value="<?= e($user['last_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                        <div class="form-text">Zmiana adresu e-mail nie jest obsługiwana w tej wersji systemu.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" required value="<?= e($user['phone']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rola</label>
                        <div><span class="badge bg-primary badge-role"><?= e($user['role']) ?></span></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Zmiana hasła</div>
            <div class="card-body">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_type" value="password">
                    <div class="mb-3">
                        <label class="form-label">Aktualne hasło</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nowe hasło</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Powtórz nowe hasło</label>
                        <input type="password" name="new_password_confirm" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Zmień hasło</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
