# 🐾 CATástrofe Petshop - Sistema de Gestión de Compras y Depósito

Sistema interno de gestión web desarrollado para el área de **Compras y Depósito** de CATástrofe Petshop. 

El objetivo del proyecto es reemplazar los controles manuales por una plataforma web segura, clara y fácil de auditar, construida puramente con tecnologías web base (sin frameworks) para facilitar su estudio y defensa académica.

## 🛠️ Tecnologías Utilizadas

*   **Backend:** PHP 8.x (Arquitectura modular, PDO para base de datos).
*   **Base de Datos:** MySQL / MariaDB (Motor InnoDB, transacciones y claves foráneas).
*   **Frontend:** HTML5, CSS3 (Vanilla, con variables CSS y diseño responsivo) y JavaScript (Vanilla, sin compiladores).
*   **Entorno Local:** XAMPP.

## ✨ Características Principales

*   **Autenticación y Roles:** Sistema de login seguro con contraseñas hasheadas (password_hash). Dos roles definidos: **Encargado** (acceso total y operaciones) y **Gerencia** (solo lectura y reportes).
*   **Gestión de Stock:** Catálogo de productos con alertas visuales (crítico, próximo a agotarse, normal). Cálculo automático de cantidad sugerida descontando lo que ya está en pedido.
*   **Movimientos Inmutables:** Registro de entradas y salidas con control estricto para evitar stock negativo. Los errores se corrigen con movimientos de ajuste, no editando el historial.
*   **Órdenes de Compra:** Emisión de pedidos a proveedores con recepción atómica (transaccional). Soporta entregas parciales y calcula desvíos de tiempo.
*   **Reportes Dinámicos:** Reportes de productos más comprados, cumplimiento de proveedores y control de vencimientos (estimación PEPS), con filtros en tiempo real que no recargan la página.
*   **Gestión de Usuarios:** Módulo exclusivo para el Encargado que permite dar de alta nuevos usuarios en el sistema asignándoles su respectivo rol.

## 🔒 Arquitectura y Seguridad

El sistema fue diseñado pensando en las buenas prácticas de seguridad y la integridad de los datos:

*   **Consultas Preparadas (PDO):** 100% del código utiliza prepare() y execute() para prevenir inyecciones SQL.
*   **Protección CSRF:** Todos los formularios (POST) incluyen y validan un token de seguridad único por sesión.
*   **Manejo de Errores Seguro:** Los errores internos de la base de datos no se muestran al usuario (se registran en el log de PHP). La constante APP_DEBUG controla si se muestran detalles técnicos.
*   **Integridad Transaccional:** El stock (stock_actual) solo se modifica junto con la creación de un movimiento, y siempre dentro de un bloque beginTransaction() / commit().
*   **Baja Lógica:** Los productos y proveedores nunca se borran físicamente (se marcan como activo = 0) para no romper el historial de auditoría.
*   **Validación en 3 Capas:** Interfaz de usuario (oculta botones), Backend (funciones requerir_login() y requerir_encargado()) y Base de datos (restricciones CHECK y claves foráneas).

## 🚀 Instalación y Ejecución Local

1.  **Clonar el repositorio** dentro de la carpeta de servidor de XAMPP (C:\xampp\htdocs):
    Abrí la consola en esa ruta y ejecutá: `git clone https://github.com/fedex888/CATastrofe.git catastrofe`
    *(Asegurate de que la carpeta final se llame exactamente `catastrofe`, todo en minúsculas).*

2.  **Iniciar XAMPP:** Abrí el panel de control y dale "Start" a los módulos **Apache** y **MySQL**.

3.  **Importar la Base de Datos:**
    *   Entrá a `http://localhost/phpmyadmin/`.
    *   Andá a la pestaña **Importar**.
    *   Seleccioná el archivo `database.sql` que está en la raíz del proyecto y dale a **Importar**.
    *   *Nota: El script creará automáticamente la base catastrofe_db, las 8 tablas y cargará los datos de prueba (productos, proveedores y movimientos coherentes).*

4.  **Acceder al Sistema:**
    Abrí tu navegador y entrá a: `http://localhost/catastrofe/`

## 🔑 Credenciales de Prueba

El archivo `database.sql` carga dos usuarios por defecto para probar el sistema:

| Rol | Usuario | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Encargado** | `encargado` | `password` | Puede crear, editar, recibir órdenes y registrar movimientos. |
| **Gerencia** | `gerencia` | `password` | Solo puede visualizar (Dashboard, Stock, Movimientos y Reportes). |

*Si necesitás generar un nuevo hash de contraseña para un usuario manual, podés usar en consola:*
`C:\xampp\php\php.exe -r "echo password_hash('nueva_clave', PASSWORD_DEFAULT);"`

## 📌 Detalles de Implementación (Para la Defensa)

*   **stock_actual es derivado:** No se calcula con un SUM en cada consulta, sino que se guarda en la tabla productos para consultas instantáneas. Su consistencia está garantizada por transacciones y por una consulta de control incluida al final del database.sql.
*   **Recepción Atómica:** Al recibir una orden, se usa un `UPDATE ... WHERE estado = 'pendiente'`. Si dos usuarios intentan recibir la misma orden al mismo tiempo, el segundo recibe un error y no se duplica el stock.
*   **PEPS (Primero Entrado, Primero Salido):** El reporte de vencimientos es una estimación. Como las salidas no descuentan de un lote específico, se asume que el stock físico remanente pertenece a los lotes más nuevos. La pantalla aclara que es una aproximación.

---
*Desarrollado para la cátedra de Trabajo Práctico.*