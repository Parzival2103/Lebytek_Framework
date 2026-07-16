-- Permisos CRUD para recurso dom clientes (config/cruds/clientes.json).
-- Idempotente (INSERT IGNORE). No elimina permisos legacy.

INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver clientes', 'clientes.ver', 'clientes', 'CRUD Engine: listar y ver clientes'),
('Crear clientes', 'clientes.crear', 'clientes', 'CRUD Engine: crear clientes'),
('Editar clientes', 'clientes.editar', 'clientes', 'CRUD Engine: editar clientes'),
('Eliminar clientes', 'clientes.eliminar', 'clientes', 'CRUD Engine: borrado lógico clientes');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'clientes.ver',
  'clientes.crear',
  'clientes.editar',
  'clientes.eliminar'
)
WHERE `r`.`slug` = 'administrador';
