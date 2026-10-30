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
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El email no es válido.');

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
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El email no es válido.');

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

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE proveedor_id = ? AND activo = 1');
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('No se puede dar de baja: tiene productos activos. Reasignalos a otro proveedor o dalos de baja primero.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordenes_compra WHERE proveedor_id = ? AND estado = 'pendiente'");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('No se puede dar de baja: tiene órdenes pendientes. Recibilas o cancelalas primero.');
        }

        $pdo->prepare('UPDATE proveedores SET activo = 0 WHERE id = ?')->execute([$id]);
        set_mensaje('ok', 'Proveedor dado de baja.');
        redirigir(BASE_URL . 'views/proveedores.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    // Errores de validación: se muestran; errores técnicos: se registran y se oculta el detalle.
    $msg = mensaje_error($t);
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/proveedores.php');
}