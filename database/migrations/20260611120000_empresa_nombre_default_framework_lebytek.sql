-- Normaliza el nombre de empresa por defecto en instalaciones existentes.
UPDATE cfg_configuraciones
SET valor = 'Framework Lebytek'
WHERE clave = 'empresa_nombre'
  AND valor IN ('', 'Mi Empresa', 'Sistema', 'Sistema Administrativo', 'Lebytek');
