<?php
require_once __DIR__ . '/config.php';

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Nie ujawniamy szczegółów technicznych użytkownikowi.
            error_log('Blad polaczenia z baza danych: ' . $e->getMessage());
            http_response_code(500);
            die('Wystąpił błąd serwera. Spróbuj ponownie później.');
        }
    }

    return $pdo;
}
