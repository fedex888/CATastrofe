<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login();

$es_encargado = ($_SESSION['rol'] ?? '') === 'encargado';

$usuarios = $pdo->query(
    "SELECT id, nombre, usuario, rol, fecha_creacion 
     FROM usuarios 
     ORDER BY id ASC"
)->fetchAll();

$titulo = 'Usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between mb-3">
    <div>
        <h1>Usuarios del sistema</h1>
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
                <th>ID</th><th>Nombre completo</th><th>Usuario</th><th>Rol</th><th>Fecha de creación</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><strong><?= e($u['nombre']) ?></strong></td>
                <td><code><?= e($u['usuario']) ?></code></td>
                <td>
                    <span class="badge badge-<?= $u['rol'] === 'encargado' ? 'entrada' : 'pendiente' ?>">
                        <?= e($u['rol']) ?>
                    </span>
                </td>
                <td><?= e(date('d/m/Y H:i', strtotime($u['fecha_creacion']))) ?></td>
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
            <label>Nombre completo 
                <input type="text" name="nombre" required maxlength="100" placeholder="Ej: María González">
            </label>
            <label>Usuario de login 
                <input type="text" name="usuario" required maxlength="50" pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guiones bajos, sin espacios" placeholder="Ej: mgonzalez">
            </label>
        </div>
        
        <div class="form-row">
            <label>Contraseña 
                <input type="password" name="password" required minlength="4" placeholder="Mínimo 4 caracteres">
            </label>
            <label>Rol
                <select name="rol" required>
                    <option value="encargado">Encargado (puede editar y operar)</option>
                    <option value="gerencia">Gerencia (solo lectura)</option>
                </select>
            </label>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-ghost" data-dialog-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>