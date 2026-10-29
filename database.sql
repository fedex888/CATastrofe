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

INSERT INTO productos (categoria_id, proveedor_id, nombre, descripcion, stock_actual, stock_minimo, stock_maximo, unidad_medida, activo) VALUES
(1,1,'Alimento Premium Perro Adulto 15kg','Bolsa 15 kg', 8.00, 10.00, 40.00,'unidad',1),
(1,1,'Alimento Premium Gato Adulto 10kg', 'Bolsa 10 kg',25.00, 10.00, 40.00,'unidad',1),
(1,1,'Alimento Cachorro 3kg',             'Bolsa 3 kg', 12.00, 10.00, 30.00,'unidad',1),
(1,1,'Alimento Gato Esterilizado 7kg',    'Urinary',    30.00, 10.00, 40.00,'unidad',1),
(1,1,'Alimento a Granel Perro 1kg',       'Por kg',     12.50, 10.00, 50.00,'kg',1),
(2,2,'Arena Aglutinante 10kg',            'Lavanda',    15.00, 20.00, 50.00,'unidad',1),
(2,2,'Arena Sílice 5kg',                  'Cristales',  22.00, 15.00, 40.00,'unidad',1),
(2,2,'Arena Sanitaria 20kg',              'Bentonita',  18.00, 15.00, 45.00,'unidad',1),
(3,3,'Collar Ajustable',                  'Nylon',      20.00,  5.00, 30.00,'unidad',1),
(3,3,'Correa Reforzada',                  '2 m',         3.00,  5.00, 20.00,'unidad',1),
(3,3,'Cama Mediana',                      'Polar',      12.00,  5.00, 25.00,'unidad',1),
(3,3,'Comedero Doble',                    'Acero',      16.00,  8.00, 30.00,'unidad',1),
(4,4,'Shampoo Perros 500ml',              'Neutro',      9.00, 10.00, 30.00,'unidad',1),
(4,4,'Toallitas Húmedas x50',             'Sanitarias', 25.00, 15.00, 50.00,'unidad',1),
(4,4,'Antipulgas Spot-On',                'Pipeta',     40.00, 20.00, 80.00,'unidad',1),
(4,4,'Paños Absorbentes x30',             'Entrenamiento',8.00, 10.00, 40.00,'unidad',1),
(5,3,'Pelota Goma Resistente',            'Mordedora',  30.00, 10.00, 50.00,'unidad',1),
(5,3,'Ratón de Peluche',                  'Cascabel',    5.00,  8.00, 30.00,'unidad',1);

INSERT INTO ordenes_compra (id, proveedor_id, usuario_id, estado, fecha_creacion, fecha_prometida, fecha_recepcion) VALUES
(1, 1, 1, 'recibida',  DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY)),
(2, 2, 1, 'recibida',  DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL  8 DAY), DATE_SUB(NOW(), INTERVAL  9 DAY)),
(3, 1, 1, 'recibida',  DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(4, 3, 1, 'pendiente', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL  5 DAY), NULL),
(5, 3, 1, 'cancelada', DATE_SUB(NOW(), INTERVAL 10 DAY), CURDATE(), NULL);

INSERT INTO ordenes_detalle (orden_id, producto_id, cantidad_pedida, cantidad_recibida) VALUES
(1,  2, 30.00, 30.00), (2,  6, 22.00, 22.00),
(3,  1, 15.00, 10.00), (3,  4, 30.00, 30.00),
(4, 16, 15.00, NULL),  (4, 17, 20.00, NULL), (5,  9,  5.00, NULL);

