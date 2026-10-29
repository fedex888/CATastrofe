<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/movimientos.php');
}
verificar_csrf();

try {
    $producto_id = (int)($_POST['producto_id'] ?? 0);
    $tipo        = (string)($_POST['tipo'] ?? '');
    $motivo_id   = (int)($_POST['motivo_id'] ?? 0);
    $cantidad    = (float)($_POST['cantidad'] ?? 0);
    $fecha_venc  = trim((string)($_POST['fecha_vencimiento'] ?? ''));

    if ($producto_id <= 0) throw new RuntimeException('Producto inválido.');
    if ($cantidad <= 0)    throw new RuntimeException('La cantidad debe ser mayor a 0.');
    if (!in_array($tipo, ['entrada', 'salida'], true)) throw new RuntimeException('Tipo inválido.');

    $stmt = $pdo->prepare('SELECT p.id, c.controla_vencimiento FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ? AND p.activo = 1');
    $stmt->execute([$producto_id]);
    $prod = $stmt->fetch();
    if (!$prod) throw new RuntimeException('Producto no disponible.');

    $stmt = $pdo->prepare('SELECT * FROM motivos_movimiento WHERE id = ?');
    $stmt->execute([$motivo_id]);
    $motivo = $stmt->fetch();
    if (!$motivo) throw new RuntimeException('Motivo inválido.');
    if ($motivo['tipo'] !== $tipo) throw new RuntimeException('El motivo no corresponde al tipo elegido.');
    if ((int)$motivo['es_sistema'] === 1) throw new RuntimeException('Ese motivo no se puede usar manualmente.');

    if ($tipo === 'entrada' && (int)$prod['controla_vencimiento'] === 1) {
        if ($fecha_venc === '') throw new RuntimeException('La fecha de vencimiento es obligatoria.');
        
        // NUEVA VALIDACIÓN: No fechas pasadas
        if (strtotime($fecha_venc) < strtotime(date('Y-m-d'))) {
            throw new RuntimeException('La fecha de vencimiento no puede ser una fecha pasada.');
        }
    } else {
        $fecha_venc = null;
    }

    $pdo->beginTransaction();
    try {
        if ($tipo === 'entrada') {
            registrar_entrada($pdo, $producto_id, (int)$_SESSION['usuario_id'], $motivo_id, $cantidad, $fecha_venc);
        } else {
            registrar_salida($pdo, $producto_id, (int)$_SESSION['usuario_id'], $motivo_id, $cantidad);
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }

    set_mensaje('ok', 'Movimiento registrado correctamente.');
    redirigir(BASE_URL . 'views/movimientos.php');
} catch (Throwable $t) {
    error_log('Error en accion_movimiento: ' . $t->getMessage());
    $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $t->getMessage() : 'Ocurrió un error al procesar el movimiento.';
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/movimientos.php');
}