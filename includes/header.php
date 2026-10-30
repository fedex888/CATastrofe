<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/funciones.php';
$m = mensaje_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Sistema') ?> · CATástrofe Petshop</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <span class="brand-icon">🐾</span>
        <div>
            <div class="brand-name">CATástrofe Petshop</div>
            <div class="brand-sub">Compras y Depósito</div>
        </div>
    </div>
    <div class="userbox">
        <div class="user-text">
            <div class="user-nombre"><?= e($_SESSION['nombre'] ?? '') ?></div>
            <div class="user-rol"><?= e($_SESSION['rol'] ?? '') ?></div>
        </div>
        <form action="<?= BASE_URL ?>views/logout.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="btn btn-ghost">Cerrar sesión</button>
        </form>
    </div>
</header>
<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <?php if ($m): ?>
            <div class="flash flash-<?= e($m['tipo']) ?>" data-flash><?= e($m['texto']) ?></div>
        <?php endif; ?>