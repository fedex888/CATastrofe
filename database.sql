-- =====================================================================
-- CATástrofe Petshop - Sistema de Compras y Depósito
-- Script de estructura, restricciones y datos de prueba
-- =====================================================================

DROP DATABASE IF EXISTS catastrofe_db;
CREATE DATABASE catastrofe_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE catastrofe_db;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE usuarios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    usuario         VARCHAR(50)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    rol             ENUM('encargado','gerencia') NOT NULL DEFAULT 'encargado',
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    nombre                VARCHAR(80) NOT NULL UNIQUE,
    controla_vencimiento  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE proveedores (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(120) NOT NULL,
    contacto            VARCHAR(120),
    telefono            VARCHAR(40),
    email               VARCHAR(120),
    plazo_entrega_dias  INT NOT NULL DEFAULT 7,
    activo              TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_prov_plazo CHECK (plazo_entrega_dias >= 0)
) ENGINE=InnoDB;

CREATE TABLE productos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id    INT NOT NULL,
    proveedor_id    INT NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    descripcion     TEXT,
    stock_actual    DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_minimo    DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_maximo    DECIMAL(10,2) NOT NULL DEFAULT 0,
    unidad_medida   VARCHAR(20) NOT NULL DEFAULT 'unidad',
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_prod_cat  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    CONSTRAINT fk_prod_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT,
    CONSTRAINT chk_prod_stock_actual CHECK (stock_actual >= 0),
    CONSTRAINT chk_prod_stock_min    CHECK (stock_minimo >= 0),
    CONSTRAINT chk_prod_stock_max    CHECK (stock_maximo >= stock_minimo)
) ENGINE=InnoDB;

