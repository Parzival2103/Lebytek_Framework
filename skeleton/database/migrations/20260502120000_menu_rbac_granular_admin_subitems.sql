-- Alinear permisos de subítems del menú Administración con rutas (RBAC granular).
-- Idempotente.

UPDATE `core_menu_items`
SET `permiso_slug` = 'usuarios.gestionar'
WHERE `slug` = 'administracion_usuarios';

UPDATE `core_menu_items`
SET `permiso_slug` = 'roles.gestionar'
WHERE `slug` = 'administracion_roles';
