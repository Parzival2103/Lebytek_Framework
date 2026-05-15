# Menú administrativo (`core_menu_items`)

La barra lateral, la topnav y la bottomnav del área `/admin/**` muestran un **árbol de ítems** cargado desde base de datos, filtrado por:

1. **`config/vertical.php`** — `VerticalProfile::filterMenuByModules()` (toggle por `slug`/`vertical_module` cuando el módulo está desactivado en el deploy).
2. **`config/vertical.php`** → `labels.menu.<id>` — `applyMenuLabels()` para textos sin tocar BD.
3. **Sesión** — slugs efectivos `auth_permisos` / `auth_roles`; `AdminNavigationMenuService` aplica [`RbacPolicy`](../../app/Domain/Policies/RbacPolicy.php) igual que antes con `config/menu.php` legacy.

Fuente técnica: [`MenuCatalogRepository`](../../app/Infrastructure/Repositories/MenuCatalogRepository.php) (`core_menu_items`) + [`AdminNavigationMenuService`](../../app/Application/Services/AdminNavigationMenuService.php).

## Tabla núcleo

Ver definición en [`database/schema/schema.sql`](../../database/schema/schema.sql): `core_menu_items`.

| Columna | Rol |
|---------|-----|
| `parent_id` | `NULL` = raíz (`slug` estable como antes `id`). Hijos agrupan **un solo nivel de submenú** bajo ese padre (`submenu[]` en el array pasado a las vistas). |
| `orden` | Orden relativo dentro del mismo padre. |
| `slug` | Único; identificador estable (p. ej. `dashboard`, `administracion`, `administracion_usuarios`). Se expone como clave **`id`** en el array para `VerticalProfile` y etiquetas. |
| `label`, `icon`, `url`, `` `match` `` | Misma semántica que el array legacy; padres sólo-submenú pueden tener `url` vacío si la UI lo permite. |
| `permiso_slug` | Opcional; debe existir como `slug` en `auth_permisos` si no está vacío. En el array de vista aparece como `permiso`. |
| `vertical_module` | Opcional; mismo uso que antes en filtros/subítems (`vertical_module` en entrada de menú legacy). |
| `activo` | Excluye filas de la consulta cuando es `0`. |

**Sin tabla usuario↔menú:** no hace falta; el usuario solo ve ítems cuyos permisos están en sesión.

**Multi-instancia:** una base de datos por despliegue es el modelo habitual. Multi-tenant en una sola BD requeriría columnas extra (p. ej. `tenant_id`) y filtro en el repositorio en una evolución futura.

## Semilla de plataforma

[`database/seeds/015_core_menu_items.sql`](../../database/seeds/015_core_menu_items.sql) inserta (idempotente, `INSERT IGNORE` + subconsultas) las filas del menú mínimo (Dashboard + Administración y subítems). Se ejecuta vía **`php scripts/seed.php`** en orden junto al resto de semillas (`010` … `035`). Convención: cargar antes permisos de plataforma si los ítems referencian `permiso_slug` existentes (`010_auth_permisos.sql`).

Migración incremental nuevas instalaciones viejas sin la tabla: [`database/migrations/20260427120000_core_menu_items.sql`](../../database/migrations/20260427120000_core_menu_items.sql).

## Contrato para las vistas (`$menuFiltrado`)

Estructura idéntica a la antigua `config/menu.php`:

**Raíz**

- `id` — copia del `slug` BD.
- `label`, `icon`, opcionalmente `url`, `match`.
- Opcionalmente `permiso`, `vertical_module`.
- Si hay hijos: `submenu` = lista de líneas siguientes.

**Subítems (solo un nivel bajo cada raíz actualmente)**

- `label`, `icon`, `url`.
- Opcionalmente `permiso`, `vertical_module`.

Las parciales `nav_side`, `nav_top`, `nav_bottom` esperan este contrato (`AdminBaseController` inyecta `menuFiltrado`).

## Añadir un módulo (dominio)

1. Registrar permisos: SQL en `database/seeds/` o migraciones.
2. Asignaciones rol↔permiso: SQL (`auth_roles_permisos`) como corresponda.
3. **INSERT en `core_menu_items`** (filas nuevas por raíz/subítem según rutas `/admin/...`; `orden` coherentes). Slugs únicos; `permiso_slug` opcional coherentes con RBAC.
4. Registrar clave si aplica en `config/vertical.php` (`modules`).
5. Documentar la fila nueva en este mapa cuando el módulo se integre: [`schema-code-map.md`](../core/schema-code-map.md).

Ejemplo conceptual (adaptar valores):

```sql
INSERT INTO core_menu_items
  (parent_id, orden, slug, label, icon, url, `match`, permiso_slug, vertical_module, activo)
VALUES
  (NULL, 40, 'mi_modulo', 'Mi módulo', 'bi-grid', NULL, '/admin/mi_modulo', 'mi_modulo.ver', 'mi_modulo', 1);
```

Subítems: `parent_id` = `id` del padre (tras consultar por `slug`).

## Archivo `config/menu.php`

Queda **vacío y no usado en runtime**; referencia histórica. El catálogo activo es la tabla + archivos en `database/seeds/`.
