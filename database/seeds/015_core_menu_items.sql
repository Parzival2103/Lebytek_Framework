-- core_menu_items — menú admin (idempotente por slug UNIQUE).
-- Subítems vinculados al padre por subconsulta sobre slug.
INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
VALUES
  (NULL, 10, 'dashboard', 'Dashboard', 'bi-speedometer2', '/admin/dashboard', '/admin/dashboard', 'dashboard.ver', NULL, 1),
  (NULL, 20, 'administracion', 'Administración', 'bi-shield-lock', NULL, '/admin/administracion', 'administracion.ver', NULL, 1);

INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT
  `p`.`id`,
  `r`.`orden`,
  `r`.`slug`,
  `r`.`label`,
  `r`.`icon`,
  `r`.`url`,
  NULL,
  `r`.`permiso_slug`,
  NULL,
  1
FROM (SELECT 10 AS orden, 'administracion_usuarios' AS slug, 'Usuarios' AS label, 'bi-people' AS icon, '/admin/administracion/usuarios' AS url, 'usuarios.gestionar' AS permiso_slug
      UNION ALL SELECT 20, 'administracion_roles', 'Roles y permisos', 'bi-key', '/admin/administracion/roles', 'roles.gestionar'
      UNION ALL SELECT 30, 'administracion_ajustes', 'Ajustes', 'bi-gear', '/admin/ajustes', 'administracion.ver') AS `r`
JOIN `core_menu_items` AS `p` ON `p`.`slug` = 'administracion'
WHERE NOT EXISTS (
  SELECT 1 FROM `core_menu_items` `x` WHERE `x`.`slug` = `r`.`slug`
);
