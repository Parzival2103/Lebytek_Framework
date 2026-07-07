-- Administrador: todos los permisos
INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
CROSS JOIN `auth_permisos` `p`
WHERE `r`.`slug` = 'administrador';

-- Operador y soporte: solo dashboard.ver (si existe)
INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'dashboard.ver'
WHERE `r`.`slug` IN ('operador', 'soporte');
