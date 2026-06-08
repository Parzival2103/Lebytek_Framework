# Uso de módulo (dominio de negocio)

Guía para añadir un **nuevo módulo de dominio** sobre este framework (auth, RBAC, ajustes, shell admin). Cada módulo debe repetir la **misma estructura y puntos de cableado** para mantener cohesion entre equipos.


## Convenciones fijas del framework

- **Tablas de negocio:** prefijo `dom_*` ([table-prefix-convention.md](../core/table-prefix-convention.md)). La plataforma usa `auth_*`, `cfg_*`, `log_*`, `core_*`, `int_*`, `rep_*`, `tmp_*`, `sys_*` — no mezclar responsabilidades.
- **Flujo de petición:** ruta → `Presentation` → `Application` (casos de uso) → `Domain` (reglas/contratos) → `Infrastructure` (persistencia).
- **Autorización:** slugs en `auth_permisos` como `mi_modulo.accion`; ítems en `core_menu_items` (`permiso_slug` opcional, ver [modulo-menu.md](./modulo-menu.md)); visibilidad por `config/vertical.php` → `modules`; filtro RBAC centralizado en [`AdminNavigationMenuService`](../../app/Application/Services/AdminNavigationMenuService.php) desde `AdminBaseController` (administradores ven todo por política).

## Checklist por módulo (orden recomendado)

| Orden | Artefacto | Acción |
|-------|-----------|--------|
| 1 | Tablas y FKs | Greenfield: `CREATE TABLE` en `database/schema/schema.sql` o, para módulos opcionales con bootstrap propio, `database/schema/modules/<modulo>.sql` referenciado en `config/modules/*.php` (`bootstrap_sql`). Post-deploy: migración incremental en [`database/migrations/`](../../database/migrations/README.md). |
| 2 | Permisos | SQL idempotente (`INSERT IGNORE`) en migración incremental, sección `DATOS INICIALES` de `schema.sql`, o bootstrap modular del manifiesto — slugs `mi_modulo.ver`, `mi_modulo.crear`, etc. |
| 3 | Roles / roles↔permisos | Mismo criterio que permisos: bootstrap en schema/migración del módulo. |
| 4 | Rutas admin | `routes/web.php` — grupo con `prefix` `/admin`, `middlewares` `AuthMiddleware` y donde aplique `RbacMiddleware('mi_modulo.ver')` o acción granular. Usar `CsrfMiddleware::class` en POST/PUT/DELETE. |
| 5 | Ruta pública (opcional) | Misma sintaxis router; sin `AuthMiddleware` si es captación pública con token u otro criterio. |
| 6 | Menú | Tabla **`core_menu_items`** + semilla o migración: `slug`, jerarquía `parent_id`, `orden`, `label`, iconos/rutas, `permiso_slug`, `vertical_module`. Detalle en [modulo-menu.md](./modulo-menu.md). |
| 7 | Vertical | `config/vertical.php` — clave booleana en `modules` por **`slug`/id de entrada** de menú (p. ej. `dashboard`). |
| 8 | Domain | Interfaces de repositorio en `app/Domain/Interfaces/` y entidades u objetos de dominio en `app/Domain/Entities/`. |
| 9 | Application | Casos de uso `app/Application/UseCases/<MiModulo>/`, DTOs `app/Application/DTO/`, validadores `app/Application/Validators/`. |
| 10 | Infrastructure | Implementaciones `app/Infrastructure/Repositories/`. |
| 11 | Presentation | Controlador `app/Presentation/Controllers/Admin/<MiModuloController>.php`, extendiendo `AdminBaseController`; vistas `app/Presentation/Views/admin/<ruta_snake>/`. |
| 12 | Contenedor DI | `config/container.php` — `singleton` del repositorio por interfaz; `bind` del controlador instanciando use cases (el contenedor no resuelve constructores complejos solo). |
| 13 | Servicios | PDF, uploads, integraciones — `Application/Services` o `Infrastructure` según reglas del proyecto; registro explícito en el contenedor; servicios nombrados (`'fileUploader.mi_modulo'`). |
| 14 | Activos | `public/assets/uploads/...` acorde a política documentada en el módulo. |
| 15 | Documentación | Añadir filas en `docs/core/schema-code-map.md` para tabla ↔ repositorio ↔ controlador. |

