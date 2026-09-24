<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

function require_login(): void
{
    start_secure_session();

    if (empty($_SESSION['user_id'])) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $inSubdir = preg_match('#/(admin|api)(/|$)#', $script) === 1;
        header('Location: ' . ($inSubdir ? '../login.php' : 'login.php'));
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Zugriff verweigert.');
    }
}
