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
