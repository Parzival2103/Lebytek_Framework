-- Control de visibilidad del nombre de empresa junto al logo (login y navegación).
INSERT IGNORE INTO `cfg_configuraciones` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
  ('empresa_mostrar_nombre', '1', 'boolean', 'Mostrar nombre junto al logo en login y barras de navegación');
