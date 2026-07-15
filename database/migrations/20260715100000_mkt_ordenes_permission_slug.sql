-- Repair: earlier membership-orders migration used non-existent auth_permisos.clave.
INSERT INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`)
SELECT 'Autorizar órdenes de membresía', 'marketing.ordenes', 'marketing', 'Autorizar órdenes de membresía'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `slug` = 'marketing.ordenes');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'marketing.ordenes'
WHERE `r`.`slug` = 'administrador';
