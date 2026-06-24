# Auditoría y mapa del proyecto

**Repositorio:** Lebytek Framework (`lebytek/app`)  
**Alcance:** inspección read-only — estructura, core, configuración, módulos, acoplamientos  
**Fecha:** 2026-06-23

---

## 1. Resumen ejecutivo

| Área | Estado | Observación clave |
| ---- | ------ | ----------------- |
| Arquitectura | Madura | MVC + Onion en 5 capas (`Presentation`, `Application`, `Domain`, `Infrastructure`, `Kernel`) documentada y aplicada |
| Core / Kernel | Terminado | Bootstrap, Router, DI, Session, Config, Autoloader PSR-4 propio |
| Modularidad | Parcial | Manifiestos + instalador existen; rutas/DI siguen centralizados en archivos globales |
| Configurabilidad | Alta (CRUD) / Media (plataforma) | CRUD/calendario/reportes vía JSON; rutas y bindings no auto-registrados por módulo |
| Plataforma admin | Terminada | Auth, RBAC, menú dinámico, ajustes, dashboard, perfil |
| Módulos opcionales | Mixto | CRUD demo, calendario y pdf-kit operativos; reportes reciente; marketing solo planificado |
| Base de datos | Estructurada | Prefijos por capa; schema base + bootstrap por módulo; migraciones versionadas |
| Tests | Sólido | ~137 archivos `*Test.php` + harness `tests/run.php`; PHPUnit presente pero secundario |
| Documentación | Extensa | `docs/core/`, `docs/modules/`, auditorías previas, planes superpowers |
| Verticales / desacople | En progreso | `config/vertical.php` filtra menú; `nuevo_modulo/` es app PHP paralela no integrada |

---

## 2. Formato general del proyecto

| Aspecto | Valor detectado |
| ------- | --------------- |
| Tipo de aplicación | Framework de plataforma administrativa (sin lógica de negocio empotrada en core) |
| Lenguaje principal | PHP ≥ 8.1 (`composer.json`) |
| Framework externo | No — framework propio (Router, Container, Bootstrap custom) |
| Dependencias Composer | `dompdf/dompdf`, `phpmailer/phpmailer` |
| Patrón arquitectónico | MVC (entrada/salida HTTP) + Onion (capas internas) |
| Convenciones | Documentadas en `docs/core/convenciones_nombres.md`, `.cursor/rules/` |
| Punto de entrada | `public/index.php` → `app/Kernel/Bootstrap.php` |
| Flujo básico | Request → Autoloader/ENV/Config → Session → PDO lazy → Container → Router (`routes/web.php`, `routes/api.php`) → Controller → UseCase/Service → Repository → Response |

---

## 3. Estructura de carpetas

| Ruta | Función probable | Observaciones |
| ---- | ---------------- | ------------- |
| `app/Presentation/` | Controllers, Views, Middlewares | 17 controladores; vistas Bootstrap 5 + LEBYTEK UI |
| `app/Application/` | UseCases, Services, DTOs, Install, Pdf, Reporte, Crud | ~109 clases PHP |
| `app/Domain/` | Entities, Interfaces, Policies, Pdf, Reporte, Calendar | ~69 clases; sin dependencias externas |
| `app/Infrastructure/` | Repositories, Mail, Pdf, Dashboard providers | 13 repositorios PDO |
| `app/Kernel/` | Bootstrap, Router, Container, Config, Security, Helpers | 25 archivos transversales |
| `config/` | Configuración PHP + manifiestos de módulo | 22 archivos PHP; JSON en subcarpetas |
| `config/cruds/` | Definiciones CRUD Engine | 6 JSON (5 demo + `clientes.json` huérfano) |
| `config/calendars/` | Definiciones calendario | 1 JSON (`demo_citas.json`) |
| `config/reportes/` | Fuentes reportables | 4 JSON |
| `config/modules/` | Manifiestos de módulo | 6 manifiestos (`core`, `dashboard`, `crud-engine`, `calendario`, `pdf-kit`, `reportes`) |
| `database/schema/` | Schema base + bootstrap por módulo | `schema.sql` + 4 módulos en `schema/modules/` |
| `database/migrations/` | Migraciones activas | 13 archivos SQL |
| `database/migrations_legacy/` | Histórico | Referencia; no para instalaciones nuevas |
| `database/seeds/` | Semillas idempotentes | 6 archivos (auth, menú, config) |
| `public/` | Web root, assets, PWA, wizard install | `public/install/` con 11 archivos |
| `routes/` | Rutas HTTP | Solo `web.php` y `api.php` (centralizadas) |
| `scripts/` | CLI (install, seed, migrate, status) | 7 scripts |
| `storage/` | Logs, uploads, temp, cache | Generado en runtime |
| `tests/` | Suite microtest propia | 137+ tests; `tests/run.php` |
| `docs/` | Documentación normativa y de módulos | 87+ archivos `.md` |
| `nuevo_modulo/` | Prototipo SaaS WhatsApp (PHP plano) | **No integrado** al framework; arquitectura distinta |
| `vertical-kit/` | Kit de vertical | Carpeta presente; **sin archivos detectados** |
| `plan/` | Notas de planificación | 3 archivos |
| `vendor/` | Dependencias Composer | dompdf, phpmailer |
| `auditoria/` | Entregables de auditoría | Este reporte |

