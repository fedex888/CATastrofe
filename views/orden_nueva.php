<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_encargado();

$proveedores = $pdo->query(
    "SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre"
)->fetchAll();

$proveedor_id  = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
$producto_id   = isset($_GET['producto_id'])  ? (int)$_GET['producto_id']  : 0;

$proveedor_sel = null;
$productos = [];
if ($proveedor_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ? AND activo = 1");
    $stmt->execute([$proveedor_id]);
    $proveedor_sel = $stmt->fetch() ?: null;

    if ($proveedor_sel) {
        $stmt = $pdo->prepare(
            "SELECT p.*, c.nombre AS categoria, c.controla_vencimiento,
                    COALESCE((SELECT SUM(d.cantidad_pedida)
                              FROM ordenes_detalle d
                              JOIN ordenes_compra o ON o.id = d.orden_id
                              WHERE d.producto_id = p.id AND o.estado = 'pendiente'), 0) AS en_pedido
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND p.proveedor_id = ?
             ORDER BY c.nombre, p.nombre"
        );
        $stmt->execute([$proveedor_id]);
        $productos = $stmt->fetchAll();
    }
}

$titulo = 'Nueva orden';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Nueva orden de compra</h1>
        <p class="muted">Elegí el proveedor y las cantidades a pedir.</p>
    </div>
    <a class="btn btn-ghost" href="<?= BASE_URL ?>views/ordenes.php">← Volver</a>
</div>

<form method="get" action="<?= BASE_URL ?>views/orden_nueva.php" class="card">
    <label>Proveedor
        <select name="proveedor_id" required onchange="this.form.submit()">
            <option value="">— Elegí un proveedor —</option>
            <?php foreach ($proveedores as $pv): ?>
                <option value="<?= (int)$pv['id'] ?>" <?= $proveedor_id === (int)$pv['id'] ? 'selected' : '' ?>>
                    <?= e($pv['nombre']) ?> (plazo <?= (int)$pv['plazo_entrega_dias'] ?> días)
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($proveedor_sel): ?>
<form action="<?= BASE_URL ?>api/accion_orden.php" method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="proveedor_id" value="<?= (int)$proveedor_sel['id'] ?>">

    <div class="flex-between mb-3">
        <h2 class="mb-0"><?= e($proveedor_sel['nombre']) ?></h2>
        <span class="muted">Fecha prometida estimada: <?= e(date('d/m/Y', strtotime('+' . (int)$proveedor_sel['plazo_entrega_dias'] . ' days'))) ?></span>
    </div>

    <?php if (!$productos): ?>
        <p class="muted">Este proveedor no tiene productos activos.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th><th>Categoría</th>
                    <th class="num">Stock</th><th class="num">Mín.</th><th class="num">Máx.</th>
                    <th class="num">En pedido</th><th class="num">Sugerida</th>
                    <th class="num">Cantidad a pedir</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p):
                $estado = estado_stock($p['stock_actual'], $p['stock_minimo']);
                $sug = cantidad_sugerida($p['stock_actual'], $p['stock_maximo'], $p['en_pedido']);
                $preMarcar = ($producto_id === (int)$p['id'])
                    || (in_array($estado, ['critico','proximo'], true) && $sug > 0);
                $valorInicial = $preMarcar && $sug > 0 ? $sug : 0;
            ?>
                <tr class="<?= e($estado) ?>">
                    <td><?= e($p['nombre']) ?></td>
                    <td><?= e($p['categoria']) ?></td>
                    <td class="num"><?= e(number_format((float)$p['stock_actual'], 2, ',', '.')) ?></td>
                    <td class="num"><?= e(number_format((float)$p['stock_minimo'], 2, ',', '.')) ?></td>
                    <td class="num"><?= e(number_format((float)$p['stock_maximo'], 2, ',', '.')) ?></td>
                    <td class="num"><?= e(number_format((float)$p['en_pedido'], 2, ',', '.')) ?></td>
                    <td class="num"><?= $sug > 0 ? e(number_format($sug, 2, ',', '.')) : '—' ?></td>
                    <td class="num">
                        <input type="number" step="0.01" min="0"
                               name="cantidades[<?= (int)$p['id'] ?>]"
                               value="<?= e($valorInicial) ?>" style="width:100px">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions mt-3">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>views/ordenes.php">Cancelar</a>
        <button type="submit" class="btn btn-primary">Emitir orden</button>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>