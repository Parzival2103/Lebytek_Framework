# Legacy seeds y migraciones — inventario FPS (caracterización)

**Fecha:** 2026-07-17  
**Plan:** 02 — Estabilización plataforma  
**Política:** **No archivar ni eliminar** hasta Plan 03 (instalación greenfield con evidencia).

## Contexto

El bootstrap greenfield actual usa `database/schema/schema.sql` + módulos en `database/schema/modules/`. Los scripts incrementales de junio 2026 se consolidaron; copias de referencia permanecen en legacy.

### Proveniencia (rama `consolidation/framework-portal-separation`)

En esta rama (derivada de `main`), **`database/seeds_legacy/` y `database/migrations_legacy/` no están presentes** en el árbol de trabajo. Las listas de paths en `docs/archive/superpowers/fps-artifacts/FPS-legacy-seeds-list.txt` y `FPS-legacy-migrations-list.txt` se caracterizaron desde un checkout que **sí** contiene esos directorios (`feature/backoffice-api-integration` / rama docs). **Política sin cambio:** no archivar ni eliminar hasta evidencia greenfield del Plan 03.

## seeds_legacy

| Path | Rol | Usado por instalador actual |
|------|-----|----------------------------|
| `database/seeds_legacy/baseline-2026-06/` | Copia seeds `010`–`035` pre-consolidación | **No** — ver README interno |
| Archivos | `010_auth_permisos.sql`, `015_core_menu_items.sql`, `020_auth_roles.sql`, `025_auth_roles_permisos.sql`, `030_auth_usuario_admin.sql`, `035_cfg_configuraciones.sql` | Referencia histórica |

Lista completa: `docs/archive/superpowers/fps-artifacts/FPS-legacy-seeds-list.txt`

## migrations_legacy

| Path | Rol | Usado por instalador actual |
|------|-----|----------------------------|
| `database/migrations_legacy/incrementales-2026-06/` | Incrementales pre-consolidación | **No** |
| `database/migrations_legacy/*.sql` (raíz) | Scripts sueltos archivados | **No** |

Lista completa: `docs/archive/superpowers/fps-artifacts/FPS-legacy-migrations-list.txt`

## Migraciones activas (no legacy)

- Directorio: `database/migrations/` — post-baseline incremental
- Reglas: `database/migrations/README.md`
- Manifiesto: cada archivo en `config/modules/*.php`
- Migraciones `*mkt*`: **negocio Portal** — no son SoT del paquete (Plan 05/06)

## Decisión diferida (Plan 03)

Tras instalar greenfield vía `PackagePaths` + `install.php`:

1. Confirmar que seeds plataforma vienen del paquete (`PackagePaths::seedsDir()`).
2. Documentar retención de legacy en `FPS-legacy-archival-decision.md` (Plan 03). **No** archivar ni eliminar físicamente en el roadmap FPS 00–08.

## Decisión aplicada (Plan 03 — 2026-07-17)

Ver `docs/superpowers/FPS-legacy-archival-decision.md`. Resumen: legacy **conservado**, no borrado; seeds activos `010`–`035` movidos a `database/seeds_legacy/` (Option A); greenfield confirmado vía `PackagePaths` + smoke temporal; migraciones huérfanas activas documentadas sin mass-rewrite.

## Comandos de verificación (solo lectura)

```powershell
Get-ChildItem -Recurse database/seeds_legacy -File
Get-ChildItem -Recurse database/migrations_legacy -File -Filter *.sql
Get-ChildItem database/migrations -File -Filter *.sql
```
