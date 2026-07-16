-- CRUD Demo: el ítem padre no debe exigir administracion.ver; se muestra como agrupador
-- si el usuario tiene permisos granulares en los hijos (ver AdminNavigationMenuService).

SET FOREIGN_KEY_CHECKS = 0;

UPDATE `core_menu_items`
SET `permiso_slug` = NULL
WHERE `slug` = 'crud-demo'
  AND `permiso_slug` = 'administracion.ver';

SET FOREIGN_KEY_CHECKS = 1;
