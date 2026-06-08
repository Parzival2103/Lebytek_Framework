<!--
  DOCUMENTO TEMPORAL / SNAPSHOT DE CONTEXTO
  ────────────────────────────────────────
  No forma parte del contrato oficial del código; sirve solo para onboarding,
  revisión externa y planificación de expansión con otros equipos.
  Fecha orientativa de captura: 2026 (estado repo en el momento del análisis).
-->

# Contraste — contexto técnico del proyecto (snapshot)

## 1. Resumen ejecutivo

**Contraste** (nombre de carpeta de trabajo / producto tipo “shell administrativo”) es un **framework PHP de aplicación única punto de entrada** (`public/index.php`), orientado a **backend MVC + onion architecture**: capas separadas (`Presentation`, `Application`, `Domain`, `Infrastructure`, `Kernel`). El alcance contenido en el repositorio es **plataforma base reutilizable**: autenticación por sesión, RBAC por slugs (`auth_permisos` / `auth_roles`), panel admin con layouts configurables (`side` / `top` / `bottom`), **dashboard extensible por proveedores**, gestión CRUD de usuarios, roles y permisos, ajustes de marca almacenados en BD, auditoría opcional sobre `log_bitacora`, manifest PWA stub y API mínima (`/api/ping`).

No se distribuyen **tablas de negocio** `dom_*` en [`database/schema/schema.sql`](database/schema/schema.sql): el producto vertical concreto se desarrolla **encima** de esta plantilla siguiendo [docs/modules/uso-de-modulo-dominio.md](docs/modules/uso-de-modulo-dominio.md).

Nombre Composer del paquete: `Lebytek/app` (`composer.json`). Descripción declarada: *Aplicación framework Lebytek*.

---

## 2. Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Lenguaje | **PHP >= 8.1** (uso de typed properties `readonly`, etc.; evitar sintaxis exclusiva PHP 8.2+ en servidor 8.1) |
| Base de datos | **MySQL 8.0+** / **MariaDB 10.6+**, charset **utf8mb4** / collation **utf8mb4_unicode_ci** |
| Persistencia acceso | **PDO** (singleton [`App\Kernel\Database\Connection`](app/Kernel/Database/Connection.php), `PDO::FETCH_ASSOC`) |
| Dependencias Composer | **`dompdf/dompdf`** ^3.1 (PDF; disponible pero no necesariamente uso en todas las rutas); autoload Composer opcional cargado desde Bootstrap si existe `vendor/` |
| Front en vistas | Plantillas PHP en vistas; típicamente **Bootstrap 5 + Bootstrap Icons** (parciales bajo [`app/Presentation/Views`](app/Presentation/Views)); sin SPA obligatoria |
| Sesión | [`App\Kernel\Security\Session`](app/Kernel/Security/Session.php); login HTML en rutas `/`, `/login` |
| Routing | Router propio en [`App\Kernel\Http\Router`](app/Kernel/Http/Router.php); rutas en [`routes/web.php`](routes/web.php), [`routes/api.php`](routes/api.php) |
| DI | [`App\Kernel\Container\Container`](app/Kernel/Container/Container.php) simple singleton/bind; registros explícitos en [`config/container.php`](config/container.php) |

Variables de entorno (vía `.env` / `.env.example` y [`App\Kernel\EnvLoader`](app/Kernel/EnvLoader.php)): típicamente `DB_*`, `APP_URL`, `APP_DEBUG`, nombre de aplicación en [`config/app.php`](config/app.php).

---

## 3. Principios arquitectónicos vigentes

- **Flujo de petición:** Ruta → `Presentation` (controladores, vistas) → `Application` (casos de uso, servicios, DTO) → `Domain` (políticas, entidades, contratos de repositorio) → `Infrastructure` (PDO, logs, proveedores concretos). `Kernel`: utilidades transversales (**no** como “caja de código de dominio”).
- **Domain** no debe depender de frameworks concretos, HTTP ni infraestructura.
- **`Presentation`** solo validaciones de entrada básicas / adaptación HTTP; autorización efectiva mediante `AuthMiddleware`, `RbacMiddleware` según rutas registradas.

Reglas de proyecto detalladas y guardrails están en [.cursor/rules](.cursor/rules) (`arquitectura-base.mdc`, `estructura-proyecto.mdc`, `dependencias-y-flujo.mdc`, `convenciones-nombres.mdc`).

---

## 4. Estructura relevante del repositorio (raíz)

