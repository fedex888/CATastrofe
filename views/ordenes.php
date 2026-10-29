<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

$es_encargado = ($_SESSION['rol'] ?? '') === 'encargado';

$ordenes = $pdo->query(
    "SELECT o.*, pr.nombre AS proveedor, u.nombre AS usuario,
            DATEDIFF(CURDATE(), o.fecha_prometida) AS dias_prometida
     FROM ordenes_compra o
     JOIN proveedores pr ON pr.id = o.proveedor_id
     JOIN usuarios    u  ON u.id = o.usuario_id
     ORDER BY o.fecha_creacion DESC"
)->fetchAll();

$detalles = $pdo->query(
    "SELECT d.*, p.nombre AS producto, p.unidad_medida, c.controla_vencimiento
     FROM ordenes_detalle d
     JOIN productos p  ON p.id = d.producto_id
     JOIN categorias c ON c.id = p.categoria_id"
)->fetchAll();

$detalles_por_orden = [];
foreach ($detalles as $d) {
    $detalles_por_orden[(int)$d['orden_id']][] = $d;
}

$grupos = ['pendiente' => [], 'recibida' => [], 'cancelada' => []];
foreach ($ordenes as $o) {
    $grupos[$o['estado']][] = $o;
}

$titulo = 'Órdenes de compra';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Órdenes de compra</h1>
        <p class="muted">Emitir, recibir y cancelar pedidos a proveedores.</p>
    </div>
    <?php if ($es_encargado): ?>
        <a class="btn btn-primary" href="<?= BASE_URL ?>views/orden_nueva.php">+ Nueva orden</a>
    <?php endif; ?>
</div>

