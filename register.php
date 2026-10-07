<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect(basePath('index.php'));
}

$errors = [];
$values = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $values['first_name'] = trim($_POST['first_name'] ?? '');
    $values['last_name']  = trim($_POST['last_name'] ?? '');
    $values['email']      = trim($_POST['email'] ?? '');
    $values['phone']      = trim($_POST['phone'] ?? '');
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_confirm'] ?? '');

    if ($values['first_name'] === '' || mb_strlen($values['first_name']) > 60) {
        $errors[] = 'Podaj poprawne imię.';
    }
    if ($values['last_name'] === '' || mb_strlen($values['last_name']) > 60) {
        $errors[] = 'Podaj poprawne nazwisko.';
    }
    if (!isValidEmail($values['email'])) {
        $errors[] = 'Podaj poprawny adres e-mail.';
    }
    if (!isValidPhone($values['phone'])) {
        $errors[] = 'Podaj poprawny numer telefonu (9 cyfr).';
    }
    if (!isStrongPassword($password)) {
        $errors[] = 'Hasło musi mieć min. 8 znaków oraz zawierać literę i cyfrę.';
    }
    if ($password !== $password2) {
        $errors[] = 'Hasła nie są identyczne.';
    }

    if (empty($errors)) {
        $pdo = getDb();
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $values['email']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'Ten adres e-mail jest już zarejestrowany.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, phone, role, active)
             VALUES (:first_name, :last_name, :email, :password_hash, :phone, "client", 1)'
        );
        $stmt->execute([
            'first_name'    => $values['first_name'],
            'last_name'     => $values['last_name'],
            'email'         => $values['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'phone'         => $values['phone'],
        ]);

        $newUserId = (int) $pdo->lastInsertId();
        $userRow = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $userRow->execute(['id' => $newUserId]);
        loginUser($userRow->fetch());

        setFlash('success', 'Konto zostało utworzone. Witamy!');
        redirect(basePath('index.php'));
    }
}

$pageTitle = 'Rejestracja';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <h1 class="h3 mb-4">Załóż konto klienta</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Imię</label>
                    <input type="text" name="first_name" class="form-control" required maxlength="60"
                           value="<?= e($values['first_name']) ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Nazwisko</label>
                    <input type="text" name="last_name" class="form-control" required maxlength="60"
                           value="<?= e($values['last_name']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Adres e-mail</label>
                <input type="email" name="email" class="form-control" required maxlength="150"
                       value="<?= e($values['email']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" required placeholder="np. 600100200"
                       value="<?= e($values['phone']) ?>">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Hasło</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="col-6">
                    <label class="form-label">Powtórz hasło</label>
                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                </div>
            </div>
            <p class="form-text">Hasło: min. 8 znaków, przynajmniej jedna litera i jedna cyfra.</p>
            <button type="submit" class="btn btn-primary w-100">Zarejestruj się</button>
        </form>

        <p class="text-center mt-3 mb-0">
            Masz już konto? <a href="<?= e(basePath('login.php')) ?>">Zaloguj się</a>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
