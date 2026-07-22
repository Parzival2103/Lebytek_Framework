# Decisión legacy seeds/migrations — Plan 03

**Fecha:** 2026-07-17  
**Evidencia:** `InstallGreenfieldTest` + smoke consumidor temporal sin `database/schema/`.

## Hallazgos

1. Bootstrap greenfield activo: `database/schema/schema.sql` (sección `DATOS INICIALES`).
2. `database/seeds/` activo: sin `*.sql` sueltos (solo `README.md`).
3. Manifiestos `config/modules/*.php`: `seeds` = `[]`; bootstrap vía `bootstrap_sql` resuelto por `PackagePaths`.
4. `database/seeds_legacy/` y `database/migrations_legacy/`: **no** referenciados por Installer ni scripts activos.
5. En esta rama (desde `main`), `database/migrations_legacy/` **no** estaba presente en el árbol; las listas `FPS-legacy-migrations-list.txt` caracterizan un checkout que sí lo tenía (`feature/backoffice-api-integration`). No se crea ni se elimina en Plan 03.

### Movimiento Plan 03 (Option A — aprobado)

Los seeds sueltos activos `010`–`035` se movieron (git mv, sin delete) de `database/seeds/` a `database/seeds_legacy/` porque el bootstrap greenfield vive en `schema.sql` (`DATOS INICIALES`). Esto **no** es limpieza destructiva del archivo legacy: se conserva el contenido en `seeds_legacy/` y se deja `database/seeds/` vacío de `*.sql` para el gate greenfield.

Archivos movidos:

- `010_auth_permisos.sql`
- `015_core_menu_items.sql`
- `020_auth_roles.sql`
- `025_auth_roles_permisos.sql`
- `030_auth_usuario_admin.sql`
- `035_cfg_configuraciones.sql`

### Inventario migraciones activas huérfanas / no declaradas

`database/migrations/*.sql` vs `config/modules/*/migraciones`: **18** archivos presentes en disco y **no** listados en manifiestos (no se reescriben manifiestos en Plan 03; fuera de gates InstallGreenfield / PackagePaths / PlatformSqlResolve):

- `20260427120000_core_menu_items.sql`
- `20260428132500_crud_engine_demo_resources.sql`
- `20260428132600_drop_crud_engine_demo_resources.sql`
- `20260428133000_crud_demo_menu_parent_perm_null.sql`
- `20260502120000_menu_rbac_granular_admin_subitems.sql`
- `20260502150000_auth_permisos_dom_clientes.sql`
- `20260503100000_deprecate_legacy_domain_permissions_and_menus.sql`
- `20260611120000_empresa_nombre_default_framework_lebytek.sql`
- `20260612120000_empresa_mostrar_nombre.sql`
- `20260612130000_auth_login_intentos.sql`
- `20260630120000_mkt_leads_api_columns.sql`
- `20260630180000_mkt_landing_whatsapp_content.sql`
- `20260701160000_mkt_leads_api_instance_public_id.sql`
- `20260701170000_mkt_leads_api_lifecycle_status.sql`
- `20260706120000_mkt_leads_churn_columns.sql`
- `20260706120100_mkt_paquetes_limits.sql`
- `20260706120200_rep_churn_metrics.sql`
- `20260714210000_mkt_landing_copy_seo.sql`

Varias son negocio Portal (`*mkt*`) o incrementales pre-consolidación; ownership/manifest queda para planes posteriores (no mass-rewrite en Plan 03).

## Decisión

| Path | Acción Plan 03 | Acción futura |
|------|----------------|---------------|
| `database/seeds_legacy/` | **Conservar** (incluye `010`–`035` movidos desde `seeds/`) | Permanece hasta plan/aprobación explícita de archivo o eliminación (fuera del roadmap FPS 00–08) |
| `database/migrations_legacy/` | **Conservar** si/cuando exista en el árbol; no crear ni borrar en Plan 03 | Idem |
| `database/seeds/*.sql` sueltos | **Movidos** a `seeds_legacy/`; **no reintroducir** en activo | N/A |
| `database/schema/schema.sql` | SoT plataforma en paquete | Consumidor no copia como SoT |
| Migraciones activas huérfanas | **Documentadas**; sin mass-rewrite de manifiestos | Spec/plan futuro |

## Criterio cumplido

Greenfield no requiere seeds legacy numerados (`010`–`035`) en `database/seeds/` activo. El movimiento a `seeds_legacy/` es archivo de seeds activos sueltos (Option A), **no** eliminación destructiva del archivo legacy. Archivo destructivo (delete de `seeds_legacy/` / `migrations_legacy/`) exige spec/plan futuro y sign-off humano.

## Smoke greenfield (consumidor temporal)

Ejecutado 2026-07-17 desde worktree `framework-portal-separation`:

- Consumidor Composer `path` + symlink al paquete; **sin** `database/schema/` local (`LOCAL_SCHEMA_DIR=False`).
- `composer install --no-interaction` exit 0.
- `PackagePaths::schema('schema.sql')` → `SCHEMA_OK`.
- Migrate MySQL opcional **no** ejecutado (sin BD vacía garantizada en el entorno de smoke).