<div data-tabs>
    <div class="tabs">
        <button class="tab active" data-tab="tab-pendientes">Pendientes (<?= count($grupos['pendiente']) ?>)</button>
        <button class="tab" data-tab="tab-recibidas">Recibidas (<?= count($grupos['recibida']) ?>)</button>
        <button class="tab" data-tab="tab-canceladas">Canceladas (<?= count($grupos['cancelada']) ?>)</button>
    </div>

    <!-- Pendientes -->
    <div class="tab-content active" id="tab-pendientes">
        <?php if (!$grupos['pendiente']): ?>
            <p class="muted">No hay órdenes pendientes.</p>
        <?php endif; ?>
        <?php foreach ($grupos['pendiente'] as $o):
            $demorada = ((int)$o['dias_prometida']) > 0;
            $lineas = $detalles_por_orden[(int)$o['id']] ?? [];
        ?>
            <div class="orden-card<?= $demorada ? ' demorada' : '' ?>">
                <div class="orden-head">
                    <div>
                        <h3>Orden #<?= (int)$o['id'] ?> · <?= e($o['proveedor']) ?></h3>
                        <div class="orden-meta">
                            Emitida: <?= e(date('d/m/Y', strtotime($o['fecha_creacion']))) ?> ·
                            Prometida: <?= e(date('d/m/Y', strtotime($o['fecha_prometida']))) ?>
                            <?php if ($demorada): ?>
                                · <span class="badge badge-demora">Demorada <?= (int)$o['dias_prometida'] ?> d</span>
                            <?php endif; ?>
                            · Usuario: <?= e($o['usuario']) ?>
                        </div>
                    </div>
                    <?php if ($es_encargado): ?>
                    <div class="orden-actions">
                        <button class="btn btn-sm btn-success" data-dialog-open="dlg-recibir-<?= (int)$o['id'] ?>">
                            Confirmar Recepción
                        </button>
                        <form action="<?= BASE_URL ?>api/accion_orden.php" method="post" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="accion" value="cancelar">
                            <input type="hidden" name="orden_id" value="<?= (int)$o['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    data-confirm="¿Cancelar la orden #<?= (int)$o['id'] ?>?">
                                Cancelar
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Producto</th><th class="num">Pedido</th><th>Unidad</th><th>Vence</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lineas as $l): ?>
                            <tr>
                                <td><?= e($l['producto']) ?></td>
                                <td class="num"><?= e(number_format((float)$l['cantidad_pedida'], 2, ',', '.')) ?></td>
                                <td><?= e($l['unidad_medida']) ?></td>
                                <td><?= ((int)$l['controla_vencimiento']) === 1 ? 'Sí' : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($es_encargado): ?>
            <dialog id="dlg-recibir-<?= (int)$o['id'] ?>">
                <div class="dialog-header">
                    <h3>Recepción de la orden #<?= (int)$o['id'] ?></h3>
                    <button class="dialog-close" data-dialog-close>×</button>
                </div>
                <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_orden.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="recibir">
                    <input type="hidden" name="orden_id" value="<?= (int)$o['id'] ?>">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th><th class="num">Pedido</th>
                                <th class="num">Recibido</th><th>Vencimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lineas as $l): ?>
                            <tr>
                                <td><?= e($l['producto']) ?></td>
                                <td class="num"><?= e(number_format((float)$l['cantidad_pedida'], 2, ',', '.')) ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="<?= e($l['cantidad_pedida']) ?>"
                                           name="recibido[<?= (int)$l['id'] ?>]"
                                           value="<?= e($l['cantidad_pedida']) ?>" style="width:90px">
                                </td>
                                <td>
                                    <?php if ((int)$l['controla_vencimiento'] === 1): ?>
                                        <input type="date" name="vencimiento[<?= (int)$l['id'] ?>]" min="<?= date('Y-m-d') ?>">
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="hint">Si un producto no llegó, poné 0 en su cantidad. Si no llegó nada, cancelá la orden.</p>
                    <div class="form-actions">
                        <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar recepción</button>
                    </div>
                </form>
            </dialog>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Recibidas -->
    <div class="tab-content" id="tab-recibidas">
        <?php if (!$grupos['recibida']): ?>
            <p class="muted">No hay órdenes recibidas.</p>
        <?php endif; ?>
        <?php foreach ($grupos['recibida'] as $o):
            $lineas = $detalles_por_orden[(int)$o['id']] ?? [];
        ?>
            <div class="orden-card">
                <div class="orden-head">
                    <div>
                        <h3>Orden #<?= (int)$o['id'] ?> · <?= e($o['proveedor']) ?></h3>
                        <div class="orden-meta">
                            Emitida: <?= e(date('d/m/Y', strtotime($o['fecha_creacion']))) ?> ·
                            Prometida: <?= e(date('d/m/Y', strtotime($o['fecha_prometida']))) ?> ·
                            Recibida: <?= $o['fecha_recepcion'] ? e(date('d/m/Y H:i', strtotime($o['fecha_recepcion']))) : '—' ?>
                        </div>
                    </div>
                    <span class="badge badge-recibida">Recibida</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Producto</th><th class="num">Pedido</th><th class="num">Recibido</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lineas as $l):
                            $completa = ((float)$l['cantidad_recibida'] >= (float)$l['cantidad_pedida']);
                        ?>
                            <tr>
                                <td><?= e($l['producto']) ?></td>
                                <td class="num"><?= e(number_format((float)$l['cantidad_pedida'], 2, ',', '.')) ?></td>
                                <td class="num"><?= e(number_format((float)$l['cantidad_recibida'], 2, ',', '.')) ?></td>
                                <td>
                                    <?php if ($completa): ?>
                                        <span class="badge badge-normal">Completa</span>
                                    <?php else: ?>
                                        <span class="badge badge-demora">Incompleta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Canceladas -->
    <div class="tab-content" id="tab-canceladas">
        <?php if (!$grupos['cancelada']): ?>
            <p class="muted">No hay órdenes canceladas.</p>
        <?php endif; ?>
        <?php foreach ($grupos['cancelada'] as $o):
            $lineas = $detalles_por_orden[(int)$o['id']] ?? [];
        ?>
            <div class="orden-card">
                <div class="orden-head">
                    <div>
                        <h3>Orden #<?= (int)$o['id'] ?> · <?= e($o['proveedor']) ?></h3>
                        <div class="orden-meta">
                            Emitida: <?= e(date('d/m/Y', strtotime($o['fecha_creacion']))) ?>
                        </div>
                    </div>
                    <span class="badge badge-cancelada">Cancelada</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Producto</th><th class="num">Pedido</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lineas as $l): ?>
                            <tr>
                                <td><?= e($l['producto']) ?></td>
                                <td class="num"><?= e(number_format((float)$l['cantidad_pedida'], 2, ',', '.')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>