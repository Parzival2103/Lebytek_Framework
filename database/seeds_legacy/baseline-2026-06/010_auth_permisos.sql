-- auth_permisos — plataforma base (INSERT IGNORE por slug UNIQUE)
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`) VALUES
  ('Ver administración', 'administracion.ver', 'administracion'),
  ('Gestionar usuarios', 'usuarios.gestionar', 'administracion'),
  ('Gestionar roles', 'roles.gestionar', 'administracion'),
  ('Ver bitácora', 'bitacora.ver', 'administracion'),
  ('Ver dashboard', 'dashboard.ver', 'dashboard'),
  ('Ver clientes', 'clientes.ver', 'clientes'),
  ('Crear clientes', 'clientes.crear', 'clientes'),
  ('Editar clientes', 'clientes.editar', 'clientes'),
  ('Eliminar clientes', 'clientes.eliminar', 'clientes'),
  ('Ver estado del sistema', 'sistema.ver', 'sistema');
