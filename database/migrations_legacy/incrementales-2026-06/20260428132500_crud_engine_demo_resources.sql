-- CRUD Engine demo resources (provisional para pruebas)
-- Incluye:
-- 1) Tablas dom_* de prueba
-- 2) Permisos RBAC para recursos demo
-- 3) Ítems de menú para navegar al CRUD genérico
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
  KEY `idx_demo_productos_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver demo clientes', 'demo_clientes.ver', 'crud_demo', 'Permite listar y ver detalle en CRUD demo de clientes'),
('Crear demo clientes', 'demo_clientes.crear', 'crud_demo', 'Permite crear en CRUD demo de clientes'),
('Editar demo clientes', 'demo_clientes.editar', 'crud_demo', 'Permite editar en CRUD demo de clientes'),
('Eliminar demo clientes', 'demo_clientes.eliminar', 'crud_demo', 'Permite eliminar lógico en CRUD demo de clientes'),
('Ver demo productos', 'demo_productos.ver', 'crud_demo', 'Permite listar y ver detalle en CRUD demo de productos'),
('Crear demo productos', 'demo_productos.crear', 'crud_demo', 'Permite crear en CRUD demo de productos'),
('Editar demo productos', 'demo_productos.editar', 'crud_demo', 'Permite editar en CRUD demo de productos'),
('Eliminar demo productos', 'demo_productos.eliminar', 'crud_demo', 'Permite eliminar lógico en CRUD demo de productos');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'demo_clientes.ver',
  'demo_clientes.crear',
  'demo_clientes.editar',
  'demo_clientes.eliminar',
  'demo_productos.ver',
  'demo_productos.crear',
  'demo_productos.editar',
  'demo_productos.eliminar'
)
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 90, 'crud-demo', 'CRUD Demo', 'bi-ui-checks-grid', NULL, '/admin/crud', 'administracion.ver', NULL, 1);

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

SET FOREIGN_KEY_CHECKS = 1;
