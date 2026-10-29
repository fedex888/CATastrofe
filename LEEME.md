# CATástrofe Petshop · Sistema de Compras y Depósito

TP de la cátedra. Stack: **HTML + CSS Vanilla + JS Vanilla + PHP 8 + MySQL (MariaDB)** sobre **XAMPP**.

## Instalación

1. Copiar la carpeta del proyecto en `C:\xampp\htdocs\catastrofe\`.
2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.
3. Abrir `http://localhost/phpmyadmin/` e importar `database.sql` (crea `catastrofe_db` con datos de prueba).
4. Verificar en `config/db.php` que el usuario sea `root` y la contraseña vacía.
5. Abrir `http://localhost/catastrofe/`.

### Usuarios de demo

| Usuario    | Contraseña  | Rol       |
|------------|-------------|-----------|
| `encargado`| `password`  | Encargado |
| `gerencia` | `password`  | Gerencia  |

> Si querés cambiar las contraseñas, generá un hash nuevo con:
> `C:\xampp\php\php.exe -r "echo password_hash('nueva_clave', PASSWORD_DEFAULT);"`
> y actualizá el `password_hash` del usuario en la tabla `usuarios` (phpMyAdmin → SQL, por ejemplo:
> `UPDATE usuarios SET password_hash = '<HASH>' WHERE usuario = 'encargado';`).

## Estructura
