<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

$es_encargado = ($_SESSION['rol'] ?? '') === 'encargado';

$proveedores = $pdo->query(
    "SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre"
)->fetchAll();

$titulo = 'Proveedores';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Proveedores</h1>
        <p class="muted">Fichas técnicas y plazos de entrega habituales.</p>
    </div>
    <?php if ($es_encargado): ?>
        <button class="btn btn-primary" data-dialog-open="dialog-nuevo-proveedor">+ Nuevo proveedor</button>
    <?php endif; ?>
</div>

<div data-filtros-container>
    <div class="filters">
        <input type="text" data-filtro="texto" placeholder="Buscar por nombre o contacto…">
    </div>
    <div class="table-wrap">
        <table class="table" data-filtro-tabla>
            <thead>
                <tr>
                    <th>Nombre</th><th>Contacto</th><th>Teléfono</th><th>Email</th>
                    <th class="num">Plazo (días)</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($proveedores as $pv):
                $dataEditar = json_encode([
                    'id' => (int)$pv['id'],
                    'nombre' => $pv['nombre'],
                    'contacto' => $pv['contacto'],
                    'telefono' => $pv['telefono'],
                    'email' => $pv['email'],
                    'plazo_entrega_dias' => (int)$pv['plazo_entrega_dias'],
                ], JSON_UNESCAPED_UNICODE);
            ?>
                <tr data-nombre="<?= e($pv['nombre'] . ' ' . $pv['contacto']) ?>">
                    <td><strong><?= e($pv['nombre']) ?></strong></td>
                    <td><?= e($pv['contacto']) ?></td>
                    <td><?= e($pv['telefono']) ?></td>
                    <td><?= e($pv['email']) ?></td>
                    <td class="num"><?= (int)$pv['plazo_entrega_dias'] ?></td>
                    <td>
                        <div class="flex">
                            <?php if ($es_encargado): ?>
                                <button class="btn btn-sm btn-ghost" data-editar-proveedor="<?= e($dataEditar) ?>">Editar</button>
                                <form action="<?= BASE_URL ?>api/accion_proveedor.php" method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="baja">
                                    <input type="hidden" name="id" value="<?= (int)$pv['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="¿Dar de baja '<?= e($pv['nombre']) ?>'?">
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
<dialog id="dialog-nuevo-proveedor">
    <div class="dialog-header">
        <h3>Nuevo proveedor</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_proveedor.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="crear">
        <label>Nombre <input type="text" name="nombre" required maxlength="120"></label>
        <div class="form-row">
            <label>Contacto <input type="text" name="contacto" maxlength="120"></label>
            <label>Teléfono <input type="text" name="telefono" maxlength="40"></label>
        </div>
        <div class="form-row">
            <label>Email <input type="email" name="email" maxlength="120"></label>
            <label>Plazo de entrega (días) <input type="number" name="plazo_entrega_dias" min="0" value="7" required></label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear proveedor</button>
        </div>
    </form>
</dialog>

<dialog id="dialog-editar-proveedor">
    <div class="dialog-header">
        <h3>Editar proveedor</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_proveedor.php" method="post" id="form-editar-proveedor">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id">
        <label>Nombre <input type="text" name="nombre" required maxlength="120"></label>
        <div class="form-row">
            <label>Contacto <input type="text" name="contacto" maxlength="120"></label>
            <label>Teléfono <input type="text" name="telefono" maxlength="40"></label>
        </div>
        <div class="form-row">
            <label>Email <input type="email" name="email" maxlength="120"></label>
            <label>Plazo de entrega (días) <input type="number" name="plazo_entrega_dias" min="0" required></label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
document.querySelectorAll('[data-editar-proveedor]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var p = JSON.parse(btn.dataset.editarProveedor);
        var f = document.getElementById('form-editar-proveedor');
        f.querySelector('[name=id]').value = p.id;
        f.querySelector('[name=nombre]').value = p.nombre || '';
        f.querySelector('[name=contacto]').value = p.contacto || '';
        f.querySelector('[name=telefono]').value = p.telefono || '';
        f.querySelector('[name=email]').value = p.email || '';
        f.querySelector('[name=plazo_entrega_dias]').value = p.plazo_entrega_dias;
        document.getElementById('dialog-editar-proveedor').showModal();
    });
});
</script>
<?php
$extra_js = ['filtros.js'];
require __DIR__ . '/../includes/footer.php';