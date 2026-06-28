-- Menú y permiso demo para el módulo pdf-kit (instalaciones existentes).
SET NAMES utf8mb4;

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver demo Kit de PDF', 'pdf_kit.ver', 'pdf_kit', 'Permite abrir la vista demo del módulo Kit de PDF');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'pdf_kit.ver'
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
(NULL, 96, 'pdf-kit', 'Kit de PDF', 'bi-file-earmark-pdf', NULL, '/admin/pdf-kit', NULL, 'pdf_kit', 1);

INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 1, 'pdf-kit-demo', 'Demostración PDF', 'bi-file-earmark-arrow-down', '/admin/pdf-kit/demo', '/admin/pdf-kit/demo', 'pdf_kit.ver', 'pdf_kit', 1
FROM core_menu_items p
WHERE p.slug = 'pdf-kit';