INSERT INTO movimientos_deposito (producto_id, usuario_id, motivo_id, orden_id, tipo, cantidad, fecha_vencimiento, fecha) VALUES
( 2, 1, 1, 1, 'entrada', 30.00, DATE_ADD(CURDATE(), INTERVAL 80 DAY), DATE_SUB(NOW(), INTERVAL 17 DAY)),
( 6, 1, 1, 2, 'entrada', 22.00, NULL, DATE_SUB(NOW(), INTERVAL  9 DAY)),
( 1, 1, 1, 3, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
( 4, 1, 1, 3, 'entrada', 30.00, DATE_ADD(CURDATE(), INTERVAL 200 DAY),DATE_SUB(NOW(), INTERVAL 18 DAY)),
( 3, 1, 2, NULL, 'entrada', 24.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
( 5, 1, 2, NULL, 'entrada', 18.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
( 7, 1, 2, NULL, 'entrada', 18.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
( 8, 1, 2, NULL, 'entrada', 20.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
( 9, 1, 2, NULL, 'entrada',  5.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(10, 1, 2, NULL, 'entrada', 12.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(11, 1, 2, NULL, 'entrada', 16.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(12, 1, 2, NULL, 'entrada', 12.00, DATE_ADD(CURDATE(), INTERVAL 300 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(13, 1, 2, NULL, 'entrada', 25.00, DATE_ADD(CURDATE(), INTERVAL 400 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(14, 1, 2, NULL, 'entrada', 40.00, DATE_ADD(CURDATE(), INTERVAL 250 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(15, 1, 2, NULL, 'entrada', 10.00, DATE_ADD(CURDATE(), INTERVAL  15 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
(16, 1, 2, NULL, 'entrada', 30.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(17, 1, 2, NULL, 'entrada',  8.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
(18, 1, 2, NULL, 'entrada', 12.50, DATE_ADD(CURDATE(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY)),
( 1, 1, 5, NULL, 'salida',  2.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
( 2, 1, 5, NULL, 'salida',  5.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
( 3, 1, 5, NULL, 'salida', 12.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
( 5, 1, 5, NULL, 'salida',  3.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
( 9, 1, 5, NULL, 'salida',  2.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(12, 1, 5, NULL, 'salida',  3.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(15, 1, 5, NULL, 'salida',  2.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(17, 1, 5, NULL, 'salida',  3.00, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- =====================================================================
-- DATOS MASIVOS (Agregado para el profesor)
-- =====================================================================
INSERT INTO ordenes_compra (proveedor_id, usuario_id, estado, fecha_creacion, fecha_prometida, fecha_recepcion) VALUES
(1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 54 DAY)),
(1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 46 DAY)),
(2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 33 DAY)),
(2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 23 DAY), DATE_SUB(NOW(), INTERVAL 24 DAY)),
(3, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
(1, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL  5 DAY), DATE_SUB(NOW(), INTERVAL  4 DAY)),
(2, 1, 'recibida', DATE_SUB(NOW(), INTERVAL 5 DAY),  DATE_SUB(CURDATE(), INTERVAL  2 DAY), DATE_SUB(NOW(), INTERVAL  2 DAY));

INSERT INTO movimientos_deposito (producto_id, usuario_id, motivo_id, tipo, cantidad, fecha) VALUES
(1, 1, 5, 'salida', 1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 1, 5, 'salida', 2, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(3, 1, 5, 'salida', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(5, 1, 5, 'salida', 3, DATE_SUB(NOW(), INTERVAL  9 DAY)),
(6, 1, 5, 'salida', 4, DATE_SUB(NOW(), INTERVAL  8 DAY)),
(9, 1, 5, 'salida', 2, DATE_SUB(NOW(), INTERVAL  7 DAY)),
(12,1, 5, 'salida', 1, DATE_SUB(NOW(), INTERVAL  6 DAY)),
(15,1, 5, 'salida', 2, DATE_SUB(NOW(), INTERVAL  5 DAY)),
(17,1, 5, 'salida', 1, DATE_SUB(NOW(), INTERVAL  4 DAY)),
(1, 1, 5, 'salida', 1, DATE_SUB(NOW(), INTERVAL  3 DAY)),
(2, 1, 6, 'salida', 1, DATE_SUB(NOW(), INTERVAL  2 DAY)),
(4, 1, 6, 'salida', 1, DATE_SUB(NOW(), INTERVAL  1 DAY)),
(1, 1, 7, 'salida', 1, NOW()),
(8, 1, 8, 'salida', 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(10,1, 8, 'salida', 1, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(1, 1, 3, 'entrada', 2, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 1, 3, 'entrada', 1, DATE_SUB(NOW(), INTERVAL  5 DAY)),
(1, 1, 4, 'entrada', 5, DATE_SUB(NOW(), INTERVAL  2 DAY));

-- Sincronización final de stock
UPDATE productos p
LEFT JOIN (
    SELECT producto_id, SUM(CASE tipo WHEN 'entrada' THEN cantidad ELSE -cantidad END) AS neto
    FROM movimientos_deposito GROUP BY producto_id
) m ON m.producto_id = p.id
SET p.stock_actual = COALESCE(m.neto, 0);