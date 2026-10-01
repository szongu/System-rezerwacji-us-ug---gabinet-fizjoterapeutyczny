<?php
/** Skrót do htmlspecialchars() - używaj przy KAŻDYM wypisywaniu danych z bazy/użytkownika w HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Zapisuje komunikat wyświetlany raz, po przekierowaniu (wzorzec Post/Redirect/Get). */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Odczytuje i usuwa komunikat flash. Zwraca null, jeśli go nie ma. */
function getFlash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function formatPrice(float $price): string
{
    return number_format($price, 2, ',', ' ') . ' zł';
}

function formatDate(string $date): string
{
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
}

function formatTime(string $time): string
{
    // Akceptuje zarówno "HH:MM:SS" jak i "HH:MM".
    return substr($time, 0, 5);
}

function formatDateTime(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts ? date('d.m.Y H:i', $ts) : $datetime;
}


/** Mapowanie statusu z bazy (bez polskich znaków) na czytelną etykietę PL. */
function statusLabel(string $status): string
{
    $labels = [
        'oczekujaca'   => 'Oczekująca',
        'potwierdzona' => 'Potwierdzona',
        'zrealizowana' => 'Zrealizowana',
        'anulowana'    => 'Anulowana',
    ];
    return $labels[$status] ?? $status;
}

/** Klasa koloru Bootstrap (badge) dla danego statusu rezerwacji. */
function statusBadgeClass(string $status): string
{
    $classes = [
        'oczekujaca'   => 'bg-warning text-dark',
        'potwierdzona' => 'bg-success',
        'zrealizowana' => 'bg-secondary',
        'anulowana'    => 'bg-danger',
    ];
    return $classes[$status] ?? 'bg-light text-dark';
}

/** Nazwa dnia tygodnia dla wartości 1 (poniedziałek) .. 7 (niedziela). */
function dayName(int $dayOfWeek): string
{
    $names = [
        1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek',
        5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela',
    ];
    return $names[$dayOfWeek] ?? '?';
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Prosta walidacja polskiego numeru telefonu: 9 cyfr, opcjonalne spacje/myślniki/+48. */
function isValidPhone(string $phone): bool
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    $digits = preg_replace('/^48/', '', $digits); // usuń prefiks kraju, jeśli podany
    return preg_match('/^\d{9}$/', $digits) === 1;
}

/** Silne hasło: min. 8 znaków, przynajmniej jedna litera i jedna cyfra.*/
function isStrongPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-ząćęłńóśźż]/i', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

/** Zapisuje wpis w logu działań administratora (funkcja dodatkowa - historia operacji). */
function logAdminAction(int $adminId, string $action): void
{
    try {
        $stmt = getDb()->prepare('INSERT INTO admin_log (admin_id, action) VALUES (:admin_id, :action)');
        $stmt->execute(['admin_id' => $adminId, 'action' => $action]);
    } catch (PDOException $e) {
        error_log('Nie udalo sie zapisac wpisu admin_log: ' . $e->getMessage());
    }
}

/** Zapisuje zmianę statusu rezerwacji w historii (funkcja dodatkowa). */
function logStatusChange(int $reservationId, ?string $oldStatus, string $newStatus, ?int $changedBy): void
{
    try {
        $stmt = getDb()->prepare(
            'INSERT INTO reservation_status_history (reservation_id, old_status, new_status, changed_by)
             VALUES (:reservation_id, :old_status, :new_status, :changed_by)'
        );
        $stmt->execute([
            'reservation_id' => $reservationId,
            'old_status'     => $oldStatus,
            'new_status'     => $newStatus,
            'changed_by'     => $changedBy,
        ]);
    } catch (PDOException $e) {
        error_log('Nie udalo sie zapisac historii statusu: ' . $e->getMessage());
    }
}
