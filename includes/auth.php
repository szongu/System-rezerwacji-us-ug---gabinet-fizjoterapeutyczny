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

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . basePath('login.php'));
        exit;
    }
}

function requireRole($roles): void
{
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}


function basePath(string $path = ''): string
{

    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}


function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrf(): void
{
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        die('Nieprawidłowy token bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.');
    }
}
