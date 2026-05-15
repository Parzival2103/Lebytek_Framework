# Core schema y módulos

Este repositorio es el **framework base** (solo plataforma). No incluye datos de negocio empotrados (`dom_*`).

**Procedimiento oficial** para añadir un módulo de dominio nuevo: **[uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md)**.

La página **Dashboard** del admin es también módulo de plataforma (sin tablas nuevas obligatorias). Ver **[modulo-dashboard.md](../modules/modulo-dashboard.md)**.

## Plataforma (cualquier instalación)

Definición en [`database/schema/schema.sql`](../../database/schema/schema.sql):

1. Autenticación / RBAC: `auth_usuarios`, `auth_roles`, `auth_permisos`, `auth_roles_permisos`, `auth_usuarios_roles`
2. Configuración: `cfg_configuraciones`, `cfg_catalogos_auxiliares`
3. Auditoría: `log_bitacora`
4. Navegación admin: **`core_menu_items`** ([modulo-menu.md](../modules/modulo-menu.md))
5. Stubs: `core_modules`, `int_webhooks`, `rep_metric_defs`, `tmp_jobs`, `sys_kv`

## Dominio (`dom_*`)

Las tablas de negocio se definen cuando se desarrolla cada producto vertical. No son obligatorias en una instalación mínima.

## Bases ya existentes (legacy)

Si la base aún tiene tablas del dominio de ejemplo anterior, ejecutar tras backup:

- [`database/schema/drop_legacy_domain_tables.sql`](../../database/schema/drop_legacy_domain_tables.sql)

## Migraciones históricas

Scripts `001`–`008` y similares viven en [`database/migrations_legacy/`](../../database/migrations_legacy/) como referencia; instancias nuevas no deben depender de ellos.

## Alineación con `config/vertical.php`

Las claves de `modules` deben coincidir con **`slug`/raíz de entrada** declarados en **`core_menu_items`** (filas padre o campo `vertical_module` cuando aplique): por defecto `dashboard` y `administracion`. Al añadir un módulo, registrar su clave en `modules`.
