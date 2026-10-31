<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

// -------- Reporte 1: productos más comprados (datos crudos) ---------
$compras = $pdo->query(
    "SELECT p.id AS producto_id, p.nombre AS producto, c.nombre AS categoria,
            d.cantidad_pedida, d.cantidad_recibida, o.fecha_recepcion
     FROM ordenes_detalle d
     JOIN ordenes_compra o ON o.id = d.orden_id
     JOIN productos p  ON p.id = d.producto_id
     JOIN categorias c ON c.id = p.categoria_id
     WHERE o.estado = 'recibida' AND o.fecha_recepcion IS NOT NULL
     ORDER BY o.fecha_recepcion ASC"
)->fetchAll();

// -------- Reporte 2: cumplimiento de proveedores --------------------
$proveedoresData = $pdo->query(
    "SELECT o.id AS orden_id, pr.id AS proveedor_id, pr.nombre AS proveedor,
            o.fecha_prometida, o.fecha_recepcion,
            COUNT(d.id) AS lineas,
            SUM(CASE WHEN d.cantidad_recibida >= d.cantidad_pedida THEN 1 ELSE 0 END) AS lineas_completas
     FROM ordenes_compra o
     JOIN proveedores pr ON pr.id = o.proveedor_id
     LEFT JOIN ordenes_detalle d ON d.orden_id = o.id
     WHERE o.estado = 'recibida' AND o.fecha_recepcion IS NOT NULL
     GROUP BY o.id, pr.id, pr.nombre, o.fecha_prometida, o.fecha_recepcion
     ORDER BY o.fecha_recepcion ASC"
)->fetchAll();

// -------- Reporte 3: control de vencimientos (PEPS) -----------------
$lotes = obtener_lotes_peps($pdo);
usort($lotes, function ($a, $b) {
    return $a['dias_restantes'] <=> $b['dias_restantes'];
});

$desde = date('Y-m-d', strtotime('-180 days'));
$hasta = date('Y-m-d');

$titulo = 'Reportes';
require __DIR__ . '/../includes/header.php';
?>
<h1>Reportes</h1>
<p class="muted mb-3">Filtros interactivos en el navegador. Los totales se recalculan al cambiar los filtros.</p>

<!-- ============ Reporte 1 ============ -->
<div class="card">
    <h2>1. Productos más comprados</h2>
    <p class="hint">Suma de cantidades recibidas por producto en órdenes recibidas dentro del rango.</p>
    <div class="filters">
        <label>Desde <input type="date" id="comp-desde" value="<?= e($desde) ?>"></label>
        <label>Hasta <input type="date" id="comp-hasta" value="<?= e($hasta) ?>"></label>
    </div>
    <div class="table-wrap">
        <table class="table" id="tabla-compras">
            <thead>
                <tr><th>Producto</th><th>Categoría</th><th class="num">Pedida</th><th class="num">Recibida</th></tr>
            </thead>
            <tbody></tbody>
            <tfoot></tfoot>
        </table>
    </div>
</div>

<!-- ============ Reporte 2 ============ -->
<div class="card">
    <h2>2. Cumplimiento de proveedores</h2>
    <p class="hint">Porcentaje de entregas a tiempo, demora promedio y porcentaje de líneas completas.</p>
    <div class="filters">
        <label>Desde <input type="date" id="prov-desde" value="<?= e($desde) ?>"></label>
        <label>Hasta <input type="date" id="prov-hasta" value="<?= e($hasta) ?>"></label>
    </div>
    <div class="table-wrap">
        <table class="table" id="tabla-proveedores">
            <thead>
                <tr>
                    <th>Proveedor</th><th class="num">Órdenes</th>
                    <th class="num">% a tiempo</th><th class="num">Demora promedio</th>
                    <th class="num">% líneas completas</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ============ Reporte 3 ============ -->
<div class="card">
    <h2>3. Control de vencimientos</h2>
    <p class="hint">Estimación PEPS: el stock actual se asigna a los lotes más recientes. Es una aproximación.</p>
    <div data-filtros-container>
        <div class="filters">
            <input type="text" data-filtro="texto" placeholder="Buscar producto…">
            <select data-filtro="ventana">
                <option value="">Todos los vencimientos</option>
                <option value="vencido">Vencido</option>
                <option value="30">Hasta 30 días</option>
                <option value="60">31 a 60 días</option>
                <option value="90">61 a 90 días</option>
            </select>
        </div>
        <div class="table-wrap">
            <table class="table" data-filtro-tabla>
                <thead>
                    <tr>
                        <th>Producto</th><th>Categoría</th>
                        <th class="num">Cantidad estimada</th>
                        <th>Vencimiento</th><th class="num">Días restantes</th><th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$lotes): ?>
                    <tr><td colspan="6" class="muted">No hay lotes con stock para mostrar.</td></tr>
                <?php endif; ?>
                <?php foreach ($lotes as $l):
                    $ventanaLabel = match ($l['ventana']) {
                        'vencido' => ['Vencido', 'demora'],
                        '30'      => ['≤ 30 días', 'pendiente'],
                        '60'      => ['≤ 60 días', 'proximo'],
                        '90'      => ['≤ 90 días', 'normal'],
                        default   => ['Normal', 'normal'],
                    };
                ?>
                    <tr data-nombre="<?= e($l['producto']) ?>" data-ventana="<?= e($l['ventana']) ?>">
                        <td><?= e($l['producto']) ?></td>
                        <td><?= e($l['categoria']) ?></td>
                        <td class="num"><?= e(number_format((float)$l['cantidad'], 2, ',', '.')) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($l['fecha_vencimiento']))) ?></td>
                        <td class="num"><?= (int)$l['dias_restantes'] ?></td>
                        <td><span class="badge badge-<?= e($ventanaLabel[1]) ?>"><?= e($ventanaLabel[0]) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
window.REPORTE_COMPRAS = <?= json_encode($compras, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
window.REPORTE_PROVEEDORES = <?= json_encode($proveedoresData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<?php
$extra_js = ['filtros.js', 'reportes.js'];
require __DIR__ . '/../includes/footer.php';