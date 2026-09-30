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
