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
