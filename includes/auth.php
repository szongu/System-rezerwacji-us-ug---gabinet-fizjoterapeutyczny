<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function currentRole(): ?string
{
    $user = currentUser();
    return $user['role'] ?? null;
}

function loginUser(array $userRow): void
{
    session_regenerate_id(true); 

    $_SESSION['user'] = [
        'id'         => (int) $userRow['id'],
        'first_name' => $userRow['first_name'],
        'last_name'  => $userRow['last_name'],
        'email'      => $userRow['email'],
        'role'       => $userRow['role'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
