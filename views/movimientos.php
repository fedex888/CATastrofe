<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

$es_encargado = ($_SESSION['rol'] ?? '') === 'encargado';

$productos = $pdo->query(
    "SELECT p.id, p.nombre, p.stock_actual, p.unidad_medida, c.controla_vencimiento
     FROM productos p
     JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1 ORDER BY p.nombre"
)->fetchAll();

$motivos = $pdo->query(
    "SELECT * FROM motivos_movimiento WHERE es_sistema = 0 ORDER BY tipo, nombre"
)->fetchAll();

$historial = $pdo->query(
    "SELECT m.*, p.nombre AS producto, u.nombre AS usuario, mo.nombre AS motivo,
            o.id AS orden_ref
     FROM movimientos_deposito m
     JOIN productos p ON p.id = m.producto_id
     JOIN usuarios u  ON u.id = m.usuario_id
     JOIN motivos_movimiento mo ON mo.id = m.motivo_id
     LEFT JOIN ordenes_compra o ON o.id = m.orden_id
     ORDER BY m.fecha DESC, m.id DESC
     LIMIT 200"
)->fetchAll();

$titulo = 'Movimientos';
require __DIR__ . '/../includes/header.php';
?>
<h1>Movimientos de depósito</h1>
<p class="muted mb-3">Registro inmutable de entradas y salidas.</p>

<?php if ($es_encargado): ?>
<div class="card">
    <h2>Registrar movimiento</h2>
    <form action="<?= BASE_URL ?>api/accion_movimiento.php" method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-row">
            <label>Producto
                <select name="producto_id" required data-producto-select>
                    <option value="">— Elegí un producto —</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                                data-vencimiento="<?= (int)$p['controla_vencimiento'] ?>"
                                data-stock="<?= e($p['stock_actual']) ?>">
                            <?= e($p['nombre']) ?> (stock: <?= e(number_format((float)$p['stock_actual'], 2, ',', '.')) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Tipo
                <select name="tipo" required data-tipo-movimiento>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                </select>
            </label>
            <label>Motivo
                <select name="motivo_id" required data-motivo-select>
                    <option value="">— Elegí un motivo —</option>
                    <?php foreach ($motivos as $mo): ?>
                        <option value="<?= (int)$mo['id'] ?>" data-tipo="<?= e($mo['tipo']) ?>">
                            <?= e($mo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-row">
            <label>Cantidad
                <input type="number" name="cantidad" step="0.01" min="0.01" required>
            </label>
            <label data-vencimiento-wrapper style="display:none">
                Fecha de vencimiento
                <input type="date" name="fecha_vencimiento" min="<?= date('Y-m-d') ?>">
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Registrar movimiento</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Historial</h2>
    <div data-filtros-container>
        <div class="filters">
            <input type="text" data-filtro="texto" placeholder="Buscar por producto o motivo…">
            <select data-filtro="tipo">
                <option value="">Todos los tipos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>
        </div>
        <div class="table-wrap">
            <table class="table" data-filtro-tabla>
                <thead>
                    <tr>
                        <th>Fecha</th><th>Producto</th><th>Tipo</th>
                        <th class="num">Cantidad</th><th>Motivo</th>
                        <th>Orden</th><th>Usuario</th><th>Vencimiento</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historial as $mv): ?>
                    <tr data-nombre="<?= e($mv['producto'] . ' ' . $mv['motivo']) ?>" data-tipo="<?= e($mv['tipo']) ?>">
                        <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($mv['fecha']))) ?></td>
                        <td><?= e($mv['producto']) ?></td>
                        <td><span class="badge badge-<?= e($mv['tipo']) ?>"><?= e($mv['tipo']) ?></span></td>
                        <td class="num"><?= e(number_format((float)$mv['cantidad'], 2, ',', '.')) ?></td>
                        <td><?= e($mv['motivo']) ?></td>
                        <td><?= $mv['orden_ref'] ? '#' . (int)$mv['orden_ref'] : '—' ?></td>
                        <td><?= e($mv['usuario']) ?></td>
                        <td><?= $mv['fecha_vencimiento'] ? e(date('d/m/Y', strtotime($mv['fecha_vencimiento']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$extra_js = ['filtros.js'];
require __DIR__ . '/../includes/footer.php';