# Checklist: nuevo proyecto desde esta base

Guía para clonar el repo y levantar una instancia (taller, clínica, almacén, etc.) con **despliegue y base de datos propios**. El repositorio distribuye **solo el framework de plataforma** (auth, RBAC, menú admin, ajustes, shell); las tablas **`dom_*`** se añaden cuando desarrollas el producto concreto ([uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md)).

## 1. Base de datos

- [ ] Crear base MySQL/MariaDB vacía (utf8mb4 / `utf8mb4_unicode_ci`).
- [ ] **Instalación nueva:** importar [`database/schema/schema.sql`](../../database/schema/schema.sql) — núcleo `auth_*`, `cfg_*`, `log_*`, `core_menu_items`, stubs `core_*` / `int_*` / etc. **No** incluye tablas de negocio `dom_*`; esas se crean por módulo cuando las necesites.
- [ ] **Actualizar una base que ya existía sin `core_menu_items`:** ejecutar la migración incremental [`database/migrations/20260427120000_core_menu_items.sql`](../../database/migrations/20260427120000_core_menu_items.sql) y luego poblar el menú (semilla o inserts manuales según [modulo-menu.md](../modules/modulo-menu.md)).
- [ ] **Bases muy antiguas con tablas de un dominio de ejemplo previo:** tras backup, revisar [`database/schema/drop_legacy_domain_tables.sql`](../../database/schema/drop_legacy_domain_tables.sql) y la nota en [core-schema-and-modules.md](./core-schema-and-modules.md). Scripts numerados `001`–`008` y similares están en [`database/migrations_legacy/`](../../database/migrations_legacy/) solo como **referencia histórica**; no forman parte del bootstrap de una instalación nueva.
- [ ] Convención de prefijos: [table-prefix-convention.md](./table-prefix-convention.md).

## 2. Entorno

- [ ] Copiar `.env.example` → `.env` y definir `DB_*`, `APP_URL`, `APP_DEBUG` según entorno.
- [ ] Verificar que el document root del servidor apunte a `public/`.

## 3. Perfil vertical (esta instancia)

Archivo: [`config/vertical.php`](../../config/vertical.php)

- [ ] Mantener coherencia entre `modules` y el menú: cada clave debe corresponder al **`slug` de una entrada raíz** en `core_menu_items` (u omitir si el ítem define `vertical_module`). Por defecto existen `dashboard` y `administracion`; pon `false` lo que esta instancia no ofrezca **y** ocultará el filtro de módulos (además conviene retirar o desactivar filas en BD si no deben existir rutas huérfanas).
- [ ] Opcional: en `labels.menu`, sobrescribir textos (`slug` → etiqueta visible) sin tocar [`core_menu_items`](../../database/schema/schema.sql) — ver [modulo-menu.md](../modules/modulo-menu.md).
- [ ] Para nuevos ítems de menú, insertar en **`core_menu_items`** y ampliar `vertical.modules` si aplica el toggle por vertical.

## 4. Semillas y permisos

- [ ] Ejecutar semillas [`php scripts/seed.php`](../../scripts/seed.php), que recorre en orden los `.sql` de [`database/seeds/`](../../database/seeds/) (permisos, menú, roles, vínculos, usuario admin, configuración). Ver [`database/seeds/README.md`](../../database/seeds/README.md).
- [ ] Sin filas en `core_menu_items` el menú administrativo sale vacío; los `INSERT IGNORE` del núcleo no duplican por `slug` único al re-ejecutar.
- [ ] Al ampliar módulos: coherencia entre nuevos `.sql`, rutas y `permiso_slug` en menú donde corresponda.

## 5. Marca y layout

- [ ] Panel **Ajustes** o `cfg_configuraciones`: nombre de empresa, logo, colores, posición del menú (lateral / superior / inferior).
- [ ] PWA: activos bajo `public/`; si cambias rutas, revisar [`PwaController`](../../app/Presentation/Controllers/PwaController.php).

## 6. Dominio de negocio propio (`dom_*`)

- [ ] Nuevas tablas y flujos de producto: migraciones SQL bajo `database/migrations/` (o ampliación de `schema.sql` en greenfield), entidades, repositorios y casos de uso según [.cursor/rules/arquitectura-base.mdc](../../.cursor/rules/arquitectura-base.mdc).
- [ ] Rutas en `routes/web.php`, permisos/asignaciones en `database/seeds/`, entradas en **`core_menu_items`** y claves en `vertical.php` siguiendo [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md).
- [ ] No hay pipeline de dominio “incluido” en el repo base: cada vertical define sus propias `dom_*` y reglas.

## 7. Documentación de referencia

- [ ] [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md) — checklist oficial de un módulo nuevo.
- [ ] [modulo-menu.md](../modules/modulo-menu.md) — menú admin desde BD y contrato con las vistas.
- [ ] [modulo-dashboard.md](../modules/modulo-dashboard.md) — extensión del dashboard por proveedores.
- [ ] [table-prefix-convention.md](./table-prefix-convention.md) — prefijos de plataforma vs `dom_*`.
- [ ] [schema-code-map.md](./schema-code-map.md) — tablas ↔ código.
- [ ] [core-schema-and-modules.md](./core-schema-and-modules.md) — qué es solo plataforma y qué es extensión.
- [ ] *Opcional / histórico:* [example-domain-imprenta.md](../legacy/example-domain-imprenta.md) — ideas de modelo de un dominio que **ya no** está en el código actual; no como origen de tablas obligatorias.

---

*Objetivo: mismo framework, `vertical.php` y datos por instancia, más migraciones y `dom_*` propias del producto.*
