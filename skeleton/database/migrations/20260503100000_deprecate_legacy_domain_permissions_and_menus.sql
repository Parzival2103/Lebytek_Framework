-- =========================================================
-- Migration:
-- 20260503100000_deprecate_legacy_domain_permissions_and_menus.sql
--
-- Objetivo:
-- 1. Agregar columnas de vigencia a auth_permisos.
-- 2. Marcar permisos legacy de dominio anterior como inactivos.
-- 3. Retirar asignaciones de roles sobre permisos legacy.
-- 4. Desactivar menús legacy ligados a esos permisos.
--
-- Compatible con MariaDB 10.11+
-- =========================================================

START TRANSACTION;

-- =========================================================
-- 1. Agregar columnas a auth_permisos
-- =========================================================

ALTER TABLE auth_permisos
    ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion,
    ADD COLUMN IF NOT EXISTS deprecated_at DATETIME NULL AFTER activo,
    ADD COLUMN IF NOT EXISTS deprecated_reason VARCHAR(255) NULL AFTER deprecated_at;

-- Índice útil para filtrar permisos activos por módulo en la UI de roles/permisos.
CREATE INDEX IF NOT EXISTS idx_auth_permisos_activo_modulo
    ON auth_permisos (activo, modulo);

-- =========================================================
-- 2. Marcar permisos legacy como inactivos
-- =========================================================
-- Legacy detectado en el dump actual:
-- pedidos.*, formularios.*, temporadas.*, catalogo.*,
-- produccion.*, entregas.*, reportes.*
--
-- Nota:
-- clientes.* se mantiene activo porque ya pertenece al CRUD actual.
-- demo_clientes.* y demo_productos.* se mantienen activos.
-- dashboard.*, administracion.*, usuarios.*, roles.*, bitacora.* se mantienen activos.
-- =========================================================

UPDATE auth_permisos
SET
    activo = 0,
    deprecated_at = COALESCE(deprecated_at, NOW()),
    deprecated_reason = 'Permiso legacy de dominio anterior retirado del framework base'
WHERE slug REGEXP '^(pedidos|formularios|temporadas|catalogo|produccion|entregas|reportes)\\.';

-- =========================================================
-- 3. Retirar asignaciones de roles a permisos legacy
-- =========================================================
-- No borra auth_permisos; solo quita la relación efectiva
-- para que no sigan apareciendo como permisos asignados.
-- =========================================================

DELETE arp
FROM auth_roles_permisos arp
INNER JOIN auth_permisos ap ON ap.id = arp.permiso_id
WHERE ap.activo = 0
  AND ap.slug REGEXP '^(pedidos|formularios|temporadas|catalogo|produccion|entregas|reportes)\\.';

-- =========================================================
-- 4. Desactivar menús legacy de dominio anterior
-- =========================================================
-- Se asume estructura actual de core_menu_items con:
-- - permiso_slug
-- - activo
-- - slug
-- - url
-- - vertical_module
--
-- Si alguna instalación antigua no tiene alguna columna,
-- ajustar esta sección manualmente.
-- =========================================================

UPDATE core_menu_items
SET activo = 0
WHERE
    (
        permiso_slug REGEXP '^(pedidos|formularios|temporadas|catalogo|produccion|entregas|reportes)\\.'
        OR slug REGEXP '^(pedidos|formularios|temporadas|catalogo|produccion|entregas|reportes)(\\.|_|-|$)'
        OR vertical_module IN ('pedidos', 'formularios', 'temporadas', 'catalogo', 'produccion', 'entregas', 'reportes')
        OR url REGEXP '/(pedidos|formularios|temporadas|catalogo|produccion|entregas|reportes)(/|$)'
    );

-- =========================================================
-- 5. Asegurar permisos actuales como activos
-- =========================================================

UPDATE auth_permisos
SET
    activo = 1,
    deprecated_at = NULL,
    deprecated_reason = NULL
WHERE slug IN (
    'administracion.ver',
    'usuarios.gestionar',
    'roles.gestionar',
    'bitacora.ver',
    'dashboard.ver',

    'clientes.ver',
    'clientes.crear',
    'clientes.editar',
    'clientes.eliminar',

    'demo_clientes.ver',
    'demo_clientes.crear',
    'demo_clientes.editar',
    'demo_clientes.eliminar',

    'demo_productos.ver',
    'demo_productos.crear',
    'demo_productos.editar',
    'demo_productos.eliminar'
);

COMMIT;

-- =========================================================
-- 6. Consultas de verificación
-- Ejecutar después de la migración.
-- =========================================================

SELECT
    modulo,
    activo,
    COUNT(*) AS total
FROM auth_permisos
GROUP BY modulo, activo
ORDER BY modulo, activo DESC;

SELECT
    id,
    nombre,
    slug,
    modulo,
    activo,
    deprecated_at,
    deprecated_reason
FROM auth_permisos
ORDER BY activo ASC, modulo ASC, slug ASC;

SELECT
    ap.slug,
    COUNT(arp.rol_id) AS asignaciones
FROM auth_permisos ap
LEFT JOIN auth_roles_permisos arp ON arp.permiso_id = ap.id
WHERE ap.activo = 0
GROUP BY ap.slug
ORDER BY ap.slug;
