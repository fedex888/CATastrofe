# 🐾 CATástrofe Petshop - Sistema de Gestión de Compras y Depósito

Sistema interno de gestión web desarrollado para el área de **Compras y Depósito** de CATástrofe Petshop. 

El objetivo del proyecto es reemplazar los controles manuales por una plataforma web segura, clara y fácil de auditar, construida puramente con tecnologías web base (sin frameworks) para facilitar su estudio y defensa académica.

## 🛠️ Tecnologías Utilizadas

*   **Backend:** PHP 8.x (Arquitectura modular, PDO para base de datos).
*   **Base de Datos:** MySQL / MariaDB (Motor InnoDB, transacciones y claves foráneas).
*   **Frontend:** HTML5, CSS3 (Vanilla, con variables CSS y diseño responsivo) y JavaScript (Vanilla, sin compiladores).
*   **Entorno Local:** XAMPP.

## ✨ Características Principales

*   **Autenticación y Roles:** Sistema de login seguro con contraseñas hasheadas (password_hash). Dos roles definidos: **Encargado** (opera el depósito: stock, proveedores, órdenes y movimientos) y **Gerencia** (solo lectura y reportes, y es la única que crea y gestiona usuarios).
*   **Gestión de Stock:** Catálogo de productos con alertas visuales (crítico, próximo a agotarse, normal). Cálculo automático de cantidad sugerida descontando lo que ya está en pedido.
*   **Movimientos Inmutables:** Registro de entradas y salidas con control estricto para evitar stock negativo. Los errores se corrigen con movimientos de ajuste, no editando el historial.
*   **Órdenes de Compra:** Emisión de pedidos a proveedores con recepción atómica (transaccional). Soporta entregas parciales y calcula desvíos de tiempo.
*   **Reportes Dinámicos:** Reportes de productos más comprados, cumplimiento de proveedores y control de vencimientos (estimación PEPS), con filtros en tiempo real que no recargan la página.
*   **Gestión de Usuarios:** Módulo exclusivo de Gerencia: alta, edición, cambio de contraseña, baja/alta de usuarios y asignación de rol. El Encargado no ve este panel (ni por menú ni por URL). Los cambios rigen de inmediato (ver "Arquitectura y Seguridad"). Nadie puede darse de baja ni cambiarse el rol a sí mismo, así que siempre queda al menos una persona de Gerencia activa.
*   **Mi perfil:** cada persona (Encargado o Gerencia) edita solo su propia cuenta: nombre, usuario y contraseña. Cambiar el usuario o la contraseña exige confirmar la contraseña actual. Desde ahí no se puede tocar el rol, el estado ni la cuenta de otra persona (el id sale de la sesión, no del formulario).
*   **Login:** la pantalla de ingreso aclara "¿No tenés un usuario? Pedile uno a gerencia", porque no hay registro público.

## 🔒 Arquitectura y Seguridad

El sistema fue diseñado pensando en las buenas prácticas de seguridad y la integridad de los datos:

*   **Consultas Preparadas (PDO):** todo dato que viene del usuario viaja en consultas preparadas (`prepare()` / `execute()`); ninguna variable del usuario se concatena en el SQL. Los listados y KPIs sin parámetros usan `query()` con SQL fijo.
*   **Protección CSRF:** Todos los formularios (POST) incluyen y validan un token de seguridad único por sesión.
*   **Manejo de Errores Seguro:** Los errores de validación ("Stock insuficiente", "La fecha de vencimiento no puede ser pasada", etc.) se muestran al usuario; los errores internos de la base de datos no: se registran en el log de PHP y se muestra un mensaje genérico (`mensaje_error()` en `funciones.php`). La constante APP_DEBUG controla si se muestran detalles técnicos.
*   **Sesión revalidada en cada pedido:** `requerir_login()` consulta la base en cada request. Si un usuario es dado de baja o le cambian el rol, el efecto es inmediato, sin esperar a que cierre sesión. El cierre de sesión es un POST con token CSRF.
*   **Validación en el servidor:** fechas de vencimiento (deben existir en el calendario y no ser pasadas), roles, unidades de medida, formato de usuario/email y largo mínimo de contraseña (6) se validan en PHP; el HTML solo las repite por comodidad. No se puede dar de baja un proveedor con productos activos u órdenes pendientes, ni un producto que está en una orden pendiente.
*   **Integridad Transaccional:** El stock (stock_actual) solo se modifica junto con la creación de un movimiento, y siempre dentro de un bloque beginTransaction() / commit().
*   **Baja Lógica:** Los productos y proveedores nunca se borran físicamente (se marcan como activo = 0) para no romper el historial de auditoría.
*   **Validación en 3 Capas:** Interfaz de usuario (oculta botones), Backend (funciones requerir_login(), requerir_encargado() y requerir_gerencia()) y Base de datos (restricciones CHECK y claves foráneas).

