<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

// KPIs de stock
$criticos = (int)$pdo->query(
    "SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock_actual <= stock_minimo"
)->fetchColumn();
$proximos = (int)$pdo->query(
    "SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock_actual > stock_minimo
     AND stock_actual <= stock_minimo * 1.2"
)->fetchColumn();
$totalProductos = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn();

// Órdenes pendientes
$pendientes = (int)$pdo->query(
    "SELECT COUNT(*) FROM ordenes_compra WHERE estado = 'pendiente'"
)->fetchColumn();
$demoradas = (int)$pdo->query(
    "SELECT COUNT(*) FROM ordenes_compra
     WHERE estado = 'pendiente' AND fecha_prometida < CURDATE()"
)->fetchColumn();

// Lotes vencidos / por vencer (con PEPS)
$lotes = obtener_lotes_peps($pdo);
$vencidos = 0;
$porVencer30 = 0;
foreach ($lotes as $l) {
    if ($l['dias_restantes'] < 0) {
        $vencidos++;
    } elseif ($l['dias_restantes'] <= 30) {
        $porVencer30++;
    }
}

// Últimos 5 movimientos
$ultimos = $pdo->query(
    "SELECT m.*, p.nombre AS producto, u.nombre AS usuario, mo.nombre AS motivo
     FROM movimientos_deposito m
     JOIN productos p ON p.id = m.producto_id
     JOIN usuarios u  ON u.id = m.usuario_id
     JOIN motivos_movimiento mo ON mo.id = m.motivo_id
     ORDER BY m.fecha DESC, m.id DESC
     LIMIT 5"
)->fetchAll();

$titulo = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Resumen general</h1>
<p class="muted mb-3">Estado actual del depósito y de las compras.</p>

<div class="kpi-grid">
    <div class="kpi kpi-danger">
        <div class="kpi-label">Stock crítico</div>
        <div class="kpi-value"><?= $criticos ?></div>
        <div class="kpi-foot">productos en o bajo el mínimo</div>
    </div>
    <div class="kpi kpi-warn">
        <div class="kpi-label">Próximos a agotarse</div>
        <div class="kpi-value"><?= $proximos ?></div>
        <div class="kpi-foot">de <?= $totalProductos ?> productos activos</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Órdenes pendientes</div>
        <div class="kpi-value"><?= $pendientes ?></div>
        <div class="kpi-foot"><?= $demoradas ?> demoradas</div>
    </div>
    <div class="kpi kpi-accent">
        <div class="kpi-label">Lotes vencidos</div>
        <div class="kpi-value"><?= $vencidos ?></div>
        <div class="kpi-foot"><?= $porVencer30 ?> vencen en ≤30 días</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Últimos movimientos</h2>
        <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>views/movimientos.php">Ver todos</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Producto</th><th>Tipo</th>
                    <th class="num">Cantidad</th><th>Motivo</th><th>Usuario</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$ultimos): ?>
                <tr><td colspan="6" class="muted">Sin movimientos registrados.</td></tr>
            <?php else: foreach ($ultimos as $mv): ?>
                <tr>
                    <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($mv['fecha']))) ?></td>
                    <td><?= e($mv['producto']) ?></td>
                    <td><span class="badge badge-<?= e($mv['tipo']) ?>"><?= e($mv['tipo']) ?></span></td>
                    <td class="num"><?= e(number_format((float)$mv['cantidad'], 2, ',', '.')) ?></td>
                    <td><?= e($mv['motivo']) ?></td>
                    <td><?= e($mv['usuario']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>