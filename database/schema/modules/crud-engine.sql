-- Bootstrap del módulo crud-engine (showcase demo).
-- Ejecutado solo cuando el wizard selecciona crud-engine.
-- Estado final consolidado (ex migraciones 20260428–20260607).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `dom_demo_clientes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(150)    NOT NULL,
  `email`       VARCHAR(191)    NOT NULL,
  `telefono`    VARCHAR(30)     DEFAULT NULL,
  `status`      VARCHAR(30)     NOT NULL DEFAULT 'activo',
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demo_clientes_status` (`status`),
  KEY `idx_demo_clientes_deleted` (`deleted`),
  KEY `idx_demo_clientes_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_demo_productos` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`        VARCHAR(50)     NOT NULL,
  `nombre`        VARCHAR(150)    NOT NULL,
  `categoria_id`  BIGINT UNSIGNED NULL,
  `precio_venta`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `stock_actual`  INT             NOT NULL DEFAULT 0,
  `status`        VARCHAR(30)     NOT NULL DEFAULT 'activo',
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_demo_productos_codigo` (`codigo`),
  KEY `idx_demo_productos_status` (`status`),
  KEY `idx_demo_productos_deleted` (`deleted`),
  KEY `idx_demo_productos_categoria` (`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_demo_categorias` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120)    NOT NULL,
  `activa`      TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_demo_categorias_nombre` (`nombre`),
  KEY `idx_demo_categorias_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_demo_pedidos` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folio`       VARCHAR(40)     NOT NULL,
  `cliente_id`  BIGINT UNSIGNED NOT NULL,
  `total`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `status`      VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `notas`       VARCHAR(255)    DEFAULT NULL,
  `deleted`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`  BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`  DATETIME        DEFAULT NULL,
  `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`  DATETIME        DEFAULT NULL,
  `deleted_by`  BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_demo_pedidos_folio` (`folio`),
  KEY `idx_demo_pedidos_status` (`status`),
  KEY `idx_demo_pedidos_cliente` (`cliente_id`),
  KEY `idx_demo_pedidos_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_demo_pedido_items` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id`        BIGINT UNSIGNED NOT NULL,
  `producto_id`      BIGINT UNSIGNED NOT NULL,
  `descripcion`      VARCHAR(150)    NOT NULL,
  `cantidad`         INT             NOT NULL DEFAULT 1,
  `precio_unitario`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `subtotal`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `deleted`          TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`       DATETIME        DEFAULT NULL,
  `updated_by`       BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`       DATETIME        DEFAULT NULL,
  `deleted_by`       BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demo_pedido_items_pedido` (`pedido_id`),
  KEY `idx_demo_pedido_items_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver demo clientes', 'demo_clientes.ver', 'demo_clientes', 'Permite listar y ver detalle en CRUD demo de clientes'),
('Crear demo clientes', 'demo_clientes.crear', 'demo_clientes', 'Permite crear en CRUD demo de clientes'),
('Editar demo clientes', 'demo_clientes.editar', 'demo_clientes', 'Permite editar en CRUD demo de clientes'),
('Eliminar demo clientes', 'demo_clientes.eliminar', 'demo_clientes', 'Permite eliminar lógico en CRUD demo de clientes'),
('Ver demo productos', 'demo_productos.ver', 'demo_productos', 'Permite listar y ver detalle en CRUD demo de productos'),
('Crear demo productos', 'demo_productos.crear', 'demo_productos', 'Permite crear en CRUD demo de productos'),
('Editar demo productos', 'demo_productos.editar', 'demo_productos', 'Permite editar en CRUD demo de productos'),
('Eliminar demo productos', 'demo_productos.eliminar', 'demo_productos', 'Permite eliminar lógico en CRUD demo de productos'),
('Ver demo categorias', 'demo_categorias.ver', 'demo_categorias', 'Listar/ver categorías demo'),
('Crear demo categorias', 'demo_categorias.crear', 'demo_categorias', 'Crear categorías demo'),
('Editar demo categorias', 'demo_categorias.editar', 'demo_categorias', 'Editar categorías demo'),
('Eliminar demo categorias', 'demo_categorias.eliminar', 'demo_categorias', 'Eliminar (lógico) categorías demo'),
('Ver demo pedidos', 'demo_pedidos.ver', 'demo_pedidos', 'Listar/ver pedidos demo'),
('Crear demo pedidos', 'demo_pedidos.crear', 'demo_pedidos', 'Crear pedidos demo'),
('Editar demo pedidos', 'demo_pedidos.editar', 'demo_pedidos', 'Editar pedidos demo'),
('Eliminar demo pedidos', 'demo_pedidos.eliminar', 'demo_pedidos', 'Eliminar (lógico) pedidos demo'),
('Pagar demo pedidos', 'demo_pedidos.pagar', 'demo_pedidos', 'Transición pagar pedido demo'),
('Cancelar demo pedidos', 'demo_pedidos.cancelar', 'demo_pedidos', 'Transición cancelar pedido demo');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'demo_clientes.ver', 'demo_clientes.crear', 'demo_clientes.editar', 'demo_clientes.eliminar',
  'demo_productos.ver', 'demo_productos.crear', 'demo_productos.editar', 'demo_productos.eliminar',
  'demo_categorias.ver', 'demo_categorias.crear', 'demo_categorias.editar', 'demo_categorias.eliminar',
  'demo_pedidos.ver', 'demo_pedidos.crear', 'demo_pedidos.editar', 'demo_pedidos.eliminar',
  'demo_pedidos.pagar', 'demo_pedidos.cancelar'
)
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 90, 'crud-demo', 'CRUD Demo', 'bi-ui-checks-grid', NULL, '/admin/crud', NULL, NULL, 1);

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 1, 'crud-demo-clientes', 'Demo Clientes', 'bi-people', '/admin/crud/demo_clientes', '/admin/crud/demo_clientes', 'demo_clientes.ver', NULL, 1
FROM core_menu_items p
WHERE p.slug = 'crud-demo';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 2, 'crud-demo-productos', 'Demo Productos', 'bi-box-seam', '/admin/crud/demo_productos', '/admin/crud/demo_productos', 'demo_productos.ver', NULL, 1
FROM core_menu_items p
WHERE p.slug = 'crud-demo';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 3, 'crud-demo-categorias', 'Demo Categorías', 'bi-tags', '/admin/crud/demo_categorias', '/admin/crud/demo_categorias', 'demo_categorias.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 4, 'crud-demo-pedidos', 'Demo Pedidos', 'bi-receipt', '/admin/crud/demo_pedidos', '/admin/crud/demo_pedidos', 'demo_pedidos.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

