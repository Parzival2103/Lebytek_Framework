INSERT IGNORE INTO `cfg_configuraciones` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
  ('empresa_nombre', 'Framework Lebytek', 'string', 'Nombre de la empresa'),
  ('empresa_logo', '', 'string', 'URL del logo'),
  ('empresa_mostrar_nombre', '1', 'boolean', 'Mostrar nombre junto al logo en login y barras de navegación'),
  ('menu_layout', 'side', 'string', 'Posición del menú: side, top, bottom'),
  ('primary_color', '#0d6efd', 'string', 'Color principal del sistema'),
  ('navbar_color', '#1a1d2e', 'string', 'Color de fondo del navbar/sidebar'),
  ('body_color', '#f0f2f5', 'string', 'Color de fondo del área de contenido'),
  ('dark_mode', '0', 'boolean', 'Modo oscuro activado');
