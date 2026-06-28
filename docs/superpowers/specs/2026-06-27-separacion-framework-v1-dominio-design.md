# SPEC — Separación Framework v1.0 / Dominio Lebytek (paquete Composer + esqueleto)

> Estado: **diseño aprobado, sin implementar**. Fecha: 2026-06-27.
> Tipo: reestructuración estratégica del repositorio. Convierte el monolito actual (app + framework mezclados bajo `App\`) en tres artefactos: paquete Composer, esqueleto de aplicación y proyecto de producto.
> Objetivo del usuario: congelar una **Framework v1.0** limpia y reutilizable en su propio repo, y continuar el trabajo de dominio (los servicios comerciales de Lebytek) en un repo separado que la consume.

---

## 1. Resumen ejecutivo

Hoy `C:\Users\User\OneDrive\Desktop\sistemas\contraste` (que trackea `https://github.com/Parzival2103/Lebytek_Framework`, con un VPS que hace auto-pull de `main`) contiene **framework genérico y dominio/demos Lebytek mezclados** bajo un único namespace `App\` con un autoloader PSR-4 hecho a mano.

Este SPEC separa eso en **tres artefactos**, siguiendo el patrón estándar framework + skeleton (como `symfony/framework` + `symfony/skeleton`, o `laravel/framework` + `laravel/laravel`):

1. `**lebytek/framework`** — paquete Composer puro. Namespace `Lebytek\Framework\` → `src/`. Solo código genérico. No ejecutable por sí solo. Se taggea **v1.0.0** en GitHub. Reutilizable por N proyectos.
2. `**lebytek/skeleton`** — plantilla de aplicación ejecutable (entry point, config, rutas, `.env.example`) extraída del repo actual. Semilla de Repo 2 y de cada proyecto "a la medida".
3. `**lebytek/producto**` (Repo 2, nuevo) — esqueleto instanciado + `composer require lebytek/framework` + el **dominio Lebytek** (`App\`, tablas `dom_*`). Corre en el VPS.

Regla rectora intacta (arquitectura Onion): el dominio depende del framework hacia adentro; **el vendor jamás referencia `App\`**.

---

## 2. Decisiones confirmadas (brainstorming 2026-06-27)


| #   | Decisión                                                                                                                                                                                                                                            |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Mecanismo de consumo: **paquete Composer** (`lebytek/framework`), no fork ni subtree.                                                                                                                                                               |
| 2   | Namespaces: **framework → `Lebytek\Framework\`**, **dominio → `App\`** (convención Laravel/Symfony).                                                                                                                                                |
| 3   | **Un solo paquete** `lebytek/framework` que agrupa core + módulos genéricos (Calendar, PDF, Dashboard, Reportes, Integrations genérico), togglables vía `vertical.php`. No micro-paquetes por módulo.                                               |
| 4   | Modelo de **tres artefactos**: paquete / esqueleto / producto.                                                                                                                                                                                      |
| 5   | Limpieza del repo actual: los archivos app-level **no se borran**, se **mueven al esqueleto** para reintroducirlos en Repo 2 tras montar `composer require` en el VPS.                                                                              |
| 6   | Topología: **Repo 1 = paquete puro** (este repo, limpiado y taggeado v1.0); **Repo 2 = nuevo, ejecutable, corre en el VPS**. El auto-pull del VPS pasa a Repo 2.                                                                                    |
| 7   | **Demos/showcase** (`demo_clientes`, CRUD showcase, `marketing_demo`) → al **esqueleto**, **toggled OFF** por defecto.                                                                                                                              |
| 8   | Punto de extensión: **manifiestos de módulo + ServiceProvider** (se promueve el campo `providers` hoy vacío). Mínimo viable v1.0: **un entrypoint único** `FrameworkServiceProvider::register()`; promoción módulo-por-módulo después, incremental. |
| 9   | El plan de implementación debe crear un **archivo de constancia** que liste los módulos pendientes de promover al modelo de provider por módulo (listarlos, no implementarlos).                                                                     |


---

## 3. Estado verificado del punto de partida (2026-06-27)

- `389` archivos PHP en `/app`: Kernel 27 · Domain 91 · Application 118 · Infrastructure 41 · Presentation 112.
- **~94% es framework genérico.** Lo único claramente dominio/demo hoy es el vertical **Marketing** (~23 archivos) + configs `demo_*.json` + demos de integraciones. El dominio comercial (los 4 servicios) es **greenfield**.
- Autoloader PSR-4 hecho a mano (`app/Kernel/Autoloader.php`), namespace único `App\` → `/app`. Pensado originalmente para hosting compartido sin Composer.
- `composer.json` actual (`lebytek/app`) solo declara dependencias (dompdf, phpmailer), **sin `autoload`**.
- `config/container.php`: archivo central con 100+ bindings, todos `App\`.
- Sistema de manifiestos de módulo ya existe (`config/modules/*.php`) con campo `providers` **vacío** — punto de extensión latente.
- Entry point: `public/index.php` → `app/Kernel/Bootstrap.php`.

---

## 4. Arquitectura de tres artefactos

```
┌─────────────────────────────────────────────────────────────┐
│  Repo 1: lebytek/framework   (ESTE repo, limpiado)           │
│  • Paquete Composer puro. Namespace Lebytek\Framework\ → src/ │
│  • Solo genérico: Kernel, CRUD Engine, auth/RBAC, dashboard, │
│    calendario, pdf, reportes, integrations genérico          │
│  • SIN public/, SIN .env, SIN dom_*. No ejecutable solo.     │
│  • Tag v1.0.0 → GitHub (privado). Reutilizable por N proyectos│
└─────────────────────────────────────────────────────────────┘
            │ composer require lebytek/framework
            ▼
┌─────────────────────────────────────────────────────────────┐
│  ESQUELETO: lebytek/skeleton  (extraído de ESTE repo)        │
│  • App ejecutable: public/index.php, config/ plantilla,      │
│    routes/, .env.example, bootstrap del proyecto             │
│  • Lo que "se mueve a un esqueleto" en vez de borrar         │
│  • Semilla de Repo 2 y de cada proyecto "a la medida"        │
│  • Demos incluidos pero toggled OFF por defecto              │
└─────────────────────────────────────────────────────────────┘
            │ se instancia + se le agrega el dominio
            ▼
┌─────────────────────────────────────────────────────────────┐
│  Repo 2: lebytek/producto  (NUEVO, corre en el VPS)          │
│  • Esqueleto instanciado + require lebytek/framework         │
│  • Namespace App\ → app/ : el DOMINIO Lebytek (dom_*)        │
│  • Los 4 servicios (API, salones, a la medida, sitio)        │
│  • El VPS auto-pull pasa a apuntar AQUÍ                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Dónde cae cada archivo

### 5.1 Paquete `lebytek/framework` (`Lebytek\Framework\` → `src/`)


| Origen actual                                                                                              | Destino en paquete                | Nota                                                                                                                                         |
| ---------------------------------------------------------------------------------------------------------- | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Kernel/**`                                                                                            | `src/Kernel/**`                   | Bootstrap, Router, Container, EnvLoader, Session. `Autoloader.php` **se elimina** (lo reemplaza autoload de Composer)                        |
| `app/Domain/`** genérico                                                                                   | `src/Domain/**`                   | Entities (Usuario/Rol/Permiso/AuthToken), Interfaces, ValueObjects, Policies, Rules — **menos** `Domain/Marketing`                           |
| `app/Application/`** genérico                                                                              | `src/Application/**`              | Crud, Services, UseCases, DTO, Validators, Mappers — **menos** `Application/Marketing`                                                       |
| `app/Infrastructure/`** genérico                                                                           | `src/Infrastructure/**`           | Database, Repositories genéricos, Mail, Logging, Storage, Cache, Integrations genérico — **menos** `Infrastructure/Marketing`                |
| `app/Presentation/`** genérico                                                                             | `src/Presentation/**`             | Controllers Admin/Auth genéricos, Middlewares, Presenters, Requests, Responses, Views de framework (auth, errors, layouts, partials, emails) |
| `database/schema/schema.sql` + `schema/modules/{calendario,crud-engine,integrations,pdf-kit,reportes}.sql` | `database/schema/**` del paquete  | Baseline + módulos genéricos                                                                                                                 |
| `database/seeds/0**` (auth, menu, roles, cfg)                                                              | `database/seeds/**` del paquete   | Seeds core                                                                                                                                   |
| `config/modules/{core,crud-engine,dashboard,calendario,pdf-kit,reportes,integrations}.php`                 | manifiestos genéricos del paquete | Marketing NO                                                                                                                                 |
| `scripts/{install,migrate,seed,status,rbac_integrity_report,crear_usuario}.php`                            | `bin/`/`scripts/` del paquete     | Herramientas CLI del framework                                                                                                               |


### 5.2 Esqueleto `lebytek/skeleton` (semilla de Repo 2)


| Origen actual                                                                                                                                                     | Destino esqueleto                                | Nota                                                           |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ | -------------------------------------------------------------- |
| `public/index.php`, `public/assets`, `sw.js`, `install/`                                                                                                          | `public/**`                                      | Entry point — **propiedad del proyecto, no del paquete**       |
| `config/*.php` (app, database, mail, session, security, container, dashboard, menu, settings_sections, crud_handlers, rbac_route_permissions, pdf, integrations…) | `config/`** plantilla                            | El proyecto los edita; `container.php` se parte (§6)           |
| `routes/{web,api}.php`                                                                                                                                            | `routes/**`                                      | Rutas del proyecto; framework registra las suyas vía extensión |
| `.env.example`, `composer.json` del proyecto (con `require lebytek/framework`)                                                                                    | raíz esqueleto                                   |                                                                |
| `storage/` (estructura vacía)                                                                                                                                     | `storage/`                                       |                                                                |
| `tests/bootstrap.php`, `tests/HarnessSelfTest.php`, arnés base                                                                                                    | `tests/**`                                       | Tests de dominio se agregan en Repo 2                          |
| Demos: `config/cruds/demo_*.json`, `schema/modules/crud-engine.sql` (datos demo), `marketing_demo.sql`                                                            | esqueleto, **OFF** por defecto en `vertical.php` | Showcase opcional                                              |


### 5.3 Repo 2 `lebytek/producto` (dominio `App\`)


| Origen actual                                                                                                        | Destino Repo 2                              | Nota                                           |
| -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- | ---------------------------------------------- |
| `app/{Domain,Application,Infrastructure,Presentation}/Marketing`                                                     | `app/...` (`App\` dominio)                  | Marketing **es dominio Lebytek**, no framework |
| `config/cruds/mkt_*.json` + `config/modules/marketing.php` + `routes/marketing.php` + `schema/modules/marketing.sql` | `config/`, `routes/`, `database/` de Repo 2 |                                                |
| **Los 4 servicios nuevos** (API product, salones, etc.)                                                              | `app/...` dom_* nuevos                      | Greenfield, basados en `int_accounts`          |


### 5.4 Carpetas a archivar/descartar (no entran a ningún artefacto)

`auditoria/`, `nuevo_modulo/`, `plan/`, `vertical-kit/`, `database/migrations_legacy/`, `database/seeds_legacy/`, `vendor/` (se regenera).

---

## 6. Mecanismo de extensión (cómo el dominio se enchufa sin tocar el vendor)

Hoy `config/container.php` (100+ bindings), `routes/web.php`, `menu.php`, `dashboard.php`, `settings_sections.php`, `crud_handlers.php` **mezclan framework + dominio** en archivos centrales. Una vez que el framework es vendor, el proyecto **no puede editar esos archivos dentro del paquete**.

### 6.1 Modelo objetivo — un ServiceProvider por módulo

Se promueve el sistema de manifiestos existente (`config/modules/*.php`, campo `providers` hoy vacío) al punto de extensión real:

```
Lebytek\Framework\Kernel\Bootstrap
   │
   ├─ descubre manifiestos del PAQUETE  (core, crud-engine, calendario, pdf, reportes, integrations)
   │     cada uno expone un Provider que registra:
   │        • bindings de container   • rutas
   │        • items de menú            • dashboard contributions
   │        • settings sections        • crud handlers
   │
   └─ descubre manifiestos del PROYECTO (config/modules/*.php en Repo 2)
         marketing, + los 4 servicios dom_*  → mismos hooks
```

### 6.2 Traducción de cada archivo central


| Archivo central hoy                                                       | Después                                                                                                                                                                                                            |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `config/container.php` (mezcla)                                           | Paquete: `FrameworkServiceProvider` registra bindings genéricos (`Lebytek\Framework\`). Proyecto: `config/container.php` queda **delgado** — llama al registro del framework y agrega bindings de dominio (`App\`) |
| `routes/web.php` (mezcla)                                                 | Framework registra sus rutas vía sus providers; `routes/web.php` del proyecto solo agrega rutas de dominio. `routes/marketing.php`, `integrations.php` pasan a declararse por sus manifiestos                      |
| `menu.php`, `dashboard.php`, `settings_sections.php`, `crud_handlers.php` | Cada módulo aporta sus entradas vía su Provider; los archivos del proyecto solo listan/togglean, no enumeran clases del vendor                                                                                     |


### 6.3 Mínimo viable para v1.0 (no refactorizar todo de golpe)

1. Framework expone **un** entrypoint de registro `FrameworkServiceProvider::register($container)` con todos los bindings genéricos.
2. El proyecto tiene un `container.php` delgado que llama a ese entrypoint y añade dominio.
3. La promoción módulo-por-módulo a `providers` se hace incremental **después**.

### 6.4 Requisito de constancia (obligatorio en el plan)

El plan de implementación **debe** crear un archivo de constancia, p. ej.:

```
docs/superpowers/PENDIENTE-promocion-modulos-providers.md
```

que liste **qué módulos aún faltan promover** al modelo de provider por módulo (núcleo: core, crud-engine, dashboard, calendario, pdf-kit, reportes, integrations, y los de dominio cuando existan). **Listarlos, no implementarlos** en este ciclo. El archivo es la fuente de verdad del trabajo incremental pendiente.

---

## 7. Visión del dominio (alto nivel; cada servicio tendrá su propio spec)

Los 4 servicios como verticales `dom_`* en Repo 2, todos apoyados en `int_accounts` (framework: instancia Green API + token cifrado). **El dominio agrega la capa comercial encima:**

```
FRAMEWORK (int_accounts)              →  DOMINIO Lebytek (dom_*) agrega encima
instancia Green API + token cifrado       quién la posee, qué plan, API key, uso
```


| Servicio                       | Vertical dom_*                                                                                                                              | Se apoya en                               |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| **A. Sitio comercial Lebytek** | `marketing` (ya existe) presenta los 3 servicios + embudo de leads                                                                          | módulo marketing                          |
| **B. Producto "API sola"**     | `dom_apiwa_`*: clientes, **API keys**, planes, medición de uso, endpoints REST públicos que autentican al cliente y reenvían a SU instancia | `int_accounts` + `NotificationDispatcher` |
| **C. Vertical salones/citas**  | `dom_salon_`*: salones, citas recurrentes, comunicación WhatsApp constante con clientes                                                     | calendario + integrations + int_accounts  |
| **D. Software a la medida**    | embudo de leads + **el esqueleto reutilizable** (cada proyecto = nueva instancia del esqueleto)                                             | skeleton + paquete                        |


Regla de separación respetada: el framework provee la plomería de instancias; el dominio envuelve lo comercial. **Ningún `dom_`* toca Green API directo** — todo pasa por la fachada `NotificationDispatcher`. `int_accounts.lead_id`/owner es referencia blanda (sin FK dura a `dom_`*).

> Cada uno de A–D es un ciclo spec → plan → implementación **posterior**. Este SPEC solo deja la base (separación + repos) lista.

---

## 8. Secuencia de migración (orden de menor riesgo) + VPS

```
0. RESPALDO   → tag/branch del main actual (pre-split-backup) ANTES de tocar nada
1. CARVE      → rename App\→Lebytek\Framework\ en src/; mover app-level al esqueleto;
                composer.json del paquete (PSR-4 autoload Lebytek\Framework\ → src/);
                borrar Autoloader.php manual; partir container.php (FrameworkServiceProvider)
2. VERIFICAR  → arnés de tests (php tests/run.php) verde en el paquete + instancia del esqueleto
3. PUBLICAR   → este repo = lebytek/framework, tag v1.0.0 en GitHub (privado)
4. REPO 2     → nuevo desde esqueleto + composer require lebytek/framework
                (vía repositorio VCS de Composer con deploy key, repo privado) + migrar Marketing/demos
5. VPS        → el auto-pull pasa a Repo 2; el deploy incluye `composer install`
6. DOMINIO    → recién aquí arrancan los 4 servicios, cada uno su propio spec→plan
```

### 8.1 Consumo de paquete privado por Composer

Como `lebytek/framework` es repo privado, `composer.json` de Repo 2 debe declarar un `repositories` de tipo `vcs` apuntando al repo de GitHub, y el VPS necesita auth (deploy key SSH o token). Documentar en el README de Repo 2 y en `.env.example`/deploy.

---

## 9. Riesgos


| Riesgo                                                           | Mitigación                                                                                             |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Rename masivo (~350 archivos) rompe referencias                  | Mecánico + scriptable; arnés de tests como red; respaldo previo (paso 0)                               |
| Paquete privado → Composer en VPS necesita auth                  | Repo VCS privado en `composer.json` + deploy key/token en VPS (§8.1)                                   |
| Constraint viejo "shared hosting sin Composer"                   | Se **abandona**: VPS tiene Composer (dompdf ya instalado vía composer). Documentar el cambio en README |
| `container.php` circular o binding de dominio filtrado al vendor | Vendor jamás referencia `App\`; revisión + regla explícita en el plan                                  |
| Acoplamiento dominio↔`int_accounts` mal trazado                  | `lead_id`/owner como referencia blanda; el dominio lee, el framework no conoce `dom_*`                 |
| Promoción por módulo queda a medias / se olvida                  | **Archivo de constancia** obligatorio (§6.4) listando lo pendiente                                     |
| Vistas/paths que asumen `ROOT_PATH/app`                          | Revisar referencias a `APP_PATH`, rutas de vistas y assets al mover al esqueleto                       |
| Demos encendidos por error en producción                         | `vertical.php` los deja **OFF**; bootstrap SQL idempotente; documentado                                |


---

## 10. Criterios de aceptación (de esta separación)

1. Existe un paquete `lebytek/framework` con namespace `Lebytek\Framework\` → `src/`, autoload por Composer, **sin** `public/`, **sin** `.env`, **sin** código `App\`/`dom_*`.
2. El paquete **no referencia `App\`** en ningún archivo.
3. Existe un esqueleto ejecutable que, tras `composer install` + `require lebytek/framework`, **arranca** y pasa el arnés de tests (`php tests/run.php`).
4. El repo actual queda como Repo 1 (paquete), taggeado **v1.0.0** en GitHub; el main previo quedó respaldado en un tag/branch antes del carve.
5. Repo 2 (nuevo) instancia el esqueleto, hace `composer require lebytek/framework`, **migra Marketing + demos**, y corre en el VPS vía auto-pull con `composer install`.
6. El dominio (`App\`, `dom_`*) se registra **solo** vía el mecanismo de extensión (entrypoint del framework + manifiestos del proyecto), sin editar archivos del vendor.
7. Existe el **archivo de constancia** (§6.4) con la lista de módulos pendientes de promover a provider por módulo.
8. Los demos quedan en el esqueleto **OFF** por defecto; encenderlos no rompe el arranque.
9. El framework sigue siendo togglable por módulo vía `vertical.php`; el bootstrap SQL del framework es idempotente.

---

## 11. Fuera de alcance (de este ciclo)

- Implementar los 4 servicios de dominio (A–D): cada uno su propio spec → plan → implementación posterior.
- Promover los módulos uno por uno al modelo de provider (solo se **lista** el pendiente en el archivo de constancia).
- Webhooks reales, cola/reintentos, plantillas en DB, recordatorios (siguen diferidos según los SPEC de `integrations`).
- Publicar el paquete en Packagist (se mantiene privado vía VCS).

---

## Apéndice A — Mapa de reuso (qué ya existe y se conserva)


| Pieza                                                             | Estado                                                     |
| ----------------------------------------------------------------- | ---------------------------------------------------------- |
| Sistema de manifiestos de módulo (`config/modules/*.php`)         | Existe; se promueve `providers` como hook de extensión     |
| `int_accounts` + `NotificationDispatcher` + canales               | Existe (integrations Fase 1/2); base del dominio comercial |
| Arnés de tests (`php tests/run.php`, `tests/HarnessSelfTest.php`) | Existe; red de seguridad del rename                        |
| `config/vertical.php` (toggles de módulo)                         | Existe; gobierna demos OFF y módulos genéricos             |
| Patrón módulo opcional (manifiesto + schema/modules + toggle)     | Existe; lo heredan paquete y dominio                       |