INSERT INTO `dom_demo_clientes` (`nombre`, `email`, `telefono`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'Cliente Demo Uno', 'cliente1.demo@example.com', '5550001001', 'activo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_clientes` WHERE `email` = 'cliente1.demo@example.com');

INSERT INTO `dom_demo_clientes` (`nombre`, `email`, `telefono`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'Cliente Demo Dos', 'cliente2.demo@example.com', '5550001002', 'inactivo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_clientes` WHERE `email` = 'cliente2.demo@example.com');

INSERT INTO `dom_demo_clientes` (`nombre`, `email`, `telefono`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'Cliente Demo Tres', 'cliente3.demo@example.com', '5550001003', 'activo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_clientes` WHERE `email` = 'cliente3.demo@example.com');

INSERT INTO `dom_demo_productos` (`codigo`, `nombre`, `precio_venta`, `stock_actual`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'PRD-DEMO-001', 'Producto Demo A', 199.90, 35, 'activo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_productos` WHERE `codigo` = 'PRD-DEMO-001');

INSERT INTO `dom_demo_productos` (`codigo`, `nombre`, `precio_venta`, `stock_actual`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'PRD-DEMO-002', 'Producto Demo B', 89.50, 80, 'activo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_productos` WHERE `codigo` = 'PRD-DEMO-002');

INSERT INTO `dom_demo_productos` (`codigo`, `nombre`, `precio_venta`, `stock_actual`, `status`, `deleted`, `created_at`, `created_by`)
SELECT 'PRD-DEMO-003', 'Producto Demo C', 49.00, 0, 'inactivo', 0, NOW(), NULL
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_productos` WHERE `codigo` = 'PRD-DEMO-003');

INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Bebidas' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Bebidas');

INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Snacks' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Snacks');

INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Limpieza' AS n, 0 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Limpieza');

UPDATE `dom_demo_productos` p
JOIN `dom_demo_categorias` c ON c.`nombre` = 'Bebidas'
SET p.`categoria_id` = c.`id`
WHERE p.`categoria_id` IS NULL;

INSERT INTO `dom_demo_pedidos` (`folio`, `cliente_id`, `total`, `status`, `notas`, `deleted`, `created_at`)
SELECT 'PED-DEMO-001',
       (SELECT MIN(`id`) FROM `dom_demo_clientes` WHERE `deleted` = 0),
       289.40, 'pendiente', 'Pedido de demostración', 0, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_pedidos` WHERE `folio` = 'PED-DEMO-001')
  AND EXISTS (SELECT 1 FROM `dom_demo_clientes` WHERE `deleted` = 0);

INSERT INTO `dom_demo_pedido_items` (`pedido_id`, `producto_id`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`, `deleted`, `created_at`)
SELECT ped.`id`, COALESCE((SELECT MIN(`id`) FROM `dom_demo_productos` WHERE `deleted` = 0), 0),
       'Producto Demo A', 1, 199.90, 199.90, 0, NOW()
FROM `dom_demo_pedidos` ped
WHERE ped.`folio` = 'PED-DEMO-001'
  AND NOT EXISTS (SELECT 1 FROM `dom_demo_pedido_items` i WHERE i.`pedido_id` = ped.`id`);

INSERT INTO `dom_demo_pedido_items` (`pedido_id`, `producto_id`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`, `deleted`, `created_at`)
SELECT ped.`id`, COALESCE((SELECT MIN(`id`) FROM `dom_demo_productos` WHERE `deleted` = 0), 0),
       'Producto Demo B', 1, 89.50, 89.50, 0, NOW()
FROM `dom_demo_pedidos` ped
WHERE ped.`folio` = 'PED-DEMO-001'
  AND (SELECT COUNT(*) FROM `dom_demo_pedido_items` i WHERE i.`pedido_id` = ped.`id`) = 1;

SET FOREIGN_KEY_CHECKS = 1;
