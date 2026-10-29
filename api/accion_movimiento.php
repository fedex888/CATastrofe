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
    $producto_id = is_scalar($_POST['producto_id'] ?? null) ? (int)$_POST['producto_id'] : 0;
    $tipo        = is_scalar($_POST['tipo'] ?? null) ? (string)$_POST['tipo'] : '';
    $motivo_id   = is_scalar($_POST['motivo_id'] ?? null) ? (int)$_POST['motivo_id'] : 0;
    $cantidad    = is_scalar($_POST['cantidad'] ?? null) ? (float)$_POST['cantidad'] : 0;
    $fecha_venc  = is_scalar($_POST['fecha_vencimiento'] ?? null)
        ? trim((string)$_POST['fecha_vencimiento'])
        : '';

    if ($producto_id <= 0) throw new RuntimeException('Producto inválido.');
    if ($cantidad <= 0)    throw new RuntimeException('La cantidad debe ser mayor a 0.');
    if (!in_array($tipo, ['entrada', 'salida'], true)) throw new RuntimeException('Tipo inválido.');

    // Producto con su categoría
    $stmt = $pdo->prepare(
        'SELECT p.id, c.controla_vencimiento
         FROM productos p JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id = ? AND p.activo = 1'
    );
    $stmt->execute([$producto_id]);
    $prod = $stmt->fetch();
    if (!$prod) throw new RuntimeException('Producto no disponible.');

    // Motivo del tipo correcto y no de sistema
    $stmt = $pdo->prepare('SELECT * FROM motivos_movimiento WHERE id = ?');
    $stmt->execute([$motivo_id]);
    $motivo = $stmt->fetch();
    if (!$motivo) throw new RuntimeException('Motivo inválido.');
    if ($motivo['tipo'] !== $tipo) throw new RuntimeException('El motivo no corresponde al tipo elegido.');
    if ((int)$motivo['es_sistema'] === 1) throw new RuntimeException('Ese motivo no se puede usar manualmente.');

    if ($tipo === 'entrada' && (int)$prod['controla_vencimiento'] === 1) {
        if ($fecha_venc === '') throw new RuntimeException('La fecha de vencimiento es obligatoria.');
        if (!fecha_valida($fecha_venc)) throw new RuntimeException('Fecha de vencimiento inválida.');
    } else {
        $fecha_venc = null;
    }

    $pdo->beginTransaction();
    try {
        if ($tipo === 'entrada') {
            registrar_entrada(
                $pdo,
                $producto_id,
                (int)$_SESSION['usuario_id'],
                $motivo_id,
                $cantidad,
                $fecha_venc
            );
        } else {
            registrar_salida(
                $pdo,
                $producto_id,
                (int)$_SESSION['usuario_id'],
                $motivo_id,
                $cantidad
            );
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }

    set_mensaje('ok', 'Movimiento registrado correctamente.');
    redirigir(BASE_URL . 'views/movimientos.php');
} catch (Throwable $t) {
    set_mensaje('error', mensaje_error($t));
    redirigir(BASE_URL . 'views/movimientos.php');
}