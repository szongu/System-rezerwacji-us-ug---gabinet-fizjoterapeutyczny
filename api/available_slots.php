<?php
/**
 * Punkt końcowy AJAX: zwraca w formacie JSON listę wolnych terminów dla
 * wybranej usługi, pracownika i daty. Wykorzystywane przez reservation_new.php,
 * żeby dynamicznie pobierać dostępne godziny bez przeładowania strony
 * (funkcja dodatkowa: AJAX i dynamiczne pobieranie terminów).
 *
 * Zwracane dane są tylko informacyjne / do wygody UI - ostateczna kontrola
 * konfliktu i tak zawsze wykonywana jest ponownie po stronie serwera
 * bezpośrednio przed zapisem rezerwacji (patrz reservation_new.php).
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin(); // dostępność terminów widoczna tylko dla zalogowanych (spójne z resztą procesu rezerwacji)

$employeeId = (int) ($_GET['employee_id'] ?? 0);
$serviceId  = (int) ($_GET['service_id'] ?? 0);
$date       = $_GET['date'] ?? '';

if ($employeeId <= 0 || $serviceId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nieprawidłowe parametry zapytania.']);
    exit;
}

try {
    $slots = getAvailableSlots(getDb(), $employeeId, $serviceId, $date);
    echo json_encode(['slots' => $slots]);
} catch (Throwable $e) {
    error_log('Blad api/available_slots.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Wystąpił błąd podczas pobierania dostępności.']);
}
