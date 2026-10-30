<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// El cierre de sesión es un POST con token CSRF (un link GET permitiría cerrar la sesión ajena).
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
if (!empty($_SESSION['usuario_id'])) {
    verificar_csrf();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . BASE_URL . 'views/login.php');
exit;
