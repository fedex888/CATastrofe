<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/proveedores.php');
}
verificar_csrf();

$accion = (string)($_POST['accion'] ?? '');

try {
    if ($accion === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '') throw new RuntimeException('Falta el nombre.');
        $contacto = trim((string)($_POST['contacto'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $plazo    = max(0, (int)($_POST['plazo_entrega_dias'] ?? 7));

        $pdo->prepare(
            'INSERT INTO proveedores (nombre, contacto, telefono, email, plazo_entrega_dias, activo)
             VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([$nombre, $contacto, $telefono, $email, $plazo]);

        set_mensaje('ok', 'Proveedor creado.');
        redirigir(BASE_URL . 'views/proveedores.php');
    }

    if ($accion === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($id <= 0 || $nombre === '') throw new RuntimeException('Datos incompletos.');
        $contacto = trim((string)($_POST['contacto'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $plazo    = max(0, (int)($_POST['plazo_entrega_dias'] ?? 7));

        $stmt = $pdo->prepare(
            'UPDATE proveedores
             SET nombre = ?, contacto = ?, telefono = ?, email = ?, plazo_entrega_dias = ?
             WHERE id = ? AND activo = 1'
        );
        $stmt->execute([$nombre, $contacto, $telefono, $email, $plazo, $id]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('No se pudo actualizar (el proveedor pudo haber sido dado de baja).');
        }

        set_mensaje('ok', 'Proveedor actualizado.');
        redirigir(BASE_URL . 'views/proveedores.php');
    }

    if ($accion === 'baja') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Proveedor inválido.');
        $pdo->prepare('UPDATE proveedores SET activo = 0 WHERE id = ?')->execute([$id]);
        set_mensaje('ok', 'Proveedor dado de baja.');
        redirigir(BASE_URL . 'views/proveedores.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    error_log('Error en accion_proveedor: ' . $t->getMessage());
    $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $t->getMessage() : 'Ocurrió un error inesperado.';
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/proveedores.php');
}