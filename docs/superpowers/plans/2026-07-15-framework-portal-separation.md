# Framework ↔ Portal Separation Implementation Plan

> **SUPERSEDED — NO EJECUTAR MONOLÍTICAMENTE**
>
> Este plan queda como **referencia histórica** (deuda D1–D11, file map, anti-patrones).
> **Ejecución aprobada:** spec `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` y planes incrementales **`2026-07-17-fps-00` … `2026-07-17-fps-08`**.
> No fusionar tasks de este documento en una sola sesión; seguir el roadmap en orden `00 → 08`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separar el monorepo actual en dos repos — `Lebytek_Framework` (paquete Composer `lebytek/framework` puro) y `Lebytek_Portal` (tenant empresa) — con un `skeleton/` mínimo desde el cual construir otros sistemas, pagando la deuda que haría fallar Composer, Installer/SQL, assets y bootstrap tras el split.

**Architecture:** El carve de namespaces (`src/` = `Lebytek\Framework\`, `app/` = `App\`) ya existe. Este plan fija el **modelo de coexistencia**: la app posee `ROOT_PATH`; el framework entra solo por Composer en `vendor/lebytek/framework`. SQL/datos de plataforma se resuelven **paquete primero** (`PackagePaths::resolveDataFile`), negocio solo en el consumidor. Assets de UI de plataforma se **copian** al `public/assets/` del consumidor (sin plugin de publish en este ciclo). Tras el carve, la raíz del repo Framework **deja de ser un sitio desplegable**: es fuente del paquete + harness de tests.

**Tech Stack:** PHP 8.1+, Composer (PSR-4, path repo local / VCS+tag remoto), arnés `php tests/run.php` (microtest), Git/GitHub (`gh`), Windows PowerShell + paths absolutos bajo `c:\Users\User\OneDrive\Desktop\sistemas\`.

**Spec:** `docs/superpowers/specs/2026-07-15-framework-portal-separation-design.md`

**Precedente (ya hecho — no rehacer):** `docs/superpowers/plans/2026-06-27-separacion-framework-v1-dominio.md` (move a `src/`, `Bootstrap::run()`, cascada de vistas).

## Global Constraints

- Naming: Framework = plataforma `lebytek/framework`; Portal = producto/tenant `Lebytek_Portal`; otros clientes = apps nuevas desde `skeleton/`.
- El paquete `lebytek/framework` **no** debe autoload-ear `App\\` ni contener código de marketing/LebytekApi como dependencia requerida.
- Marketing permanece **dentro del Portal** hasta que un 2º tenant pida un módulo opcional (YAGNI — no extraer `lebytek/module-marketing` ahora).
- El **skeleton no es un fork del Portal**: no debe incluir `App\Domain\Marketing`, rutas/vistas públicas Lebytek, schema `marketing*.sql`, configs `mkt_*`, ni bindings Marketing en `container.php`.
- SQL de plataforma: lectura vía `PackagePaths` (paquete primero). **Prohibido** copiar `schema.sql` / modules de plataforma al Portal como SoT.
- Assets de UI de plataforma: viven en `public/assets/` del **consumidor** (URL `/assets/...`); el paquete no los sirve por HTTP. Skeleton/Portal deben shippear la lista canónica; sync documentado hasta un publish script futuro.
- **Prohibido sin orden explícita del usuario:** merge `feature/backoffice-api-integration` → `main`; deploy/SSH/`git push` a VPS; `composer update` en producción.
- Comando de tests: `php tests/run.php` (filtros `Marketing` / `Kernel` / `PackageAutoloadBoundary` / `PackagePaths` / `SkeletonPurity` / `PlatformSqlResolve`). Línea final: `N passed, M failed` con `M=0`.
- Local sibling canónico del Portal: `c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal`.
- Tag existente `v1.0.0` en Framework: no sobreescribir; nuevos bumps semver solo cuando el usuario lo pida.
- Views: `ViewHelper` resuelve proyecto → paquete; no romper esa cascada.
- Nunca editar `vendor/` en el Portal ni en un tenant; cambios de plataforma → repo Framework → `composer update lebytek/framework`.

---

## Modelo correcto de separación (leer antes de implementar)

Este bloque es la SoT conceptual del plan. Si un paso lo contradice, el paso está mal.

### Tres roles distintos

```text
┌──────────────────────────────────────────────────────────────┐
│  Lebytek_Framework  (Composer library: lebytek/framework)     │
│  · src/                  → Lebytek\Framework\                 │
│  · database/schema|seeds|migrations  → SQL PLATAFORMA         │
│  · scripts/migrate|seed|install      → PackagePaths + ROOT    │
│  · skeleton/             → plantilla MÍNIMA de app consumidor │
│  · tests/ + harness mínimo (config/public stub)               │
│  · NO es document root de lebytek.com                         │
└──────────────────────────────────────────────────────────────┘
              ▲ require (path local | VCS + tag)
              │
┌─────────────┴─────────────┐     ┌────────────────────────────┐
│  Lebytek_Portal (project) │     │  Tenant X (desde skeleton) │
│  app/ App\ marketing/api  │     │  app/ App\ dominio propio  │
│  config routes public     │     │  sin código Portal Lebytek │
│  database negocio dom_*   │     │  composer.lock propio      │
│  vendor/lebytek/framework │     │  vendor/lebytek/framework  │
└───────────────────────────┘     └────────────────────────────┘
```

### Capas de ownership (qué vive dónde)

| Capa | Dueño | Dónde vive tras el split | Cómo se resuelve en runtime |
|------|-------|--------------------------|-----------------------------|
| PHP plataforma | Framework | `src/` en el paquete | Composer autoload `Lebytek\Framework\` |
| PHP negocio | Portal / tenant | `app/` del proyecto | Composer autoload `App\` del proyecto |
| Vistas plataforma | Framework | `src/Presentation/Views` | `ViewHelper`: proyecto primero, luego paquete |
| Vistas negocio | Portal / tenant | `app/Presentation/Views` | mismo cascade |
| Config / `.env` / routes | **Siempre** consumidor | `ROOT_PATH/config`, etc. | `Config::init(ROOT_PATH.'/config')` |
| SQL plataforma (`schema.sql`, modules calendario/pdf-kit/reportes/crud-engine/integrations/payments, seeds `0xx_*`, migrations `auth_*`/`core_*`/plataforma) | Framework | `vendor/lebytek/framework/database/...` | `PackagePaths::resolveDataFile()` **paquete primero** |
| SQL negocio (`marketing*.sql`, migrations `*mkt*`) | Portal | `ROOT_PATH/database/...` | mismo helper; solo existe en ROOT |
| Assets UI plataforma (`css/app.css`, `lebytek-ui.css`, `crud-engine.*`, `js/app.js`, …) | **Copia en consumidor** | `ROOT_PATH/public/assets/` | `ViewHelper::asset()` → URL `/assets/...` (no lee vendor) |
| Assets producto (`publico/*` landing Lebytek) | Portal only | Portal `public/assets/publico/` | nunca en skeleton |

### Contrato de paths (invariantes)

| Constante / helper | Apunta a | Quién la define | Qué vive ahí |
|--------------------|----------|-----------------|--------------|
| `ROOT_PATH` | Raíz del **proyecto consumidor** | `public/index.php` / wrappers del Portal/skeleton | `.env`, `config/`, `app/`, `public/`, `storage/`, `routes/` |
| `APP_PATH` | `ROOT_PATH/app` | igual | Namespace `App\` |
| `PackagePaths::root()` | Raíz del **paquete** | Framework | `src/`, `database/` plataforma, `scripts/` |
| `PackagePaths::resolveDataFile($rel)` | Archivo de datos | Framework | Busca en paquete; si no, en `ROOT_PATH` |
| `ViewHelper` | proyecto primero, luego paquete | Framework | overrides de vistas |

Regla: **Composer resuelve código PHP; Git no se anida.** Un VPS = un checkout del Portal (o del tenant) + `composer install`. Prohibido `git pull` del Framework dentro de la misma carpeta del sitio.

### Anatomía correcta del `skeleton/` (plantilla de coexistencia)

El skeleton es la respuesta a “¿cómo construyo un sistema sobre el framework?”. Debe quedar así:

```text
skeleton/
  app/                    # stub vacío (capas .gitkeep) — SIN Marketing/Publico
  config/                 # toggles + módulos plataforma; SIN mkt_*; SIN bindings Marketing
  routes/                 # admin/plataforma only; SIN marketing.php
  public/
    index.php             # define ROOT_PATH → Bootstrap::run()
    assets/               # SOLO assets plataforma (lista Task 5); SIN assets/publico/
  database/
    migrations/           # solo demos opcionales / README; SIN *mkt*; plataforma vía paquete
    schema/modules/       # vacío o ausente (plataforma en vendor)
    seeds/                # README: seeds plataforma vienen del paquete
  scripts/migrate.php, seed.php, install.php   # thin wrappers ROOT_PATH → vendor scripts
  tests/lib/bootstrap.php # require ROOT_PATH/vendor/autoload.php
  composer.json           # type=project, require lebytek/framework, autoload solo App\
```

**No** es correcto: clonar Portal, dejar Marketing “apagado” en vertical, o asumir que el monorepo Framework es el template.

### Cómo construir un sistema sobre el framework

Orden canónico (tenant nuevo **y** el propio Portal):

1. Copiar `skeleton/` a un repo nuevo (Portal ya existirá como árbol propio; no clonar Portal para clientes).
2. `composer.json`: `"require": { "lebytek/framework": "^1.0" }` + `repositories` VCS (prod) o `path` (dev mantenedor).
3. `composer install` → `vendor/lebytek/framework` (**solo lectura**).
4. `php scripts/migrate.php` / `seed.php` / `install.php` (wrappers) → SQL plataforma vía `PackagePaths`.
5. Activar módulos en `config/vertical.php` del **proyecto**.
6. Escribir dominio en `app/` (`App\…`), SQL `dom_*` solo en el proyecto.
7. Al subir versión del framework: `composer update lebytek/framework` + revisar changelog de assets plataforma (copiar archivos nuevos de la lista canónica si cambió UI).
8. Bugs de plataforma → spec/plan en repo Framework → bump Composer; **never** patch `vendor/`.

### Desarrollo local del mantenedor (path repo)

```json
"repositories": [{
  "type": "path",
  "url": "../Lebytek_Framework",
  "options": { "symlink": true }
}],
"require": { "lebytek/framework": "*@dev" }
```

Prod/staging: VCS privado + tag/branch aprobada + `composer.lock` commiteado en el Portal. No mezclar “Portal apunta a path” en VPS.

**Windows:** si `symlink: true` falla sin privilegios, Composer cae a mirror copy; sigue siendo válido para smoke local.

### Qué queda en la raíz del repo Framework (post-carve)

| Permitido | Rol |
|-----------|-----|
| `src/`, `database/` plataforma, `scripts/` plataforma, `skeleton/`, `tests/`, `docs/`, `composer.json` library | Paquete |
| Harness mínimo: `config/` (sin marketing), `public/assets` plataforma, `app/README` stub, `.env.example` sin `LEBYTEK_API_*` de producto | Solo para `php tests/run.php` Kernel/Auth — **no deploy** |

| Prohibido en raíz Framework tras Task 9 | Motivo |
|------------------------------------------|--------|
| `app/Domain/Marketing`, Publico, LebytekApi | Eso es Portal |
| Documentar “clonar este repo y apuntar nginx aquí” | Regenera D6 |
| `routes/marketing.php`, `database/schema/modules/marketing*.sql` | Negocio |

---

## Deuda técnica: registro y política

Separar repos **sin** pagar estas deudas produce un framework “puro en paper” que no se puede instalar, instalar BD, ni bootstrapear un tenant correctamente.

### Debe pagarse en este plan (bloqueantes)

| ID | Deuda hoy | Por qué duele post-split | Task |
|----|-----------|--------------------------|------|
| D1 | `composer.json` Framework autoload-ea `App\\` + `Lebytek\\Framework\\` | `composer require` contamina o exige monorepo | 2, 7 |
| D2 | `migrate.php` / `seed.php` leen `ROOT_PATH/database/schema/...` | En consumidor el SQL está en `vendor/…`; copiar = drift | 3, 4 |
| D3 | `skeleton/` contiene Marketing completo | Nuevos tenants arrancan con negocio Lebytek | 5 |
| D4 | Bootstrap tests skeleton: `dirname(__DIR__, 3)/vendor` | Asume skeleton anidado en monorepo | 5 |
| D5 | `marketing*.sql` en repo Framework como si fuera plataforma | Frontera borrosa | 8 |
| D6 | Docs/README enseñan deploy del monorepo | Equipo/IA siguen modelo viejo | 7, 10, 11, 12 |
| D7 | Assets UI solo en `public/assets` del árbol; `ViewHelper::asset` no lee vendor | Tenant sin copia de CSS/JS → admin roto; skeleton debe shippear lista canónica y quitar `publico/` | 5, 10 |
| D8 | `install.php` + `Installer` + `bootstrap_sql` resuelven bajo `ROOT_PATH` / `Paths::appRoot()` | Módulos plataforma no encuentran SQL en vendor; o se vuelve a copiar schema al Portal | 3, 4 |
| D9 | Skeleton duplica `database/seeds` y migrations de plataforma | Drift silencioso vs paquete | 5 |
| D10 | Skeleton `config/container.php` + `cruds/mkt_*` + `modules/marketing.php` siguen cableando Marketing | `vertical.marketing=false` no basta; `composer`/IA reactivan el módulo y rompen | 5 |
| D11 | Tras copiar Portal, la raíz Framework seguiría siendo un “sitio a medias” | Dos fuentes de verdad de config/routes; cutover imposible de razonar | 9 |

### Aceptada conscientemente (no pagar ahora — documentar)

| ID | Deuda | Mitigación en este ciclo |
|----|-------|--------------------------|
| A1 | No hay `lebytek/module-marketing` | YAGNI hasta 2º tenant; marketing solo en Portal |
| A2 | Tag `v1.0.0` aún no es paquete “puro” | No retaguear; Portal local usa path/`*@dev` |
| A3 | `integrations` base en Framework + flag vertical | OK plataforma; `LebytekApiClient` va al Portal |
| A4 | Thin wrappers `scripts/*.php` en Portal/skeleton | Preferible a invocar paths vendor a mano |
| A5 | Cursor rules Framework hablan de monorepo hasta Task 11 | Actualizar en la misma tanda de docs |
| A6 | Sin Composer plugin que publique assets desde el paquete | Skeleton/Portal copian lista canónica; `docs/ASSETS-PLATFORM.md` + checklist en bumps |
| A7 | Harness mínimo (`config/` + `public/`) permanece en raíz Framework para tests | Documentado como no-deploy; follow-up: CI que teste contra `skeleton/` instalado |
| A8 | Demos CRUD (`demo_*`) pueden quedar en skeleton OFF en vertical | Producto demo de plataforma, no Marketing Lebytek |

### Anti-patrones prohibidos (generan deuda nueva)

1. Copiar `database/schema/schema.sql` (o modules plataforma) al Portal como SoT.
2. Clonar `Lebytek_Portal` para un cliente X.
3. `git submodule` / segundo `.git` en el document root.
4. Parchear `vendor/lebytek/framework`.
5. Dejar Marketing (código, SQL, config, assets `publico/`) en `skeleton/`.
6. Autoload path de `src/` desde el `composer.json` del Portal.
7. Seguir desplegando desde el clone del Framework “porque aún tiene public/”.
8. Resolver `bootstrap_sql` solo con `ROOT_PATH` tras haber movido el SQL al paquete.

### Ventana de transición (mitiga deuda humana)

Hasta Task 9 inclusive:

- SoT de **negocio** Lebytek: primero el árbol que se está copiando → luego **solo** `Lebytek_Portal`.
- No abrir features de marketing en el monorepo Framework en paralelo al Portal.
- Tras Task 8: cualquier PR que toque `App\…\Marketing` en Framework se rechaza.

---

## File map (después del split)

| Path | Repo | Rol |
|------|------|-----|
| `src/**` | Framework | Plataforma PHP |
| `src/Kernel/PackagePaths.php` | Framework | SoT rutas paquete + `resolveDataFile` |
| `skeleton/**` | Framework | Plantilla mínima **sin** Marketing |
| `database/schema|seeds|migrations` plataforma | Framework (en el paquete) | SQL plataforma |
| `database/schema/modules/marketing*.sql` | **Portal only** | Negocio |
| `scripts/migrate.php`, `seed.php`, `install.php`, … | Framework | Entrypoints plataforma |
| `tests/{Kernel,Auth,Crud,…}` | Framework | Sin `tests/Marketing/` |
| Harness `config/`, `public/assets` plataforma | Framework | Solo tests — no deploy |
| `app/**` Marketing + LebytekApi + Publico | Portal | Negocio |
| `config/**`, `routes/**`, `public/**` | Portal / skeleton | App desplegable |
| `database/migrations/*mkt*` | Portal | Migraciones negocio |
| `tests/Marketing/**` | Portal | Tests negocio |
| `docs/integration/**` | Portal | Contrato api producto |
| `docs/ARCHITECTURE-CONSUMER.md`, `ASSETS-PLATFORM.md`, `SCHEMA-OWNERSHIP.md` | Framework | Coexistencia |
| Scripts VPS / lead / api | Portal | Ops producto |

---

### Task 1: Baseline + inventario de frontera + deuda

**Files:**
- Create: `docs/superpowers/BOUNDARY-framework-vs-portal.md`
- Modify: none (solo verificación)

**Interfaces:**
- Consumes: árbol actual del monorepo en `feature/backoffice-api-integration`.
- Produces: checklist canónica Framework vs Portal + lista D1–D11 referenciada por Tasks 2–12.

- [ ] **Step 1: Registrar baseline de tests**

Run (en `Lebytek_Framework`):

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php 2>&1 | Select-Object -Last 5
```

Expected: última línea `N passed, 0 failed`. Anotar `N` en el commit message / doc (baseline).

- [ ] **Step 2: Escribir el inventario de frontera**

Create `docs/superpowers/BOUNDARY-framework-vs-portal.md` with exactly this content:

```markdown
# Boundary: Framework vs Portal

Fuente: `docs/superpowers/specs/2026-07-15-framework-portal-separation-design.md`
Plan: `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`

## Framework (`lebytek/framework`) — shipped in Composer package

- `src/` (incluye `Kernel/PackagePaths.php` con `resolveDataFile`)
- `skeleton/` (**mínimo**, sin Marketing / sin `assets/publico` / sin `mkt_*`)
- `database/schema/schema.sql`
- `database/schema/modules/` plataforma: `calendario.sql`, `pdf-kit.sql`, `reportes.sql`, `crud-engine.sql`, `integrations.sql`, `payments.sql`
- [ ] Pagos shippeados en monorepo antes del carve — incluir módulo `payments` OFF en skeleton.
- `database/seeds/` plataforma (`010_*` … `035_*`)
- `database/migrations/` plataforma (auth/core/pdf/crud demos — **no** `*mkt*`)
- `scripts/install.php`, `migrate.php`, `seed.php`, `status.php`, `apply-sql-migration.php`
- `tests/` excepto `tests/Marketing/`
- Harness no-deploy: `config/` sin marketing, `public/assets` plataforma
- Docs: `ARCHITECTURE-CONSUMER.md`, `TENANTS.md`, `ASSETS-PLATFORM.md`, `SCHEMA-OWNERSHIP.md`

## Portal (`Lebytek_Portal`) — Composer project

- `app/` completo (`App\` — Marketing, Publico, LebytekApi)
- `config/`, `routes/`, `public/`, `storage/`
- `database/migrations/` (`*mkt*` y negocio)
- `database/schema/modules/marketing.sql`, `marketing_demo.sql`
- Thin wrappers `scripts/` → vendor + `migrate-marketing.php`
- `tests/Marketing/` + harness propio
- `docs/integration/`
- Scripts producto VPS/api/leads
- `.env.example` con `LEBYTEK_API_*`, `MKT_*`
- Assets plataforma **más** `public/assets/publico/`

## Explicit NO

- Dos `git pull` en la misma carpeta VPS
- Autoload `App\\` en el package
- Extraer `lebytek/module-marketing` en este ciclo
- Copiar `schema.sql` plataforma al Portal como SoT
- Marketing / `mkt_*` / `assets/publico` dentro de `skeleton/`
- Clonar Portal para bootstrapping de cliente externo
- Desplegar desde la raíz del repo Framework

## Deuda bloqueante

- D1–D11 según el plan (autoload, PackagePaths, skeleton, install/Installer, assets, root-as-site)
```

- [ ] **Step 3: Commit**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/superpowers/BOUNDARY-framework-vs-portal.md
git commit -m "docs: inventory Framework vs Portal boundary and debt"
```

---

### Task 2: Test de pureza del paquete (falla con autoload `App\\`)

**Files:**
- Create: `tests/Kernel/PackageAutoloadBoundaryTest.php`
- Modify: none yet (el fix es Task 7)

**Interfaces:**
- Consumes: `composer.json` raíz del Framework.
- Produces: assertion contractual — el paquete no declara `App\\` en `autoload.psr-4`.

- [ ] **Step 1: Write the failing test**

Create `tests/Kernel/PackageAutoloadBoundaryTest.php`:

```php
<?php

declare(strict_types=1);

$composerPath = dirname(__DIR__, 2) . '/composer.json';

test('framework package composer.json is readable JSON', function () use ($composerPath): void {
    assert_true(is_readable($composerPath), 'composer.json must be readable');
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_true(is_array($data), 'composer.json must decode to array');
});

test('framework package name is lebytek/framework', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_same('lebytek/framework', $data['name'] ?? null);
});

test('framework package autoloads Lebytek\\Framework\\ from src/', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    $psr4 = $data['autoload']['psr-4'] ?? [];
    assert_same('src/', $psr4['Lebytek\\Framework\\'] ?? null);
});

test('framework package does not autoload App\\', function () use ($composerPath): void {
    $data = json_decode((string) file_get_contents($composerPath), true);
    $psr4 = $data['autoload']['psr-4'] ?? [];
    assert_true(!array_key_exists('App\\', $psr4), 'App\\ must not be in package autoload');
});
```

- [ ] **Step 2: Run test to verify it fails**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackageAutoloadBoundary
```

Expected: FAIL on `framework package does not autoload App\\` (hoy `composer.json` todavía declara `"App\\": "app/"`).

- [ ] **Step 3: Commit the failing test**

```powershell
git add tests/Kernel/PackageAutoloadBoundaryTest.php
git commit -m "test: require framework package without App autoload"
```

Note: completar Tasks 3–7 en la misma sesión preferible; no tratar el arnés completo como gate verde hasta Task 7 si este file está rojo.

---

### Task 3: `PackagePaths` — contrato de coexistencia (TDD)

**Files:**
- Create: `src/Kernel/PackagePaths.php`
- Create: `tests/Kernel/PackagePathsTest.php`
- Modify: none of migrate/seed/install yet (Task 4)

**Interfaces:**
- Consumes: layout del paquete (`src/` bajo la raíz del package).
- Produces:
  - `Lebytek\Framework\Kernel\PackagePaths::root(): string`
  - `Lebytek\Framework\Kernel\PackagePaths::schema(string $relative = 'schema.sql'): string`
  - `Lebytek\Framework\Kernel\PackagePaths::seedsDir(): string`
  - `Lebytek\Framework\Kernel\PackagePaths::moduleSchema(string $moduleFile): string`
  - `Lebytek\Framework\Kernel\PackagePaths::resolveDataFile(string $relative): string` — **paquete primero**, luego `ROOT_PATH`; lanza `\RuntimeException` si falta en ambos

- [ ] **Step 1: Write the failing test**

Create `tests/Kernel/PackagePathsTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\PackagePaths;

test('PackagePaths root points at package root containing composer.json named lebytek/framework', function (): void {
    $root = PackagePaths::root();
    assert_true(is_dir($root), 'package root must exist');
    $composer = $root . '/composer.json';
    assert_true(is_readable($composer), 'composer.json must exist at package root');
    $data = json_decode((string) file_get_contents($composer), true);
    assert_same('lebytek/framework', $data['name'] ?? null);
});

test('PackagePaths schema resolves platform schema.sql inside the package', function (): void {
    $schema = PackagePaths::schema('schema.sql');
    assert_true(is_readable($schema), 'schema.sql must be readable via PackagePaths');
    assert_true(
        str_contains($schema, DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'schema.sql')
        || str_contains($schema, '/database/schema/schema.sql'),
        'path must include database/schema/schema.sql'
    );
});

test('PackagePaths moduleSchema resolves integrations module under package', function (): void {
    $path = PackagePaths::moduleSchema('integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must exist in package modules');
});

test('PackagePaths seedsDir is a directory under the package', function (): void {
    $dir = PackagePaths::seedsDir();
    assert_true(is_dir($dir), 'seeds dir must exist');
});

test('PackagePaths resolveDataFile prefers package for platform module SQL', function (): void {
    $path = PackagePaths::resolveDataFile('database/schema/modules/integrations.sql');
    assert_true(is_readable($path), 'integrations.sql must resolve');
    assert_true(
        str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', PackagePaths::root())),
        'platform SQL must resolve inside the package root'
    );
});

test('PackagePaths resolveDataFile falls back to ROOT_PATH for consumer-only files', function (): void {
    $rel = 'database/schema/modules/__consumer_only_probe__.sql';
    $probe = ROOT_PATH . '/' . $rel;
    $dir = dirname($probe);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($probe, "-- probe\n");
    try {
        $resolved = PackagePaths::resolveDataFile($rel);
        assert_same(str_replace('\\', '/', $probe), str_replace('\\', '/', $resolved));
    } finally {
        @unlink($probe);
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackagePaths
```

Expected: FAIL (class `PackagePaths` not found).

- [ ] **Step 3: Write minimal implementation**

Create `src/Kernel/PackagePaths.php`:

```php
<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel;

/**
 * Rutas que pertenecen al paquete Composer lebytek/framework.
 * Distinto de ROOT_PATH (raíz del proyecto consumidor).
 *
 * SQL/datos: resolveDataFile busca primero en el paquete, luego en ROOT_PATH
 * (negocio / overrides del consumidor).
 */
final class PackagePaths
{
    public static function root(): string
    {
        // este archivo: {package}/src/Kernel/PackagePaths.php
        return dirname(__DIR__, 2);
    }

    public static function schema(string $relative = 'schema.sql'): string
    {
        return self::root() . '/database/schema/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function moduleSchema(string $moduleFile): string
    {
        return self::schema('modules/' . ltrim(str_replace('\\', '/', $moduleFile), '/'));
    }

    public static function seedsDir(): string
    {
        return self::root() . '/database/seeds';
    }

    public static function resolveDataFile(string $relative): string
    {
        $rel = ltrim(str_replace('\\', '/', $relative), '/');
        $inPackage = self::root() . '/' . $rel;
        if (is_readable($inPackage)) {
            return $inPackage;
        }
        if (defined('ROOT_PATH')) {
            $inRoot = ROOT_PATH . '/' . $rel;
            if (is_readable($inRoot)) {
                return $inRoot;
            }
        }
        throw new \RuntimeException(
            "Archivo de datos no encontrado en paquete ni en ROOT_PATH: {$rel}"
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```powershell
php tests/run.php PackagePaths
```

Expected: `N passed, 0 failed`.

- [ ] **Step 5: Commit**

```powershell
git add src/Kernel/PackagePaths.php tests/Kernel/PackagePathsTest.php
git commit -m "feat(kernel): PackagePaths with package-first data resolve"
```

---

### Task 4: Scripts + Installer leen SQL paquete-primero (cierra D2, D8)

**Files:**
- Modify: `scripts/migrate.php`
- Modify: `scripts/seed.php`
- Modify: `scripts/install.php`
- Modify: `src/Application/Install/Installer.php` (resolver archivos via `PackagePaths::resolveDataFile`)
- Create: `tests/Kernel/PlatformMigratePathsTest.php`
- Create: `tests/Kernel/PlatformSqlResolveTest.php`
- Grep: cualquier otro script bajo `scripts/` que lea `ROOT_PATH . '/database/schema/` para SQL **plataforma** — misma regla

**Interfaces:**
- Consumes: `PackagePaths` (Task 3).
- Produces:
  - Scripts plataforma aplican SQL desde el paquete; `.env` + `config/` en `ROOT_PATH`.
  - `Installer` resuelve `database/migrations/{file}`, `database/seeds/{file}` y paths `bootstrap_sql` con `resolveDataFile`.
  - `ROOT_PATH` no se redefine si ya está definida (wrappers skeleton/Portal).

- [ ] **Step 1: Write the failing path/source tests**

Create `tests/Kernel/PlatformMigratePathsTest.php`:

```php
<?php

declare(strict_types=1);

test('platform migrate.php source uses PackagePaths not ROOT_PATH for schema.sql', function (): void {
    $migrate = dirname(__DIR__, 2) . '/scripts/migrate.php';
    assert_true(is_readable($migrate), 'scripts/migrate.php must exist');
    $src = (string) file_get_contents($migrate);
    assert_true(
        str_contains($src, 'PackagePaths::schema'),
        'migrate.php must call PackagePaths::schema'
    );
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'migrate.php must not read schema.sql from consumer ROOT_PATH'
    );
});

test('platform seed.php source uses PackagePaths for schema files', function (): void {
    $seed = dirname(__DIR__, 2) . '/scripts/seed.php';
    assert_true(is_readable($seed), 'scripts/seed.php must exist');
    $src = (string) file_get_contents($seed);
    assert_true(str_contains($src, 'PackagePaths'), 'seed.php must reference PackagePaths');
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'seed.php must not read schema.sql from consumer ROOT_PATH'
    );
    assert_true(
        !str_contains($src, 'marketing_demo'),
        'framework seed.php must not reference marketing_demo'
    );
});

test('platform install.php source uses PackagePaths for schema.sql', function (): void {
    $install = dirname(__DIR__, 2) . '/scripts/install.php';
    assert_true(is_readable($install), 'scripts/install.php must exist');
    $src = (string) file_get_contents($install);
    assert_true(
        str_contains($src, 'PackagePaths'),
        'install.php must reference PackagePaths'
    );
    assert_true(
        !str_contains($src, "ROOT_PATH . '/database/schema/schema.sql'"),
        'install.php must not read schema.sql only from ROOT_PATH'
    );
});
```

Create `tests/Kernel/PlatformSqlResolveTest.php`:

```php
<?php

declare(strict_types=1);

test('Installer.php source resolves data files via PackagePaths::resolveDataFile', function (): void {
    $file = dirname(__DIR__, 2) . '/src/Application/Install/Installer.php';
    assert_true(is_readable($file), 'Installer.php must exist');
    $src = (string) file_get_contents($file);
    assert_true(
        str_contains($src, 'PackagePaths::resolveDataFile'),
        'Installer must call PackagePaths::resolveDataFile'
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

```powershell
php tests/run.php PlatformMigratePaths
php tests/run.php PlatformSqlResolve
```

Expected: FAIL (scripts/Installer aún usan `ROOT_PATH` / dirs locales).

- [ ] **Step 3: Update `scripts/migrate.php`**

Replace with (preserve Connection/Env; SQL desde paquete; no redefinir `ROOT_PATH` si existe):

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| migrate.php — Schema SQL base del PAQUETE
|--------------------------------------------------------------------------
| Uso:
|   php scripts/migrate.php
|   php vendor/lebytek/framework/scripts/migrate.php
|
| ROOT_PATH = proyecto consumidor (.env, config)
| PackagePaths = SQL de plataforma
*/

$packageScriptsDir = __DIR__;
$packageRoot = dirname($packageScriptsDir);

if (!defined('ROOT_PATH')) {
    $candidateConsumer = dirname($packageRoot, 3);
    if (
        is_readable($candidateConsumer . '/composer.json')
        && is_dir($packageRoot . '/src')
        && str_contains((string) file_get_contents($candidateConsumer . '/composer.json'), '"type": "project"')
    ) {
        define('ROOT_PATH', $candidateConsumer);
    } else {
        define('ROOT_PATH', $packageRoot);
    }
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    $autoload = $packageRoot . '/vendor/autoload.php';
}
require_once $autoload;

use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\PackagePaths;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');

Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$pdo = Connection::getInstance();
$sql = file_get_contents(PackagePaths::schema('schema.sql'));

echo "=== Ejecutando migraciones de plataforma ===\n\n";

try {
    $pdo->exec($sql);
    echo "✓ Schema de plataforma aplicado correctamente.\n";
} catch (\PDOException $e) {
    echo "✗ Error al ejecutar el schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migración completada ===\n";
```

- [ ] **Step 4: Update `scripts/seed.php`**

Usar el mismo preamble `ROOT_PATH` que migrate. Lista de archivos:

```php
use Lebytek\Framework\Kernel\PackagePaths;

$archivos = [
    PackagePaths::schema('schema.sql'),
];

if ($incluirCrudEngine) {
    $archivos[] = PackagePaths::moduleSchema('crud-engine.sql');
}
```

Eliminar cualquier referencia a `marketing_demo.sql` / `--marketing-demo` de este script Framework.

- [ ] **Step 5: Update `scripts/install.php`**

1. Mismo preamble `if (!defined('ROOT_PATH'))` + autoload dual.
2. Schema base:

```php
use Lebytek\Framework\Kernel\PackagePaths;

(new SqlFileRunner())->ejecutar(PackagePaths::schema('schema.sql'));
```

3. Bootstrap de cada módulo:

```php
$rutaBootstrap = PackagePaths::resolveDataFile($manifest->bootstrapSql);
```

(Reemplazar `ROOT_PATH . '/' . $manifest->bootstrapSql`.)

- [ ] **Step 6: Update `Installer` file resolution**

In `src/Application/Install/Installer.php`, add:

```php
use Lebytek\Framework\Kernel\PackagePaths;
```

Change the private helpers that build `$dir . '/' . $archivo` so they resolve through the package-first contract. Minimal approach — replace uses of `$this->migracionesDir` / `$this->seedsDir` concatenation in `clasificar` / `baselineArchivo` / execute paths with:

```php
private function resolveMigrationFile(string $archivo): string
{
    return PackagePaths::resolveDataFile('database/migrations/' . ltrim($archivo, '/'));
}

private function resolveSeedFile(string $archivo): string
{
    return PackagePaths::resolveDataFile('database/seeds/' . ltrim($archivo, '/'));
}
```

Use those helpers everywhere the Installer previously did `$this->migracionesDir . '/' . $archivo` or `$this->seedsDir . '/' . $archivo`. Keep constructor args for BC but stop treating them as sole SoT (or pass them unused — prefer still accepting them but documenting they are legacy; resolution goes through `PackagePaths`).

- [ ] **Step 7: Run contract tests — must PASS**

```powershell
php tests/run.php PlatformMigratePaths
php tests/run.php PlatformSqlResolve
php tests/run.php PackagePaths
```

Expected: `0 failed`.

- [ ] **Step 8: Commit**

```powershell
git add scripts/migrate.php scripts/seed.php scripts/install.php src/Application/Install/Installer.php tests/Kernel/PlatformMigratePathsTest.php tests/Kernel/PlatformSqlResolveTest.php
git commit -m "fix(install): resolve platform SQL via PackagePaths"
```

---

### Task 5: Limpiar `skeleton/` como plantilla de coexistencia (cierra D3, D4, D7, D9, D10)

**Files:**
- Delete from `skeleton/`: Marketing / landing / mkt configs / assets publico (lista Step 3)
- Modify: `skeleton/tests/lib/bootstrap.php`
- Modify: `skeleton/composer.json`
- Modify: `skeleton/config/container.php` (quitar bloque Marketing)
- Modify: `skeleton/config/vertical.php` (`marketing` => false)
- Create: `skeleton/scripts/migrate.php`, `seed.php`, `install.php`
- Create: `skeleton/database/seeds/README.md`, `skeleton/docs` pointer or reuse Framework docs later
- Create: `tests/Kernel/SkeletonPurityTest.php`
- Test: smoke `composer install` dentro de `skeleton/`

**Interfaces:**
- Consumes: Tasks 3–4.
- Produces: skeleton usable como raíz de un sistema nuevo **sin** código/config/assets Portal.

**Lista canónica de assets plataforma que el skeleton DEBE conservar** (D7 / A6):

```text
public/assets/css/app.css
public/assets/css/lebytek-ui.css
public/assets/css/crud-engine.css
public/assets/js/app.js
public/assets/js/crud-engine.js
public/assets/js/calendar.js
public/assets/js/avatar-manager.js
public/assets/js/reportes-builder.js
public/assets/icons/app-icon.svg
public/assets/images/logo.png
```

Todo bajo `public/assets/publico/` se elimina del skeleton.

- [ ] **Step 1: Write the failing purity test**

Create `tests/Kernel/SkeletonPurityTest.php`:

```php
<?php

declare(strict_types=1);

$skeleton = dirname(__DIR__, 2) . '/skeleton';

test('skeleton does not ship App Domain Marketing', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/app/Domain/Marketing'), 'no Domain/Marketing');
    assert_true(!is_dir($skeleton . '/app/Application/Marketing'), 'no Application/Marketing');
    assert_true(!is_dir($skeleton . '/app/Infrastructure/Marketing'), 'no Infrastructure/Marketing');
    assert_true(!is_dir($skeleton . '/app/Presentation/Controllers/Publico'), 'no Controllers/Publico');
    assert_true(!is_dir($skeleton . '/app/Presentation/Views/publico'), 'no Views/publico');
});

test('skeleton does not ship marketing schema or mkt configs', function () use ($skeleton): void {
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($skeleton . '/database/schema/modules/marketing_demo.sql'));
    assert_true(!is_file($skeleton . '/config/modules/marketing.php'));
    assert_true(!is_file($skeleton . '/routes/marketing.php'));
    foreach (glob($skeleton . '/config/cruds/mkt_*.json') ?: [] as $_) {
        assert_true(false, 'skeleton must not contain config/cruds/mkt_*.json');
    }
});

test('skeleton does not ship Marketing tests or publico assets', function () use ($skeleton): void {
    assert_true(!is_dir($skeleton . '/tests/Marketing'));
    assert_true(!is_dir($skeleton . '/public/assets/publico'));
});

test('skeleton ships required platform UI assets', function () use ($skeleton): void {
    $required = [
        'public/assets/css/app.css',
        'public/assets/css/lebytek-ui.css',
        'public/assets/css/crud-engine.css',
        'public/assets/js/app.js',
        'public/assets/js/crud-engine.js',
        'public/assets/icons/app-icon.svg',
    ];
    foreach ($required as $rel) {
        assert_true(is_readable($skeleton . '/' . $rel), "missing platform asset: {$rel}");
    }
});

test('skeleton container.php does not reference App\\Infrastructure\\Marketing', function () use ($skeleton): void {
    $src = (string) file_get_contents($skeleton . '/config/container.php');
    assert_true(
        !str_contains($src, 'App\\Infrastructure\\Marketing')
        && !str_contains($src, 'App\\Domain\\Marketing')
        && !str_contains($src, 'App\\Application\\Marketing'),
        'container.php must not hard-bind Marketing classes'
    );
});

test('skeleton test bootstrap loads consumer vendor autoload', function () use ($skeleton): void {
    $boot = $skeleton . '/tests/lib/bootstrap.php';
    assert_true(is_readable($boot));
    $src = (string) file_get_contents($boot);
    assert_true(
        str_contains($src, "ROOT_PATH . '/vendor/autoload.php'")
        || str_contains($src, 'ROOT_PATH . "/vendor/autoload.php"'),
        'bootstrap must load ROOT_PATH/vendor/autoload.php'
    );
    assert_true(
        !str_contains($src, 'dirname(__DIR__, 3)'),
        'bootstrap must not assume monorepo parent vendor'
    );
});

test('skeleton does not duplicate platform seeds as SoT', function () use ($skeleton): void {
    $seedFiles = glob($skeleton . '/database/seeds/*.sql') ?: [];
    assert_true(
        $seedFiles === [],
        'skeleton database/seeds must not ship platform *.sql copies (use package seeds)'
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

```powershell
php tests/run.php SkeletonPurity
```

Expected: FAIL.

- [ ] **Step 3: Remove Marketing / portal landing / mkt / publico from skeleton**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
Remove-Item -Recurse -Force skeleton/app/Domain/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Application/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Infrastructure/Marketing -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Presentation/Controllers/Publico -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/app/Presentation/Views/publico -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/tests/Marketing -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/routes/marketing.php -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/database/schema/modules/marketing.sql -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/database/schema/modules/marketing_demo.sql -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/config/modules/marketing.php -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/config/cruds/mkt_*.json -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force skeleton/public/assets/publico -ErrorAction SilentlyContinue
Remove-Item -Force skeleton/database/seeds/*.sql -ErrorAction SilentlyContinue
```

Ensure empty app layer dirs exist (`.gitkeep` under `app/Domain`, `Application`, `Infrastructure`, `Presentation`).

In `skeleton/config/container.php`: delete the entire `if (vertical.modules.marketing)` block and any `Marketing*SettingsProvider` entries in `SettingsSectionRegistry`. Keep integrations bindings gated by vertical.

In `skeleton/routes/web.php` (or equivalent): remove `require marketing.php` / marketing route includes.

In `skeleton/config/vertical.php`: `'marketing' => false`.

Write `skeleton/database/seeds/README.md`:

```markdown
# Seeds

Los seeds de plataforma viven en el paquete `lebytek/framework` (`PackagePaths::seedsDir()`).
No copies aquí `010_*.sql` … `035_*.sql` — usar `php scripts/seed.php` / install del paquete.
Seeds de dominio del tenant (si aplica) sí van en este directorio.
```

- [ ] **Step 4: Fix skeleton test bootstrap (D4)**

Replace `skeleton/tests/lib/bootstrap.php` with:

```php
<?php

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

require_once ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

$envFile = ROOT_PATH . '/.env';
if (is_readable($envFile)) {
    EnvLoader::load($envFile);
} elseif (is_readable(ROOT_PATH . '/.env.example')) {
    EnvLoader::load(ROOT_PATH . '/.env.example');
}

Config::init(ROOT_PATH . '/config');
$dbConfig = Config::get('database', []);
if (is_array($dbConfig) && $dbConfig !== []) {
    Connection::configure($dbConfig);
}
```

- [ ] **Step 5: Skeleton `composer.json` for local path smoke**

Write `skeleton/composer.json`:

```json
{
    "name": "lebytek/skeleton",
    "description": "Esqueleto minimo de aplicacion Lebytek (consume lebytek/framework)",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "..",
            "options": { "symlink": true }
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 6: Thin wrappers de scripts en skeleton**

Create `skeleton/scripts/migrate.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/migrate.php';
```

Create `skeleton/scripts/seed.php` and `skeleton/scripts/install.php` the same way (swap script name). Scripts Framework already guard `if (!defined('ROOT_PATH'))`.

- [ ] **Step 7: composer install + purity tests**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\skeleton"
composer install
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') && class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK' : 'FAIL';"
cd ..
php tests/run.php SkeletonPurity
```

Expected: `OK` y SkeletonPurity `0 failed`.

- [ ] **Step 8: Commit**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add skeleton tests/Kernel/SkeletonPurityTest.php scripts/migrate.php scripts/seed.php scripts/install.php
git commit -m "chore(skeleton): strip Marketing; package-first consumer template"
```

---

### Task 6: Crear árbol local `Lebytek_Portal` (copy + composer project)

**Files:**
- Create: `c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\` (árbol portal)
- Create: `Lebytek_Portal/composer.json`
- Create: `Lebytek_Portal/README.md`, `CLAUDE.md`, `.gitignore`
- Create: `Lebytek_Portal/scripts/migrate.php`, `seed.php`, `install.php`, `migrate-marketing.php`
- Modify: none in Framework yet (pureza autoload es Task 7)

**Interfaces:**
- Consumes: BOUNDARY + Tasks 3–5.
- Produces: proyecto `lebytek/portal` con `require: lebytek/framework`, autoload solo `App\\`.

- [ ] **Step 1: Crear directorio e inicializar git**

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
New-Item -ItemType Directory -Force -Path $portal | Out-Null
Set-Location $portal
git init
git checkout -b main
```

- [ ] **Step 2: Copiar árbol portal desde el Framework**

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
$fw = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"

$dirs = @(
  'app','config','routes','public','storage','tests\Marketing','docs\integration'
)
foreach ($d in $dirs) {
  $src = Join-Path $fw $d
  $dst = Join-Path $portal $d
  New-Item -ItemType Directory -Force -Path (Split-Path $dst) | Out-Null
  if (Test-Path $src) {
    Copy-Item -Recurse -Force $src $dst
  }
}

New-Item -ItemType Directory -Force -Path "$portal\database\migrations" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\database\schema\modules" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\scripts" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\tests\lib" | Out-Null

Copy-Item -Force "$fw\database\migrations\*mkt*" "$portal\database\migrations\" -ErrorAction SilentlyContinue
if (Test-Path "$fw\database\schema\modules\marketing.sql") {
  Copy-Item -Force "$fw\database\schema\modules\marketing*.sql" "$portal\database\schema\modules\"
}

$portalScripts = @(
  'vps-deploy-lebytek-com.sh','vps-deploy-waapi.sh','vps-finalize-lebytek.sh',
  'vps-fix-lebytek-db.sh','vps-fix-lebytek-ssl.sh','vps-restore-lebytek-nginx-ssl.sh',
  'vps-setup-lebytek-db.sh','lebytek-api-health.php','lead-lifecycle-report.php',
  'confirm-api-lifecycle.php','expire-api-demos.php','resend-lead-credentials.php',
  'smoke-send-test-message.php','deprovision-debug.php','cleanup-orphan-lead-instance.php',
  'backfill-lead-instance-ids.php','apply-sql-migration.php','email-render-smoke.php',
  'smtp-probe.php','route-probe.php','test-mail.php'
)
foreach ($s in $portalScripts) {
  $p = Join-Path $fw "scripts\$s"
  if (Test-Path $p) { Copy-Item -Force $p "$portal\scripts\" }
}

Copy-Item -Force "$fw\.env.example" "$portal\.env.example"
Copy-Item -Force "$fw\tests\run.php" "$portal\tests\run.php"
Copy-Item -Force "$fw\tests\bootstrap.php" "$portal\tests\bootstrap.php" -ErrorAction SilentlyContinue
Copy-Item -Recurse -Force "$fw\tests\lib" "$portal\tests\lib"
Copy-Item -Recurse -Force "$fw\tests\fixtures" "$portal\tests\fixtures" -ErrorAction SilentlyContinue
```

Do **not** copy Framework `database/schema/schema.sql` into Portal as SoT. Do copy platform **assets** already under `public/assets/` (Portal needs them — D7) including `publico/`.

- [ ] **Step 3: Write Portal `composer.json`**

```json
{
    "name": "lebytek/portal",
    "description": "Lebytek Portal — tenant admin/marketing for lebytek.com (consumes lebytek/framework)",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "lebytek/framework": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../Lebytek_Framework",
            "options": { "symlink": true }
        }
    ],
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 4: Write Portal `.gitignore`**

```gitignore
/vendor/
.env
public/uploads/
public/error_log
storage/logs/*.log
!storage/logs/.gitkeep
!storage/cache/.gitkeep
!storage/sessions/.gitkeep
```

- [ ] **Step 5: Write Portal `README.md`**

Contents must state:

- Title: Lebytek Portal
- Company tenant (lebytek.com / waapi), **not** the framework
- New customers start from Framework `skeleton/`, never from this repo
- Setup: `cp .env.example .env`, `composer install`, `php scripts/migrate.php`, `php scripts/seed.php` or `install.php`, `php scripts/migrate-marketing.php`, `php -S localhost:8000 -t public`, `php tests/run.php Marketing`
- Local path repo to `../Lebytek_Framework`
- Prod: VCS + lockfile (`docs/DEPLOY-VPS.md`)

- [ ] **Step 6: Write Portal `CLAUDE.md`**

```markdown
# CLAUDE.md — Lebytek Portal

Portal tenant Lebytek. El framework vive en `vendor/lebytek/framework` (**solo lectura**).

- Negocio: `app/`, `config/`, `routes/`, `database/` (dom_*), `tests/Marketing/`
- Plataforma: cambiar en repo `Lebytek_Framework`, luego `composer update lebytek/framework`
- Nunca editar `vendor/`
- Nunca copiar `schema.sql` de plataforma a este repo como SoT
- Assets UI plataforma viven en `public/assets/` (copia); al bumpear framework revisar `docs` del package `ASSETS-PLATFORM.md`
```

- [ ] **Step 7: Portal script wrappers**

Create `Lebytek_Portal/scripts/migrate.php`, `seed.php`, `install.php` like skeleton wrappers.

Create `Lebytek_Portal/scripts/migrate-marketing.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';

use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Database\Connection;
use Lebytek\Framework\Kernel\EnvLoader;

EnvLoader::load(ROOT_PATH . '/.env');
Config::init(ROOT_PATH . '/config');
Connection::configure([
    'host'     => Config::get('database.host'),
    'port'     => Config::get('database.port'),
    'database' => Config::get('database.database'),
    'username' => Config::get('database.username'),
    'password' => Config::get('database.password'),
    'charset'  => 'utf8mb4',
]);

$runner = new SqlFileRunner();
$files = [
    ROOT_PATH . '/database/schema/modules/marketing.sql',
];
foreach ($files as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "Missing: {$file}\n");
        exit(1);
    }
    $runner->ejecutar($file);
    echo "Applied: {$file}\n";
}
```

- [ ] **Step 8: Enable marketing in Portal vertical**

In Portal `config/vertical.php`:

```php
'marketing' => true,
```

- [ ] **Step 9: `composer install` + smoke classes**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer install
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') ? 'OK_BOOTSTRAP' : 'FAIL'; echo PHP_EOL; echo class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK_PATHS' : 'FAIL_PATHS'; echo PHP_EOL; echo class_exists('App\\Infrastructure\\Integrations\\LebytekApi\\LebytekApiClient') ? 'OK_API' : 'FAIL_API'; echo PHP_EOL;"
```

Expected:

```
OK_BOOTSTRAP
OK_PATHS
OK_API
```

- [ ] **Step 10: Commit Portal tree**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add -A
git commit -m "chore: initial Portal tree consuming lebytek/framework via path"
```

---

### Task 7: Pureza del paquete Framework (quitar `App\\` del autoload — D1)

**Files:**
- Modify: `Lebytek_Framework/composer.json`
- Modify: `Lebytek_Framework/docs/composer-setup.md`
- Modify: `Lebytek_Framework/README.md`, `CLAUDE.md`
- Test: `tests/Kernel/PackageAutoloadBoundaryTest.php`

**Interfaces:**
- Consumes: Portal + skeleton ya corren con path repo (Tasks 5–6).
- Produces: `lebytek/framework` solo exporta `Lebytek\\Framework\\` → `src/`.

- [ ] **Step 1: Update Framework `composer.json`**

```json
{
    "name": "lebytek/framework",
    "description": "Lebytek Framework — reusable PHP platform (Kernel, RBAC, CRUD Engine)",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "dompdf/dompdf": "^3.1",
        "phpmailer/phpmailer": "^7.1"
    },
    "autoload": {
        "psr-4": {
            "Lebytek\\Framework\\": "src/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Regenerate Framework autoload**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
composer dump-autoload
```

Expected: exit 0. `App\\` ausente de `vendor/composer/autoload_psr4.php` del Framework.

- [ ] **Step 3: Purity tests PASS**

```powershell
php tests/run.php PackageAutoloadBoundary
php tests/run.php PackagePaths
php tests/run.php SkeletonPurity
php tests/run.php PlatformSqlResolve
```

Expected: `0 failed`.

- [ ] **Step 4: Platform subset**

```powershell
php tests/run.php Kernel
php tests/run.php Auth
```

Expected: `0 failed`.

- [ ] **Step 5: Re-install Portal + skeleton**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer update lebytek/framework
php -r "require 'vendor/autoload.php'; echo (class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') && class_exists('App\\Infrastructure\\Integrations\\LebytekApi\\LebytekApiClient')) ? 'OK' : 'FAIL';"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\skeleton"
composer update lebytek/framework
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK' : 'FAIL';"
```

Expected: `OK` en ambos.

- [ ] **Step 6: Update `docs/composer-setup.md`**

Autoload example **only** `Lebytek\\Framework\\` → `src/`. Replace “Desplegar lebytek.com” with: deploy **Portal**, never this package tree as site root. Add “Modelo consumidor” (ROOT_PATH vs PackagePaths; never path-autoload `src/` from consumer).

- [ ] **Step 7: README header**

```markdown
# Lebytek Framework

Paquete Composer `lebytek/framework`. El portal de la empresa Lebytek vive en el repo **Lebytek_Portal**.
Los tenants nuevos parten de `skeleton/`, no del Portal. **No desplegar este repo como document root.**
```

- [ ] **Step 8: Commit Framework**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add composer.json docs/composer-setup.md README.md CLAUDE.md
git commit -m "chore(composer): drop App autoload from framework package"
```

---

### Task 8: Arnés Marketing en Portal + retirar negocio del Framework (D5)

**Files:**
- Modify: `Lebytek_Portal/tests/lib/bootstrap.php`
- Delete from Framework: Marketing / Publico / LebytekApi / `tests/Marketing/` / `marketing*.sql` / `*mkt*` migrations
- Create: `Lebytek_Framework/app/README.md`
- Test: Portal Marketing; Framework Kernel + purity

**Interfaces:**
- Consumes: Task 7 pure package.
- Produces: SoT Marketing solo en Portal; Framework sin `marketing*.sql` ni `*mkt*` migrations.

- [ ] **Step 1: Portal test bootstrap**

`Lebytek_Portal/tests/lib/bootstrap.php` must load `ROOT_PATH/vendor/autoload.php` (same pattern as skeleton). Preserve Env/Config/Connection init.

- [ ] **Step 2: Run Marketing in Portal**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php tests/run.php Marketing
```

Expected: `N passed, 0 failed`. If DB/env missing, copy local Framework `.env` for smoke (never commit secrets).

- [ ] **Step 3: Safety check before delete**

```powershell
Test-Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\app\Domain\Marketing"
Test-Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\database\schema\modules\marketing.sql"
Test-Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\tests\Marketing"
```

Expected: all `True`. **Abort if any False.**

- [ ] **Step 4: Remove Portal business from Framework**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git rm -r app/Application/Marketing app/Domain/Marketing app/Infrastructure/Marketing
git rm -r app/Infrastructure/Integrations/LebytekApi
git rm -r app/Presentation/Controllers/Publico app/Presentation/Views/publico
git rm -r tests/Marketing
git rm database/schema/modules/marketing.sql database/schema/modules/marketing_demo.sql
git rm database/migrations/*mkt* -ErrorAction SilentlyContinue
```

Also remove root `config/modules/marketing.php`, `config/cruds/mkt_*.json`, `routes/marketing.php`, and Marketing bindings from root harness `config/container.php` (Task 9 completes harness cleanup; do the obvious Marketing deletes here).

Create `app/README.md`:

```markdown
# app/ en el repo Framework

El código de aplicación **no** vive aquí (este directorio es stub/harness).

- Plantilla vacía: `skeleton/app/`
- Portal Lebytek (empresa): repo `Lebytek_Portal`
- Este repo **no** se despliega como sitio
```

- [ ] **Step 5: Grep leftover marketing references in Framework**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
rg -n "marketing_demo|modules/marketing|Domain\\\\Marketing|cruds/mkt_" scripts database src config routes --glob '!skeleton/**'
```

Expected: no platform script/config still pointing at marketing as SoT. Fix leftovers.

- [ ] **Step 6: Run Framework + Portal suites**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php Kernel
php tests/run.php PackageAutoloadBoundary
php tests/run.php SkeletonPurity

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php tests/run.php Marketing
```

Expected: all `0 failed`.

- [ ] **Step 7: Commit both**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add app/README.md config routes
git commit -m "chore: remove Portal business and marketing schema from Framework"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add tests
git commit -m "test: green Marketing suite on Portal harness"
```

---

### Task 9: Raíz Framework = package + harness (cierra D11)

**Files:**
- Modify: `Lebytek_Framework/config/**` — sin marketing; vertical marketing false
- Modify: `Lebytek_Framework/public/` — quitar `assets/publico/` si queda; conservar assets plataforma
- Modify: `Lebytek_Framework/routes/` — sin marketing
- Create: `Lebytek_Framework/docs/PACKAGE-ROOT.md`
- Create: `tests/Kernel/FrameworkRootNotPortalTest.php`

**Interfaces:**
- Consumes: Tasks 7–8.
- Produces: raíz Framework usable para `php tests/run.php` Kernel/Auth, **explícitamente no desplegable**.

- [ ] **Step 1: Write failing guard test**

Create `tests/Kernel/FrameworkRootNotPortalTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('framework root does not ship Marketing domain', function () use ($root): void {
    assert_true(!is_dir($root . '/app/Domain/Marketing'));
    assert_true(!is_dir($root . '/app/Presentation/Views/publico'));
    assert_true(!is_file($root . '/database/schema/modules/marketing.sql'));
    assert_true(!is_file($root . '/routes/marketing.php'));
});

test('framework PACKAGE-ROOT doc exists and forbids deploy', function () use ($root): void {
    $doc = $root . '/docs/PACKAGE-ROOT.md';
    assert_true(is_readable($doc));
    $src = (string) file_get_contents($doc);
    assert_true(str_contains($src, 'no deploy') || str_contains($src, 'no se despliega'), 'must forbid deploy');
});
```

- [ ] **Step 2: Run to verify fail if doc missing**

```powershell
php tests/run.php FrameworkRootNotPortal
```

Expected: FAIL until doc + cleanup done.

- [ ] **Step 3: Write `docs/PACKAGE-ROOT.md`**

```markdown
# Package root layout

Este repositorio es la **fuente del paquete** `lebytek/framework`, no el sitio lebytek.com.

| Path | Uso |
|------|-----|
| `src/` | Código del paquete |
| `skeleton/` | Plantilla para nuevos tenants |
| `database/`, `scripts/` | SQL/scripts de plataforma shippeados en el paquete |
| `config/`, `public/`, stub `app/` | **Harness de tests / smoke local del mantenedor** |
| Portal | Repo hermano `Lebytek_Portal` |

**Política:** este árbol **no se despliega** en VPS. Deploy = Portal (o tenant desde skeleton) + `composer install`.

Accepted debt A7: el harness permanece hasta que CI ejecute tests contra `skeleton/` instalado.
```

- [ ] **Step 4: Clean harness root**

- `config/vertical.php`: `'marketing' => false`
- Remove any remaining marketing includes/bindings/assets `publico/`
- Keep platform assets list (same as skeleton)
- Keep enough `config/` + `routes/` for Kernel/Auth tests to boot

- [ ] **Step 5: Re-run**

```powershell
php tests/run.php FrameworkRootNotPortal
php tests/run.php Kernel
php tests/run.php Auth
```

Expected: `0 failed`.

- [ ] **Step 6: Commit**

```powershell
git add docs/PACKAGE-ROOT.md tests/Kernel/FrameworkRootNotPortalTest.php config public routes
git commit -m "chore: mark Framework root as package harness, not a site"
```

---

### Task 10: Docs de coexistencia + tenants + schema + assets

**Files:**
- Create: `Lebytek_Framework/docs/ARCHITECTURE-CONSUMER.md`
- Create: `Lebytek_Framework/docs/TENANTS.md`
- Create: `Lebytek_Framework/docs/database/SCHEMA-OWNERSHIP.md`
- Create: `Lebytek_Framework/docs/ASSETS-PLATFORM.md`
- Create: `Lebytek_Portal/docs/database/SCHEMA-OWNERSHIP.md`
- Modify: BOUNDARY doc only if paths drifted

**Interfaces:**
- Consumes: Tasks 3–9.
- Produces: guía humana/IA sin regenerar D6/D7.

- [ ] **Step 1: Write `docs/ARCHITECTURE-CONSUMER.md`**

```markdown
# Architecture: Framework as a Composer dependency

## Roles

| Role | Repo | Composer |
|------|------|----------|
| Platform | `Lebytek_Framework` | library `lebytek/framework` |
| Company tenant | `Lebytek_Portal` | project `lebytek/portal` |
| Other tenant | new repo from `skeleton/` | project |

## Path contract

- `ROOT_PATH` → consumer (env, config, app, public, storage)
- `PackagePaths::root()` → package checkout / `vendor/lebytek/framework`
- Platform SQL → `PackagePaths::schema()` / `resolveDataFile()`
- Business SQL → consumer `database/`
- Platform UI assets → consumer `public/assets/` (copied; see ASSETS-PLATFORM.md)

## Build a system

1. Copy `skeleton/` to a new git repo
2. `composer require lebytek/framework` (path locally; VCS+tag in shared envs)
3. `php scripts/migrate.php` / `seed.php` / `install.php` (wrappers)
4. Implement `App\` modules; toggle `config/vertical.php`
5. Never edit `vendor/`; never clone Portal to start a customer; never deploy Framework root

## Anti-patterns

- Dual git pull into one web root
- Copying platform `schema.sql` into the consumer as SoT
- Shipping Lebytek marketing inside skeleton
- Path-autoloading Framework `src/` from the consumer composer.json
```

- [ ] **Step 2: Write `docs/TENANTS.md`**

```markdown
# Tenants vs Framework vs Portal

| Name | Repo | Role |
|------|------|------|
| Lebytek Framework | `Lebytek_Framework` | Reusable platform package |
| Lebytek Portal | `Lebytek_Portal` | Company tenant (lebytek.com / waapi) |
| Customer X | new repo from `skeleton/` | Other client app |

Rule: never start a customer project by cloning `Lebytek_Portal`.
Local maintainer: Portal/skeleton `repositories` path → `../Lebytek_Framework`.
Production: VCS + semver/branch + committed `composer.lock`.
```

- [ ] **Step 3: Write `docs/database/SCHEMA-OWNERSHIP.md`**

```markdown
# Schema ownership

| Layer | Owner | Apply with |
|-------|-------|------------|
| Platform `auth_*`, `cfg_*`, `core_*`, … | Framework package | Consumer wrapper → package scripts (`PackagePaths`) |
| Platform modules calendario, pdf-kit, reportes, crud-engine, integrations, payments | Framework package | `resolveDataFile` / `moduleSchema` |
| Marketing / `dom_mkt_*` | **Portal** | `php scripts/migrate-marketing.php` + `database/migrations/*mkt*` |

Installer resolves migration/seed **filenames** package-first, then ROOT_PATH.
```

- [ ] **Step 4: Write `docs/ASSETS-PLATFORM.md`**

```markdown
# Platform UI assets (accepted debt A6)

`ViewHelper::asset()` serves URLs under the **consumer** `public/assets/`.
The package does not publish assets via Composer plugin in this cycle.

## Canonical files (must exist in skeleton + Portal)

- css: `app.css`, `lebytek-ui.css`, `crud-engine.css`
- js: `app.js`, `crud-engine.js`, `calendar.js`, `avatar-manager.js`, `reportes-builder.js`
- `icons/app-icon.svg`, `images/logo.png`

## On framework UI bumps

1. Diff those files in the Framework harness/`skeleton/public/assets`
2. Copy into each consumer (`Portal`, other tenants)
3. Bump `config app.asset_version` in the consumer

Follow-up (out of scope): Composer plugin or `scripts/publish-assets.php`.
```

- [ ] **Step 5: Mirror schema ownership short doc in Portal**

Same table; state: Portal never vendors a fork of platform `schema.sql`.

- [ ] **Step 6: Commit both repos**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/ARCHITECTURE-CONSUMER.md docs/TENANTS.md docs/database/SCHEMA-OWNERSHIP.md docs/ASSETS-PLATFORM.md
git commit -m "docs: consumer architecture, schema ownership, platform assets"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/database/SCHEMA-OWNERSHIP.md
git commit -m "docs: schema ownership for Portal migrations"
```

---

### Task 11: Actualizar reglas de agente (cerrar D6 en tooling)

**Files:**
- Modify: `Lebytek_Framework/.cursor/rules/framework-en-vendor.mdc` → este repo es package source; apps viven fuera
- Modify: `Lebytek_Framework/CLAUDE.md`
- Modify: `Lebytek_Framework/.cursor/rules/reglas-para-ia.mdc` where primary workflow still says edit business app here
- Create/Update: `Lebytek_Portal/.cursor/rules/framework-en-vendor.mdc` (from skeleton)

**Interfaces:**
- Consumes: Task 10 docs.
- Produces: IA defaults alineados a package+consumer.

- [ ] **Step 1: Rewrite Framework `CLAUDE.md` key table**

| Path | Role |
|------|------|
| `src/` | Framework package |
| `skeleton/` | Minimal consumer template |
| Portal | Separate repo `Lebytek_Portal` |
| Root `config/`/`public/` | Test harness only — not deploy |

- [ ] **Step 2: Portal agent rules**

Copy from `skeleton/.cursor/rules/framework-en-vendor.mdc` into Portal. Hard rule: never edit `vendor/`.

- [ ] **Step 3: Commit**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add CLAUDE.md .cursor/rules
git commit -m "docs(rules): package-source model after Portal split"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add .cursor/rules CLAUDE.md
git commit -m "docs(rules): consumer vendor-readonly guardrails"
```

---

### Task 12: Publicar repo GitHub Portal + runbook VPS (docs only, gated)

**Files:**
- Create: `Lebytek_Portal/docs/DEPLOY-VPS.md`
- Create: `Lebytek_Framework/docs/CUTOVER-PORTAL.md`
- Modify: none of production servers

**Interfaces:**
- Consumes: green Portal + pure Framework + ownership docs.
- Produces: runbook humano; **no SSH / no pull prod** sin orden explícita.

- [ ] **Step 1: Create GitHub remote for Portal**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
gh repo create Parzival2103/Lebytek_Portal --private --source=. --remote=origin --push
```

Expected: repo URL. If `gh` auth fails, stop and ask the user.

- [ ] **Step 2: Write Portal `docs/DEPLOY-VPS.md`**

Must include:

- One Portal checkout + `composer install` (never Framework git pull into same folder)
- Clone `Parzival2103/Lebytek_Portal`, `composer install --no-dev`, Composer auth for private framework
- Switch Portal `composer.json` repositories from path → VCS before prod:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
  "lebytek/framework": "dev-feature/backoffice-api-integration"
}
```

(Exact constraint follows whatever branch/tag the user approves — do not invent a new tag here.)

- Document root `public/`; never overwrite prod `.env`
- Migrate order → SCHEMA-OWNERSHIP; assets checklist → ASSETS-PLATFORM
- Smoke: landing 200, admin login, one api path
- `composer.lock` committed

- [ ] **Step 3: Write Framework `docs/CUTOVER-PORTAL.md`**

Checklist only (no prod commands):

- Portal Marketing green; Framework purity + PackagePaths + SkeletonPurity + FrameworkRootNotPortal green
- Composer auth on VPS; DB+env backup
- Point site tree to Portal; `composer install --no-dev`; smoke; retire monorepo auto-pull
- Forbidden without explicit user order: merge feature → main; forced prod migrate; disable RBAC
- Debt acceptance: A1–A8 remain unless a follow-up plan opens them

- [ ] **Step 4: Commit docs (both repos)**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/DEPLOY-VPS.md
git commit -m "docs: VPS deploy runbook for Portal"
git push

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/CUTOVER-PORTAL.md
git commit -m "docs: Portal cutover checklist (no prod execution)"
```

- [ ] **Step 5: Stop — ask user before any VPS action**

Do **not** run deploy scripts. Report Portal URL + cutover doc paths and wait.

---

## Self-review (author)

| Spec requirement | Task |
|------------------|------|
| Dos repos + Composer | 6, 7, 12 |
| No dual git pull same folder | Modelo + Task 12 |
| Autoload solo Framework vs App | 2, 7 |
| Marketing en Portal (YAGNI módulos) | 6, 8 |
| Schema ownership | 4, 10 |
| Tenants from skeleton | 5, 10 |
| Naming guide | 6 README, 10 TENANTS |
| VPS smoke checklist without executing | 12 |
| No merge to main / no prod without order | Global Constraints + Task 12 |
| Coexistencia package/consumer (paths) | Modelo + Tasks 3–5 |
| Installer/`bootstrap_sql` package-first | 3–4 (D8) |
| Skeleton limpio (código+config+assets) | 5 (D3/D4/D7/D9/D10) |
| Framework root ≠ site | 9 (D11) |
| Assets platform documented | 5, 10 (D7/A6) |
| Deuda D1–D11 cerrada / A1–A8 aceptada | registro + tasks |

Placeholder scan: none intentional. Type/name consistency: `PackagePaths::{root,schema,moduleSchema,seedsDir,resolveDataFile}`, package `lebytek/framework`, project `lebytek/portal`, dir `Lebytek_Portal`.

Gaps closed vs prior plan revision: Installer/install.php (D8), assets canon + skeleton purge beyond PHP dirs (D7/D10), no duplicate platform seeds in skeleton (D9), Framework root harness vs deployable (D11), explicit ownership layers for coexistence.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
