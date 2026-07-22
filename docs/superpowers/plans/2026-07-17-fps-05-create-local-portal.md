# FPS Plan 05 — Crear `Lebytek_Portal` local

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear el proyecto Composer sibling `Lebytek_Portal` con el negocio completo del Portal copiado desde un SHA congelado de `feature/backoffice-api-integration`, consumiendo `lebytek/framework` vía path repo local, con baseline Marketing verde y manifiesto de ownership validado.

**Architecture:** El Portal es un proyecto `type: project` con autoload solo `App\`. El código de plataforma llega exclusivamente por `vendor/lebytek/framework` (rama `consolidation/framework-portal-separation` tras Plans 01–04). La copia de negocio sale del SHA `dad0590` con `git checkout <sha> -- <path>` — **no** se clona la feature ni se usan worktrees/ramas derivadas para el árbol Portal. El Portal **no** contiene `src/` ni `database/schema/schema.sql` de plataforma como SoT; conserva assets UI en `public/assets/` (incluido `publico/`) y SQL de negocio `marketing*.sql` / `*mkt*`.

**Tech Stack:** PHP 8.1+, Composer path repository (`../Lebytek_Framework`), Git, PowerShell, microtest (`php tests/run.php Marketing`).

**Reemplaza para ejecución (parcial):** Task 6 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`.

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 05.

**Predecesor obligatorio:** Plan 04 (`2026-07-17-fps-04-minimal-consumer-skeleton.md`) — skeleton mínimo verde en `consolidation/framework-portal-separation`.

**Sucesor:** Plan 06 (`2026-07-17-fps-06-local-boundary-cutover.md`).

## Global Constraints

- Rama Framework de trabajo: **`consolidation/framework-portal-separation`** (Portal apunta a este árbol vía path repo).
- SHA fuente congelado Portal: **`dad059056d26b6eb527815f85cf71ecd507a57fe`** (`feature/backoffice-api-integration`).
- Directorio sibling canónico: **`c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal`**.
- Manifiesto de frontera: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`.
- **Prohibido:** merge `feature/backoffice-api-integration` → `main`; worktrees o ramas derivadas para crear Portal; copiar `src/` o `database/schema/schema.sql` al Portal; deploy, SSH, push remoto, editar `vendor/` manualmente, secretos en commits.
- Gate principal Portal: `php tests/run.php Marketing` → **`0 failed`** (con `.env` local copiado, nunca commiteado).
- Gate documental: test `PortalOwnership` → **`0 failed`** (Task 4).

---

### Task 1: Congelar SHA y preparar directorio Portal (TDD documental)

**Files:**
- Create: `c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\` (directorio vacío inicial)
- Create: `Lebytek_Portal/docs/superpowers/FPS-portal-source-sha.md`
- Create: `Lebytek_Portal/tests/Kernel/PortalOwnershipTest.php` (versión inicial — solo SHA doc)

**Interfaces:**
- Consumes: `docs/superpowers/FPS-git-baseline.md`; ref `feature/backoffice-api-integration`.
- Produces: directorio Portal inicializado con git; doc SHA congelado; test documental que falla hasta completar Task 2.

- [ ] **Step 1: Verificar SHA congelado en el repo Framework**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git fetch origin feature/backoffice-api-integration 2>&1
$expected = "dad059056d26b6eb527815f85cf71ecd507a57fe"
$actual = git rev-parse feature/backoffice-api-integration
if ($actual -ne $expected) {
  Write-Error "SHA drift: expected $expected got $actual — stop for human decision"
  exit 1
}
Write-Output "OK frozen SHA $actual"
```

Expected: `OK frozen SHA dad059056d26b6eb527815f85cf71ecd507a57fe`. Si el SHA difiere, **detener** — no continuar sin decisión humana (roadmap: delta posterior requiere plan aparte).

- [ ] **Step 2: Crear directorio Portal e inicializar git**

