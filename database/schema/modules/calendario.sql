-- Bootstrap del módulo calendario (demo agenda de citas).
-- Ejecutado solo cuando el wizard selecciona el módulo calendario.
-- Provee la tabla demo del recurso CRUD `demo_citas`, sus permisos, la entrada
-- de menú al calendario y citas de ejemplo en el mes actual.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `dom_demo_citas` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente`       VARCHAR(150)    NOT NULL,
  `servicio`      VARCHAR(150)    NOT NULL,
  `estado`        VARCHAR(30)     NOT NULL DEFAULT 'pendiente',
  `fecha_inicio`  DATETIME        NOT NULL,
  `fecha_fin`     DATETIME        DEFAULT NULL,
  `deleted`       TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`    BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`    DATETIME        DEFAULT NULL,
  `updated_by`    BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`    DATETIME        DEFAULT NULL,
  `deleted_by`    BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demo_citas_estado` (`estado`),
  KEY `idx_demo_citas_deleted` (`deleted`),
  KEY `idx_demo_citas_fecha_inicio` (`fecha_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver demo citas', 'demo_citas.ver', 'demo_citas', 'Permite listar y ver detalle en la agenda demo de citas'),
('Crear demo citas', 'demo_citas.crear', 'demo_citas', 'Permite crear en la agenda demo de citas'),
('Editar demo citas', 'demo_citas.editar', 'demo_citas', 'Permite editar en la agenda demo de citas'),
('Eliminar demo citas', 'demo_citas.eliminar', 'demo_citas', 'Permite eliminar lógico en la agenda demo de citas');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'demo_citas.ver', 'demo_citas.crear', 'demo_citas.editar', 'demo_citas.eliminar'
)
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 95, 'calendario', 'Calendario', 'bi-calendar-event', NULL, '/admin/calendario', NULL, 'calendario', 1);

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 1, 'calendario-demo-citas', 'Agenda de Citas', 'bi-calendar-week', '/admin/calendario/demo_citas', '/admin/calendario/demo_citas', 'demo_citas.ver', 'calendario', 1
FROM core_menu_items p
WHERE p.slug = 'calendario';

-- Citas de ejemplo en el mes actual (solo si la tabla está vacía).
INSERT INTO `dom_demo_citas` (`cliente`, `servicio`, `estado`, `fecha_inicio`, `fecha_fin`, `deleted`, `created_at`)
SELECT * FROM (
  SELECT
    'Ana López'  AS cliente, 'Corte de cabello' AS servicio, 'confirmada' AS estado,
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 10:00:00'), INTERVAL 2 DAY)  AS fecha_inicio,
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 11:00:00'), INTERVAL 2 DAY)  AS fecha_fin,
    0 AS deleted, NOW() AS created_at
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_demo_citas`);

INSERT INTO `dom_demo_citas` (`cliente`, `servicio`, `estado`, `fecha_inicio`, `fecha_fin`, `deleted`, `created_at`)
SELECT * FROM (
  SELECT
    'Beto Ruiz', 'Tinte', 'pendiente',
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 13:00:00'), INTERVAL 8 DAY),
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 14:30:00'), INTERVAL 8 DAY),
    0, NOW()
) AS t
WHERE (SELECT COUNT(*) FROM `dom_demo_citas`) = 1;

INSERT INTO `dom_demo_citas` (`cliente`, `servicio`, `estado`, `fecha_inicio`, `fecha_fin`, `deleted`, `created_at`)
SELECT * FROM (
  SELECT
    'Carla Díaz', 'Peinado', 'confirmada',
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 09:00:00'), INTERVAL 14 DAY),
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 10:00:00'), INTERVAL 14 DAY),
    0, NOW()
) AS t
WHERE (SELECT COUNT(*) FROM `dom_demo_citas`) = 2;

INSERT INTO `dom_demo_citas` (`cliente`, `servicio`, `estado`, `fecha_inicio`, `fecha_fin`, `deleted`, `created_at`)
SELECT * FROM (
  SELECT
    'Darío Sol', 'Arreglo de barba', 'pendiente',
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 16:00:00'), INTERVAL 19 DAY),
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 16:30:00'), INTERVAL 19 DAY),
    0, NOW()
) AS t
WHERE (SELECT COUNT(*) FROM `dom_demo_citas`) = 3;

INSERT INTO `dom_demo_citas` (`cliente`, `servicio`, `estado`, `fecha_inicio`, `fecha_fin`, `deleted`, `created_at`)
SELECT * FROM (
  SELECT
    'Elena Mar', 'Manicure', 'cancelada',
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 11:00:00'), INTERVAL 23 DAY),
    DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 12:00:00'), INTERVAL 23 DAY),
    0, NOW()
) AS t
WHERE (SELECT COUNT(*) FROM `dom_demo_citas`) = 4;

SET FOREIGN_KEY_CHECKS = 1;