---

## 4. Componentes core detectados

| Componente | Ruta | Responsabilidad | Nivel de reutilización | Observaciones |
| ---------- | ---- | --------------- | ---------------------- | ------------- |
| Bootstrap | `app/Kernel/Bootstrap.php` | Secuencia de arranque | Alto | Copia `.env` desde example si falta |
| Autoloader | `app/Kernel/Autoloader.php` | PSR-4 `App\` → `/app` | Alto | Funciona sin Composer |
| Config | `app/Kernel/Config/Config.php` | Carga `config/*.php` | Alto | — |
| Container | `app/Kernel/Container/Container.php` | DI singleton/bind | Alto | ~86 bindings en `config/container.php` |
| Router | `app/Kernel/Http/Router.php` | Rutas + middleware por ruta | Alto | Grupos con prefix |
| Request/Response | `app/Kernel/Http/` | HTTP abstracción | Alto | — |
| Session / CSRF | `app/Kernel/Security/` | Sesión, tokens CSRF | Alto | — |
| Connection | `app/Kernel/Database/Connection.php` | PDO singleton lazy | Alto | — |
| VerticalProfile | `app/Kernel/Vertical/VerticalProfile.php` | Toggle módulos + labels menú | Medio | Solo filtra menú; uso parcial en controladores |
| BaseController | `app/Kernel/BaseClasses/BaseController.php` | Base presentación | Alto | — |
| ViewHelper | `app/Kernel/Helpers/ViewHelper.php` | Utilidades vista | Alto | — |
| LebytekUiConfig | `app/Kernel/Helpers/LebytekUiConfig.php` | Contrato UI admin | Alto | Tema/layout desde `cfg_configuraciones` |
| ModuleRegistry | `app/Application/Install/ModuleRegistry.php` | Lee manifiestos | Alto | Cache en memoria |
| Installer | `app/Application/Install/Installer.php` | Instala/actualiza módulos | Alto | CLI + wizard web |
| DeploymentStatus | `app/Application/Install/DeploymentStatus.php` | Estado deploy | Medio | `/admin/sistema/estado` |
| CrudResourceService | `app/Application/Services/CrudResourceService.php` | Orquestación CRUD Engine | Alto | Motor configurable |
| AdminNavigationMenuService | `app/Application/Services/AdminNavigationMenuService.php` | Menú desde BD + vertical | Alto | `core_menu_items` |
| RbacService / RbacPolicy | `app/Application/Services/`, `app/Domain/Policies/` | Autorización | Alto | Slugs `modulo.accion` |
| PdfRenderingService | `app/Application/Pdf/PdfRenderingService.php` | Render PDF | Alto | Dompdf |
| GenericCrudRepository | `app/Infrastructure/Repositories/GenericCrudRepository.php` | CRUD genérico PDO | Alto | Tablas `dom_*` |

---

## 5. Sistema de configuración

| Archivo / carpeta | Qué configura | Formato | Qué tan reusable es | Problemas detectados |
| ----------------- | ------------- | ------- | ------------------- | -------------------- |
| `.env` / `.env.example` | APP, DB, sesión, mail, registro | ENV | Alto | Secretos fuera de repo (correcto) |
| `config/app.php` | Nombre, env, debug, timezone, versión plataforma | PHP array | Alto | Versión fija `1.0.0` |
| `config/database.php` | Conexión MySQL | PHP | Alto | — |
| `config/vertical.php` | Módulos activos por deploy | PHP | Medio | No desregistra rutas/DI automáticamente |
| `config/container.php` | Bindings DI (~86) | PHP | Bajo por módulo | Monolítico; cada módulo nuevo exige edición manual |
| `config/modules/*.php` | Manifiesto: versión, deps, SQL, cruds | PHP | Alto | Rutas/permisos/menú en manifiesto a menudo vacíos |
| `config/cruds/*.json` | Recursos CRUD Engine | JSON | Alto | `clientes.json` sin tabla en schema actual |
| `config/calendars/*.json` | Vistas calendario | JSON | Alto | Solo 1 demo |
| `config/reportes/*.json` | Fuentes reportables | JSON | Alto | Depende de CRUDs existentes |
| `config/crud_handlers.php` | Handlers PHP whitelist | PHP | Medio | Claves string → FQCN |
| `config/dashboard.php` | Providers dashboard | PHP | Medio | Duplicado parcial vs manifiestos |
| `config/pdf.php`, `config/pdf_templates.php` | PDF engine + plantillas | PHP | Alto | — |
| `config/rbac_route_permissions.php` | Slugs en middleware | PHP | Bajo | Lista incompleta vs rutas reales (`reportes.*`, `pdf_kit.*` ausentes) |
| `config/menu.php` | Menú admin | PHP vacío | — | Obsoleto; catálogo en BD |
| `config/auth.php`, `config/session.php`, `config/mail.php`, `config/security.php` | Auth, sesión, correo, uploads | PHP | Alto | — |
| `database/seeds/015_core_menu_items.sql` | Ítems menú | SQL | Medio | Requiere migración/seed para nuevos módulos |
| `database/schema/modules/*.sql` | Bootstrap idempotente por módulo | SQL | Alto | Patrón consistente |

---

## 6. Módulos detectados

| Módulo | Ruta | Propósito | Estado | Dependencias | Qué falta |
| ------ | ---- | --------- | ------ | ------------ | --------- |
| **Core / Plataforma** | `/admin/*` (auth shell) | Auth, RBAC, menú, ajustes, bitácora | Terminado | — | Endurecer RBAC en algunas rutas (ver auditoría previa) |
| **Auth (login/registro/recuperación)** | `/login`, `/registro`, `/recuperar` | Autenticación pública | Terminado | core | API token-based: no confirmado |
| **Dashboard** | `/admin/dashboard` | Panel extensible por providers | Terminado | core | Providers no auto-registrados desde manifiesto |
| **Administración (usuarios/roles/permisos)** | `/admin/administracion/*` | Gestión RBAC | Terminado | core | Módulo sin manifiesto propio (embebido en core) |
| **Ajustes** | `/admin/ajustes` | `cfg_configuraciones`, tema UI | Terminado | core | Secciones extensibles por módulo: no implementado (plan marketing) |
| **Perfil / Avatares** | `/admin/perfil` | Perfil propio + uploads | Terminado | core | — |
| **CRUD Engine (demo)** | `/admin/crud/{resource}` | Motor CRUD JSON + showcase | Demo / Terminado | core | Recurso `clientes` huérfano; handlers demo acoplados a demo |
| **Calendario** | `/admin/calendario/{key}` | Vista calendario sobre CRUD | Terminado | core, crud-engine | Gate por `vertical.php` en rutas: no confirmado |
| **PDF Kit** | (servicio) + `/admin/pdf-kit/demo` | Render PDF + componentes | Terminado (lib) / Demo (UI) | core | Rutas demo; toggle vertical parcial |
| **Reportes** | `/admin/reportes` | Builder reportes → PDF | En proceso / Parcial | core, crud-engine, pdf-kit | Iteraciones documentadas; posibles gaps UX |
| **Sistema / Estado** | `/admin/sistema/estado` | Diagnóstico deploy | Terminado | core (Installer) | — |
| **PWA** | `/manifest.webmanifest`, `public/sw.js` | Manifest + service worker | Parcial | core | Alcance PWA completo: no confirmado |
| **API** | `/api/ping` | Health JSON | Parcial / Placeholder | core | CRUD API comentada en `routes/api.php` |
| **Marketing** | No confirmado | Landing, leads, contenido público | Pendiente | — | Solo plan en `docs/superpowers/plans/2026-06-23-modulo-marketing-cimientos.md` |
| **nuevo_modulo (WhatsApp SaaS)** | Fuera del framework | Prototipo comercial independiente | Legacy / Paralelo | Ninguna al core | No es módulo Lebytek; arquitectura PHP plana propia |

---

## 7. Módulos en proceso o incompletos

| Módulo | Evidencia encontrada | Qué abarca | Pendientes probables | Riesgo |
| ------ | -------------------- | ---------- | -------------------- | ------ |
| **Reportes** | Manifiesto `config/modules/reportes.php`; capas `Application/Reporte`, `Domain/Reporte`; tests en `tests/Reporte/`; vistas `admin/reportes/`; planes en `docs/superpowers/` | Builder, fuentes JSON, PDF vía pdf-kit, tabla `rep_reportes` | Pulido UX, catálogo demo, permiso `reportes.compartir` en UI | Medio |
| **API REST** | `routes/api.php` con rutas comentadas; solo `/api/ping` activo | Health check | Controladores API, auth token, CRUD JSON | Medio |
| **Marketing** | Plan 3000+ líneas; spec design; **sin** `config/modules/marketing.php` ni código | Cimientos desacoplables planificados | Implementación completa del plan | Bajo (aún no existe) |
| **PWA** | `PwaController`, `public/sw.js`, `APP_ASSET_VERSION` | Manifest básico | Offline strategy, cache policies | Bajo |
| **CRUD recurso `clientes`** | `config/cruds/clientes.json`; migración permisos; **sin** `dom_clientes` en schema activo | Ejemplo vertical | Tabla + menú + handler o eliminar config | Medio |
| **vertical-kit** | Carpeta raíz vacía | Kit reutilizable vertical | Contenido por definir | Bajo |
| **nuevo_modulo** | 177 archivos; README propio; `src/`, `views/` planas | SaaS WhatsApp demo | Integración o extracción a repo aparte | Alto (confusión arquitectónica) |

---

## 8. Relaciones entre módulos

| Módulo origen | Depende de | Tipo de dependencia | Riesgo |
| ------------- | ---------- | ------------------- | ------ |
| dashboard | core | Manifiesto `requiere: ['core']` | Bajo |
| crud-engine | core | Manifiesto + tablas `dom_demo_*` | Bajo |
| calendario | core, crud-engine | Manifiesto; lee JSON calendario + CRUD config | Medio (requiere recurso CRUD activo) |
| pdf-kit | core | Manifiesto; servicio DI | Bajo |
| reportes | core, crud-engine, pdf-kit | Manifiesto; fuentes desde CRUD + PDF | Medio |
| calendario → dashboard | calendario | `CalendarDashboardProvider` en `config/dashboard.php` | Bajo |
| reportes → pdf-kit | reportes | `GenerarDocumentoUseCase` usa `PdfRenderingService` | Bajo |
| reportes → crud-engine | reportes | `CrudReporteDataSource` | Medio |
| CRUD demo handlers | crud-engine | Clases en `Application/Crud/Handlers/Demo*` | Bajo (solo demo) |
| nuevo_modulo | — | Ninguna al framework | Nulo (aislado) |

---

## 9. Reglas y convenciones detectadas

**Archivos / clases**
- Clases PHP: `PascalCase.php` bajo namespace `App\{Capa}\...`
- Vistas/rutas: `snake_case.php`
- Métodos/variables: `camelCase`; constantes: `UPPER_SNAKE_CASE`

**Rutas**
- Web admin: prefijo `/admin`; CRUD genérico `/admin/crud/{resource}`
- API: prefijo `/api` (mínima)
- Permisos en middleware por ruta (`RbacMiddleware('slug')`)

**Vistas**
- Layout: `app/Presentation/Views/layouts/base.php`
- Admin: contrato LEBYTEK UI (`.ct-page`, `.ct-card`, partials reutilizables)
- PDF: partials en `partials/pdf/components/`

**Permisos**
- Formato slug: `modulo.accion` (ej. `reportes.generar`, `demo_clientes.ver`)
- Semillas en SQL; CRUD genera `{permission_prefix}.{ver|crear|editar|eliminar}`

**Menús**
- Catálogo en tabla `core_menu_items` (campo `permiso_slug`, `vertical_module`)
- Filtrado por RBAC + `VerticalProfile::filterMenuByModules()`

**CRUDs**
- JSON en `config/cruds/{key}.json`; tablas preferentemente `dom_*`
- Prohibidos en motor: prefijos `auth_*`, `cfg_*`, `core_*`, `log_*`
- Handlers solo vía whitelist `config/crud_handlers.php`

**Configuración**
- Manifiesto módulo: `config/modules/{clave}.php`
- Toggle deploy: `config/vertical.php` → `modules.{clave}`

**Base de datos**
- Prefijos: `auth_`, `cfg_`, `log_`, `core_`, `dom_`, `rep_`, `int_`, `tmp_`, `sys_`
- PK `id`; FK `{tabla}_id`; auditoría soft-delete estándar en CRUD

**Assets**
- Globales en `public/assets/css|js/`; sin carpeta por módulo
- Versionado cache: `APP_ASSET_VERSION`

---

## 10. Problemas de desacoplamiento

| Problema | Evidencia | Impacto | Recomendación |
| -------- | --------- | ------- | ------------- |
| Rutas centralizadas | Todo en `routes/web.php` (~134 líneas) | Nuevo módulo = editar archivo global | Rutas declarativas por manifiesto o `routes/{modulo}.php` condicional |
| DI monolítica | `config/container.php` registra todos los módulos | Acoplamiento de arranque | Registro condicional por toggle/manifiesto |
| Toggle vertical incompleto | `VerticalProfile` solo en menú + 2 controladores | Rutas activas con módulo “apagado” | Gate uniforme en router o middleware por módulo |
| Handlers demo en Application | `Application/Crud/Handlers/Demo*` | Lógica demo mezclada con plataforma | Mover a paquete demo o módulo crud-engine |
| `nuevo_modulo/` en raíz | App PHP plana con vendor propio | Confunde límites del framework | Extraer a repo aparte o convertir en vertical Lebytek |
| Providers dashboard duplicados | `config/dashboard.php` + manifiesto calendario | Dos fuentes de verdad | Unificar registro vía manifiesto |
| Config CRUD huérfana | `clientes.json` sin tabla | Error en runtime si se usa | Crear vertical o retirar JSON/permisos |
| API acoplada a AuthMiddleware sesión | `routes/api.php` | No apta para clientes externos | Capa auth API separada |

---

## 11. Problemas de configurabilidad

| Elemento hardcodeado | Ubicación | Por qué afecta | Cómo volverlo configurable |
| -------------------- | --------- | -------------- | -------------------------- |
| Rutas admin por módulo | `routes/web.php` | No escala con N módulos | `routes/*.php` + include condicional desde manifiesto |
| Bindings DI | `config/container.php` | Cada módulo toca core config | Provider/bootstrappers por módulo |
| Dashboard providers | `config/dashboard.php` | Desincronía con manifiestos | Campo `providers` en manifiesto → auto-merge |
| Lista RBAC rutas | `config/rbac_route_permissions.php` | Documentación desactualizada | Generar desde rutas o ampliar lista |
| Menú nuevo módulo | SQL seeds/migrations | Requiere SQL manual | API de registro en Installer desde manifiesto `menu` |
| Assets por módulo | Solo `public/assets/` global | Colisiones en verticales grandes | Convención `public/assets/modules/{clave}/` |
| Secciones Ajustes | `AjustesController` + vistas | Módulos no pueden extender settings | `SettingsSectionProvider` (planificado en marketing) |

---

## 12. Compatibilidad con módulos futuros

| Criterio | Estado actual | Riesgo | Recomendación |
| -------- | ------------- | ------ | ------------- |
| Manifiesto de módulo | **Sí** — `config/modules/*.php` (6 módulos) | Bajo | Completar campos `permisos`, `menu`, `providers` |
| Versionado de módulo | **Sí** — campo `version` + tablas `cfg_modulos`, `cfg_migraciones` | Bajo | Semver estricto en releases |
| Instalador de módulo | **Sí** — `scripts/install.php`, wizard `public/install/` | Bajo | Automatizar registro menú/permisos desde manifiesto |
| Migraciones | **Sí** — por manifiesto + test integridad | Medio | Evitar archivos huérfanos (test existente) |
| Menú dinámico | **Sí** — BD `core_menu_items` + RBAC | Medio | Declaración en manifiesto aún manual vía SQL |
| Permisos declarativos | **Parcial** — SQL bootstrap; manifiesto a menudo `[]` | Medio | Poblar `permisos` en manifiesto y aplicar en Installer |
| Rutas declarativas | **No** — centralizadas | Alto | Patrón `routes/marketing.php` del plan pendiente |
| Assets por módulo | **No** | Medio | Convención documentada + carpeta |
| Configuración JSON | **Sí** — CRUD, calendario, reportes | Bajo | Extender a más superficies |
| Dependencias entre módulos | **Sí** — `requiere` + `DependencyResolver` | Bajo | — |
| Compatibilidad con verticales | **Parcial** — `vertical.php` + docs onboarding | Medio | Completar desacople rutas/DI; kit vertical vacío |

---

## 13. Hallazgos críticos

1. **Hallazgo:** Rutas y DI no se registran automáticamente desde manifiestos de módulo.  
   **Evidencia:** `routes/web.php` lista manualmente calendario, pdf-kit, reportes; `config/container.php` ~86 bindings globales.  
   **Impacto:** Cada módulo nuevo modifica archivos core compartidos → riesgo de regresiones.  
   **Prioridad:** Alta

2. **Hallazgo:** Toggle `config/vertical.php` no desactiva rutas de módulos opcionales de forma uniforme.  
   **Evidencia:** `VerticalProfile::moduleEnabled` usado en menú, `ReportesController`, `PdfKitDemoController`; calendario/CRUD sin gate equivalente.  
   **Impacto:** Deploy “sin calendario” puede seguir exponiendo URLs.  
   **Prioridad:** Media

3. **Hallazgo:** Prototipo `nuevo_modulo/` convive en raíz con arquitectura incompatible.  
   **Evidencia:** README propio, `src/*.php` plano, sin capas Onion ni manifiesto.  
   **Impacto:** Confusión para verticales y auditorías; posible deuda si se mezcla con core.  
   **Prioridad:** Media

4. **Hallazgo:** Recurso CRUD `clientes` configurado sin tabla en schema activo.  
   **Evidencia:** `config/cruds/clientes.json` → `dom_clientes`; tabla ausente en `database/schema/`; sí en `drop_legacy_domain_tables.sql`.  
   **Impacto:** Fallo al activar recurso; permisos en migración sin datos.  
   **Prioridad:** Media

5. **Hallazgo:** API prácticamente vacía.  
   **Evidencia:** `routes/api.php` — controladores comentados; solo `/api/ping`.  
   **Impacto:** Integraciones externas requieren trabajo desde cero.  
   **Prioridad:** Media

6. **Hallazgo:** Manifiestos declaran `permisos`/`menu` vacíos en módulos recientes.  
   **Evidencia:** `reportes.php`, `calendario.php`, `pdf-kit.php` → arrays vacíos; permisos en SQL bootstrap.  
   **Impacto:** Dos fuentes de verdad (manifiesto vs SQL).  
   **Prioridad:** Media

7. **Hallazgo:** Módulo Marketing planificado pero no implementado.  
   **Evidencia:** Plan en `docs/superpowers/plans/2026-06-23-modulo-marketing-cimientos.md`; sin `config/modules/marketing.php`.  
   **Impacto:** Expectativa de vertical pública sin código.  
   **Prioridad:** Baja

8. **Hallazgo:** Suite de tests propia (microtest) coexiste con PHPUnit.  
   **Evidencia:** `tests/run.php` vs `phpunit.xml.dist`; CLAUDE.md menciona phpunit.  
   **Impacto:** Dos comandos de test; posible inconsistencia CI.  
   **Prioridad:** Baja

9. **Hallazgo:** `config/rbac_route_permissions.php` desactualizado respecto a rutas actuales.  
   **Evidencia:** No incluye slugs `reportes.*`, `pdf_kit.ver`, `sistema.ver`.  
   **Impacto:** Documentación/checks de integridad incompletos.  
   **Prioridad:** Baja

10. **Hallazgo:** Carpeta `vertical-kit/` vacía.  
    **Evidencia:** Directorio en raíz sin archivos en glob.  
    **Impacto:** Promesa de kit vertical sin entregable.  
    **Prioridad:** Baja

---

## 14. Recomendaciones prioritarias

| Prioridad | Acción recomendada | Beneficio | Esfuerzo |
| --------- | ------------------ | --------- | -------- |
| Alta | Auto-registro de rutas y bindings desde manifiesto (patrón del plan marketing) | Verticales plug-in sin tocar core | Alto |
| Alta | Middleware/gate uniforme por `vertical.modules` en router | Deploys configurables coherentes | Medio |
| Media | Resolver `clientes.json` (tabla vertical o retirar config) | Evitar recurso roto | Bajo |
| Media | Poblar manifiestos con `permisos`/`menu` y aplicar en Installer | Una sola fuente de verdad | Medio |
| Media | Aislar o eliminar `nuevo_modulo/` del repo framework | Claridad arquitectónica | Bajo–Medio |
| Media | Completar capa API mínima (auth + CRUD read) | Integraciones | Alto |
| Baja | Unificar dashboard providers vía manifiesto | Menos duplicación | Bajo |
| Baja | Documentar/llenar `vertical-kit/` o remover carpeta | Expectativas claras | Bajo |
| Baja | Sincronizar `rbac_route_permissions.php` con rutas | Mantenimiento RBAC | Bajo |

---

## 15. Mapa mental técnico del proyecto

```
Lebytek Framework
├── Core (obligatorio)
│   ├── Kernel — Bootstrap, Router, Container, Session, Config, VerticalProfile
│   ├── Auth — login, registro, recuperación, tokens, rate limit
│   ├── RBAC — usuarios, roles, permisos, RbacMiddleware
│   ├── Ajustes — cfg_configuraciones, tema LEBYTEK UI
│   ├── Menú — core_menu_items + AdminNavigationMenuService
│   ├── Perfil / Avatares — uploads, ArchivoRepository
│   └── Install — ModuleRegistry, Installer, DeploymentStatus, wizard public/install
├── Configuración
│   ├── config/app.php, database.php, auth.php, session.php, mail.php
│   ├── config/vertical.php — toggles por deploy
│   ├── config/container.php — DI global
│   ├── config/modules/*.php — manifiestos (6)
│   ├── config/cruds/*.json — CRUD Engine
│   ├── config/calendars/*.json
│   └── config/reportes/*.json
├── Módulos plataforma
│   ├── dashboard — providers KPI/actividad
│   ├── crud-engine (demo) — GenericCrudRepository + handlers demo
│   ├── calendario — lectura sobre CRUD + widget dashboard
│   ├── pdf-kit — DompdfRenderer + componentes Pdf*
│   ├── reportes — builder + rep_reportes + PDF
│   ├── sistema/estado — diagnóstico
│   └── pwa — manifest + sw.js (parcial)
├── Base de datos
│   ├── Plataforma — auth_*, cfg_*, log_*, core_*
│   ├── Demo CRUD — dom_demo_clientes, dom_demo_productos, dom_demo_pedidos, dom_demo_categorias, dom_demo_citas
│   ├── Reportes — rep_reportes
│   └── Versionado — cfg_migraciones, cfg_modulos
├── Externo / no integrado
│   ├── nuevo_modulo/ — SaaS WhatsApp (PHP plano)
│   └── vertical-kit/ — vacío
└── Pendientes
    ├── Módulo Marketing — solo plan/spec
    ├── API REST completa
    ├── Registro declarativo rutas/DI por módulo
    ├── vertical-kit contenido
    └── Recurso clientes (dom_clientes) o limpieza config
```

---

## 16. Conclusión técnica

Lebytek es un **framework PHP propio maduro en arquitectura** (MVC + Onion, capas claras, documentación extensa) con **núcleo de plataforma operativo**: auth, RBAC, menú dinámico, ajustes, dashboard y motor de instalación versionado. La **modularidad es parcial**: existen manifiestos, dependencias resueltas e instalador, pero rutas, DI y parte del menú siguen **centralizados y manuales**, lo que limita el desacople plug-in. La **configurabilidad es fuerte** en CRUD, calendario y reportes vía JSON; más débil en registro automático de superficies HTTP. Lo prioritario es **unificar registro de módulos** (rutas, permisos, DI, vertical gate) y **limpiar artefactos paralelos** (`nuevo_modulo`, configs huérfanas). Es **viable como base para verticales** si se sigue `docs/modules/uso-de-modulo-dominio.md` y se completan los cimientos modulares ya diseñados (plan marketing como referencia). Estado global estimado: **plataforma ~85%**, **ecosistema modular ~55%**, **listo para verticales CRUD-centric con trabajo adicional en desacople**.
