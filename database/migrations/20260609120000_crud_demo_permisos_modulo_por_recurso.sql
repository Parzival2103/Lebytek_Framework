-- Agrupa permisos CRUD demo por recurso en la UI de roles (columna auth_permisos.modulo).
-- Idempotente: solo actualiza filas que aún tienen modulo = 'crud_demo'.

UPDATE `auth_permisos` SET `modulo` = 'demo_clientes'
WHERE `slug` LIKE 'demo_clientes.%' AND `modulo` = 'crud_demo';

UPDATE `auth_permisos` SET `modulo` = 'demo_productos'
WHERE `slug` LIKE 'demo_productos.%' AND `modulo` = 'crud_demo';

UPDATE `auth_permisos` SET `modulo` = 'demo_categorias'
WHERE `slug` LIKE 'demo_categorias.%' AND `modulo` = 'crud_demo';

UPDATE `auth_permisos` SET `modulo` = 'demo_pedidos'
WHERE `slug` LIKE 'demo_pedidos.%' AND `modulo` = 'crud_demo';
