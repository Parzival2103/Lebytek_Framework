# Checklist: nuevo proyecto desde esta base

Guía para clonar el repo y levantar una instancia (taller, clínica, almacén, etc.) con **despliegue y base de datos propios**. El repositorio distribuye **solo el framework de plataforma** (auth, RBAC, menú admin, ajustes, shell); las tablas **`dom_*`** se añaden cuando desarrollas el producto concreto ([uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md)).

## 1. Base de datos

- [ ] Crear base MySQL/MariaDB vacía (utf8mb4 / `utf8mb4_unicode_ci`).
- [ ] **Instalación nueva:** importar [`database/schema/schema.sql`](../../database/schema/schema.sql) — incluye DDL de plataforma y sección **`DATOS INICIALES`** (permisos, roles, menú admin, usuario `admin@sistema.local`, ajustes). **No** incluye tablas demo `dom_demo_*`; esas las aporta el módulo opcional `crud-engine` vía [`database/schema/modules/crud-engine.sql`](../../database/schema/modules/crud-engine.sql) (wizard o `php scripts/seed.php --crud-engine`).
- [ ] **Alternativa automatizada:** wizard web en `public/install/` o `php scripts/install.php --modules=core,crud-engine` (schema → bootstrap modular → registro en `cfg_modulos`).
- [ ] **Actualizar una base legacy muy antigua:** scripts incrementales archivados en [`database/migrations_legacy/incrementales-2026-06/`](../../database/migrations_legacy/incrementales-2026-06/); nuevas instalaciones no los necesitan.
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

## 4. Bootstrap y permisos

- [ ] Greenfield: `schema.sql` ya incluye permisos, menú, roles y usuario admin de prueba. Para desarrollo local: [`php scripts/seed.php`](../../scripts/seed.php) (re-ejecuta `schema.sql`; añadir `--crud-engine` para demo CRUD).
- [ ] Los archivos numerados antiguos (`010`–`035`) están en [`database/seeds_legacy/baseline-2026-06/`](../../database/seeds_legacy/baseline-2026-06/) solo como referencia.

## 5. Marca y layout

- [ ] Panel **Ajustes** o `cfg_configuraciones`: nombre de empresa, logo, colores, posición del menú (lateral / superior / inferior).
- [ ] PWA: activos bajo `public/`; si cambias rutas, revisar [`PwaController`](../../app/Presentation/Controllers/PwaController.php).

## 6. Dominio de negocio propio (`dom_*`)

- [ ] Nuevas tablas y flujos de producto: ampliar `database/schema/schema.sql` en greenfield, o migraciones SQL incrementales bajo [`database/migrations/`](../../database/migrations/README.md) (post-baseline), más entidades, repositorios y casos de uso según [.cursor/rules/arquitectura-base.mdc](../../.cursor/rules/arquitectura-base.mdc).
- [ ] Rutas en `routes/web.php`, permisos/menú vía SQL idempotente (`INSERT IGNORE` / `WHERE NOT EXISTS`) en migración o sección de bootstrap del módulo, entradas en **`core_menu_items`** y claves en `vertical.php` siguiendo [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md).
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
