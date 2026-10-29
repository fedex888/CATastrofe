<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/stock.php');
}
verificar_csrf();

$accion = (string)($_POST['accion'] ?? '');

try {
    if ($accion === 'crear') {
        $nombre        = trim((string)($_POST['nombre'] ?? ''));
        $categoria_id  = (int)($_POST['categoria_id'] ?? 0);
        $proveedor_id  = (int)($_POST['proveedor_id'] ?? 0);
        $descripcion   = trim((string)($_POST['descripcion'] ?? ''));
        $stock_ini     = (float)($_POST['stock_inicial'] ?? 0);
        $stock_min     = (float)($_POST['stock_minimo'] ?? 0);
        $stock_max     = (float)($_POST['stock_maximo'] ?? 0);
        $unidad        = (string)($_POST['unidad_medida'] ?? 'unidad');
        $fecha_venc    = trim((string)($_POST['fecha_vencimiento_inicial'] ?? ''));

        if ($nombre === '') throw new RuntimeException('Falta el nombre.');
        if ($stock_min < 0 || $stock_max < 0 || $stock_max < $stock_min) throw new RuntimeException('Los stock mínimo/máximo no son válidos.');
        if ($stock_ini < 0) throw new RuntimeException('El stock inicial no puede ser negativo.');

        $stmt = $pdo->prepare('SELECT controla_vencimiento FROM categorias WHERE id = ?');
        $stmt->execute([$categoria_id]);
        $controla = $stmt->fetchColumn();
        if ($controla === false) throw new RuntimeException('Categoría inválida.');

        $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ? AND activo = 1');
        $stmt->execute([$proveedor_id]);
        if (!$stmt->fetch()) throw new RuntimeException('Proveedor inválido.');

        if ((int)$controla === 1 && $stock_ini > 0) {
            if ($fecha_venc === '') throw new RuntimeException('La fecha de vencimiento es obligatoria para el stock inicial de esta categoría.');
            
            // NUEVA VALIDACIÓN: No fechas pasadas
            if (strtotime($fecha_venc) < strtotime(date('Y-m-d'))) {
                throw new RuntimeException('La fecha de vencimiento no puede ser pasada.');
            }
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO productos (categoria_id, proveedor_id, nombre, descripcion, stock_actual, stock_minimo, stock_maximo, unidad_medida, activo) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 1)')
                ->execute([$categoria_id, $proveedor_id, $nombre, $descripcion, $stock_min, $stock_max, $unidad]);
            $nuevo_id = (int)$pdo->lastInsertId();

            if ($stock_ini > 0) {
                registrar_entrada($pdo, $nuevo_id, (int)$_SESSION['usuario_id'], motivo_id($pdo, 'stock_inicial'), $stock_ini, ((int)$controla === 1) ? $fecha_venc : null);
            }
            $pdo->commit();
        } catch (Throwable $t) {
            $pdo->rollBack();
            throw $t;
        }
        set_mensaje('ok', 'Producto creado correctamente.');
        redirigir(BASE_URL . 'views/stock.php');
    }

    if ($accion === 'editar') {
        $id           = (int)($_POST['id'] ?? 0);
        $nombre       = trim((string)($_POST['nombre'] ?? ''));
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
        $descripcion  = trim((string)($_POST['descripcion'] ?? ''));
        $stock_min    = (float)($_POST['stock_minimo'] ?? 0);
        $stock_max    = (float)($_POST['stock_maximo'] ?? 0);
        $unidad       = (string)($_POST['unidad_medida'] ?? 'unidad');

        if ($id <= 0 || $nombre === '') throw new RuntimeException('Datos incompletos.');
        if ($stock_min < 0 || $stock_max < $stock_min) throw new RuntimeException('Stock mínimo/máximo inválido.');

        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = ?');
        $stmt->execute([$categoria_id]);
        if (!$stmt->fetch()) throw new RuntimeException('Categoría inválida.');

        $stmt = $pdo->prepare('SELECT id FROM proveedores WHERE id = ? AND activo = 1');
        $stmt->execute([$proveedor_id]);
        if (!$stmt->fetch()) throw new RuntimeException('Proveedor inválido.');

        $stmt = $pdo->prepare('UPDATE productos SET nombre = ?, categoria_id = ?, proveedor_id = ?, descripcion = ?, stock_minimo = ?, stock_maximo = ?, unidad_medida = ? WHERE id = ? AND activo = 1');
        $stmt->execute([$nombre, $categoria_id, $proveedor_id, $descripcion, $stock_min, $stock_max, $unidad, $id]);
        
        if ($stmt->rowCount() === 0) throw new RuntimeException('No se pudo actualizar (el producto pudo haber sido dado de baja).');

        set_mensaje('ok', 'Producto actualizado.');
        redirigir(BASE_URL . 'views/stock.php');
    }

    if ($accion === 'baja') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Producto inválido.');
        $pdo->prepare('UPDATE productos SET activo = 0 WHERE id = ?')->execute([$id]);
        set_mensaje('ok', 'Producto dado de baja.');
        redirigir(BASE_URL . 'views/stock.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    error_log('Error en accion_producto: ' . $t->getMessage());
    $msg = (defined('APP_DEBUG') && APP_DEBUG) ? $t->getMessage() : 'Ocurrió un error inesperado.';
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/stock.php');
}