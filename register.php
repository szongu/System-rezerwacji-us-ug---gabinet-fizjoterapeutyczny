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