## Plantilla de rutas (`routes/web.php`)

Patrón (placeholders):

```php
// Dentro del group /admin ya existente que usa AuthMiddleware:
$router->group([
    'prefix'      => '/mi_modulo',
    'middlewares' => [new \App\Presentation\Middlewares\RbacMiddleware('mi_modulo.ver')],
], function ($router) {
    $router->get('',              [\App\Presentation\Controllers\Admin\MiModuloController::class, 'index']);
    $router->get('/crear',        [\App\Presentation\Controllers\Admin\MiModuloController::class, 'crear']);
    $router->post('',             [\App\Presentation\Controllers\Admin\MiModuloController::class, 'guardar'],
        [\App\Presentation\Middlewares\CsrfMiddleware::class]);
    // editar/{id}, put, delete…
});
```

Reutilizar siempre los mismos middleware que el resto del admin (`AuthMiddleware` en el grupo padre).

## Plantilla de permisos y menú

**Permisos (SQL)** — ejemplo en migración incremental o bootstrap del módulo; usar `INSERT IGNORE` por `slug` único:

```sql
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`) VALUES
  ('Ver mi módulo', 'mi_modulo.ver', 'mi_modulo'),
  ('Crear en mi módulo', 'mi_modulo.crear', 'mi_modulo');
```

Ejecutar la migración o `php scripts/install.php` / wizard según el flujo de despliegue, o importar el `.sql` manualmente contra la base.

**Menú (BD)** — ejemplo SQL; equivalencia de columnas vs array anterior en [modulo-menu.md](./modulo-menu.md):

```sql
INSERT INTO core_menu_items
  (parent_id, orden, slug, label, icon, url, `match`, permiso_slug, vertical_module, activo)
VALUES
  (NULL, 40, 'mi_modulo', 'Mi módulo', 'bi-grid', NULL, '/admin/mi_modulo', 'mi_modulo.ver', 'mi_modulo', 1);
-- Subítems: INSERT con parent_id apuntando al id del slug mi_modulo
```

**vertical.php:**

```php
'modules' => [
    'mi_modulo' => true,
],
```

## Plantilla `config/container.php`

- Un `singleton( MiEntidadRepositoryInterface::class, fn() => new MiEntidadRepository() )`.
- Un `bind( MiModuloController::class, function (Container $c) { return new MiModuloController( $c->get(ConfiguracionService::class), $c->get(\App\Application\Services\AdminNavigationMenuService::class), new AlgoUseCase(...), ... ); } );` — cualquier controller que extienda `AdminBaseController` debe recibir `AdminNavigationMenuService` en el constructor después de `ConfiguracionService`.

No registrar el controlador sin `bind` si su constructor tiene dependencias no resolvibles por el `Container` simple.

## API (opcional)

- Rutas JSON en `routes/api.php`; convención `/api/recursos` como en [.cursor/rules/convenciones-nombres.mdc](../../.cursor/rules/convenciones-nombres.mdc).
- Middleware de autenticación coherente con el resto del API.

## Scripts para bases ya desplegadas

Si solo se amplía el dominio después del primer deploy, usar migraciones SQL incrementales en [`database/migrations/`](../../database/migrations/README.md), ordenadas por dependencias FK, o automatizaciones bajo [`scripts/`](../../scripts) según política del proyecto.

---

*Este documento es el contrato para que cada nuevo módulo conserve la misma forma estructural que los dominios integrados antes de una base solo-plataforma.*
