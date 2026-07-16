-- Limpieza completa de recursos demo del CRUD Engine
-- Ejecutar al finalizar pruebas para no dejar ruido en entorno.
-- NO forma parte del instalador; uso manual únicamente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE rp
FROM auth_roles_permisos rp
INNER JOIN auth_permisos p ON p.id = rp.permiso_id
WHERE p.slug IN (
  'demo_clientes.ver',
  'demo_clientes.crear',
  'demo_clientes.editar',
  'demo_clientes.eliminar',
  'demo_productos.ver',
  'demo_productos.crear',
  'demo_productos.editar',
  'demo_productos.eliminar'
);

DELETE FROM auth_permisos
WHERE slug IN (
  'demo_clientes.ver',
  'demo_clientes.crear',
  'demo_clientes.editar',
  'demo_clientes.eliminar',
  'demo_productos.ver',
  'demo_productos.crear',
  'demo_productos.editar',
  'demo_productos.eliminar'
);

DELETE FROM core_menu_items
WHERE slug IN ('crud-demo-clientes', 'crud-demo-productos', 'crud-demo');

DROP TABLE IF EXISTS `dom_demo_productos`;
DROP TABLE IF EXISTS `dom_demo_clientes`;

SET FOREIGN_KEY_CHECKS = 1;
