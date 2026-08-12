# Contexto técnico para evaluación del Framework Lebytek

**Documento:** `docs/FRAMEWORK_EVALUATION_CONTEXT.md`  
**Fecha de inspección:** 2026-08-12 (UTC)  
**Repositorio inspeccionado:** `Parzival2103/Lebytek_Framework`  
**SHA de `origin/main` en la inspección:** `cf9e67e` (`fix(crud): p04 CAS, bulk guards, equality fail-closed (1.2.11)`)  
**Versión de paquete declarada:** `1.2.11` (`composer.json`, `config/app.php`, `skeleton/config/app.php`)  
**Alcance:** investigación y documentación únicamente. Sin cambios de código de producto, sin refactor, sin recomendación de migración a Laravel/Symfony ni de reescritura.

---

## Nota de lectura para el arquitecto externo

Este repositorio **no es** una aplicación de negocio desplegable (lebytek.com / waapi). Es el **código fuente del paquete Composer** `lebytek/framework`. La aplicación de negocio Lebytek vive en el repo separado `Lebytek_Portal`. Los tenants nuevos parten de `skeleton/`.

Cuando el prompt de auditoría habla de “módulos” tipo Eventos/Clientes/Pagos de negocio, en **este** repo el equivalente observable son:

1. **Módulos de plataforma** (`config/modules/*.php`): `core`, `crud-engine`, `dashboard`, `calendario`, `reportes`, `pdf-kit`, `integrations`, `payments`, `invoicing`.
2. **Recursos CRUD demo** (`config/cruds/demo_*.json`): `demo_clientes`, `demo_productos`, `demo_categorias`, `demo_pedidos`, `demo_citas`.
3. **Código de plataforma** en `src/` (`Lebytek\Framework\`).

El negocio real (Marketing, leads, membresías, checkout) **no está aquí** por diseño documentado (`docs/ARCHITECTURE-CONSUMER.md`, `docs/TENANTS.md`, `AGENTS.md`).

---

# 1. RESUMEN EJECUTIVO

## Qué es

`lebytek/framework` es una **plataforma PHP 8.2+** empaquetada como librería Composer. Incluye kernel HTTP propio, auth/sesión, RBAC, menú admin, motor CRUD dirigido por JSON, calendario sobre CRUD, reportes/PDF, e integraciones/payments/invoicing genéricos (apagados por defecto vía `config/vertical.php`).

## Objetivo aparente

Acelerar el desarrollo de **back-offices administrativos multi-tenant-por-despliegue** (una BD por instancia) reutilizando:

- shell admin + auth + RBAC + menú;
- CRUD declarativo (`tabla dom_*` + JSON);
- módulos opcionales (calendario, reportes, PDF, payments, invoicing, integrations).

Los consumidores (`Lebytek_Portal` o repos desde `skeleton/`) instalan el paquete, aportan `App\`, SQL de negocio `dom_*`, rutas y toggles verticales.

## Tecnologías

| Área | Tecnología |
|------|------------|
| Lenguaje | PHP `>=8.2` |
| Empaquetado | Composer (`lebytek/framework`) |
| HTTP | Kernel propio (`Bootstrap`, `Router`, `Request`, `Response`) — no Symfony/Laravel |
| Persistencia | PDO MySQL (`Kernel\Database\Connection`) + repositorios SQL |
| UI | PHP views + Bootstrap / Bootstrap Icons / AOS (layout `base.php`) |
| PDF | `dompdf/dompdf` |
| Mail | `phpmailer/phpmailer` |
| Payments | `stripe/stripe-php` (módulo OFF por defecto) |
| Invoicing | `facturapi/facturapi-php` (módulo OFF por defecto) |
| Tests | Harness propio `tests/run.php` + `microtest` (PHPUnit declarado como plantilla en `phpunit.xml.dist`, no es el runner principal) |

## Arquitectura general

Híbrida **MVC + Onion** en cuatro capas bajo `src/`:

```text
Presentation  → Application → Domain
                     ↑
               Infrastructure
```

Más `Kernel/` (bootstrap, HTTP, DI, seguridad, DB). El `Kernel` vive en el paquete; los consumidores **no** recrean un Kernel local.

## Cómo se construyen aplicaciones sobre él

1. Copiar `skeleton/` a un repo nuevo **o** usar `Lebytek_Portal`.
2. `composer require lebytek/framework` (tag semver).
3. `public/index.php` del consumidor llama `Lebytek\Framework\Kernel\Bootstrap::run()`.
4. Config del consumidor en `config/` (incluye `cruds/*.json`, `vertical.php`, `container.php`).
5. Lógica de negocio en `app/` (`App\`) del consumidor; plataforma solo lectura en `vendor/`.

## Responsabilidades

| Actor | Resuelve |
|-------|----------|
| **Framework (`src/`)** | Kernel, auth, RBAC, CRUD engine, dashboard shell, calendario genérico, reportes genéricos, PDF kit, payments/invoicing genéricos, install/migrate, vistas admin de plataforma |
| **Módulos de plataforma (`config/modules` + schema modules)** | Declaran bootstrap SQL, dependencias entre módulos, ownership de CRUDs/calendars, migraciones asociadas |
| **JSON/config** | Definición declarativa de recursos CRUD, calendarios, reportes, handlers whitelist, toggles verticales, DI bindings, RBAC de rutas estáticas |
| **Código PHP** | Interpretación de JSON, validación, SQL genérico, políticas RBAC, CAS/soft-delete, guards/validators/handlers, controladores, vistas PHP |
| **Consumidor (`App\` / Portal)** | Dominio de negocio, tablas `dom_*` propias, checkout/membresías/Marketing, rutas HTTP de webhooks de negocio, UI específica |

**Sin recomendación de mantener o reemplazar en esta etapa.**

---

# 2. MAPA REAL DEL REPOSITORIO

```text
/
├── src/                          # Paquete Lebytek\Framework (núcleo)
│   ├── Kernel/                   # Bootstrap, Router, Container, Session, PDO, Config
│   ├── Presentation/             # Controllers, Middlewares, Views (PHP)
│   ├── Application/              # Services, UseCases, Crud/, Install, Payments, …
│   ├── Domain/                   # Entities, Policies, Interfaces, ValueObjects
│   └── Infrastructure/           # Repositorios PDO, Stripe, Facturapi, Mail, Pdf
├── config/                       # Harness local del mantenedor (espejo de consumer)
│   ├── modules/                  # Manifiestos PHP de módulos plataforma
│   ├── cruds/                    # JSON CRUD demo
│   ├── calendars/                # JSON calendario
│   ├── reportes/                 # JSON fuentes de reporte
│   ├── vertical.php              # Toggles de módulos por deploy
│   ├── container.php             # DI
│   └── rbac_route_permissions.php
├── skeleton/                     # Plantilla mínima de tenant consumidor
│   ├── app/                      # Capas App\ vacías (.gitkeep)
│   ├── config/                   # Copia de configs de plataforma demo
│   ├── routes/, public/, database/, scripts/, tests/
├── database/
│   ├── schema/schema.sql         # Bootstrap plataforma
│   ├── schema/modules/*.sql      # Bootstrap módulos opcionales
│   ├── migrations/               # Incrementales plataforma
│   └── seeds/
├── routes/                       # web.php, api.php, integrations.php (harness)
├── public/                       # Front controller harness
├── app/                          # Stub vacío (harness) — NO app de producción
├── tests/                        # Harness microtest (~206 *Test.php)
├── scripts/                      # install/migrate/seed
├── docs/                         # Contratos, ownership, módulos, automatización
└── composer.json                 # name: lebytek/framework, version 1.2.11
```

### Áreas relevantes

| Área | Responsabilidad | Dependencias | Quién la usa | Centralidad | Ámbito |
|------|-----------------|--------------|--------------|-------------|--------|
| `src/Kernel` | Boot, HTTP, DI, Session, Config, PDO | PHP stdlib | Todo request | **Crítica** | Core |
| `src/Application/Services/Crud*` | Motor CRUD | Domain CRUD defs, GenericCrudRepository, RBAC | CrudController, Calendar, Reportes | **Crítica (diferencial)** | Core |
| `src/Presentation` | Controllers + Views PHP | Application | Router | Alta | Core |
| `config/cruds/*.json` | Definición declarativa de recursos | Interpretado por CrudConfigLoader | CRUD UI | Alta | Config (consumer + harness) |
| `config/modules/*.php` | Registry de módulos / install | ModuleRegistry, install scripts | Installer | Media-Alta | Core/config |
| `database/schema` | Schema plataforma | MySQL | install/migrate | Alta | Core |
| `skeleton/` | Template tenant | Framework via Composer | Nuevos tenants | Alta (DX) | Template |
| Root `app/` | Stub | — | Nada productivo | Baja | Harness |
| `tests/` | Verificación plataforma | microtest + PDO | CI/mantenedores | Alta | Core |
| Payments/Invoicing en `src/` | Gateways genéricos | Stripe/Facturapi SDKs | Consumidor (rutas HTTP en consumer) | Media (OFF) | Core opcional |
| Integrations demo | Provision WhatsApp demo + leads fields | int_* tables | Admin UI si vertical ON | Media (demo-flavored) | Core/demo |

---

# 3. FLUJO COMPLETO DE UNA REQUEST

## Arranque común

```text
public/index.php
→ vendor/autoload.php
→ Lebytek\Framework\Kernel\Bootstrap::run()
→ Config::init(appRoot/config)
→ Session::start()
→ Connection::configure(...)
→ Container + config/container.php
→ Request::fromGlobals()
→ Router + routes/web.php + routes/api.php
→ dispatch → middlewares → controller → Response::send()
```

Evidencia: `public/index.php`, `src/Kernel/Bootstrap.php`, `src/Kernel/Http/Router.php`.

---

## Flujo A — CRUD simple: listar `demo_clientes`

```text
GET /admin/crud/demo_clientes
→ Router (routes/web.php grupo /admin)
→ AuthMiddleware          (sesión auth_user)
→ CrudRbacMiddleware      (resuelve permiso demo_clientes.ver desde JSON)
→ CrudController::index
→ CrudResourceService::buildIndexData
→ CrudConfigLoader::load('demo_clientes')   # config/cruds/demo_clientes.json
→ RbacService::verificar('demo_clientes.ver')
→ CrudDataService::list
   → where deleted=0
   → CrudScopeResolver + OwnerListScope(created_by = userId)  # scope en JSON
→ GenericCrudRepository::selectPaginated
→ CrudTableBuilder
→ view admin/crud/index.php (layout base.php)
```

| Concern | Dónde ocurre |
|---------|--------------|
| Autenticación | `AuthMiddleware` + `Session::has('auth_user')` |
| Autorización | `CrudRbacMiddleware` + `RbacPolicy` + re-check en `CrudResourceService` |
| Organización/tenant | **No hay resolución de organización.** Solo user id de sesión |
| Validación | N/A en list; en store/update: `CrudFieldValidationService` + validators JSON/handlers |
| Acceso a datos | `GenericCrudRepository` (SQL preparado + identificadores quotados) |
| Transformación | `CrudTableBuilder` (badges, formats, summaries) |
| Renderizado | `Presentation/Views/admin/crud/index.php` |
| Logging | Bitácora en mutaciones (`BitacoraRepositoryInterface`); list no escribe audit |
| Errores | Excepciones de dominio/validación → handlers del Kernel / flash + redirect |

Archivos clave:

- `routes/web.php` (`/crud/{resource}`)
- `src/Presentation/Middlewares/AuthMiddleware.php`
- `src/Presentation/Middlewares/CrudRbacMiddleware.php`
- `src/Application/Services/CrudRoutePermissionResolver.php`
- `src/Presentation/Controllers/Admin/CrudController.php`
- `config/cruds/demo_clientes.json`
- `src/Application/Services/CrudResourceService.php`
- `src/Application/Crud/Scopes/OwnerListScope.php`
- `src/Infrastructure/Repositories/GenericCrudRepository.php`

---

## Flujo B — Operación compleja: provision Integrations

Condicionado a `vertical.modules.integrations === true`.

```text
POST /admin/integraciones/provision
→ AuthMiddleware
→ RbacMiddleware('integrations.enviar')
→ CsrfMiddleware
→ IntegrationsController::provision
→ (guards de modo legacy/API)
→ DemoProvisioningService::provisionAuto|provisionManual
→ partner createInstance (si auto)
→ IntegrationAccountRepository::save  # int_accounts, token encrypted
→ NotificationDispatcher (email channel)
→ IntegrationLogRepository::record    # int_logs
→ flash + redirect /admin/integraciones
```

Ruta pública relacionada (sin sesión; token firmado):

```text
GET /wa/activar/{token}
→ IntegrationsController::activar
→ SignedToken::verify
→ activa cuenta
```

| Concern | Dónde ocurre |
|---------|--------------|
| Autenticación | Sesión admin; activación pública por `SignedToken` |
| Autorización | `integrations.enviar` / `integrations.configurar` / `integrations.ver` |
| Tenant | **Ausente** — cuentas/logs globales por BD de instancia |
| Validación | Inputs lead_id/nombre/email en controller; crypto en repo |
| Datos | `int_accounts`, `int_logs` |
| Side effects | Email + partner API |

Archivos: `routes/integrations.php`, `src/Presentation/Controllers/Admin/IntegrationsController.php`, `src/Application/Integrations/DemoProvisioningService.php`, `src/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php`.

**Nota:** Payments/Invoicing tienen lógica de webhook (`StripeGateway::parseWebhook`, `FacturapiInvoiceProvider::parseWebhook`) pero **no hay rutas HTTP de webhook en `routes/` de este paquete**; el contrato documentado asigna esos endpoints al consumidor (`docs/ARCHITECTURE-CONSUMER.md`).

---

# 4. SISTEMA DE CONFIGURACIONES JSON

## Inventario (harness `config/` + skeleton)

| Categoría | Formato | Cantidad (harness) | Ejemplo | Intérprete |
|-----------|---------|-------------------:|---------|------------|
| Recursos CRUD | JSON | 5 | `config/cruds/demo_productos.json` | `CrudConfigLoader`, `CrudConfigValidator`, `CrudResourceDefinition` |
| Fuentes reporte | JSON | 4 | `config/reportes/productos.json` | `ReporteConfigLoader` (+ lee CRUD) |
| Calendarios | JSON | 1 | `config/calendars/demo_citas.json` | `CalendarConfigLoader` (+ lee CRUD) |
| Manifiestos módulo | PHP | 9 | `config/modules/crud-engine.php` | `ModuleRegistry` |
| Handlers whitelist | PHP | 1 | `config/crud_handlers.php` | `CrudHandlerRegistry` |
| Vertical toggles | PHP | 1 | `config/vertical.php` | `VerticalProfile` / menú |
| DI | PHP | 1 | `config/container.php` | Container + `FrameworkServiceProvider` |
| RBAC rutas estáticas | PHP | 1 | `config/rbac_route_permissions.php` | Documentación/registry (middleware usa permisos en rutas) |
| App/auth/db/mail/… | PHP | varios | `config/app.php`, `auth.php`, … | `Config` |

**Totales JSON:** 10 en `config/` + 10 en `skeleton/config/` = **20 archivos JSON de producto** (espejo 1:1).  
Fixtures de test Payments/Invoicing existen aparte y no son configs de runtime.

### Por “módulo” / sistema

| Sistema | JSON harness | Notas |
|---------|-------------:|-------|
| crud-engine (4 recursos) | 4 | + SQL bootstrap |
| calendario (`demo_citas`) | 1 CRUD + 1 calendar | depende de crud-engine |
| reportes | 4 | re-declaran columnas del CRUD |
| payments / invoicing / integrations | 0 JSON | PHP config + SQL |

**Promedio JSON por módulo plataforma con JSON:** ~2–5.  
**Módulo/sistema con más JSON:** CRUD engine + reportes asociados.  
**Duplicación aproximada harness↔skeleton:** ~100% de los 10 JSON (copia espejo).  
**Duplicación semántica dentro de un CRUD** (list badges vs states badges; list.actions vs actions.row; form field + relation + report expose): frecuente; estimación cualitativa **20–40%** de propiedades repetidas entre bloques/archivos relacionados — no hay herramienta de deduplicación.

### Estructura típica de un CRUD JSON

Bloques: `resource`, `security`, `states?`, `relations?`, `list` (columns, filters, actions, scope?, summaries?), `actions` (row/bulk), `form` (fields, validators?), `detail?`, `uploads`, `hooks?`.

**Herencia entre JSON:** no existe. Defaults en PHP (`CrudResourceDefinition`, field/action/relation defaults).  
**Overrides:** por archivo completo; skeleton vs harness se mantienen a mano / con tests de paridad parcial.  
**Schemas JSON formales:** no hay JSON Schema; validación en `CrudConfigValidator` (shape + existencia de tabla/columnas/permisos en BD).

### Señales de “config explosion”

En este repo el volumen absoluto es **bajo** (5 CRUDs demo). La señal de explosion es **estructural**, no de escala actual:

- Un campo visible en lista + form + detalle + reporte toca varios bloques/archivos.
- Harness y skeleton deben mantenerse en sync.
- Reportes/calendarios re-declaran conocimiento del CRUD.

### Ejercicio real 1 — Agregar campo a `demo_productos`

Objetivo: campo sencillo (p. ej. `descripcion`) visible en form/list/detalle y reportable.

```text
Agregar campo descripcion a demo_productos:

1. database/schema/modules/crud-engine.sql          (columna física)
2. config/cruds/demo_productos.json                 (form.fields + list.columns + detail si aplica)
3. skeleton/config/cruds/demo_productos.json        (paridad template)
4. config/reportes/productos.json                   (si debe reportarse)
5. skeleton/config/reportes/productos.json
6. (opcional) migración incremental en database/migrations/ para installs existentes
7. (opcional) config/crud_handlers.php si hay validator custom

Total típico: 3–5 archivos (5–6 con reporte + skeleton)
Permisos: NO cambian para un campo
Rutas/controllers/views PHP: NO cambian (motor genérico)
```

Evidencia: `CrudConfigValidator` exige que columnas de form/list existan en BD; tests de paridad root/skeleton en `tests/Crud/State/CrudDemoStatesFormLockTest.php`.

### Ejercicio real 2 — Agregar campo a `demo_pedidos`

```text
Agregar campo origen_pedido a demo_pedidos:

1. database/schema/modules/crud-engine.sql (dom_demo_pedidos)
2. config/cruds/demo_pedidos.json (form + list + detail tabs fields)
3. skeleton/config/cruds/demo_pedidos.json
4. config/reportes/pedidos.json (+ skeleton) si se expone
5. Si afecta transición/pagar: posible guard DemoPedidoPagarGuard + crud_handlers.php

Total: 3–6 archivos
```

`demo_clientes` además tiene `list.scope.owner`: un campo nuevo no afecta scope, pero show/edit ya pasan por `assertOwnership`.

---

# 5. MOTOR DECLARATIVO

## Capacidades

### Capacidad: CRUD automático (list/create/edit/show/delete)

```text
Implementación: CrudController + CrudResourceService + CrudDataService + GenericCrudRepository + views admin/crud/*
Nivel de madurez: Alto (CAS update/delete, soft delete, aggregation limits, ownership assert) — release notes v1.2.7–v1.2.11
Acoplamiento: Medio-Alto al schema de columnas sistema (deleted, created_by, …) y a RBAC
Dependencias: JSON config, auth_permisos, tabla dom_*
Usada por: 5 recursos demo + cualquier consumer que añada JSON
Código aproximado: ~6.3k LOC PHP con *Crud* en path; ~4.2k en Application/Services/Crud*
Clasificación: A (valiosa/reutilizable propia)
```

### Capacidad: Definición de campos / formularios / tablas / filtros

```text
Implementación: CrudFieldDefinition, CrudFormBuilder, CrudTableBuilder, CrudFieldValidationService
Nivel: Medio-Alto (tipos, validation email/unique/exists, badges, formats)
Acoplamiento: Alto a JSON + columnas físicas
Usada por: todos los CRUD demo
Clasificación: A
```

### Capacidad: Relaciones belongsTo / hasMany

```text
Implementación: CrudRelationDefinition, CrudRelationService, GenericCrudRepository::distinctOptions / child selects
Nivel: Medio (filtros estáticos en JSON; sin scope owner propagado a options)
Acoplamiento: Medio
Usada por: demo_productos, demo_pedidos
Clasificación: A (con caveat de aislamiento — ver §8)
```

### Capacidad: Acciones row/bulk + handlers

```text
Implementación: CrudActionDefinition, CrudActionResolver, CrudActionService, CrudHandlerRegistry
Nivel: Alto (ownership en acciones, bulk parity en v1.2.11)
Clasificación: A
```

### Capacidad: Estados / transitions / guards

```text
Implementación: CrudStateMachine, CrudTransitionService (CAS), CrudTransitionGuardInterface
Nivel: Alto
Ejemplos: demo_productos activar/desactivar; demo_pedidos pagar/cancelar
Clasificación: A
```

### Capacidad: Permisos

```text
Implementación: auth_* + RbacPolicy + RbacMiddleware + CrudRbacMiddleware + permission_prefix en JSON
Nivel: Alto para feature-level; bajo para record-level salvo scope owner
Clasificación: A (RBAC) + B (sesión/login — infraestructura común)
```

### Capacidad: Dashboards (providers)

```text
Implementación: BuildDashboardViewModelUseCase + DashboardContributionProviderInterface
Nivel: Medio
Clasificación: A (ligera)
```

### Capacidad: Calendario sobre CRUD

```text
Implementación: CalendarConfigLoader + CalendarioController + CrudDataService::eventsInRange
Nivel: Medio
Clasificación: A
```

### Capacidad: Reportes + PDF

```text
Implementación: Reporte* + Pdf kit + templates whitelist
Nivel: Medio
Clasificación: A
```

### Capacidad: API generation

```text
Implementación: NO existe generador de API REST desde JSON
Nivel: N/A — solo HealthController (+ ping autenticado)
Clasificación: — (ausente)
```

### Capacidad: Payments / Invoicing genéricos

```text
Implementación: Domain ports + Stripe/Facturapi adapters + event log claim
Nivel: Medio (código presente; HTTP en consumidor; vertical OFF)
Clasificación: A (ports) + C (metadatos membresia_id / demo leads en integrations)
```

## Distinción A / B / C

| Tipo | Ejemplos en este repo |
|------|------------------------|
| **A — Valiosa propia** | CRUD JSON engine, state machine CAS, scope ownership, calendar/reportes sobre CRUD, module registry/install, RBAC slug + CrudRbac dinámico |
| **B — Infraestructura estándar** | Router/DI/Session/CSRF/PDO wrapper, login/registro/recuperación, layout Bootstrap, mailer, healthcheck |
| **C — Específica de app/demo** | `demo_*` resources/handlers, `DemoProvisioningService` (leads), `membresia_id` en Payments, `EnviarWhatsappDemoHandler`, vertical key `marketing` (OFF, sin código Marketing aquí) |

---

# 6. ACOPLAMIENTO

| Hallazgo | Severidad | Evidencia |
|----------|-----------|-----------|
| Session/Config estáticos usados desde Application/Presentation | Medio | `Session::get('auth_user')`, `Config::get(...)` en servicios/middlewares |
| Container singleton + `FrameworkServiceProvider` monolítico | Medio | `src/Kernel/Container/FrameworkServiceProvider.php` registra casi todo |
| SQL embebido en repositorios | Medio (esperado) | `UsuarioRepository`, `GenericCrudRepository` — preparado, sin ORM |
| Referencias por nombre de archivo JSON | Alto (para DX/IA) | `config/cruds/{resource}.json` debe coincidir con `resource.key` y URL |
| Handlers por clave string whitelist | Medio | `config/crud_handlers.php` ↔ JSON `guard`/`validators`/`hooks.handler` |
| Lógica de negocio en JSON (transitions, visible_when, validations) | Medio | `demo_pedidos.json` / `demo_productos.json` |
| Lógica demo de negocio en framework | Alto (ownership) | `DemoProvisioningService`, `membresiaId` en PaymentEvent |
| Relación CRUD sin reutilizar scope del recurso relacionado | Alto (seguridad en multi-user) | `CrudRelationService` → `distinctOptions` solo `deleted=0` + filter JSON |
| Vistas PHP genéricas sin lógica de dominio pesada | Bajo (positivo) | `admin/crud/index.php` itera view-models |
| Dependencias circulares módulos | Bajo | Manifiestos acíclicos: reportes→crud+pdf; calendario→crud |
| Globals microtest | Bajo (tests) | `$GLOBALS['__mt']` en `tests/lib/microtest.php` |
| Includes legacy | Bajo | Autoload PSR-4; sin `require` masivo de módulos |

No se observó un grafo clásico “Eventos↔Pagos↔Clientes” de negocio: ese acoplamiento vive fuera (Portal). Aquí el acoplamiento fuerte es **Core ↔ JSON ↔ Schema ↔ RBAC**.

---

# 7. BASE DE DATOS

## Acceso

- PDO singleton: `Kernel\Database\Connection`
- Repositorios: `BaseRepository` + específicos + `GenericCrudRepository`
- Sin ORM / sin query builder de terceros
- Identificadores dinámicos validados con regex y backtick-quoted

## Migraciones / versionado de esquema

- Greenfield: `database/schema/schema.sql` + `database/schema/modules/*.sql`
- Incrementales: `database/migrations/*.sql` (3 archivos activos) + `migrations_legacy/` (histórico)
- Tracking: tabla `cfg_migraciones` (schema) + `MigrationRepository`
- Manifiestos de módulo declaran `migraciones` y `bootstrap_sql`

## Convenciones

| Concepto | Implementación |
|----------|----------------|
| FK | SQL explícito en schema módulos (p. ej. pedidos→clientes) |
| Transacciones | Usadas en flujos puntuales (p. ej. claim idempotente payments/invoicing); CRUD genérico usa CAS por predicados |
| Soft deletes | CRUD: `deleted`/`deleted_at`/`deleted_by`; usuarios: `activo=0` vía `BaseRepository::softDelete` |
| Timestamps | `created_at`/`updated_at` (+ by columns en CRUD) |
| tenant_id / organization_id | **No existen** en schema de plataforma ni en CRUD demo |
| Prefijos | `auth_`, `cfg_`, `log_`, `core_`, `int_`, `rep_`, `tmp_`, `sys_` plataforma; `dom_*` negocio; `pay_`, `inv_` módulos |

**Tablas CREATE en schema:** 31 (19 core + módulos).  
**SQL files bajo database/:** 28.

## Dificultad de cambio

| Cambio | Dificultad | Por qué |
|--------|------------|---------|
| Añadir columna | Media | Schema + JSON (varios bloques) + skeleton + posible reporte |
| Renombrar columna | Alta | JSON, validators, relations, reportes, SQL, tests de boundary |
| Cambiar relación | Media-Alta | JSON relations + form field + exists validation + UI detail tab |
| Cambiar estructura de módulo plataforma | Alta | manifiesto + bootstrap SQL + seeds permisos/menú + vertical + docs + tests integridad |
| Cambiar tabla auth_* | Crítica | bloqueada para CRUD; acoplada a Auth/RBAC |

---

# 8. MULTI-TENANCY Y AISLAMIENTO DE DATOS

## Modelo documentado

`docs/modules/modulo-menu.md`:

> Multi-instancia: una base de datos por despliegue es el modelo habitual. Multi-tenant en una sola BD requeriría columnas extra (p. ej. `tenant_id`) …

`docs/TENANTS.md` / `ENVIRONMENTS.md`: tenants = **despliegues distintos** (Portal, skeleton lab, CRM tenant), no filas compartidas por `organization_id`.

## Cómo se determina la “organización actual”

**No se determina.** La sesión guarda:

```text
auth_user: { id, nombre, apellido, nombreCompleto, email, avatar }
auth_permisos: string[]
auth_roles: string[]
```

(`AuthService::iniciarSesion`). No hay `organization_id` / `tenant_id` en sesión ni middleware de tenant.

Búsqueda de `organization_id|tenant_id|empresa_id` en `src/`, `config/`, `database/`: **0 coincidencias** (salvo dominio Invoicing `inv_organizations.external_org_id`, que es config de proveedor fiscal, no tenant de request).

## Mecanismos de limitación de datos

| Mecanismo | Qué filtra | Dónde |
|-----------|------------|-------|
| Despliegue / BD separada | Todo | Operacional (fuera de código) |
| RBAC | Acciones/features | Middleware + services |
| Soft-delete `deleted=0` | Filas borradas | CrudDataService / repos |
| Owner scope JSON opcional | Filas por `created_by` | Solo si `list.scope.type=owner` |
| assertOwnership | show/edit/update/delete/actions | Si hay scope configurado |
| Reportes guardados | `created_by` OR `compartido=1` | PdoReporteRepository |
| Admin bypass | Ve todo en owner scope | Permiso bypass en meta de scope |

### Clasificación por superficie

| Superficie | Protección observada |
|------------|----------------------|
| CRUD list | `deleted=0` + scope **si** JSON lo declara (`demo_clientes` sí; `demo_productos`/`demo_pedidos`/`demo_categorias`/`demo_citas` **no**) |
| CRUD show/update/delete/action | Ownership assert **solo si** hay scope; si no hay scope → cualquier usuario con permiso de recurso |
| Relaciones / autocomplete options | `deleted=0` + filter JSON; **sin** owner/tenant |
| hasMany children | FK padre + `deleted=0` |
| Dashboards | RBAC; widgets delegan feeds a CRUD scope si aplica |
| Reportes data | Reusa CRUD scope del resource |
| Integrations accounts/logs | **Sin** filtro user/org — globales de instancia |
| auth_usuarios / roles | Globales de instancia |
| APIs | `/api/health` público; `/api/ping` sesión; sin datos de negocio |
| Exports | Vía reportes/PDF; hereda CRUD scope |
| Jobs/cron/webhooks HTTP | Tablas `tmp_jobs`/`int_webhooks` en schema; webhooks payments/invoicing **sin rutas en este repo** |
| Archivos | `core_archivos` / uploads CRUD path; no se auditó ACL multi-tenant (no aplica el modelo) |

### Ejemplos concretos

Owner scope (`config/cruds/demo_clientes.json`):

```json
"scope": {"type": "owner", "column": "created_by"}
```

`OwnerListScope` añade `created_by = :userId` (o `-1` si user null; bypass si permiso).

`demo_productos.json` / `demo_pedidos.json`: **sin** bloque `scope` → listado completo de la tabla (no eliminados) para cualquier usuario con `*.ver`.

Relaciones (`CrudRelationService` → `distinctOptions`): un pedido puede ofrecer **todos** los clientes activos, aunque `demo_clientes` esté scoped por owner en su propio listado.

## Conclusión de diseño (no veredicto de “inseguro”)

El aislamiento está pensado como:

```text
1 instancia / 1 base de datos / N usuarios con RBAC
(+ opcional row-level owner scope por recurso CRUD)
```

**No** como:

```text
1 base / N organizaciones / filtro obligatorio organization_id
```

Cualquier evaluación de “tenant isolation SaaS” debe partir de ese diseño explícito.

---

# 9. AUTENTICACIÓN, ROLES Y PERMISOS

## Modelo real

```text
Usuario (auth_usuarios)
  → auth_usuarios_roles
    → Rol (auth_roles)   [slug p.ej. administrador]
      → auth_roles_permisos
        → Permiso (auth_permisos)  [slug modulo.accion]
          → Middleware / Crud permission_prefix + acción
            → (opcional) row scope owner
```

## Componentes

| Tema | Implementación |
|------|----------------|
| Autenticación | Email/password, `Hash::verify`, sesión PHP `CONTRASTE_SESSION`, regenerate on login |
| Tokens | `auth_tokens` para verificación email / recuperación (`AuthTokenService`) |
| Rate limit login | `LoginIntentoRepository` + use case |
| Roles | Tabla + `RbacPolicy::esAdministrador()` bypass total de permisos |
| Permisos granulares | Slugs `recurso.ver|crear|editar|eliminar`, `reportes.*`, `integrations.*`, etc. |
| Autorización endpoints | `RbacMiddleware('slug')` o `CrudRbacMiddleware` dinámico |
| Autorización vistas/menú | `AdminNavigationMenuService` filtra `core_menu_items` por permisos + vertical |
| Autorización por registro | Solo vía CRUD scope owner / reporte ownership — **no** genérico por org |

## Diferencias

| Concepto | Estado en este repo |
|----------|---------------------|
| Autenticación | Implementada (sesión) |
| Autorización (RBAC feature) | Implementada y cubierta por tests |
| Tenant isolation | **No implementada como org multi-tenant**; aislamiento = BD por deploy + owner opcional |

---

# 10. APIs

## Inventario

| Endpoint | Auth | Autorización explícita | Notas |
|----------|------|------------------------|-------|
| `GET /api/health` | Público | No | `HealthController::health` |
| `GET /api/ping` | Sesión (`AuthMiddleware`) | Solo autenticado | Comentario: token futuro |
| Rutas Api Usuarios | Comentadas | — | No activas |

**API centralizada REST de negocio:** no.  
**API por módulo generada:** no.  
**Versionado API:** no.  
**Validación/respuestas estándar de API resource:** no (JSON ad hoc health).

### Conteo aproximado

```text
Endpoints bajo /api activos: 2
  públicos: 1
  autenticados (sesión): 1
  con RBAC explícito de permiso: 0
  indeterminados: 0

JSON-like fuera de /api (aprox.):
  calendario eventos: 1 (auth + CrudRbac)
  integraciones test/estado: 2 (auth+RBAC o signed token)
  avatares list: varios (auth ± RBAC usuarios)
```

**Metodología:** conteo literal de `$router->(get|post|...)` en `routes/api.php` (2 activos + 2 comentados) y revisión de responses JSON en controllers.

**Rutas web totales registradas:** ~70 en `web.php` + 5 integrations + 2 api ≈ **77** superficies HTTP en harness con integrations ON.

Payments/Invoicing webhooks: lógica en Infrastructure, **rutas en consumidor** según docs.

---

# 11. VISTAS

## Modelo

- **Motor de UI:** plantillas PHP (no React/Vue; no render JSON→HTML server-side separado).
- **Datos dinámicos:** JSON → builders → arrays → PHP views genéricas.
- **Layout:** `src/Presentation/Views/layouts/base.php` (Bootstrap, theme vars, nav, confirm modal).
- **Vistas PHP en paquete:** 76 bajo `Presentation/Views`.
- **HTML/JS manual:** parciales admin + `public/assets` (~1.9k LOC JS, ~1.9k CSS).
- **Generado:** filas/columnas/filtros/acciones CRUD a partir de config; no se generan archivos de vista por recurso.

## Comparación de dos recursos

### `demo_clientes` (con owner scope)

| Operación | Código/config necesario |
|-----------|-------------------------|
| listar | JSON `list` + permisos `demo_clientes.ver` + vista genérica |
| crear | JSON `form` + `demo_clientes.crear` |
| editar | mismo form + ownership assert + `editar` |
| ver | detail default o tabs + ownership |
| eliminar | soft delete + ownership + `eliminar` |

Sin controlador/vista propios.

### `demo_pedidos` (estados + relations + PDF links)

| Operación | Extra vs clientes |
|-----------|-------------------|
| listar | columns + filters status; sin scope owner |
| crear/editar | relation `cliente_id`, validator `demo_pedido_total` |
| ver | tabs fields + hasMany `items` + history |
| eliminar | soft delete |
| acciones | transitions pagar/cancelar + guards + links PDF reportes |

### Duplicación entre configs

- Badges de estado repetidos en `states.values` y `list.columns[].badge`.
- Acciones builtin en `list.actions` y `actions.row`.
- Columnas de negocio repetidas en `config/reportes/*.json`.
- Root ↔ skeleton duplicados.

---

# 12. LÓGICA DE NEGOCIO

## Dónde vive

| Capa | Rol observado |
|------|---------------|
| Controllers | Delgados (parse + CSRF + delegate) |
| Application Services / UseCases | Orquestación principal |
| Domain Entities/Policies | Invariantes (Usuario, RbacPolicy, CrudStateMachine) |
| Handlers CRUD | Reglas demo (total>0, guards de estado) |
| JSON | Declaración de transitions/validations/visibility |
| Views | Presentación |
| SQL | Persistencia + seeds permisos/menú |
| Triggers | No evidenciados en schema revisado |

## Tres operaciones no triviales

### 1) Login con rate limit

`AuthController` → `LoginUseCase` → rate limit → `AuthService::autenticar` → `Usuario::puedeIniciarSesion` → `UsuarioRepository` → `iniciarSesion` (permisos/roles a sesión).

Capa clara Application/Domain.

### 2) Transición CRUD `pagar` en pedido

Request action → `CrudActionService` → RBAC + `assertOwnedBy` (si scope) → `CrudTransitionService` CAS (`status=pendiente` expected) → `DemoPedidoPagarGuard` → update + audit.

Lógica repartida JSON (grafo estados) + Domain (state machine) + Handler (guard) + Infrastructure (CAS SQL).

### 3) Issue invoice from source (genérico)

`IssueInvoiceFromSource`: claim idempotency → consumer `InvoiceableSourceInterface` draft → provider create → mark issued / needs-reconcile.

Dominio genérico en framework; **datos fiscales de negocio en consumidor**.

**Veredicto de distribución:** hay capa Application/Domain real para plataforma; la “lógica de módulo demo” está en handlers + JSON; el negocio Portal no está aquí.

---

# 13. TESTS

## Inventario

| Tipo | Evidencia | Cantidad |
|------|-----------|----------|
| Unit / service (microtest) | `tests/**/**Test.php` | **206** archivos |
| Integration-ish (DB/config) | Crud, Install, Auth, Payments claim | Incluidos en los 206 |
| API tests | Health cubierto vía Docs/release; no suite API resource | Mínimo |
| Permission / RBAC | CrudRbac, Auth, Security | Sí |
| Tenant org A vs org B | **No encontrados** | 0 |
| Owner IDOR | `CrudActionOwnershipTest`, Scope tests | Sí |
| Browser/E2E | No hay Playwright/Cypress en repo | 0 |
| Framework runner | `tests/run.php` + `microtest.php` | — |
| PHPUnit | `phpunit.xml.dist` plantilla | No runner principal |
| LOC tests | ~13.8k | |

**Cobertura de código (%):** no hay reporte de coverage en el repo inspeccionado → **no inventada**.

### ¿Existe test “Org A no ve datos de Org B”?

**No.** Existen tests equivalentes a **usuario A no ve/actúa sobre registro de usuario B** cuando hay owner scope (`assertOwnedBy bloquea a un usuario ajeno (IDOR)` en `tests/Security/CrudActionOwnershipTest.php`). Eso **no** demuestra aislamiento por organización.

### Qué cubren bien / qué no

| Cubren | No cubren (en este repo) |
|--------|---------------------------|
| CRUD config validation, CAS, scopes, actions | Multi-tenant org |
| Auth login/registro/tokens | Portal Marketing flows |
| Payments webhook signature/idempotency unitario | HTTP webhook end-to-end en package routes |
| Install/module integrity skeleton | E2E browser |
| Docs/version sync 1.2.11 | Coverage % |

---

# 14. VERSIONADO

| Componente | Versión detectable | Fuente | Confiabilidad |
|------------|-------------------:|--------|---------------|
| Core / package | 1.2.11 | `composer.json`, `config/app.php`, tags `v1.2.11` | Alta |
| Skeleton app.version | 1.2.11 | `skeleton/config/app.php` | Alta (testeado sync) |
| Módulo `core` | 1.0.0 | `config/modules/core.php` | Baja (estática, no semver de release) |
| `crud-engine` | 1.0.0 | manifiesto | Baja |
| `dashboard` | 1.0.0 | manifiesto | Baja |
| `calendario` | 1.0.0 | manifiesto | Baja |
| `reportes` | 1.0.0 | manifiesto | Baja |
| `pdf-kit` | 1.0.0 | manifiesto | Baja |
| `integrations` | 1.0.0 | manifiesto | Baja |
| `payments` | 1.0.0 | manifiesto | Baja |
| `invoicing` | 1.0.0 | manifiesto | Baja |
| demo_clientes (recurso) | DESCONOCIDA | — | — |
| demo_pedidos | DESCONOCIDA | — | — |
| Config JSON schema version | DESCONOCIDA | docs dicen CRUD Engine v0.1 | Baja |
| DB schema | vía migraciones + `cfg_migraciones` | archivos SQL | Media |
| API | DESCONOCIDA / sin versionado | — | — |
| Changelog raíz | No hay `CHANGELOG*` | `docs/release/v1.2.{7,9,10,11}.md` | Media |
| Git tags | v1.0.0 … v1.2.11 (+ archive tags) | `git tag` | Alta |

---

# 15. DEPENDENCIAS ENTRE MÓDULOS

```text
core
├── (base: auth, rbac, menu, settings)

dashboard
└── core                         [explícita]

crud-engine
└── core                         [explícita]
    └── recursos demo_*          [por config cruds + SQL]

pdf-kit
└── core                         [explícita]

calendario
├── core                         [explícita]
└── crud-engine                  [explícita]
    └── demo_citas               [config + SQL calendario]

reportes
├── core                         [explícita]
├── crud-engine                  [explícita]
└── pdf-kit                      [explícita]
    └── reportes/*.json → CRUD   [por config]

payments
└── core                         [explícita; vertical OFF]

invoicing
└── core                         [explícita; vertical OFF]

integrations
└── core                         [explícita; vertical ON en harness]
```

### Dependencias implícitas / por config / BD

| Desde | Hacia | Tipo |
|-------|-------|------|
| `demo_pedidos` | `dom_demo_clientes` | JSON relation + SQL FK/tabla |
| `demo_productos` | `dom_demo_categorias` | JSON relation |
| reportes fuentes | CRUD resources | config `fuente.resource` |
| calendario | CRUD `demo_citas` | config |
| Crud PDF actions | reportes + pdf_templates | JSON link routes |
| Menu | permisos + vertical | BD + config |

**Circulares:** no detectadas en manifiestos `requiere`.

---

# 16. MÉTRICAS DEL FRAMEWORK

Metodología: `find` + `wc -l` sobre el tree inspeccionado (2026-08-12). Excluye `vendor/` no listado en conteos de `src`.

| Métrica | Valor |
|---------|------:|
| Líneas PHP `src/` | 31 122 |
| PHP files `src/` | 415 |
| Kernel LOC | 3 126 (29 files) |
| Application LOC | 11 787 (130) |
| Domain LOC | 4 420 (116) |
| Infrastructure LOC | 4 144 (40) |
| Presentation LOC | 7 645 (100) |
| CRUD-related PHP LOC (`*Crud*` path) | ~6 353 |
| JSON config LOC (`config`+`skeleton/config`) | 1 368 |
| JSON files producto | 20 (10+10) |
| Módulos plataforma (`config/modules`) | 9 |
| Controllers Presentation | 18 |
| Views PHP | 76 |
| Middlewares | 4 |
| Endpoints `/api` activos | 2 |
| Rutas HTTP registradas (aprox.) | ~77 con integrations |
| Tablas CREATE schema | 31 |
| Migraciones activas | 3 (+10 legacy) |
| Tests `*Test.php` | 206 (~13.8k LOC) |
| JS `public/assets` | ~1 908 |
| CSS `public/assets` | ~1 935 |
| PHP en root `app/` | 0 |
| PHP en `skeleton/` | 47 |

### Infraestructura vs aplicación

Clasificación aproximada **dentro de este repo** (todo es “plataforma”, pero se puede separar):

| Clase | LOC aprox. | Criterio |
|-------|-----------:|----------|
| Infraestructura común (Kernel + Auth/RBAC base + Views shell) | ~10–12k | Kernel + parte Presentation/Application Auth |
| Motor diferencial (CRUD/Calendar/Reportes/Pdf/Install) | ~12–15k | Crud* + Reporte + Pdf + Calendar + Install |
| Módulos opcionales Payments/Invoicing/Integrations | ~4–6k | carpeta Payments/Invoicing/Integrations |
| Demo/app-flavored | <1k + JSON demo | Handlers Demo*, provisioning leads |

No hay un “código de aplicación Portal” medible aquí.

---

# 17. COMPLEJIDAD PARA IA

## Factores que dificultan comprensión

| Factor | Evidencia |
|--------|-----------|
| Comportamiento distribuido | JSON + Validator + DataService + Scope + ActionService + View |
| Convenciones implícitas | `resource.key` = filename = URL segment; columnas sistema obligatorias |
| Configs relacionadas sin links formales | CRUD ↔ reporte ↔ calendar ↔ handlers ↔ module manifest ↔ SQL |
| Duplicación harness/skeleton | Cambios deben repetirse |
| Metaprogramación ligera | SQL dinámico con whitelist de identificadores; handlers por string key |
| Falta JSON Schema | Solo validator PHP runtime (necesita BD) |
| Docs buenos pero largos | `docs/modules/crud/*`, ownership multi-repo |
| Residuos demo/Portal naming | leads, membresia_id confunden ownership |
| Paths en docs a veces apuntan a `app/` legacy | p.ej. enlaces en `uso-crud-engine.md` hacia paths de app |

## Estimación de archivos a tocar (tareas típicas)

| Tarea | Archivos a comprender/modificar (típico en este repo) | Ejemplo |
|-------|------------------------------------------------------|---------|
| Agregar campo | 3–6 | schema + 2 JSON CRUD + 2 reportes |
| Agregar acción/transition | 2–5 | JSON + guard class + crud_handlers (+ skeleton) |
| Agregar permiso | 2–4 | SQL permisos/roles + menú + posiblemente JSON permission |
| Agregar vista admin custom | 5–10 | controller App\, views, routes, container, permisos, menú, vertical |
| Agregar módulo CRUD-only | ~8–12 | ver §22 |
| Agregar módulo Domain completo (checklist docs) | 15+ | `uso-de-modulo-dominio.md` |
| Corregir bug IDOR/scope | 3–8 + tests Scope/Security | CrudScopeResolver + Resource/Action services |

Para un agente, el riesgo principal es **omitir skeleton parity, reporte, o permisos SQL**, no encontrar el controlador (porque a menudo no existe).

---

# 18. PORTABILIDAD DEL MOTOR PROPIO

| Componente | Clasificación | Notas |
|------------|---------------|-------|
| Definición JSON CRUD | Portable con refactor | Necesitaría schema versionado + quitar supuestos de columnas |
| CRUD engine (services) | Portable con refactor | Acoplado a Session/RBAC/PDO propios |
| View engine (PHP templates) | Muy acoplado | Bootstrap layout + helpers del Kernel |
| Fields/forms/tables builders | Portable con refactor | Relativamente puros sobre definitions |
| Workflows/states/CAS | Muy portable (núcleo) | State machine + CAS SQL adaptable |
| Actions/handlers registry | Portable con refactor | |
| Permissions model | Portable con refactor / o reemplazable | RBAC clásico |
| Relationships | Portable con caveat | Falta política de scope en options |
| Reporting | Portable con refactor | Depende CRUD defs + Pdf |
| API generator | No vale la pena portar | No existe |
| Kernel HTTP/DI/Session | No vale la pena portar | Sustituible por infra estándar |
| Payments/Invoicing ports | Muy portable | Ya están pensados como puertos |
| Demo handlers / provisioning leads | No vale la pena portar | |

**Pregunta de rescate:** el valor diferencial rescatable es el **pipeline Declarative Resource → Definition → Scope/RBAC → CAS Data → Builders**, no el Kernel HTTP ni las vistas Bootstrap.

---

# 19. DEUDA TÉCNICA

| Hallazgo | Severidad | Alcance | Evidencia | Probable costo |
|----------|-----------|---------|-----------|----------------|
| Sin multi-tenant por fila pese a producto multi-instancia documentado como futuro | Alta (si se exige SaaS shared-DB) | Seguridad/datos | `modulo-menu.md`; ausencia `tenant_id` | Diseño + migración datos + scopes globales |
| Owner scope opt-in; mayoría de demos sin scope | Media | CRUD | solo `demo_clientes` tiene scope | Política + defaults + tests |
| Relation options sin scope | Alta en multi-user | CRUD relations | `CrudRelationService`/`distinctOptions` | Extender relation loader |
| Duplicación config root↔skeleton | Media | DX/release | 10 JSON ×2 | Generación única o test sync total |
| Residuos demo/Portal en `src` (leads, membresia) | Media | Ownership FPS | `DemoProvisioningService`, `PaymentEvent::membresiaId` | Extracción a Portal / limpieza |
| Manifiestos módulo version `1.0.0` estáticos | Baja | Release | `config/modules/*.php` | Alinear a semver o eliminar |
| API de negocio inexistente / comentada | Media (si se necesita headless) | Integraciones | `routes/api.php` | Diseño API + auth token |
| PHPUnit no es runner real | Baja | Tooling | `phpunit.xml.dist` template | Unificar runners |
| Docs con paths `app/` desactualizados en guía CRUD | Baja | DX | `uso-crud-engine.md` links | Corregir docs |
| `config/rbac_route_permissions.php` incompleto vs routes integrations | Baja | Auditoría RBAC | integrations no listados | Actualizar registry |
| Config explosion estructural (bloques repetidos) | Media a largo plazo | Escalabilidad módulos | badges/actions/report columns | Single source + projections |

---

# 20. ACTIVOS VALIOSOS

| Activo | Qué aporta | Qué se pierde al reescribir desde cero |
|--------|------------|----------------------------------------|
| CRUD Engine JSON | CRUD admin sin controllers por recurso | Meses de reimplementar forms/tables/actions/states |
| CAS + soft-delete + ownership asserts | Endurecimiento reciente (v1.2.7–1.2.11) + tests IDOR | Regresiones sutiles de carrera/IDOR |
| RBAC slug + menú BD + vertical toggles | Deploy profiles limpios | Rehacer navigation/authz wiring |
| Module registry + bootstrap SQL | Install reproducible | Rehacer installer |
| Calendar/Reportes sobre mismo resource | Multiplicador del CRUD | Features satélite |
| Payments/Invoicing ports + idempotent claims | Base de billing/fiscal genérica | Reescribir adapters y claim logs |
| Skeleton + docs ownership FPS | Onboarding tenants / separación Portal | Confusión monorepo otra vez |
| Suite microtest 206 archivos | Red de seguridad del motor | Pérdida de regresiones conocidas |
| Convenciones `dom_*` / prefijos plataforma | Claridad schema | Drift de ownership SQL |

---

# 21. CASO DE ESTUDIO: MÓDULO REPRESENTATIVO

## Selección: `crud-engine` + recurso `demo_pedidos`

Es el showcase más completo: CRUD + states + relations + validators + guards + bulk + PDF links + reportes.

### Archivos involucrados

| Tipo | Paths |
|------|-------|
| Manifiesto | `config/modules/crud-engine.php` (+ skeleton) |
| Config CRUD | `config/cruds/demo_pedidos.json` |
| Handlers | `DemoPedidoTotalValidator.php`, `DemoPedidoPagarGuard.php`, `config/crud_handlers.php` |
| SQL | `database/schema/modules/crud-engine.sql` (tablas pedidos/items + permisos) |
| Migración | `database/migrations/20260609120000_crud_demo_permisos_modulo_por_recurso.sql` |
| Reportes/PDF | `config/reportes/pedidos.json`, `config/pdf_templates.php` |
| Código motor | `CrudController`, `CrudResourceService`, `CrudActionService`, `CrudTransitionService`, … |
| Views | `Presentation/Views/admin/crud/*` (genéricas) |
| Tests | `tests/Crud/**`, `tests/Security/CrudActionOwnershipTest.php`, state/form lock tests |

### DB

`dom_demo_pedidos`, `dom_demo_pedido_items` (+ FK lógica a `dom_demo_clientes`).

### API

Sin API REST propia; solo UI `/admin/crud/demo_pedidos` y documento PDF vía reportes.

### Permissions

Prefijo `demo_pedidos` → `ver/crear/editar/eliminar`; acciones usan `permission` relativo; links PDF piden `reportes.generar`.

### Dependencies

```text
demo_pedidos
├── crud-engine/core
├── dom_demo_clientes (relation)
├── dom_demo_pedido_items (hasMany)
├── reportes + pdf templates
└── handlers whitelist
```

### ¿Qué debe comprender un desarrollador nuevo?

1. Contrato tabla CRUD (columnas sistema).
2. Forma del JSON (resource/list/form/actions/states/relations/detail).
3. Que **no** hay controller del recurso.
4. Whitelist de handlers (nunca FQCN en JSON).
5. RBAC slugs en BD.
6. Scope: este recurso **no** aísla por owner (a diferencia de clientes).
7. Paridad skeleton si el cambio es de plataforma.
8. Tests Crud/Security a ejecutar (`php tests/run.php Crud`).

---

# 22. CASO DE ESTUDIO: AGREGAR MÓDULO `ReservasPrueba`

Simulación **CRUD-first** siguiendo `docs/modules/crud/uso-crud-engine.md` + checklist parcial de `uso-de-modulo-dominio.md`, **sin implementarlo**.

Campos pedidos: `id`, `organization_id`, `cliente_id`, `fecha`, `status`, `total`, `created_at`, `updated_at`.

### Ajuste a convenciones reales

El motor espera además: `deleted`, `created_by`, `updated_by`, `deleted_at`, `deleted_by`.  
`organization_id` **no es un concepto del framework hoy**: quedaría como columna de negocio sin filtro automático salvo que se implemente `scope_handler` custom.

### Archivos nuevos (estimado)

```text
1. database/schema/modules/reservas-prueba.sql          (o migración incremental)
2. config/cruds/reservas_prueba.json
3. skeleton/config/cruds/reservas_prueba.json
4. (opcional) config/modules/reservas-prueba.php        (manifiesto si módulo instalable)
5. skeleton/config/modules/reservas-prueba.php
6. (opcional) app consumer: scope handler si organization_id
7. (opcional) config/reportes/reservas_prueba.json ×2
8. (opcional) tests/Crud/... 
```

### Archivos modificados

```text
1. config/modules/crud-engine.php OR nuevo manifiesto + Install registry scan
2. config/vertical.php (+ skeleton) si menú/toggle
3. database seeds / SQL permisos + auth_roles_permisos + core_menu_items
4. config/crud_handlers.php si validators/scopes
5. docs/core/schema-code-map.md (checklist)
```

### Cálculo DX

```text
Archivos nuevos:           3–8
Archivos modificados:      3–6
Configs JSON:              1–4
Código PHP nuevo:          0 (CRUD puro) … ~50–150 LOC si scope_handler/validator
Pasos manuales:            crear tabla → permisos SQL → JSON → menú → vertical → install/migrate → probar UI
Rutas/controllers/views:   0 nuevos (usan genéricos) si solo CRUD admin
```

Si se sigue el checklist Domain completo (`uso-de-modulo-dominio.md`) en vez de CRUD engine, el costo salta a **15+ artefactos** (entities, repos, use cases, controller, views, container, routes).

---

# 23. CASO DE ESTUDIO: AGREGAR CAMPO `origen_prospecto`

Módulo real elegido: **`demo_clientes`** (tiene form/list/scope/report).

### Lugares

| Superficie | ¿Automático? | Acción |
|------------|--------------|--------|
| BD | No | Columna en `dom_demo_clientes` (schema module + migración si aplica) |
| Crear/Editar | Semi | Añadir a `form.fields` en `demo_clientes.json` (+ skeleton) |
| Detalle | Semi | Si hay `detail.tabs` fields, añadir; si no, detail default usa list columns → añadir también a `list.columns` o detail explícito |
| Lista | Semi | `list.columns` |
| API | N/A | No hay API del recurso |
| Filtros | Semi | Entrada en `list.filters` si se desea filtrar |
| Export/Reportes | Semi | `config/reportes/clientes.json` (+ skeleton) `expose.columns` |
| Permisos | Automático | Mismos `demo_clientes.*` |
| Validación | Semi | `validation` en field JSON o handler |
| Ownership | Automático | Sigue aplicando `created_by` scope |

```text
Total archivos típicos: 3–5
PHP controllers/views: 0
```

---

# 24. RIESGO DE REESCRITURA

Funcionalidades/comportamientos fáciles de olvidar:

- CAS en transitions/update/soft-delete y fail-closed en equality (`v1.2.11`)
- Owner scope + bypass permission + mensaje IDOR genérico (“no existe”)
- Whitelist de handlers (prohibición de FQCN en JSON) — superficie de seguridad
- Bloqueo de prefijos `auth_/cfg_/core_/log_` en CRUD
- Aggregation limits / require_filter_above en listados grandes
- Paridad bulk actions con row ownership
- Calendar events reusando CRUD scope
- Reportes: visibilidad `created_by|compartido` distinta del CRUD
- Vertical toggles + menú BD + RBAC combinados
- Install order: schema → modules bootstrap → migrations → seeds
- Idempotent claims payments/invoicing
- SignedToken activation links integrations
- Convenciones columnas sistema CRUD
- Skeleton parity como contrato de template
- Separación FPS Portal vs Framework (no recolocar Marketing)

---

# 25. RIESGO DE CONTINUAR

Aspectos que se encarecen al seguir construyendo encima (con evidencia):

| Riesgo | Evidencia |
|--------|-----------|
| Crecimiento de JSON duplicado (root/skeleton/report/calendar) | 20 JSON espejo; badges/actions repetidos |
| Expectativa SaaS multi-tenant choca con modelo 1-BD | Docs menú; demos sin organization scope |
| Residuos demo/Portal en plataforma confunden boundary | Integrations leads; membresia_id |
| Motor CRUD como “única vía” vs módulos Domain ricos | Dos caminos DX (CRUD-only vs checklist 16 pasos) |
| API headless insuficiente para integraciones modernas | Solo health/ping; webhooks fuera del package |
| Provider DI monolítico | `FrameworkServiceProvider` grande |
| Conocimiento implícito para agentes/humanos | Multi-archivo sin schema formal |

---

# 26. INFORMACIÓN QUE NO PUDE DETERMINAR

- Código y acoplamiento real de **`Lebytek_Portal`** (fuera de este repo): volumen de módulos de negocio, configs JSON de producción, endpoints reales.
- Si en producción se usa **shared-DB multi-org** pese a la documentación (no hay evidencia aquí).
- **Cobertura %** de tests (no hay artefacto coverage).
- Número exacto de endpoints HTTP del Portal / WhatsApi.
- Uso real en VPS de cada vertical flag.
- Complejidad del wiring de webhooks Payments/Invoicing **en el consumidor** (solo contrato documentado).
- Performance en tablas grandes (solo guards de aggregation en config).
- Existencia de jobs/cron activos usando `tmp_jobs` en runtime real.
- Si `config/rbac_route_permissions.php` es enforced automáticamente o solo inventario.
- Historial completo de deuda ya cerrada fuera de `docs/release/*` y PRs no leídos exhaustivamente.
- Cantidad de configs JSON en tenants reales derivados del skeleton.

---

# 27. EVIDENCIA (ÍNDICE RÁPIDO)

| Tema | Referencias |
|------|-------------|
| Package identity | `composer.json`, `README.md`, `docs/PACKAGE-ROOT.md` |
| Consumer contract | `docs/ARCHITECTURE-CONSUMER.md`, `docs/TENANTS.md` |
| Boot/HTTP | `public/index.php`, `src/Kernel/Bootstrap.php`, `src/Kernel/Http/Router.php` |
| Auth/RBAC | `AuthService.php`, `RbacPolicy.php`, `AuthMiddleware.php`, `RbacMiddleware.php` |
| CRUD | `CrudController.php`, `CrudConfigLoader.php`, `CrudDataService.php`, `config/cruds/*.json` |
| Scope/IDOR | `OwnerListScope.php`, `CrudScopeResolver.php`, `tests/Security/CrudActionOwnershipTest.php` |
| Multi-instance note | `docs/modules/modulo-menu.md` |
| Modules | `config/modules/*.php`, `config/vertical.php` |
| API | `routes/api.php` |
| DB ownership | `docs/database/SCHEMA-OWNERSHIP.md`, `database/schema/` |
| Version | `composer.json` 1.2.11, `docs/release/v1.2.11.md`, git tags |
| Tests runner | `tests/run.php`, `tests/lib/microtest.php` |

---

# 28. MATRIZ FINAL

| Dimensión | Estado actual | Evidencia | Riesgo |
|-----------|---------------|-----------|--------|
| Modularidad | **4/5** | Manifiestos `requiere`, vertical toggles, capas Onion | Bajo-medio: residuos demo |
| Configuración | **3/5** | JSON potente pero duplicado/sin schema/sin herencia | Medio: explosion estructural al crecer |
| Developer Experience | **3/5** | CRUD reduce código; checklist Domain es largo; skeleton ayuda | Medio |
| Seguridad | **3/5** | CSRF, RBAC, upload tests, CAS, IDOR owner; API mínima | Medio según threat model |
| Tenant isolation | **2/5** | 1-BD/deploy documentado; sin org scope; relation leak potencial multi-user | **Alto** si se exige shared-tenancy |
| Testabilidad | **4/5** | 206 tests microtest, buenos en CRUD/Auth/Payments unit | Medio: sin E2E/org tests |
| APIs | **2/5** | Solo health/ping; webhooks en consumer | Alto para integraciones headless |
| Versionado | **4/5** | Semver package + tags + release notes; módulos 1.0.0 cosméticos | Bajo |
| Mantenibilidad | **3/5** | Buena estructura capas; provider monolítico; dual paths CRUD vs Domain | Medio |
| Escalabilidad de módulos | **3/5** | OK por deploy; JSON/sync skeleton limitan escala de muchos recursos | Medio-alto a largo plazo |
| Trabajo con IA | **3/5** | Docs fuertes; convenciones implícitas multi-archivo | Medio |
| Portabilidad | **3/5** | Motor CRUD/ports rescatables; Kernel/views no | Medio |
| Deuda técnica | **3/5** | Deuda concreta (§19) no catastrófica en package scope | Medio |
| Valor del motor propio | **4/5** | CRUD+states+calendar+reportes+RBAC es diferencial real | Riesgo de subestimarlo en rewrite |

Puntuación: 1=crítico … 5=excelente. **Sin seleccionar ganador mantener vs reemplazar.**

---

# Conclusión (sin decisión)

1. **Qué hace especialmente bien:** empaquetar una plataforma admin coherente (auth, RBAC, menú vertical, install) con un **CRUD declarativo maduro** (estados, acciones, CAS, ownership opcional, calendario/reportes) y una separación documentada Framework↔Portal↔skeleton.

2. **Mayor deuda técnica:** el **modelo de aislamiento** (instancia/BD vs expectativa multi-org), el **scope opt-in** inconsistente entre recursos, las **relation options sin el mismo aislamiento**, y la **duplicación/configuración estructural** (JSON multi-bloque + harness/skeleton).

3. **Partes propias y valiosas:** CRUD engine + state/actions/handlers, builders, module registry, calendar/reportes sobre resources, ports Payments/Invoicing, tests de IDOR/CAS.

4. **Partes infraestructura:** Kernel HTTP/DI/Session/CSRF/PDO, layout Bootstrap, login/registro, healthcheck — sustituibles por stacks estándar sin perder el diferencial.

5. **Nivel de acoplamiento:** **medio-alto** entre JSON↔schema↔RBAC↔servicios CRUD; **bajo** entre módulos plataforma (grafo acíclico); acoplamiento a negocio Portal **parcialmente filtrado** pero con residuos demo.

6. **Verificabilidad por tests:** **buena para el motor de plataforma** (206 tests); **nula para aislamiento por organización**; sin E2E browser ni coverage %.

7. **Riesgo de continuar:** crecimiento de configs duplicadas, ambigüedad multi-tenant, y dos DX paths (CRUD JSON vs módulo Domain completo) sin API headless de primer nivel.

8. **Riesgo de reescribir:** perder endurecimientos sutiles (CAS, fail-closed, handler whitelist, aggregation guards), el multiplicador calendar/reportes, y el contrato FPS/skeleton ya operacionalizado — más el costo de re-probar comportamientos implícitos no documentados en un solo lugar.

---

*Fin del contexto de evaluación. Próximo paso esperado (fuera de este documento): evaluación arquitectónica externa con criterios de negocio/costo, aún sin presuponer Laravel/Symfony ni reescritura.*