| Ruta | Propósito |
|------|-----------|
| `/app` | Capas Presentation, Application, Domain, Infrastructure, Kernel |
| `/config` | Configuraciones PHP (`app.php`, `database.php`, `container.php`, `vertical.php`, `dashboard.php`, `menu.php` legado vacío, etc.) |
| `/database/schema` | `schema.sql` canónico de plataforma |
| `/database/seeds` | Semillas **`*.sql`** idempotentes; ejecución [`scripts/seed.php`](scripts/seed.php) orden lexigráfico |
| `/database/migrations` | Migraciones incrementales puntualmente (ej. tabla menú incremental) |
| `/database/migrations_legacy` | Solo referencia histórica |
| `/database/seeders` | `README.md` puente → apunta a `database/seeds` |
| `/public` | `index.php`, assets públicos |
| `/routes` | `web.php`, `api.php` |
| `/storage` | logs, uploads según uso |
| `/scripts` | `seed.php`, utilidades CLI (ej. `crear_usuario.php`) |
| `/docs` | Documentación textual del proyecto (ver índice abajo) |
| `/tests` | Estructura preparada; cobertura depende del equipo |

---

## 5. Qué está implementado (alto nivel)

### 5.1 Autenticación y sesión

- Login/logout, flash, CSRF en formularios sensibles.
- Sesión almacena usuario y slugs de permisos y roles para [`RbacPolicy`](app/Domain/Policies/RbacPolicy.php) (rol `administrador` con acceso amplio por política).

### 5.2 RBAC y administración

- CRUD usuarios, roles, permisos bajo `/admin/administracion/...` con `RbacMiddleware('administracion.ver')` en el grupo de rutas.
- Repositorios PDO: [`UsuarioRepository`](app/Infrastructure/Repositories/UsuarioRepository.php), [`RolRepository`](app/Infrastructure/Repositories/RolRepository.php), [`PermisoRepository`](app/Infrastructure/Repositories/PermisoRepository.php), [`ConfiguracionRepository`](app/Infrastructure/Repositories/ConfiguracionRepository.php), [`BitacoraRepository`](app/Infrastructure/Repositories/BitacoraRepository.php).

### 5.3 Menú administrativo desde base de datos

- Tabla **`core_menu_items`**; contrato Domain [`MenuCatalogRepositoryInterface`](app/Domain/Interfaces/MenuCatalogRepositoryInterface.php), implementación [`MenuCatalogRepository`](app/Infrastructure/Repositories/MenuCatalogRepository.php).
- Ensamblado + filtro vertical (`VerticalProfile`) + RBAC: [`AdminNavigationMenuService`](app/Application/Services/AdminNavigationMenuService.php), inyectado en controladores que extienden [`AdminBaseController`](app/Presentation/Controllers/AdminBaseController.php).
- Perfil vertical: [`config/vertical.php`](config/vertical.php) — toggle `dashboard`, `administracion` por instancia + `labels.menu` opcional.

### 5.4 Dashboard modular

- Caso de uso [`BuildDashboardViewModelUseCase`](app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php).
- Proveedores listados en [`config/dashboard.php`](config/dashboard.php) — actualmente **`DefaultPlatformDashboardProvider`** como único proveedor de plataforma.
- Contrato extensible: [`DashboardContributionProviderInterface`](app/Domain/Interfaces/DashboardContributionProviderInterface.php). Documentación: [docs/modules/modulo-dashboard.md](docs/modules/modulo-dashboard.md).

### 5.5 Ajustes y UI

- Ajustes almacenados en `cfg_configuraciones`; layout de menú lateral/superior/inferior; panel de tema/estilo en vistas según vistas parciales.

### 5.6 API

- **`GET /api/ping`** público ante autenticación según configuración actual del grupo `/api` (revisar [routes/api.php](routes/api.php) — lleva `AuthMiddleware` en grupo; comportamiento efectivo debe verificarse al exponer públicamente).

---

## 6. Schema plataforma (tablas definidas sin `dom_*`)

Incluye (no exhaustivo de columnas):

- **`auth_*`**: `auth_usuarios`, `auth_roles`, `auth_permisos`, `auth_roles_permisos`, `auth_usuarios_roles`
- **`cfg_*`**: `cfg_configuraciones`, `cfg_catalogos_auxiliares`
- **`log_*`**: `log_bitacora`
- **`core_*`**: `core_menu_items`, `core_modules`
- Otros stubs: `int_webhooks`, `rep_metric_defs`, `tmp_jobs`, `sys_kv`

Dominio nuevo: **`dom_*`** según prefijos documentados ([docs/core/table-prefix-convention.md](docs/core/table-prefix-convention.md)).

---

## 7. Semillas datos base (SQL actual)

Directorio [`database/seeds`](database/seeds):

