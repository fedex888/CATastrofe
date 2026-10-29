<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/login.php');
}
verificar_csrf();

$usuario  = trim((string)($_POST['usuario'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($usuario === '' || $password === '') {
    set_mensaje('error', 'Usuario o contraseña incorrectos.');
    redirigir(BASE_URL . 'views/login.php');
}

// MODIFICADO: Solo busca usuarios activos
$stmt = $pdo->prepare('SELECT id, nombre, usuario, password_hash, rol FROM usuarios WHERE usuario = ? AND activo = 1');
$stmt->execute([$usuario]);
$u = $stmt->fetch();

if (!$u || !password_verify($password, $u['password_hash'])) {
    set_mensaje('error', 'Usuario o contraseña incorrectos.');
    redirigir(BASE_URL . 'views/login.php');
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int)$u['id'];
$_SESSION['nombre']     = $u['nombre'];
$_SESSION['rol']        = $u['rol'];
unset($_SESSION['csrf_token']);

redirigir(BASE_URL . 'views/dashboard.php');