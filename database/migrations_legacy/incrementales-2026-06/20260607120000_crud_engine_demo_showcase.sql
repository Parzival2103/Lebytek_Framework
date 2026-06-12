-- CRUD Engine demo showcase (Fase 3 + Fase 4)
-- Añade categorías (belongsTo target), pedidos (estados + relaciones + tabs)
-- e items (hasMany). Idempotente: se puede re-ejecutar en cada despliegue.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Categorías (target de belongsTo desde productos/pedidos no; aquí demo CRUD propio)
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

-- 2) Productos: agregar categoria_id (belongsTo dom_demo_categorias) idempotente
ALTER TABLE `dom_demo_productos`
  ADD COLUMN IF NOT EXISTS `categoria_id` BIGINT UNSIGNED NULL AFTER `nombre`;

CREATE INDEX IF NOT EXISTS `idx_demo_productos_categoria`
  ON `dom_demo_productos` (`categoria_id`);

-- 3) Pedidos (estados + belongsTo cliente + hasMany items)
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

-- 4) Items de pedido (hasMany read-only en tab)
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

-- 5) Permisos RBAC para los nuevos recursos demo
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
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
  'demo_categorias.ver','demo_categorias.crear','demo_categorias.editar','demo_categorias.eliminar',
  'demo_pedidos.ver','demo_pedidos.crear','demo_pedidos.editar','demo_pedidos.eliminar',
  'demo_pedidos.pagar','demo_pedidos.cancelar'
)
WHERE `r`.`slug` = 'administrador';

-- 6) Menú (bajo el parent existente 'crud-demo')
INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 3, 'crud-demo-categorias', 'Demo Categorías', 'bi-tags', '/admin/crud/demo_categorias', '/admin/crud/demo_categorias', 'demo_categorias.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 4, 'crud-demo-pedidos', 'Demo Pedidos', 'bi-receipt', '/admin/crud/demo_pedidos', '/admin/crud/demo_pedidos', 'demo_pedidos.ver', NULL, 1
FROM core_menu_items p WHERE p.slug = 'crud-demo';

-- 7) Datos de ejemplo (solo si las tablas están vacías)
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Bebidas' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Bebidas');
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Snacks' AS n, 1 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Snacks');
INSERT INTO `dom_demo_categorias` (`nombre`, `activa`, `deleted`, `created_at`)
SELECT * FROM (SELECT 'Limpieza' AS n, 0 AS a, 0 AS d, NOW() AS c) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_categorias` WHERE `nombre` = 'Limpieza');

-- Asignar categoría a productos demo existentes que aún no la tengan
UPDATE `dom_demo_productos` p
JOIN `dom_demo_categorias` c ON c.`nombre` = 'Bebidas'
SET p.`categoria_id` = c.`id`
WHERE p.`categoria_id` IS NULL;

-- Pedido de ejemplo + items (solo si no existe el folio)
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