- Prefijos orientativos: `010_auth_permisos`, `015_core_menu_items`, `020_auth_roles`, `025_auth_roles_permisos`, `030_auth_usuario_admin`, `035_cfg_configuraciones`.
- Credencial semilla típica documentada en README de seeds (**cambiar en producción**).
- Ejecución: `php scripts/seed.php`.

---

## 8. Rutas web principales registradas (`routes/web.php`)

- PWA manifest: `/manifest.webmanifest`
- Login: `GET /`, `/login`, `POST /login`, `POST /logout`
- Admin (grupo **`/admin`** con `AuthMiddleware`): dashboard, ajustes, grupo **`/admin/administracion`** con RBAC **`administracion.ver`**: usuarios, roles, permisos con verbos RESTful y CSRF donde aplica.

---

## 9. Convenciones de nombres (resumen práctico)

- **PHP:** Clases PascalCase, métodos camelCase, constantes UPPER_SNAKE_CASE.
- **Tablas/colunas BD:** snake_case; tablas en plural; FK `tabla_id`. Ver [docs/core/convenciones_nombres.md](docs/core/convenciones_nombres.md).
- **API JSON:** claves recomendadas **camelCase** (convención proyecto).
- **Permisos:** slugs `modulo.accion` (ej. `dashboard.ver`, `administracion.ver`).
- Rutas HTTP documentadas tipo `/api/recurso`, `/api/recurso/{id}`.

---

## 10. Documentación oficial en `/docs` (referencia equipo)

| Documento | Uso |
|-----------|-----|
| [README.md](docs/README.md) | Índice de la documentación (core / modules / audits / legacy) |
| [arquitectura.md](docs/core/arquitectura.md) | Visión MVC + onion y capas |
| [uso-de-modulo-dominio.md](docs/modules/uso-de-modulo-dominio.md) | Checklist de un módulo de negocio nuevo |
| [modulo-menu.md](docs/modules/modulo-menu.md) | Menú admin (`core_menu_items`) |
| [modulo-dashboard.md](docs/modules/modulo-dashboard.md) | Extensión del dashboard por proveedores |
| [modulo-crud-engine.md](docs/modules/crud/modulo-crud-engine.md) | Especificación CRUD Engine |
| [core-schema-and-modules.md](docs/core/core-schema-and-modules.md) | Qué es solo plataforma |
| [schema-code-map.md](docs/core/schema-code-map.md) | Mapa tabla ↔ código |
| [vertical-onboarding.md](docs/core/vertical-onboarding.md) | Checklist de instancia / deploy |
| [table-prefix-convention.md](docs/core/table-prefix-convention.md) | Prefijos `auth_*`, `dom_*`, etc. |
| [estructura_proyecto.md](docs/core/estructura_proyecto.md), [convenciones_nombres.md](docs/core/convenciones_nombres.md) | Estructura y nombres |
| [example-domain-imprenta.md](docs/legacy/example-domain-imprenta.md) | **Histórico** — dominio de ejemplo ya no está en código |
| Otros | `despliegue_hosting.md`, `reglas_api.md`, `diccionario_dominio.md`, etc. |

---

## 11. Estado intencional: sin dominio empotrado

- El código y el `schema.sql` distribuidos **no** incluyen entidades `dom_*` de un producto concreto.
- Las referencias tipo “imprenta” u otros verticales anteriores quedaron como documentación histórica o limpiezas legacy (**`drop_legacy_domain_tables.sql`** si una BD vieja conserva tablas de ejemplo).

---

## 12. Expansiones naturales siguiente equipo / productos

1. Definir módulos `dom_*` + migraciones y repositorios según checklist.
2. Añadir filas **`core_menu_items`**, permisos (`database/seeds` o nueva migración) y entrada en **`config/vertical.php`**.
3. Registrar rutas **`/admin/...`** con `RbacMiddleware` alineados a nuevos permisos.
4. Registrar dependencias **`config/container.php`** (bind explícitos de controladores admin con `AdminNavigationMenuService` + caso de uso).
5. Opcional: nuevos **`DashboardContributionProviderInterface`** en `config/dashboard.php` para widgets en `/admin/dashboard`.
6. API JSON: exponer controladores bajo **`/api`** con política de autenticación acordada (token, etc.) — hoy infraestructura mínima.

---

## 13. Observaciones para analistas externos

- **Un solo archivo de entrada web** favorece hosting compartido clásico; no es obligatorio front build step.
- **Tests automatizados** y **pipelines CI** dependen del org — no están detallados en este snapshot.
- Este `.md` **no sustituye** lectura de `docs/` ni de las reglas en `.cursor/rules`; debe usarse como **mapa rápido** antes de onboarding profundo.

---

*Fin snapshot — solo referencia contextual; cualquier discrepancia debe resolverse contra el código y `docs/` en el commit vigente.*
