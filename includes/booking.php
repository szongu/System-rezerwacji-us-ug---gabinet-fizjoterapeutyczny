<?php
/**
 * Logika procesu rezerwacji: wyznaczanie dostępnych terminów oraz wykrywanie
 * konfliktów. To jest najważniejsza logika biznesowa całego projektu (Etap 4).
 *
 * Zasady:
 *  - Dostępność wynika z godzin pracy pracownika (employee_availability) w danym
 *    dniu tygodnia, pomniejszonych o urlopy (employee_time_off) i istniejące
 *    rezerwacje o statusie innym niż "anulowana".
 *  - Dwa przedziały kolidują, gdy: nowy.start < istniejący.end ORAZ nowy.end > istniejący.start
 *    (przedziały stykające się dokładnie końcem/początkiem są dozwolone).
 *  - Nie można rezerwować w przeszłości.
 */

/** Zwraca aktywnych pracowników wykonujących daną (aktywną) usługę. */
function getEmployeesForService(PDO $pdo, int $serviceId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.id, u.first_name, u.last_name, e.description
         FROM employees e
         JOIN users u ON u.id = e.user_id
         JOIN employee_services es ON es.employee_id = e.id
         WHERE es.service_id = :service_id AND e.active = 1 AND u.active = 1
         ORDER BY u.first_name, u.last_name'
    );
    $stmt->execute(['service_id' => $serviceId]);
    return $stmt->fetchAll();
}

/** Okna godzin pracy pracownika dla konkretnego dnia tygodnia (1=pon .. 7=niedz). */
function getWorkingWindows(PDO $pdo, int $employeeId, int $dayOfWeek): array
{
    $stmt = $pdo->prepare(
        'SELECT start_time, end_time FROM employee_availability
         WHERE employee_id = :employee_id AND day_of_week = :day
         ORDER BY start_time'
    );
    $stmt->execute(['employee_id' => $employeeId, 'day' => $dayOfWeek]);
    return $stmt->fetchAll();
}

/** Czy dany dzień jest dla pracownika urlopem / dniem wolnym. */
function isOnTimeOff(PDO $pdo, int $employeeId, string $date): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM employee_time_off
         WHERE employee_id = :employee_id AND :date BETWEEN date_from AND date_to'
    );
    $stmt->execute(['employee_id' => $employeeId, 'date' => $date]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Już zajęte przedziały (rezerwacje aktywne, czyli nie anulowane) danego dnia. */
function getBookedRanges(PDO $pdo, int $employeeId, string $date, ?int $excludeReservationId = null): array
{
    $sql = 'SELECT start_time, end_time FROM reservations
            WHERE employee_id = :employee_id AND reservation_date = :date AND status != "anulowana"';
    $params = ['employee_id' => $employeeId, 'date' => $date];

    if ($excludeReservationId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params['exclude_id'] = $excludeReservationId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Reguła nakładania przedziałów: koliduje, jeśli start < end2 AND end > start2. */
function rangesOverlap(string $startA, string $endA, string $startB, string $endB): bool
{
    return $startA < $endB && $endA > $startB;
}

/**
 * Generuje listę dostępnych (wolnych) terminów dla pracownika, usługi i daty.
 * Krok siatki (co ile minut proponujemy start wizyty) - domyślnie 15 minut.
 *
 * @return array<int, array{start:string,end:string}> godziny w formacie H:i
 */
function getAvailableSlots(PDO $pdo, int $employeeId, int $serviceId, string $date, int $stepMinutes = 15): array
{
    $today = date('Y-m-d');
    if ($date < $today) {
        return []; // nie rezerwujemy w przeszłości
    }

    $serviceStmt = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = :id AND active = 1');
    $serviceStmt->execute(['id' => $serviceId]);
    $duration = $serviceStmt->fetchColumn();
    if (!$duration) {
        return [];
    }
    $duration = (int) $duration;

    if (isOnTimeOff($pdo, $employeeId, $date)) {
        return [];
    }

    $dayOfWeek = (int) date('N', strtotime($date));
    $windows = getWorkingWindows($pdo, $employeeId, $dayOfWeek);
    if (empty($windows)) {
        return [];
    }

    $booked = getBookedRanges($pdo, $employeeId, $date);
    $nowTime = ($date === $today) ? date('H:i:s') : null;

    $slots = [];
    foreach ($windows as $window) {
        $cursor = strtotime($date . ' ' . $window['start_time']);
        $windowEnd = strtotime($date . ' ' . $window['end_time']);

        while ($cursor + $duration * 60 <= $windowEnd) {
            $slotStart = date('H:i:s', $cursor);
            $slotEnd = date('H:i:s', $cursor + $duration * 60);

            $isPast = $nowTime !== null && $slotStart <= $nowTime;
            $isConflict = false;
            foreach ($booked as $b) {
                if (rangesOverlap($slotStart, $slotEnd, $b['start_time'], $b['end_time'])) {
                    $isConflict = true;
                    break;
                }
            }

            if (!$isPast && !$isConflict) {
                $slots[] = ['start' => substr($slotStart, 0, 5), 'end' => substr($slotEnd, 0, 5)];
            }

            $cursor += $stepMinutes * 60;
        }
    }

    return $slots;
}

/**
 * Ostateczna, autorytatywna kontrola konfliktu tuż przed zapisem do bazy
 * (wykonywana wewnątrz transakcji, patrz reservation_new.php). Nie polega na
 * danych z formularza poza samymi wartościami, które i tak są tu ponownie
 * weryfikowane względem bazy.
 */
function hasBookingConflict(PDO $pdo, int $employeeId, string $date, string $start, string $end, ?int $excludeReservationId = null): bool
{
    foreach (getBookedRanges($pdo, $employeeId, $date, $excludeReservationId) as $b) {
        if (rangesOverlap($start, $end, $b['start_time'], $b['end_time'])) {
            return true;
        }
    }
    return false;
}

/** Sprawdza, czy podany przedział mieści się w którymkolwiek oknie godzin pracy danego dnia. */
function isWithinWorkingHours(PDO $pdo, int $employeeId, string $date, string $start, string $end): bool
{
    if (isOnTimeOff($pdo, $employeeId, $date)) {
        return false;
    }
    $dayOfWeek = (int) date('N', strtotime($date));
    foreach (getWorkingWindows($pdo, $employeeId, $dayOfWeek) as $w) {
        if ($start >= $w['start_time'] && $end <= $w['end_time']) {
            return true;
        }
    }
    return false;
}
