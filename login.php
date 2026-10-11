<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(basePath('index.php'));
}

$errors = [];
$email = '';

// Proste ograniczenie liczby prób logowania w obrębie sesji (funkcja dodatkowa).
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (time() < $_SESSION['login_locked_until']) {
        $errors[] = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za chwilę.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $stmt = getDb()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && $user['active'] && password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = 0;
            loginUser($user);

            $redirects = [
                'admin'    => basePath('admin/dashboard.php'),
                'employee' => basePath('employee/dashboard.php'),
                'client'   => basePath('index.php'),
            ];
            setFlash('success', 'Zalogowano pomyślnie. Witaj, ' . $user['first_name'] . '!');
            redirect($redirects[$user['role']] ?? basePath('index.php'));
        }

        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }

        $errors[] = 'Nieprawidłowy e-mail lub hasło.';
    }
}

$pageTitle = 'Logowanie';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <h1 class="h3 mb-4">Zaloguj się</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Adres e-mail</label>
                <input type="email" name="email" class="form-control" required value="<?= e($email) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Hasło</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Zaloguj się</button>
        </form>

        <p class="text-center mt-3 mb-1">
            Nie masz konta? <a href="<?= e(basePath('register.php')) ?>">Zarejestruj się</a>
        </p>
        <p class="text-center text-muted small">
            Konta testowe: admin@gabinet.pl / piotr.nowak@gabinet.pl / jan.kowalczyk@example.com<br>
            hasło dla wszystkich: <code>Test1234!</code>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
