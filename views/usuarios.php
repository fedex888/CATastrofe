<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_encargado(); // módulo exclusivo del Encargado

$es_encargado = true;

$usuarios = $pdo->query("SELECT id, nombre, usuario, rol, activo, fecha_creacion FROM usuarios ORDER BY activo DESC, id ASC")->fetchAll();

$titulo = 'Usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Gestor de Usuarios</h1>
        <p class="muted">Cuentas de acceso a CATástrofe Petshop.</p>
    </div>
    <?php if ($es_encargado): ?>
        <button class="btn btn-primary" data-dialog-open="dialog-nuevo-usuario">+ Nuevo usuario</button>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th><th>Nombre completo</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr class="<?= $u['activo'] ? '' : 'muted' ?>">
                <td><?= (int)$u['id'] ?></td>
                <td><strong><?= e($u['nombre']) ?></strong></td>
                <td><code><?= e($u['usuario']) ?></code></td>
                <td><span class="badge badge-<?= $u['rol'] === 'encargado' ? 'entrada' : 'pendiente' ?>"><?= e($u['rol']) ?></span></td>
                <td><span class="badge badge-<?= $u['activo'] ? 'normal' : 'cancelada' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                <td>
                    <?php if ($es_encargado): ?>
                        <div class="flex">
                            <?php 
                            $dataUser = json_encode(['id' => $u['id'], 'nombre' => $u['nombre'], 'usuario' => $u['usuario'], 'rol' => $u['rol']]);
                            ?>
                            <button class="btn btn-sm btn-ghost" data-editar-usuario="<?= e($dataUser) ?>">Editar</button>
                            
                            <form action="<?= BASE_URL ?>api/accion_usuario.php" method="post" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="<?= $u['activo'] ? 'baja' : 'alta' ?>">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-<?= $u['activo'] ? 'danger' : 'success' ?>" data-confirm="¿Seguro que querés cambiar el estado de este usuario?">
                                    <?= $u['activo'] ? 'Baja' : 'Alta' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($es_encargado): ?>
<dialog id="dialog-nuevo-usuario">
    <div class="dialog-header">
        <h3>Nuevo usuario</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_usuario.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="crear">
        <div class="form-row">
            <label>Nombre completo <input type="text" name="nombre" required maxlength="100"></label>
            <label>Usuario de login <input type="text" name="usuario" required maxlength="50" pattern="[a-zA-Z0-9_]+"></label>
        </div>
        <div class="form-row">
            <label>Contraseña <input type="password" name="password" required minlength="6"></label>
            <label>Rol <select name="rol" required><option value="encargado">Encargado</option><option value="gerencia">Gerencia</option></select></label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
    </form>
</dialog>

<dialog id="dialog-editar-usuario">
    <div class="dialog-header">
        <h3>Editar usuario</h3>
        <button class="dialog-close" data-dialog-close>×</button>
    </div>
    <form class="dialog-body form" action="<?= BASE_URL ?>api/accion_usuario.php" method="post" id="form-editar-usuario">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id">
        <div class="form-row">
            <label>Nombre completo <input type="text" name="nombre" required maxlength="100"></label>
            <label>Usuario de login <input type="text" name="usuario" required maxlength="50" pattern="[a-zA-Z0-9_]+"></label>
        </div>
        <div class="form-row">
            <label>Nueva contraseña (opcional) <input type="password" name="password" minlength="6" placeholder="Dejar vacío para mantener"></label>
            <label>Rol <select name="rol" required><option value="encargado">Encargado</option><option value="gerencia">Gerencia</option></select></label>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-editar-usuario]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var u = JSON.parse(btn.dataset.editarUsuario);
        var f = document.getElementById('form-editar-usuario');
        f.querySelector('[name=id]').value = u.id;
        f.querySelector('[name=nombre]').value = u.nombre;
        f.querySelector('[name=usuario]').value = u.usuario;
        f.querySelector('[name=rol]').value = u.rol;
        f.querySelector('[name=password]').value = '';
        document.getElementById('dialog-editar-usuario').showModal();
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>