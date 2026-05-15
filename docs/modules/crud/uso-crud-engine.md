# Guía de uso — CRUD Engine v0.1

**Contrato completo:** [`modulo-crud-engine.md`](./modulo-crud-engine.md).

## Objetivo

Esta guía resume el flujo mínimo para habilitar un recurso CRUD dinámico en el sistema:

**Tabla física + JSON + permisos + integración = CRUD funcional**

---

## 1) Crear migración de la tabla (estructura base obligatoria)

Crear la tabla en `database/migrations/` respetando convenciones `dom_*` y campos base del engine:

- `id` (PK autoincremental)
- campos del negocio (por ejemplo: `nombre`, `email`, `status`, etc.)
- `deleted` (borrado lógico)
- `created_at`, `created_by`
- `updated_at`, `updated_by`
- `deleted_at`, `deleted_by`

Ejemplo de nombre de migración:

- `20260428132500_dom_mi_recurso.sql`

> Regla: el motor **no crea tablas automáticamente**. Deben existir previamente.

---

## 2) Agregar permisos RBAC del recurso

Insertar en `auth_permisos` los slugs requeridos:

- `{recurso}.ver`
- `{recurso}.crear`
- `{recurso}.editar`
- `{recurso}.eliminar`

Ejemplo para recurso `clientes`:

- `clientes.ver`
- `clientes.crear`
- `clientes.editar`
- `clientes.eliminar`

También asignar estos permisos al rol correspondiente (normalmente `administrador`) en `auth_roles_permisos`.

---

## 3) Crear JSON de configuración en `config/cruds/`

Crear archivo:

- `config/cruds/{recurso}.json`

Con bloques mínimos:

- `resource`: `key`, `title`, `table`, `primary_key`, `permission_prefix`
- `list`: columnas, filtros, acciones
- `form`: fields
- `uploads`: habilitado/ruta
- `security` (`mode`, `allow_core_table`), `list.aggregation` / `group_by` / `summaries` si aplica
- `hooks`: clave de handler opcional (no FQCN); ver [`modulo-crud-engine.md`](./modulo-crud-engine.md)

Ejemplo:

- `config/cruds/clientes.json`

> Regla: `resource.key` debe coincidir con el nombre del archivo y con la ruta (`/admin/crud/{resource}`).

---

## 4) Integrar dependencias en `config/container.php`

Registrar singletons/bindings coherentes con el contenedor actual (orden de construcción: `CrudHandlerRegistry` y `CrudConfigValidator` antes de `CrudConfigLoader`; `CrudFieldValidationService` antes de `CrudDataService`):

- [`GenericCrudRepository`](../../../app/Infrastructure/Repositories/GenericCrudRepository.php)
- [`CrudHandlerRegistry`](../../../app/Application/Services/CrudHandlerRegistry.php) — mapa cargado desde [`config/crud_handlers.php`](../../../config/crud_handlers.php)
- [`CrudConfigValidator`](../../../app/Application/Services/CrudConfigValidator.php)
- [`CrudConfigLoader`](../../../app/Application/Services/CrudConfigLoader.php)
- [`CrudHookRunner`](../../../app/Application/Services/CrudHookRunner.php)
- [`CrudFieldValidationService`](../../../app/Application/Services/CrudFieldValidationService.php)
- [`CrudDataService`](../../../app/Application/Services/CrudDataService.php)
- [`CrudFormBuilder`](../../../app/Application/Services/CrudFormBuilder.php)
- [`CrudTableBuilder`](../../../app/Application/Services/CrudTableBuilder.php)
- [`CrudResourceService`](../../../app/Application/Services/CrudResourceService.php)
- [`CrudController`](../../../app/Presentation/Controllers/Admin/CrudController.php) — habitualmente un `bind` explícito con dependencias por constructor

Sin esta parte, el router intentará instanciar `CrudController` sin dependencias y fallará.

> Toda clase `hooks.handler` debe aparecer como clave en `config/crud_handlers.php`; el validador rechaza FQCN en el JSON.

---

## 5) Registrar rutas genéricas en `routes/web.php`

Agregar rutas del CRUD Engine dentro del grupo admin autenticado:

- `GET /admin/crud/{resource}`
- `GET /admin/crud/{resource}/crear`
- `POST /admin/crud/{resource}`
- `GET /admin/crud/{resource}/{id}`
- `GET /admin/crud/{resource}/{id}/editar`
- `POST /admin/crud/{resource}/{id}`
- `POST /admin/crud/{resource}/{id}/eliminar`

---

## 6) (Opcional) Agregar acceso al menú dinámico

En `core_menu_items` registrar URL:

- `/admin/crud/{resource}`

y `permiso_slug` relacionado (`{recurso}.ver`).

---

## 7) Checklist de validación rápida

1. La tabla `dom_*` existe con campos base.
2. Existen los 4 permisos del recurso en `auth_permisos`.
3. El rol de prueba tiene permisos en `auth_roles_permisos`.
4. Existe `config/cruds/{resource}.json` válido (coincide con permisos y con reglas de seguridad del módulo).5. `config/container.php` incluye bindings del CRUD Engine.
6. `routes/web.php` incluye rutas `/admin/crud/*`.
7. El menú apunta a `/admin/crud/{resource}` sin errores.

---

## Notas de seguridad

- El motor usa `prepared statements`.
- No se permite SQL libre desde JSON.
- Se bloquean tablas core por defecto (`auth_*`, `cfg_*`, `core_*`, `log_*`).
- Se aplica borrado lógico, no borrado físico.
- Se validan reglas declarativas en JSON en backend (`CrudFieldValidationService`; ver [`modulo-crud-engine.md`](./modulo-crud-engine.md) §9).
- Handlers sólo por clave en [`config/crud_handlers.php`](../../../config/crud_handlers.php) (FQCN prohibido en JSON).
- Se valida permiso por acción antes de operar.
