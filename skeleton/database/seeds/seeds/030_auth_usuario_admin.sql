-- Usuario inicial (INSERT IGNORE — email UNIQUE). Contraseña: Admin123! (cambiar en producción)
-- Hash bcrypt cost 12 — generado una sola vez para seeds reproducibles.
INSERT IGNORE INTO `auth_usuarios`
  (`nombre`, `apellido`, `email`, `password`, `activo`)
VALUES (
  'Admin',
  'Sistema',
  'admin@sistema.local',
  '$2y$12$KkB982JOpNRlhl.OCJFKAef/1elptraPngsoWY9l95OLDmLEze95K',
  1
);

INSERT IGNORE INTO `auth_usuarios_roles` (`usuario_id`, `rol_id`)
SELECT `u`.`id`, `r`.`id`
FROM `auth_usuarios` `u`
JOIN `auth_roles` `r` ON `r`.`slug` = 'administrador'
WHERE `u`.`email` = 'admin@sistema.local';
