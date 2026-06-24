-- Bootstrap del módulo reportes.
-- Provee la tabla rep_reportes, los permisos del módulo, la entrada de menú y un
-- reporte demo (compartido) de colección sobre el recurso CRUD demo `demo_citas`.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `rep_reportes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave`         VARCHAR(120)    DEFAULT NULL,
  `nombre`        VARCHAR(150)    NOT NULL,
  `fuente_key`    VARCHAR(120)    NOT NULL,
  `modo`          VARCHAR(20)     NOT NULL DEFAULT 'coleccion',
  `columnas`      JSON            NOT NULL,
  `tratamientos`  JSON            NOT NULL,
  `filtros`       JSON            NOT NULL,
  `periodo`       JSON            NOT NULL,
  `opciones`      JSON            NOT NULL,
  `template_key`  VARCHAR(120)    NOT NULL,
  `compartido`    TINYINT(1)      NOT NULL DEFAULT 0,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep_reportes_clave` (`clave`),
  KEY `idx_rep_reportes_fuente` (`fuente_key`),
  KEY `idx_rep_reportes_deleted` (`deleted`),
  KEY `idx_rep_reportes_owner` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver reportes',      'reportes.ver',        'reportes', 'Permite listar y ver reportes guardados'),
('Crear reportes',    'reportes.crear',      'reportes', 'Permite crear reportes'),
('Editar reportes',   'reportes.editar',     'reportes', 'Permite editar reportes propios'),
('Eliminar reportes', 'reportes.eliminar',   'reportes', 'Permite eliminar reportes propios'),
('Generar reportes',  'reportes.generar',    'reportes', 'Permite generar el PDF de un reporte'),
('Compartir reportes','reportes.compartir',  'reportes', 'Permite marcar un reporte como compartido');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'reportes.ver', 'reportes.crear', 'reportes.editar',
  'reportes.eliminar', 'reportes.generar', 'reportes.compartir'
)
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 97, 'reportes', 'Reportes', 'bi-file-earmark-bar-graph', '/admin/reportes', '/admin/reportes', 'reportes.ver', 'reportes', 1);

INSERT IGNORE INTO `rep_reportes`
(`clave`, `nombre`, `fuente_key`, `modo`, `columnas`, `tratamientos`, `filtros`, `periodo`, `opciones`, `template_key`, `compartido`, `deleted`, `created_at`)
VALUES
(
  'demo_citas_por_estado',
  'Citas por estado',
  'citas',
  'coleccion',
  '[{"name":"estado","label":"Estado","type":"text"}]',
  '{"group_by":["estado"],"aggregations":[{"op":"count","column":""}],"order":{"by":"estado","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Citas por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1,
  0,
  NOW()
);

-- Reportes demo adicionales (iteración 3): variedad de tratamientos por módulo.
INSERT IGNORE INTO `rep_reportes`
(`clave`, `nombre`, `fuente_key`, `modo`, `columnas`, `tratamientos`, `filtros`, `periodo`, `opciones`, `template_key`, `compartido`, `deleted`, `created_at`)
VALUES
(
  'demo_pedidos_ventas_cliente',
  'Ventas por cliente',
  'pedidos',
  'coleccion',
  '[{"name":"cliente_id","label":"Cliente","type":"number"},{"name":"total","label":"Total","type":"money"}]',
  '{"group_by":["cliente_id"],"aggregations":[{"op":"count","column":""},{"op":"sum","column":"total"}],"order":{"by":"cliente_id","dir":"asc"}}',
  '{}',
  '{"preset":"mes"}',
  '{"titulo":"Ventas por cliente","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_pedidos_por_estado',
  'Pedidos por estado',
  'pedidos',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Pedidos por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_productos_inventario_categoria',
  'Inventario por categoría',
  'productos',
  'coleccion',
  '[{"name":"categoria_id","label":"Categoría","type":"number"},{"name":"stock_actual","label":"Stock","type":"number"},{"name":"precio_venta","label":"Precio","type":"money"}]',
  '{"group_by":["categoria_id"],"aggregations":[{"op":"sum","column":"stock_actual"},{"op":"sum","column":"precio_venta"}],"order":{"by":"categoria_id","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Inventario por categoría","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_productos_por_estado',
  'Productos por estado',
  'productos',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Productos por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
),
(
  'demo_clientes_por_estado',
  'Clientes por estado',
  'clientes',
  'coleccion',
  '[{"name":"status","label":"Estado","type":"text"}]',
  '{"group_by":["status"],"aggregations":[{"op":"count","column":""}],"order":{"by":"status","dir":"asc"}}',
  '{}',
  '{"preset":"todo"}',
  '{"titulo":"Clientes por estado","orientacion":"portrait"}',
  'tabla_estadistica',
  1, 0, NOW()
);

SET FOREIGN_KEY_CHECKS = 1;
