<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/ordenes.php');
}
verificar_csrf();

$accion = (string)($_POST['accion'] ?? '');
$usuario_id = (int)$_SESSION['usuario_id'];

try {
    if ($accion === 'crear') {
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
        $cantidades   = $_POST['cantidades'] ?? [];

        if ($proveedor_id <= 0) throw new RuntimeException('Proveedor inválido.');
        if (!is_array($cantidades) || count($cantidades) === 0) throw new RuntimeException('No se ingresaron cantidades.');

        $stmt = $pdo->prepare('SELECT * FROM proveedores WHERE id = ? AND activo = 1');
        $stmt->execute([$proveedor_id]);
        $proveedor = $stmt->fetch();
        if (!$proveedor) throw new RuntimeException('Proveedor no disponible.');

        $lineas = [];
        foreach ($cantidades as $pid => $cant) {
            $pid = (int)$pid;
            $cant = (float)$cant;
            if ($cant <= 0) continue;

            $stmt = $pdo->prepare('SELECT id FROM productos WHERE id = ? AND proveedor_id = ? AND activo = 1');
            $stmt->execute([$pid, $proveedor_id]);
            if (!$stmt->fetch()) throw new RuntimeException('Producto inválido para el proveedor elegido.');
            if (isset($lineas[$pid])) throw new RuntimeException('Producto repetido en la orden.');
            $lineas[$pid] = $cant;
        }
        if (!$lineas) throw new RuntimeException('No se ingresaron cantidades mayores a 0.');

        $fecha_prometida = date('Y-m-d', strtotime('+' . (int)$proveedor['plazo_entrega_dias'] . ' days'));

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO ordenes_compra (proveedor_id, usuario_id, estado, fecha_prometida) VALUES (?, ?, 'pendiente', ?)")
                ->execute([$proveedor_id, $usuario_id, $fecha_prometida]);
            $orden_id = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad_pedida) VALUES (?, ?, ?)');
            foreach ($lineas as $pid => $cant) {
                $ins->execute([$orden_id, $pid, $cant]);
            }
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }

        set_mensaje('ok', 'Orden de compra emitida correctamente.');
        redirigir(BASE_URL . 'views/ordenes.php');
    }

    if ($accion === 'recibir') {
        $orden_id = (int)($_POST['orden_id'] ?? 0);
        $recibido = $_POST['recibido'] ?? [];
        $vencimientos = $_POST['vencimiento'] ?? [];

        if ($orden_id <= 0) throw new RuntimeException('Orden inválida.');
        if (!is_array($recibido)) $recibido = [];
        if (!is_array($vencimientos)) $vencimientos = [];

        $stmt = $pdo->prepare("SELECT * FROM ordenes_compra WHERE id = ? AND estado = 'pendiente'");
        $stmt->execute([$orden_id]);
        $orden = $stmt->fetch();
        if (!$orden) throw new RuntimeException('La orden no existe o ya no está pendiente.');

        $detalles = $pdo->prepare('SELECT d.*, c.controla_vencimiento FROM ordenes_detalle d JOIN productos p ON p.id = d.producto_id JOIN categorias c ON c.id = p.categoria_id WHERE d.orden_id = ?');
        $detalles->execute([$orden_id]);
        $lineas = $detalles->fetchAll();

        $motivo_recepcion = motivo_id($pdo, 'recepcion_orden');

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE ordenes_compra SET estado = 'recibida', fecha_recepcion = NOW() WHERE id = ? AND estado = 'pendiente'");
            $upd->execute([$orden_id]);
            if ($upd->rowCount() !== 1) throw new RuntimeException('La orden ya fue recibida o cancelada.');

            $alguna = false;
            $updDetalle = $pdo->prepare('UPDATE ordenes_detalle SET cantidad_recibida = ? WHERE id = ?');

            foreach ($lineas as $l) {
                $detalle_id = (int)$l['id'];
                $cant = isset($recibido[$detalle_id]) ? (float)$recibido[$detalle_id] : 0;
                $pedida = (float)$l['cantidad_pedida'];

                if ($cant < 0 || $cant > $pedida) throw new RuntimeException('Cantidad recibida inválida.');
                $updDetalle->execute([$cant, $detalle_id]);

                if ($cant > 0) {
                    $alguna = true;
                    $venc = null;
                    if ((int)$l['controla_vencimiento'] === 1) {
                        $venc = trim((string)($vencimientos[$detalle_id] ?? ''));
                        if ($venc === '') throw new RuntimeException('Falta la fecha de vencimiento para un producto que la requiere.');
                        validar_vencimiento($venc);
                    }
                    registrar_entrada($pdo, (int)$l['producto_id'], $usuario_id, $motivo_recepcion, $cant, $venc, $orden_id);
                }
            }

            if (!$alguna) throw new RuntimeException('Debe recibirse al menos una línea con cantidad mayor a 0. Si no llegó nada, cancelá la orden.');

            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }

        set_mensaje('ok', 'Recepción registrada correctamente.');
        redirigir(BASE_URL . 'views/ordenes.php');
    }

    if ($accion === 'cancelar') {
        $orden_id = (int)($_POST['orden_id'] ?? 0);
        if ($orden_id <= 0) throw new RuntimeException('Orden inválida.');

        $stmt = $pdo->prepare("UPDATE ordenes_compra SET estado = 'cancelada' WHERE id = ? AND estado = 'pendiente'");
        $stmt->execute([$orden_id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('La orden no está pendiente.');
        
        set_mensaje('ok', 'Orden cancelada.');
        redirigir(BASE_URL . 'views/ordenes.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    // Errores de validación: se muestran; errores técnicos: se registran y se oculta el detalle.
    $msg = mensaje_error($t);
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/ordenes.php');
}