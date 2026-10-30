<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'views/dashboard.php');
    exit;
}
$m = mensaje_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar · CATástrofe Petshop</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="login-page">
<div class="login-card">
    <div class="login-brand">
        <div class="brand-icon">🐾</div>
        <h1>CATástrofe Petshop</h1>
        <p>Compras y Depósito</p>
    </div>
    <?php if ($m): ?>
        <div class="flash flash-<?= e($m['tipo']) ?>"><?= e($m['texto']) ?></div>
    <?php endif; ?>
    <form action="<?= BASE_URL ?>api/accion_login.php" method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>
            Usuario
            <input type="text" name="usuario" required autofocus autocomplete="username">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
    </form>
    <p class="login-help">¿No tenés un usuario? Pedile uno a gerencia.</p>
</div>
</body>
</html>