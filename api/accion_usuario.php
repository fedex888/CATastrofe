<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/usuarios.php');
}
verificar_csrf();

$accion = (string)($_POST['accion'] ?? '');

try {
    if ($accion === 'crear') {
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $usuario  = trim((string)($_POST['usuario'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rol      = (string)($_POST['rol'] ?? 'encargado');

        if ($nombre === '' || $usuario === '' || $password === '') {
            throw new RuntimeException('Todos los campos son obligatorios.');
        }
        
        if (!in_array($rol, ['encargado', 'gerencia'], true)) {
            throw new RuntimeException('Rol inválido.');
        }

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ?');
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Ese nombre de usuario ya está en uso. Elegí otro.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)')
            ->execute([$nombre, $usuario, $hash, $rol]);

        set_mensaje('ok', 'Usuario creado correctamente.');
        redirigir(BASE_URL . 'views/usuarios.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    // Manejo seguro: log interno y mensaje genérico al usuario
    error_log('Error en accion_usuario: ' . $t->getMessage());
    $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $t->getMessage() : 'Ocurrió un error al procesar el usuario.';
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/usuarios.php');
}