Run:

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
if (Test-Path $portal) {
  $items = Get-ChildItem $portal -Force -ErrorAction SilentlyContinue
  if ($items.Count -gt 0) {
    Write-Error "Lebytek_Portal must be empty or absent — found existing files"
    exit 1
  }
}
New-Item -ItemType Directory -Force -Path $portal | Out-Null
Set-Location $portal
git init
git checkout -b main
git branch --show-current
```

Expected: `main`.

- [ ] **Step 3: Write the failing ownership test (SHA doc gate)**

Create `Lebytek_Portal/tests/Kernel/PortalOwnershipTest.php`:

```php
<?php

declare(strict_types=1);

$portalRoot = dirname(__DIR__, 2);

test('portal documents frozen source SHA', function () use ($portalRoot): void {
    $doc = $portalRoot . '/docs/superpowers/FPS-portal-source-sha.md';
    assert_true(is_readable($doc), 'FPS-portal-source-sha.md must exist');
    $src = (string) file_get_contents($doc);
    assert_true(
        str_contains($src, 'dad059056d26b6eb527815f85cf71ecd507a57fe'),
        'must record frozen feature SHA'
    );
});

test('portal ships Marketing domain from frozen source', function () use ($portalRoot): void {
    assert_true(is_dir($portalRoot . '/app/Domain/Marketing'), 'Domain/Marketing required');
    assert_true(is_dir($portalRoot . '/app/Application/Marketing'), 'Application/Marketing required');
    assert_true(is_dir($portalRoot . '/app/Infrastructure/Marketing'), 'Infrastructure/Marketing required');
    assert_true(is_dir($portalRoot . '/app/Infrastructure/Integrations/LebytekApi'), 'LebytekApi required');
    assert_true(is_dir($portalRoot . '/app/Presentation/Controllers/Publico'), 'Controllers/Publico required');
});

test('portal does not ship framework src or platform schema SoT', function () use ($portalRoot): void {
    assert_true(!is_dir($portalRoot . '/src'), 'no src/ in Portal');
    assert_true(!is_file($portalRoot . '/database/schema/schema.sql'), 'no platform schema.sql SoT');
    assert_true(!is_dir($portalRoot . '/skeleton'), 'no skeleton/ in Portal');
});
```

- [ ] **Step 4: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\tests\run.php" PortalOwnership 2>&1
```

Expected: FAIL — doc y dirs Marketing ausentes (runner invocado desde Framework hasta que Portal tenga su propio `tests/run.php` en Task 3; si el filtro no existe aún, ejecutar el archivo directamente con el bootstrap Framework y confirmar FAIL).

Alternativa hasta Task 3:

```powershell
php -r "require 'c:/Users/User/OneDrive/Desktop/sistemas/Lebytek_Framework/tests/lib/bootstrap.php'; require 'tests/Kernel/PortalOwnershipTest.php';" 2>&1
```

Expected: FAIL on `FPS-portal-source-sha.md must exist`.

- [ ] **Step 5: Write `docs/superpowers/FPS-portal-source-sha.md`**

Create `Lebytek_Portal/docs/superpowers/FPS-portal-source-sha.md`:

```markdown
# FPS — Portal source SHA (frozen)

**Registrado:** 2026-07-17  
**Repo origen:** `Lebytek_Framework`  
**Ref:** `feature/backoffice-api-integration`  
**SHA congelado:** `dad059056d26b6eb527815f85cf71ecd507a57fe`

## Política

- Todo el código Portal en este repo se extrajo con `git checkout dad059056d26b6eb527815f85cf71ecd507a57fe -- <path>` desde `Lebytek_Framework`.
- Commits posteriores a este SHA en la feature **no** entran automáticamente.
- Plataforma (`Lebytek\Framework\`, SQL plataforma) vive en `vendor/lebytek/framework` vía Composer.
- **No** se usaron worktrees ni ramas derivadas de la feature para crear este árbol.

## Framework dependency (local)

- Path repo: `../Lebytek_Framework` (rama `consolidation/framework-portal-separation`).
- Producción futura: VCS + tag — ver Plan 08.
```

