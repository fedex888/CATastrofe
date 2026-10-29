<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

$es_encargado = ($_SESSION['rol'] ?? '') === 'encargado';

$productos = $pdo->query(
    "SELECT p.*, c.nombre AS categoria, c.controla_vencimiento,
            pr.nombre AS proveedor,
            COALESCE((SELECT SUM(d.cantidad_pedida)
                      FROM ordenes_detalle d
                      JOIN ordenes_compra o ON o.id = d.orden_id
                      WHERE d.producto_id = p.id AND o.estado = 'pendiente'), 0) AS en_pedido
     FROM productos p
     JOIN categorias  c  ON c.id = p.categoria_id
     JOIN proveedores pr ON pr.id = p.proveedor_id
     WHERE p.activo = 1
     ORDER BY c.nombre, p.nombre"
)->fetchAll();

$categorias  = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
$proveedores = $pdo->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre")->fetchAll();

$titulo = 'Stock';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Stock de productos</h1>
        <p class="muted">Catálogo activo, alertas visuales y generación de órdenes sugeridas.</p>
    </div>
    <?php if ($es_encargado): ?>
        <button class="btn btn-primary" data-dialog-open="dialog-nuevo-producto">+ Nuevo producto</button>
    <?php endif; ?>
</div>

<div data-filtros-container>
    <div class="filters">
        <input type="text" data-filtro="texto" placeholder="Buscar por nombre…">
        <select data-filtro="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= e($c['nombre']) ?>"><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select data-filtro="estado">
            <option value="">Todos los estados</option>
            <option value="critico">Crítico</option>
            <option value="proximo">Próximo a agotarse</option>
            <option value="normal">Normal</option>
        </select>
    </div>

    <div class="table-wrap">
        <table class="table" data-filtro-tabla>
            <thead>
                <tr>
                    <th>Producto</th><th>Categoría</th><th>Proveedor</th>
                    <th class="num">Stock</th><th class="num">Mín.</th><th class="num">Máx.</th>
                    <th class="num">En pedido</th><th>Estado</th><th class="num">Sugerida</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p):
                $estado = estado_stock($p['stock_actual'], $p['stock_minimo']);
                $sug = cantidad_sugerida($p['stock_actual'], $p['stock_maximo'], $p['en_pedido']);
                $cubre = ((float)$p['en_pedido']) >= ((float)$p['stock_maximo'] - (float)$p['stock_actual']);
                $mostrarBoton = $es_encargado
                    && in_array($estado, ['critico','proximo'], true)
                    && $sug > 0
                    && !$cubre;
                $dataEditar = json_encode([
                    'id' => (int)$p['id'],
                    'nombre' => $p['nombre'],
                    'categoria_id' => (int)$p['categoria_id'],
                    'proveedor_id' => (int)$p['proveedor_id'],
                    'stock_minimo' => $p['stock_minimo'],
                    'stock_maximo' => $p['stock_maximo'],
                    'unidad_medida' => $p['unidad_medida'],
                    'descripcion' => $p['descripcion'],
                ], JSON_UNESCAPED_UNICODE);
            ?>
            <tr class="<?= e($estado) ?>"
                data-nombre="<?= e($p['nombre']) ?>"
                data-categoria="<?= e($p['categoria']) ?>"
                data-estado="<?= e($estado) ?>">
                <td><strong><?= e($p['nombre']) ?></strong><br>
                    <small class="muted"><?= e($p['unidad_medida']) ?></small></td>
                <td><?= e($p['categoria']) ?></td>
                <td><?= e($p['proveedor']) ?></td>
                <td class="num"><?= e(number_format((float)$p['stock_actual'], 2, ',', '.')) ?></td>
                <td class="num"><?= e(number_format((float)$p['stock_minimo'], 2, ',', '.')) ?></td>
                <td class="num"><?= e(number_format((float)$p['stock_maximo'], 2, ',', '.')) ?></td>
                <td class="num"><?= e(number_format((float)$p['en_pedido'], 2, ',', '.')) ?></td>
                <td><span class="badge badge-<?= e($estado) ?>"><?= e(etiqueta_estado($estado)) ?></span></td>
                <td class="num"><?= $sug > 0 ? e(number_format($sug, 2, ',', '.')) : '—' ?></td>
                <td>
                    <div class="flex">
                        <?php if ($mostrarBoton): ?>
                            <a class="btn btn-sm btn-accent"
                               href="<?= BASE_URL ?>views/orden_nueva.php?proveedor_id=<?= (int)$p['proveedor_id'] ?>&producto_id=<?= (int)$p['id'] ?>">
                               Generar Orden Sugerida
                            </a>
                        <?php elseif ($cubre && $es_encargado && $estado !== 'normal'): ?>
                            <span class="badge badge-pendiente">Pedido en curso</span>
                        <?php endif; ?>
                        <?php if ($es_encargado): ?>
                            <button class="btn btn-sm btn-ghost" data-editar="<?= e($dataEditar) ?>">Editar</button>
                            <form action="<?= BASE_URL ?>api/accion_producto.php" method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="baja">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        data-confirm="¿Dar de baja '<?= e($p['nombre']) ?>'? Se conserva el historial.">
                                    Baja
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($es_encargado): ?>
<!-- Dialog: nuevo producto -->
<dialog id="dialog-nuevo-producto">
    <div class="dialog-header">
        <h3>Nuevo producto</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_producto.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="crear">
        <label>Nombre <input type="text" name="nombre" required maxlength="120"></label>
        <div class="form-row">
            <label>Categoría
                <select name="categoria_id" required data-categoria-producto>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-vencimiento="<?= (int)$c['controla_vencimiento'] ?>"><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Proveedor
                <select name="proveedor_id" required>
                    <?php foreach ($proveedores as $pv): ?>
                        <option value="<?= (int)$pv['id'] ?>"><?= e($pv['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Descripción <textarea name="descripcion" rows="2" maxlength="500"></textarea></label>
        <div class="form-row">
            <label>Stock inicial <input type="number" name="stock_inicial" step="0.01" min="0" value="0"></label>
            <label>Stock mínimo <input type="number" name="stock_minimo" step="0.01" min="0" required value="0"></label>
            <label>Stock máximo <input type="number" name="stock_maximo" step="0.01" min="0" required value="0"></label>
        </div>
        <div class="form-row">
            <label>Unidad de medida
                <select name="unidad_medida">
                    <option value="unidad">Unidad</option>
                    <option value="kg">Kg</option>
                    <option value="litro">Litro</option>
                </select>
            </label>
            <label data-stock-inicial-vencimiento style="display:none">
                Vencimiento del stock inicial
                <input type="date" name="fecha_vencimiento_inicial" min="<?= date('Y-m-d') ?>">
            </label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear producto</button>
        </div>
    </form>
</dialog>

<!-- Dialog: editar producto -->
<dialog id="dialog-editar-producto">
    <div class="dialog-header">
        <h3>Editar producto</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_producto.php" method="post" id="form-editar-producto">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id">
        <label>Nombre <input type="text" name="nombre" required maxlength="120"></label>
        <div class="form-row">
            <label>Categoría
                <select name="categoria_id" required>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Proveedor
                <select name="proveedor_id" required>
                    <?php foreach ($proveedores as $pv): ?>
                        <option value="<?= (int)$pv['id'] ?>"><?= e($pv['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Descripción <textarea name="descripcion" rows="2" maxlength="500"></textarea></label>
        <div class="form-row">
            <label>Stock mínimo <input type="number" name="stock_minimo" step="0.01" min="0" required></label>
            <label>Stock máximo <input type="number" name="stock_maximo" step="0.01" min="0" required></label>
            <label>Unidad de medida
                <select name="unidad_medida">
                    <option value="unidad">Unidad</option>
                    <option value="kg">Kg</option>
                    <option value="litro">Litro</option>
                </select>
            </label>
        </div>
        <p class="hint">Para corregir el stock se debe registrar un movimiento de ajuste.</p>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php
$extra_js = ['filtros.js'];
require __DIR__ . '/../includes/footer.php';