CREATE TABLE ordenes_compra (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id    INT NOT NULL,
    usuario_id      INT NOT NULL,
    estado          ENUM('pendiente','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
    fecha_creacion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_prometida DATE NOT NULL,
    fecha_recepcion DATETIME NULL,
    CONSTRAINT fk_ord_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ord_user FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE ordenes_detalle (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    orden_id          INT NOT NULL,
    producto_id       INT NOT NULL,
    cantidad_pedida   DECIMAL(10,2) NOT NULL,
    cantidad_recibida DECIMAL(10,2) NULL,
    CONSTRAINT fk_det_orden  FOREIGN KEY (orden_id)    REFERENCES ordenes_compra(id) ON DELETE RESTRICT,
    CONSTRAINT fk_det_prod   FOREIGN KEY (producto_id) REFERENCES productos(id)      ON DELETE RESTRICT,
    CONSTRAINT uq_orden_prod UNIQUE (orden_id, producto_id),
    CONSTRAINT chk_det_pedida CHECK (cantidad_pedida > 0),
    CONSTRAINT chk_det_recibida CHECK (cantidad_recibida IS NULL OR (cantidad_recibida >= 0 AND cantidad_recibida <= cantidad_pedida))
) ENGINE=InnoDB;

CREATE TABLE motivos_movimiento (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(40) NOT NULL UNIQUE,
    nombre      VARCHAR(120) NOT NULL,
    tipo        ENUM('entrada','salida') NOT NULL,
    es_sistema  TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE movimientos_deposito (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    producto_id        INT NOT NULL,
    usuario_id         INT NOT NULL,
    motivo_id          INT NOT NULL,
    orden_id           INT NULL,
    tipo               ENUM('entrada','salida') NOT NULL,
    cantidad           DECIMAL(10,2) NOT NULL,
    fecha_vencimiento  DATE NULL,
    fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_prod  FOREIGN KEY (producto_id) REFERENCES productos(id)           ON DELETE RESTRICT,
    CONSTRAINT fk_mov_user  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)            ON DELETE RESTRICT,
    CONSTRAINT fk_mov_mot   FOREIGN KEY (motivo_id)   REFERENCES motivos_movimiento(id)  ON DELETE RESTRICT,
    CONSTRAINT fk_mov_orden FOREIGN KEY (orden_id)    REFERENCES ordenes_compra(id)      ON DELETE RESTRICT,
    CONSTRAINT chk_mov_cant CHECK (cantidad > 0)
) ENGINE=InnoDB;

-- =====================================================================
-- DATOS DE PRUEBA
-- El stock_actual de cada producto es EXACTAMENTE la suma de sus movimientos
-- (entradas - salidas), igual que lo mantiene la aplicación. Al final de este
-- script hay una consulta de control que debe devolver 0 filas.
-- Las fechas son relativas al día de la importación.
-- =====================================================================
INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES
('Encargado de Depósito', 'encargado', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'encargado', 1),
('Gerencia',              'gerencia',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gerencia', 1);

INSERT INTO categorias (nombre, controla_vencimiento) VALUES
('Alimento', 1), ('Arena', 0), ('Accesorios', 0), ('Higiene', 1), ('Juguetes', 0);

INSERT INTO proveedores (nombre, contacto, telefono, email, plazo_entrega_dias, activo) VALUES
('NutriPet S.A.',   'Laura Méndez',   '11-4455-1200', 'ventas@nutripet.com.ar',   5, 1),
('Arenas del Sur',  'Diego Ferreyra', '11-5566-2200', 'pedidos@arenasdelsur.com', 7, 1),
('Mundo Accesorio', 'Sofía Ramos',    '11-6677-3300', 'hola@mundoaccesorio.com', 10, 1),
('Higiene Total',   'Martín López',   '11-7788-4400', 'ventas@higienetotal.com',  4, 1);

INSERT INTO motivos_movimiento (codigo, nombre, tipo, es_sistema) VALUES
('recepcion_orden',     'Recepción de orden de compra', 'entrada', 1),
('stock_inicial',       'Stock inicial',                'entrada', 1),
('devolucion_cliente',  'Devolución de cliente',        'entrada', 0),
('ajuste_inventario_pos','Ajuste de inventario (+)',     'entrada', 0),
('venta',               'Venta',                        'salida',  0),
('consumo_guarderia',   'Consumo en guardería',         'salida',  0),
('consumo_peluqueria',  'Consumo en peluquería',        'salida',  0),
('producto_danado',     'Producto dañado',              'salida',  0),
('producto_vencido',    'Producto vencido',             'salida',  0),
('ajuste_inventario_neg','Ajuste de inventario (-)',    'salida',  0);

-- stock_actual coincide con la suma de movimientos de más abajo.
INSERT INTO productos (categoria_id, proveedor_id, nombre, descripcion, stock_actual, stock_minimo, stock_maximo, unidad_medida, activo) VALUES
(1,1,'Alimento Premium Perro Adulto 15kg','Bolsa 15 kg',8.00,10.00,40.00,'unidad',1),
(1,1,'Alimento Premium Gato Adulto 10kg','Bolsa 10 kg',25.00,10.00,40.00,'unidad',1),
(1,1,'Alimento Cachorro 3kg','Bolsa 3 kg',12.00,10.00,30.00,'unidad',1),
(1,1,'Alimento Gato Esterilizado 7kg','Urinary',30.00,10.00,40.00,'unidad',1),
(1,1,'Alimento a Granel Perro 1kg','Por kg',12.50,10.00,50.00,'kg',1),
(2,2,'Arena Aglutinante 10kg','Lavanda',15.00,20.00,50.00,'unidad',1),
(2,2,'Arena Sílice 5kg','Cristales',22.00,15.00,40.00,'unidad',1),
(2,2,'Arena Sanitaria 20kg','Bentonita',18.00,15.00,45.00,'unidad',1),
(3,3,'Collar Ajustable','Nylon',20.00,5.00,30.00,'unidad',1),
(3,3,'Correa Reforzada','2 m',3.00,5.00,20.00,'unidad',1),
(3,3,'Cama Mediana','Polar',12.00,5.00,25.00,'unidad',1),
(3,3,'Comedero Doble','Acero',16.00,8.00,30.00,'unidad',1),
(4,4,'Shampoo Perros 500ml','Neutro',9.00,10.00,30.00,'unidad',1),
(4,4,'Toallitas Húmedas x50','Sanitarias',25.00,15.00,50.00,'unidad',1),
(4,4,'Antipulgas Spot-On','Pipeta',40.00,20.00,80.00,'unidad',1),
(4,4,'Paños Absorbentes x30','Entrenamiento',8.00,10.00,40.00,'unidad',1),
(5,3,'Pelota Goma Resistente','Mordedora',30.00,10.00,50.00,'unidad',1),
(5,3,'Ratón de Peluche','Cascabel',5.00,8.00,30.00,'unidad',1);

-- Órdenes: 11 recibidas (con distintos desvíos de plazo y entregas incompletas),
-- 1 pendiente ya demorada y 1 cancelada.
INSERT INTO ordenes_compra (id, proveedor_id, usuario_id, estado, fecha_creacion, fecha_prometida, fecha_recepcion) VALUES
(1, 1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY)),
(2, 2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)),
(3, 1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 3, 1, 'pendiente', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), NULL),
(5, 3, 1, 'cancelada', DATE_SUB(NOW(), INTERVAL 10 DAY), CURDATE(), NULL),
(6, 1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 54 DAY)),
(7, 1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 46 DAY)),
(8, 2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 33 DAY)),
(9, 2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 23 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY)),
(10, 3, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(11, 4, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(12, 1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(13, 2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad_pedida, cantidad_recibida) VALUES
(1, 2, 30.00, 30.00),
(2, 6, 22.00, 22.00),
(3, 1, 15.00, 10.00),
(3, 4, 30.00, 30.00),
(4, 18, 25.00, NULL),
(4, 10, 17.00, NULL),
(5, 9, 5.00, NULL),
(6, 3, 8.00, 8.00),
(6, 5, 15.00, 15.00),
(7, 1, 20.00, 20.00),
(7, 4, 10.00, 10.00),
(8, 7, 20.00, 20.00),
(8, 8, 15.00, 15.00),
(9, 7, 10.00, 10.00),
(9, 8, 12.00, 10.00),
(9, 6, 20.00, 20.00),
(10, 9, 15.00, 15.00),
(10, 11, 10.00, 10.00),
(10, 12, 10.00, 8.00),
(11, 13, 20.00, 20.00),
(11, 14, 25.00, 25.00),
(11, 15, 30.00, 30.00),
(12, 2, 15.00, 15.00),
(12, 4, 10.00, 10.00),
(12, 5, 10.00, 10.00),
(13, 6, 10.00, 10.00),
(13, 7, 10.00, 10.00);

-- Movimientos de depósito. Solo las entradas de categorías que controlan vencimiento
-- (Alimento, Higiene) llevan fecha de vencimiento: cada una es un lote.
-- Incluye un lote vencido (Alimento Cachorro 3kg) para mostrar el reporte de vencimientos.
INSERT INTO movimientos_deposito (producto_id, usuario_id, motivo_id, orden_id, tipo, cantidad, fecha_vencimiento, fecha) VALUES
(1, 1, 2, NULL, 'entrada', 18.00, DATE_ADD(CURDATE(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 1, 2, NULL, 'entrada', 18.00, DATE_ADD(CURDATE(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(3, 1, 2, NULL, 'entrada', 10.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(4, 1, 2, NULL, 'entrada', 15.00, DATE_ADD(CURDATE(), INTERVAL 150 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(5, 1, 2, NULL, 'entrada', 20.00, DATE_ADD(CURDATE(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(6, 1, 2, NULL, 'entrada', 18.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(7, 1, 2, NULL, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(8, 1, 2, NULL, 'entrada', 12.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(9, 1, 2, NULL, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(10, 1, 2, NULL, 'entrada', 15.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(11, 1, 2, NULL, 'entrada', 12.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(12, 1, 2, NULL, 'entrada', 18.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(13, 1, 2, NULL, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 300 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(14, 1, 2, NULL, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 300 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(15, 1, 2, NULL, 'entrada', 15.00, DATE_ADD(CURDATE(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(16, 1, 2, NULL, 'entrada', 15.00, DATE_ADD(CURDATE(), INTERVAL 200 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY)),
(17, 1, 2, NULL, 'entrada', 33.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(18, 1, 2, NULL, 'entrada', 18.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY)),
(5, 1, 6, NULL, 'salida', 1.50, NULL, DATE_SUB(NOW(), INTERVAL 64 DAY)),
(5, 1, 5, NULL, 'salida', 2.50, NULL, DATE_SUB(NOW(), INTERVAL 63 DAY)),
(15, 1, 7, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 63 DAY)),
(10, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 61 DAY)),
(5, 1, 5, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 59 DAY)),
(2, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 58 DAY)),
(5, 1, 5, NULL, 'salida', 1.50, NULL, DATE_SUB(NOW(), INTERVAL 58 DAY)),
(15, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 56 DAY)),
(1, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 55 DAY)),
(1, 1, 7, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 55 DAY)),
(5, 1, 6, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 55 DAY)),
(3, 1, 1, 6, 'entrada', 8.00, DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 54 DAY)),
(5, 1, 1, 6, 'entrada', 15.00, DATE_ADD(CURDATE(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 54 DAY)),
(4, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 53 DAY)),
(8, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 52 DAY)),
(11, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 52 DAY)),
(6, 1, 7, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 51 DAY)),
(2, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 50 DAY)),
(12, 1, 6, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 50 DAY)),
(5, 1, 7, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(18, 1, 7, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 49 DAY)),
(6, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(13, 1, 7, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(16, 1, 7, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 48 DAY)),
(1, 1, 1, 7, 'entrada', 20.00, DATE_ADD(CURDATE(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 46 DAY)),
(4, 1, 1, 7, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 46 DAY)),
(6, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 46 DAY)),
(7, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 46 DAY)),
(8, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 46 DAY)),
(7, 1, 6, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(1, 1, 7, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 43 DAY)),
(2, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 42 DAY)),
(3, 1, 7, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 42 DAY)),
(2, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 41 DAY)),
(2, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 41 DAY)),
(5, 1, 7, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 41 DAY)),
(13, 1, 6, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 41 DAY)),
(5, 1, 5, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(5, 1, 5, NULL, 'salida', 2.50, NULL, DATE_SUB(NOW(), INTERVAL 39 DAY)),
(1, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(14, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 38 DAY)),
(7, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 37 DAY)),
(4, 1, 6, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(13, 1, 5, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(11, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 34 DAY)),
(12, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 34 DAY)),
(4, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 33 DAY)),
(7, 1, 1, 8, 'entrada', 20.00, NULL, DATE_SUB(NOW(), INTERVAL 33 DAY)),
(8, 1, 1, 8, 'entrada', 15.00, NULL, DATE_SUB(NOW(), INTERVAL 33 DAY)),
(7, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 32 DAY)),
(1, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(7, 1, 6, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(11, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(1, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(1, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(5, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(6, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(4, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 27 DAY)),
(9, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 27 DAY)),
(5, 1, 5, NULL, 'salida', 1.50, NULL, DATE_SUB(NOW(), INTERVAL 26 DAY)),
(1, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(5, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(18, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(18, 1, 5, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(6, 1, 1, 9, 'entrada', 20.00, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(6, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(7, 1, 1, 9, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(8, 1, 1, 9, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(4, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 23 DAY)),
(4, 1, 6, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 22 DAY)),
(6, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 22 DAY)),
(2, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(8, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(3, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(6, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(8, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(1, 1, 1, 3, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 1, 1, 3, 'entrada', 30.00, DATE_ADD(CURDATE(), INTERVAL 200 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(6, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(13, 1, 7, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 1, 1, 1, 'entrada', 30.00, DATE_ADD(CURDATE(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY)),
(2, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(18, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(7, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(8, 1, 8, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(17, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(6, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(10, 1, 8, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(10, 1, 6, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(14, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(1, 1, 6, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(2, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(8, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(14, 1, 6, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(16, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(9, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(13, 1, 1, 11, 'entrada', 20.00, DATE_ADD(CURDATE(), INTERVAL 400 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(14, 1, 1, 11, 'entrada', 25.00, DATE_ADD(CURDATE(), INTERVAL 250 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(15, 1, 1, 11, 'entrada', 30.00, DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, 1, 3, NULL, 'entrada', 2.00, DATE_ADD(CURDATE(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(5, 1, 7, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(5, 1, 5, NULL, 'salida', 2.50, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(9, 1, 1, 10, 'entrada', 15.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(10, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(11, 1, 1, 10, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(12, 1, 1, 10, 'entrada', 8.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(13, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 1, 1, 2, 'entrada', 22.00, NULL, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 1, 5, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(13, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(6, 1, 6, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(7, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(4, 1, 6, NULL, 'salida', 6.00, NULL, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(6, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(6, 1, 3, NULL, 'entrada', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(6, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 1, 5, NULL, 'salida', 2.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(13, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 1, 1, 12, 'entrada', 15.00, DATE_ADD(CURDATE(), INTERVAL 150 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 1, 6, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 1, 1, 12, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 210 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 1, 1, 12, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(12, 1, 5, NULL, 'salida', 5.00, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 1, 5, NULL, 'salida', 4.00, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 1, 5, NULL, 'salida', 2.50, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 6, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 1, 1, 13, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 1, 1, 13, 'entrada', 10.00, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 1, 6, NULL, 'salida', 1.00, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 1, 7, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(13, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(14, 1, 5, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(16, 1, 6, NULL, 'salida', 3.00, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 1, 7, NULL, 'salida', 1.00, NULL, NOW());

-- =====================================================================
-- CONSULTA DE CONTROL (debe devolver 0 filas)
-- stock_actual de cada producto debe ser igual a SUM(entradas) - SUM(salidas).
-- Conviene correrla después de cada prueba manual.
-- =====================================================================
SELECT p.id, p.nombre, p.stock_actual,
       COALESCE(SUM(CASE m.tipo WHEN 'entrada' THEN m.cantidad ELSE -m.cantidad END), 0) AS stock_segun_movimientos
FROM productos p
LEFT JOIN movimientos_deposito m ON m.producto_id = p.id
GROUP BY p.id, p.nombre, p.stock_actual
HAVING p.stock_actual <> stock_segun_movimientos;