- [ ] **Step 6: Commit Portal scaffold**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/superpowers/FPS-portal-source-sha.md tests/Kernel/PortalOwnershipTest.php
git commit -m "docs(fps): record frozen Portal source SHA and ownership test scaffold"
```

---

### Task 2: Copiar paths de propiedad Portal desde SHA congelado

**Files:**
- Create: `Lebytek_Portal/app/**`, `config/**`, `routes/**`, `public/**`, `storage/**`, `database/**`, `tests/**`, `docs/integration/**`, `scripts/**` (desde SHA)
- Modify: none in Framework (pureza en Plan 06)

**Interfaces:**
- Consumes: SHA `dad059056d26b6eb527815f85cf71ecd507a57fe`; allowlist portal en `BOUNDARY-framework-vs-portal-fps.md`.
- Produces: árbol Portal con Marketing, LebytekApi, SQL `marketing*.sql`, migrations `*mkt*`, assets `public/assets/publico/`; **sin** `src/`, **sin** `database/schema/schema.sql`.

- [ ] **Step 1: Exportar paths portal desde SHA congelado**

Run desde Framework (no modifica working tree permanente — checkout a Portal):

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$sha = "dad059056d26b6eb527815f85cf71ecd507a57fe"
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"

$portalPaths = @(
  'app',
  'config',
  'routes',
  'public',
  'storage',
  'docs/integration'
)

foreach ($p in $portalPaths) {
  git checkout $sha -- $p
  if (Test-Path $p) {
    Copy-Item -Recurse -Force $p (Join-Path $portal $p)
    git checkout HEAD -- $p 2>$null
    git clean -fd $p 2>$null
  }
}

New-Item -ItemType Directory -Force -Path "$portal\database\migrations" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\database\schema\modules" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\scripts" | Out-Null
New-Item -ItemType Directory -Force -Path "$portal\tests\lib" | Out-Null

git checkout $sha -- database/migrations
Get-ChildItem database/migrations -Filter '*mkt*' -ErrorAction SilentlyContinue | ForEach-Object {
  Copy-Item -Force $_.FullName "$portal\database\migrations\"
}
git checkout HEAD -- database/migrations 2>$null

git checkout $sha -- database/schema/modules/marketing.sql database/schema/modules/marketing_demo.sql 2>$null
if (Test-Path database/schema/modules/marketing.sql) {
  Copy-Item -Force database/schema/modules/marketing*.sql "$portal\database\schema\modules\"
  git checkout HEAD -- database/schema/modules/marketing.sql database/schema/modules/marketing_demo.sql 2>$null
}

git checkout $sha -- tests/Marketing tests/run.php tests/bootstrap.php tests/lib tests/fixtures 2>$null
foreach ($t in @('Marketing','run.php','bootstrap.php','lib','fixtures')) {
  $src = Join-Path 'tests' $t
  if (Test-Path $src) {
    Copy-Item -Recurse -Force $src (Join-Path $portal $src)
  }
}
git checkout HEAD -- tests 2>$null

git checkout $sha -- .env.example 2>$null
if (Test-Path .env.example) {
  Copy-Item -Force .env.example "$portal\.env.example"
  git checkout HEAD -- .env.example 2>$null
}
```

- [ ] **Step 2: Copiar scripts operativos Portal (no scripts de plataforma migrate/seed del paquete)**

Run:

```powershell
$fw = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
$sha = "dad059056d26b6eb527815f85cf71ecd507a57fe"

$portalScripts = @(
  'vps-deploy-lebytek-com.sh','vps-deploy-waapi.sh','vps-finalize-lebytek.sh',
  'vps-fix-lebytek-db.sh','vps-fix-lebytek-ssl.sh','vps-restore-lebytek-nginx-ssl.sh',
  'vps-setup-lebytek-db.sh','lebytek-api-health.php','lead-lifecycle-report.php',
  'confirm-api-lifecycle.php','expire-api-demos.php','resend-lead-credentials.php',
  'smoke-send-test-message.php','deprovision-debug.php','cleanup-orphan-lead-instance.php',
  'backfill-lead-instance-ids.php','apply-sql-migration.php','email-render-smoke.php',
  'smtp-probe.php','route-probe.php','test-mail.php'
)

cd $fw
foreach ($s in $portalScripts) {
  git checkout $sha -- "scripts/$s" 2>$null
  if (Test-Path "scripts/$s") {
    Copy-Item -Force "scripts/$s" "$portal\scripts\"
    git checkout HEAD -- "scripts/$s" 2>$null
  }
}
```

**No copiar:** `database/schema/schema.sql`, `src/`, `skeleton/`, seeds/migrations plataforma fuera de `*mkt*`.

- [ ] **Step 3: Verificar ausencia de paths prohibidos**

Run:

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
@(
  (Test-Path "$portal\src"),
  (Test-Path "$portal\database\schema\schema.sql"),
  (Test-Path "$portal\skeleton"),
  (Test-Path "$portal\app\Domain\Marketing"),
  (Test-Path "$portal\database\schema\modules\marketing.sql"),
  (Test-Path "$portal\public\assets\publico")
) | ForEach-Object { Write-Output $_ }
```

Expected: `False`, `False`, `False`, `True`, `True`, `True` (Marketing y publico **presentes**; src/schema.sql/skeleton **ausentes**).

- [ ] **Step 4: Commit Portal tree copy**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add -A
git status --short | Select-Object -First 20
git commit -m "chore: copy Portal business tree from frozen feature SHA dad0590"
```

---

### Task 3: Proyecto Composer, wrappers y smoke de clases

**Files:**
- Create: `Lebytek_Portal/composer.json`, `.gitignore`, `README.md`, `CLAUDE.md`
- Create: `Lebytek_Portal/scripts/migrate.php`, `seed.php`, `install.php`, `migrate-marketing.php`
- Modify: `Lebytek_Portal/config/vertical.php` (`marketing` => true)

**Interfaces:**
- Consumes: Plan 03 wrappers skeleton; path repo `../Lebytek_Framework`.
- Produces: `vendor/lebytek/framework` instalado; clases `Lebytek\Framework\Kernel\Bootstrap` y `App\Infrastructure\Integrations\LebytekApi\LebytekApiClient` resolubles.

- [ ] **Step 1: Write Portal `composer.json`**

Create `Lebytek_Portal/composer.json`:

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

- [ ] **Step 2: Write Portal `.gitignore`**

Create `Lebytek_Portal/.gitignore`:

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

- [ ] **Step 3: Write thin script wrappers**

Create `Lebytek_Portal/scripts/migrate.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/migrate.php';
```

Create `Lebytek_Portal/scripts/seed.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/seed.php';
```

Create `Lebytek_Portal/scripts/install.php`:

```php
<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/vendor/lebytek/framework/scripts/install.php';
```

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

- [ ] **Step 4: Enable marketing in Portal vertical**

In `Lebytek_Portal/config/vertical.php`, set:

```php
'marketing' => true,
```

(preserve other module flags from copied config; do not force `payments` ON here — Payments genérico es plataforma Framework).

- [ ] **Step 5: Write Portal `README.md` and `CLAUDE.md`**

Create `Lebytek_Portal/README.md` with:

```markdown
# Lebytek Portal

Tenant empresa Lebytek (lebytek.com / waapi). **No** es el framework reutilizable.

- Nuevos clientes: partir de `Lebytek_Framework/skeleton/`, **nunca** clonar este repo.
- Setup local: `cp .env.example .env`, `composer install`, `php scripts/migrate.php`, `php scripts/seed.php`, `php scripts/migrate-marketing.php`, `php -S localhost:8000 -t public`
- Tests negocio: `php tests/run.php Marketing`
- Framework local: path repo `../Lebytek_Framework` (rama consolidación)
- Producción futura: VCS + `composer.lock` — ver Plan 08
```

Create `Lebytek_Portal/CLAUDE.md`:

```markdown
# CLAUDE.md — Lebytek Portal

Portal tenant Lebytek. El framework vive en `vendor/lebytek/framework` (**solo lectura**).

| Área | Path |
|------|------|
| Negocio Marketing | `app/`, `config/`, `routes/`, `database/` (`*mkt*`, `marketing*.sql`) |
| Tests negocio | `tests/Marketing/` |
| Plataforma | repo `Lebytek_Framework` → `composer update lebytek/framework` |

Reglas:
- Nunca editar `vendor/`
- Nunca copiar `schema.sql` de plataforma como SoT
- Assets UI plataforma en `public/assets/`; producto en `public/assets/publico/`
- Variables api: `LEBYTEK_API_URL`, `LEBYTEK_API_TOKEN` en `.env`
```

- [ ] **Step 6: `composer install` + class smoke**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git checkout consolidation/framework-portal-separation
git branch --show-current

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer install
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') ? 'OK_BOOTSTRAP' : 'FAIL'; echo PHP_EOL; echo class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK_PATHS' : 'FAIL_PATHS'; echo PHP_EOL; echo class_exists('App\\Infrastructure\\Integrations\\LebytekApi\\LebytekApiClient') ? 'OK_API' : 'FAIL_API'; echo PHP_EOL;"
```

Expected:

```text
OK_BOOTSTRAP
OK_PATHS
OK_API
```

- [ ] **Step 7: Commit Portal Composer project**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add composer.json .gitignore README.md CLAUDE.md scripts config/vertical.php
git commit -m "chore: Portal Composer project with path repo to lebytek/framework"
```

---

### Task 4: Baseline Marketing y gate de ownership

**Files:**
- Modify: `Lebytek_Portal/tests/lib/bootstrap.php` (cargar `ROOT_PATH/vendor/autoload.php`)
- Modify: `Lebytek_Portal/tests/Kernel/PortalOwnershipTest.php` (completo)
- Modify: `.superpowers/sdd/progress.md` (Framework repo, append)

**Interfaces:**
- Consumes: Portal con `vendor/` instalado; `.env` local (copiado de Framework dev, **no** commiteado).
- Produces: Marketing suite verde; PortalOwnership verde; registro SDD Plan 05.

- [ ] **Step 1: Fix Portal test bootstrap**

Ensure `Lebytek_Portal/tests/lib/bootstrap.php` loads consumer vendor:

```php
<?php

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

require ROOT_PATH . '/vendor/autoload.php';

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
```

- [ ] **Step 2: Prepare local `.env` for Marketing (never commit)**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
if (-not (Test-Path .env)) {
  Copy-Item .env.example .env
  Write-Output "Created .env from example — fill DB credentials from local Framework .env if needed"
}
```

- [ ] **Step 3: Run Marketing baseline**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php tests/run.php Marketing 2>&1 | Select-Object -Last 5
```

Expected: última línea `N passed, 0 failed`.

- [ ] **Step 4: Run PortalOwnership gate**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php tests/run.php PortalOwnership 2>&1 | Select-Object -Last 3
```

Expected: `N passed, 0 failed`.

- [ ] **Step 5: Register SDD progress (Framework repo) + commits**

Append to `Lebytek_Framework/.superpowers/sdd/progress.md`:

```markdown
## Plan 05 — Lebytek_Portal local (2026-07-17)

- [x] SHA congelado dad0590 documentado
- [x] Árbol Portal sibling sin src/schema plataforma SoT
- [x] composer path repo → consolidation/framework-portal-separation
- [x] Marketing baseline 0 failed
- [x] PortalOwnership 0 failed
- Siguiente: Plan 06 boundary cutover
```

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add tests
git commit -m "test: green Marketing suite and Portal ownership gate on local Portal"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 05 completion in SDD progress"
```

---

## Self-review (author)

| Requisito roadmap Plan 05 | Task |
|---------------------------|------|
| Congelar SHA feature | Task 1 Step 1 + FPS-portal-source-sha.md |
| Crear Lebytek_Portal sin modificar feature | Task 1–2 (checkout paths, no worktrees) |
| Copiar solo ownership Portal | Task 2 allowlist BOUNDARY |
| Path repo Composer local | Task 3 composer.json |
| Baseline Marketing | Task 4 Step 3 |
| Sin `src/` ni schema plataforma SoT | Task 2 Step 3 + PortalOwnership |
| No worktrees/ramas derivadas | Global Constraints + FPS-portal-source-sha.md |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: SHA `dad059056d26b6eb527815f85cf71ecd507a57fe`; path `c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal`; wrappers delegan a `vendor/lebytek/framework/scripts/*.php`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-05-create-local-portal.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
