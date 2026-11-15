<?php
/** Wspólny start stron panelu pracownika: sesja, rola "employee" i ustalenie employees.id. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';
requireRole('employee');

$pdo = getDb();
$stmt = $pdo->prepare('SELECT * FROM employees WHERE user_id = :user_id');
$stmt->execute(['user_id' => currentUser()['id']]);
$currentEmployee = $stmt->fetch();

if (!$currentEmployee) {
    // Konto ma rolę "employee", ale brak powiązanego rekordu w employees - błąd danych.
    http_response_code(500);
    die('Błąd konfiguracji konta pracownika. Skontaktuj się z administratorem.');
}