## 🚀 Instalación y Ejecución Local

1.  **Clonar el repositorio** dentro de la carpeta de servidor de XAMPP (C:\xampp\htdocs):
    Abrí la consola en esa ruta y ejecutá: `git clone https://github.com/fedex888/CATastrofe.git catastrofe`
    *(Asegurate de que la carpeta final se llame exactamente `catastrofe`, todo en minúsculas).*

2.  **Iniciar XAMPP:** Abrí el panel de control y dale "Start" a los módulos **Apache** y **MySQL**.

3.  **Importar la Base de Datos:**
    *   Entrá a `http://localhost/phpmyadmin/`.
    *   Andá a la pestaña **Importar**.
    *   Seleccioná el archivo `database.sql` que está en la raíz del proyecto y dale a **Importar**.
    *   *Nota: El script creará automáticamente la base catastrofe_db (¡borra y recrea la base si ya existía!), las 8 tablas y cargará un historial de prueba de ~5 meses (40 productos, 165 órdenes de compra y más de 2.500 movimientos, todos con fechas relativas al día de la importación): el `stock_actual` de cada producto es exactamente la suma de sus movimientos. Al final del script hay una consulta de control que debe devolver 0 filas.*

4.  **Acceder al Sistema:**
    Abrí tu navegador y entrá a: `http://localhost/catastrofe/`

## 🔑 Credenciales de Prueba

El archivo `database.sql` carga dos usuarios por defecto para probar el sistema:

| Rol | Usuario | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Encargado** | `encargado` | `password` | Puede crear, editar, recibir órdenes y registrar movimientos. Edita solo su propio perfil. |
| **Gerencia** | `gerencia` | `password` | Visualiza (Dashboard, Stock, Movimientos y Reportes) y es la única que crea y gestiona usuarios. |

*Son credenciales de demostración: cambialas si el sistema sale del entorno local. Los usuarios nuevos los crea Gerencia desde el módulo de usuarios y requieren una contraseña de al menos 6 caracteres.*

*Si necesitás generar un nuevo hash de contraseña para un usuario manual, podés usar en consola:*
`C:\xampp\php\php.exe -r "echo password_hash('nueva_clave', PASSWORD_DEFAULT);"`

## 📌 Detalles de Implementación (Para la Defensa)

*   **stock_actual es derivado:** No se calcula con un SUM en cada consulta, sino que se guarda en la tabla productos para consultas instantáneas. Su consistencia está garantizada por transacciones y se puede comprobar con la consulta de control incluida al final del `database.sql` (debe devolver 0 filas; conviene correrla después de cada prueba manual).
*   **Recepción Atómica:** Al recibir una orden, se usa un `UPDATE ... WHERE estado = 'pendiente'`. Si dos usuarios intentan recibir la misma orden al mismo tiempo, el segundo recibe un error y no se duplica el stock.
*   **PEPS (Primero Entrado, Primero Salido):** El reporte de vencimientos es una estimación. Como las salidas no descuentan de un lote específico, se asume que el stock físico remanente pertenece a los lotes más nuevos. La pantalla aclara que es una aproximación.

---
*Desarrollado para la cátedra de Trabajo Práctico.*