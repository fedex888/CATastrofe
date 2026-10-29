<?php
// =====================================================================
// Sesión, roles y CSRF
// =====================================================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

function requerir_login(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'views/login.php');
        exit;
    }
}

function requerir_encargado(): void
{
    requerir_login();
    if (($_SESSION['rol'] ?? '') !== 'encargado') {
        $_SESSION['mensaje'] = [
            'tipo'  => 'error',
            'texto' => 'No tenés permisos para realizar esa acción.',
        ];
        header('Location: ' . BASE_URL . 'views/dashboard.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificar_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF inválido. Recargá la página e intentá de nuevo.');
    }
}