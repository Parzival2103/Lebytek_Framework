# Mapa schema ↔ código (plataforma)

Referencia de tablas definidas en [`database/schema/schema.sql`](../../database/schema/schema.sql) frente a código activo. Para **nuevos módulos de dominio** (`dom_*`), seguir [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md) y extender este mapa cuando se añadan tablas y repositorios.

La **UI del dashboard admin** (`/admin/dashboard`) no exige tabla propia; la extensión se documenta en [modulo-dashboard.md](../modules/modulo-dashboard.md). El **menú lateral/top/bottom** usa `core_menu_items` — ver [modulo-menu.md](../modules/modulo-menu.md).

Convención de prefijos: [table-prefix-convention.md](./table-prefix-convention.md).

## Leyenda de estado

| Estado | Significado |
|--------|-------------|
| Implementado | Lectura/escritura desde PHP (`Infrastructure` / flujos activos) |
| Parcial | Solo en vistas, seeds, o referencias indirectas |
| Esquema solamente | Tabla en SQL sin uso en `app/` |
| Desalineación | Nombre o forma distinta entre schema y código |

---

## Plataforma: autenticación y RBAC (`auth_*`)

| Tabla | Estado | Código principal |
|-------|--------|------------------|
| `auth_usuarios` | Implementado | `UsuarioRepository`, `UsuariosController`, `AuthController`, UseCases `Usuarios/*`, `Auth/*` |
| `auth_roles` | Implementado | `RolRepository`, `RolesController`, UseCases `Roles/*` |
| `auth_permisos` | Implementado | `PermisoRepository`, `PermisosController` |
| `auth_roles_permisos` | Implementado | vía `PermisoRepository`, `RolRepository` |
| `auth_usuarios_roles` | Implementado | vía `RolRepository` |

## Plataforma: configuración y auditoría (`cfg_*`, `log_*`)

| Tabla | Estado | Código principal |
|-------|--------|------------------|
| `cfg_configuraciones` | Implementado | `ConfiguracionRepository`, `ConfiguracionService`, `AjustesController` |
| `cfg_catalogos_auxiliares` | Esquema solamente | Sin uso en `app/` |
| `log_bitacora` | Implementado | `BitacoraRepository`, `InfraLogger` (campo `tabla` con nombres físicos, p. ej. `auth_usuarios`) |

## Plataforma: extensiones mínimas (stubs)

| Tabla | Estado | Código principal |
|-------|--------|------------------|
| `core_menu_items` | Implementado | [`MenuCatalogRepository`](../../app/Infrastructure/Repositories/MenuCatalogRepository.php), [`AdminNavigationMenuService`](../../app/Application/Services/AdminNavigationMenuService.php); semilla [`015_core_menu_items.sql`](../../database/seeds/015_core_menu_items.sql) |
| `core_modules` | Esquema solamente | Reservado |
| `int_webhooks` | Esquema solamente | Reservado |
| `rep_metric_defs` | Esquema solamente | Reservado |
| `tmp_jobs` | Esquema solamente | Reservado |
| `sys_kv` | Esquema solamente | Reservado |

## Dominio (`dom_*`)

Este repositorio distribuye el **framework sin tablas de dominio** en [`schema.sql`](../../database/schema/schema.sql). Al añadir un módulo nuevo, documentar aquí la relación tabla ↔ repositorio ↔ controlador como en [uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md).

## Controladores admin (resumen)

| Área | Controlador(es) |
|------|------------------|
| Auth / PWA | `AuthController`, `PwaController` |
| Dashboard | `DashboardController` + vista [`admin/dashboard/index`](../../app/Presentation/Views/admin/dashboard/index.php), proveedores en [modulo-dashboard.md](../modules/modulo-dashboard.md) |
| Ajustes | `AjustesController` |
| Administración | `UsuariosController`, `RolesController`, `PermisosController` |
| API | `Api/HealthController` |

## Rutas

- Web: [`routes/web.php`](../../routes/web.php)
- API: [`routes/api.php`](../../routes/api.php)

---

*Instalaciones legacy con tablas `dom_*` pueden eliminarlas con [`database/schema/drop_legacy_domain_tables.sql`](../../database/schema/drop_legacy_domain_tables.sql).*
