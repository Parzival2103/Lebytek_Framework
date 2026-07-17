# FPS Plan 06 — Corte de frontera local Framework ↔ Portal

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dejar `Lebytek_Framework` y `Lebytek_Portal` como árboles independientes: el paquete deja de autoload-ear `App\`, retira Marketing y negocio Portal de la raíz Framework, reduce la raíz a paquete + harness de tests, y valida gates cruzados sin reintroducir Marketing al Framework.

**Architecture:** Tras Plan 05, el Portal ya consume `lebytek/framework` por Composer. Este plan cierra **D1**, **D5** y **D11**: `composer.json` library solo exporta `Lebytek\Framework\`; paths portal (`app/Domain/Marketing`, `marketing*.sql`, `*mkt*`, LebytekApi, Publico) se eliminan del Framework; la raíz conserva harness mínimo (`config/`, `public/assets` plataforma, stub `app/README.md`) exclusivamente para `php tests/run.php` Kernel/Auth — **no deploy**. Si un gate falla, se corrige ownership en Portal o contrato plataforma; **nunca** se restaura Marketing en el paquete.

**Tech Stack:** PHP 8.1+, Composer, microtest (`php tests/run.php`), PowerShell.

**Reemplaza para ejecución (parcial):** Tasks 7–9 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`.

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 06.

**Predecesor obligatorio:** Plan 05 (`2026-07-17-fps-05-create-local-portal.md`) — Portal local verde con Marketing.

**Sucesor:** Plan 07 (`2026-07-17-fps-07-documentation-agent-rules.md`).

## Global Constraints

- Rama Framework: **`consolidation/framework-portal-separation`**.
- Portal sibling: **`c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal`** (debe existir con Marketing verde).
- **Prohibido:** merge feature→main; reintroducir Marketing/`App\` al paquete para "arreglar" tests; deploy, SSH, push remoto, editar `vendor/` manualmente, secretos en commits.
- Gates Framework (todos **`0 failed`**):
  - `php tests/run.php PackageAutoloadBoundary`
  - `php tests/run.php PackagePaths`
  - `php tests/run.php SkeletonPurity`
  - `php tests/run.php PlatformSqlResolve`
  - `php tests/run.php FrameworkRootNotPortal`
  - `php tests/run.php Kernel`
  - `php tests/run.php` (suite completa plataforma)
- Gates Portal (todos **`0 failed`**):
  - `composer validate`
  - `php tests/run.php Marketing`

---

### Task 1: Pureza autoload — quitar `App\` del paquete (TDD)

**Files:**
- Create: `tests/Kernel/PackageAutoloadBoundaryTest.php` (si no existe)
- Modify: `composer.json` (quitar `"App\\": "app/"`)
- Modify: `docs/composer-setup.md`, `README.md` (encabezados package-only; **no** reescribir `CLAUDE.md` ni `.cursor/rules` — eso es Plan 07)

**Interfaces:**
- Consumes: Portal + skeleton con path repo (Plan 04–05).
- Produces: `lebytek/framework` autoload **solo** `Lebytek\Framework\` → `src/`.

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

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackageAutoloadBoundary 2>&1 | Select-Object -Last 3
```

Expected: FAIL on `framework package does not autoload App\\`.

- [ ] **Step 3: Update Framework `composer.json`**

Replace autoload block; keep Payments dependency from Plan 01:

```json
{
    "name": "lebytek/framework",
    "description": "Lebytek Framework — reusable PHP platform (Kernel, RBAC, CRUD Engine, Payments OFF by default)",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": ">=8.1",
        "dompdf/dompdf": "^3.1",
        "phpmailer/phpmailer": "^7.1",
        "stripe/stripe-php": "^16.0"
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

- [ ] **Step 4: Regenerate autoload and verify**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
composer dump-autoload
php tests/run.php PackageAutoloadBoundary 2>&1 | Select-Object -Last 3
Select-String -Path vendor/composer/autoload_psr4.php -Pattern "App\\\\" -SimpleMatch
```

Expected: PackageAutoloadBoundary `0 failed`; Select-String **sin** coincidencias `App\\`.

- [ ] **Step 5: Re-install consumers and smoke classes**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer update lebytek/framework --no-interaction
php -r "require 'vendor/autoload.php'; echo (class_exists('Lebytek\\Framework\\Kernel\\Bootstrap') && class_exists('App\\Infrastructure\\Integrations\\LebytekApi\\LebytekApiClient')) ? 'OK_PORTAL' : 'FAIL'; echo PHP_EOL;"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\skeleton"
composer update lebytek/framework --no-interaction
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Kernel\\PackagePaths') ? 'OK_SKELETON' : 'FAIL'; echo PHP_EOL;"
```

Expected: `OK_PORTAL` y `OK_SKELETON`.

- [ ] **Step 6: Update README header (Framework)**

Prepend to `README.md`:

```markdown
# Lebytek Framework

Paquete Composer `lebytek/framework`. El portal de la empresa Lebytek vive en el repo **Lebytek_Portal**.
Los tenants nuevos parten de `skeleton/`, no del Portal. **No desplegar este repo como document root.**
```

- [ ] **Step 7: Commit Framework autoload purity**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add composer.json tests/Kernel/PackageAutoloadBoundaryTest.php README.md docs/composer-setup.md
git commit -m "chore(composer): drop App autoload from framework package"
```

---

### Task 2: Retirar negocio Portal del Framework (D5)

**Files:**
- Delete: `app/Application/Marketing/`, `Domain/Marketing/`, `Infrastructure/Marketing/`, `Infrastructure/Integrations/LebytekApi/`, `Presentation/Controllers/Publico/`, `Presentation/Views/publico/`
- Delete: `tests/Marketing/`, `database/schema/modules/marketing*.sql`, `database/migrations/*mkt*`
- Delete: `config/modules/marketing.php`, `config/cruds/mkt_*.json`, `routes/marketing.php`
- Modify: root `config/container.php` (quitar bloque Marketing completo)
- Create: `app/README.md`

**Interfaces:**
- Consumes: Portal con Marketing verde (abort si Portal incompleto).
- Produces: Framework sin CRM; SoT Marketing solo en `Lebytek_Portal`.

- [ ] **Step 1: Safety check — Portal must own Marketing before delete**

Run:

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
@(
  (Test-Path "$portal\app\Domain\Marketing"),
  (Test-Path "$portal\database\schema\modules\marketing.sql"),
  (Test-Path "$portal\tests\Marketing")
) | ForEach-Object {
  if (-not $_) { Write-Error "Portal missing Marketing — abort Framework delete"; exit 1 }
}
Write-Output "OK Portal owns Marketing"
```

Expected: `OK Portal owns Marketing`. **Abort** si cualquier `False`.

- [ ] **Step 2: Remove Portal business from Framework**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git rm -r app/Application/Marketing app/Domain/Marketing app/Infrastructure/Marketing 2>$null
git rm -r app/Infrastructure/Integrations/LebytekApi 2>$null
git rm -r app/Presentation/Controllers/Publico app/Presentation/Views/publico 2>$null
git rm -r tests/Marketing 2>$null
git rm database/schema/modules/marketing.sql database/schema/modules/marketing_demo.sql 2>$null
Get-ChildItem database/migrations -Filter '*mkt*' -ErrorAction SilentlyContinue | ForEach-Object { git rm $_.FullName }
git rm config/modules/marketing.php routes/marketing.php 2>$null
Get-ChildItem config/cruds -Filter 'mkt_*.json' -ErrorAction SilentlyContinue | ForEach-Object { git rm $_.FullName }
```

In root `config/container.php`: delete entire Marketing binding block (any line referencing `App\\Infrastructure\\Marketing`, `App\\Domain\\Marketing`, `App\\Application\\Marketing`, Stripe use cases de negocio).

Create `app/README.md`:

```markdown
# app/ en el repo Framework

El código de aplicación **no** vive aquí (stub/harness de tests).

- Plantilla vacía: `skeleton/app/`
- Portal Lebytek: repo `Lebytek_Portal`
- Este repo **no** se despliega como sitio
```

- [ ] **Step 3: Grep leftover marketing references in Framework (exclude skeleton)**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
rg -n "Domain\\\\Marketing|modules/marketing|cruds/mkt_|marketing_demo" scripts database src config routes --glob '!skeleton/**' 2>&1
```

Expected: **sin** hits en paths de plataforma activa. Corregir leftovers (bindings, includes) sin reintroducir clases Marketing.

- [ ] **Step 4: Run Framework purity subsets**

Run:

```powershell
php tests/run.php SkeletonPurity 2>&1 | Select-Object -Last 3
php tests/run.php PlatformSqlResolve 2>&1 | Select-Object -Last 3
```

Expected: ambos `0 failed`.

- [ ] **Step 5: Confirm Portal Marketing still green**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php tests/run.php Marketing 2>&1 | Select-Object -Last 3
```

Expected: `0 failed`. Si falla: arreglar Portal (paths, bindings, `.env`) — **no** restaurar Marketing en Framework.

- [ ] **Step 6: Commit Framework business removal**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add app/README.md config routes database
git commit -m "chore: remove Portal business and marketing schema from Framework"
```

---

### Task 3: Raíz Framework = paquete + harness (D11)

**Files:**
- Create: `docs/PACKAGE-ROOT.md`
- Create: `tests/Kernel/FrameworkRootNotPortalTest.php`
- Modify: `config/vertical.php` (`marketing` => false)
- Modify: `public/` — quitar `assets/publico/` si existe; conservar assets plataforma canónicos
- Modify: `routes/` — quitar `require marketing.php`

**Interfaces:**
- Consumes: Task 2 (sin Marketing en Framework).
- Produces: raíz documentada como no-desplegable; harness Kernel/Auth bootable.

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

test('framework root vertical keeps marketing OFF', function () use ($root): void {
    $vertical = require $root . '/config/vertical.php';
    assert_same(false, $vertical['modules']['marketing'] ?? null);
});

test('framework PACKAGE-ROOT doc exists and forbids deploy', function () use ($root): void {
    $doc = $root . '/docs/PACKAGE-ROOT.md';
    assert_true(is_readable($doc));
    $src = (string) file_get_contents($doc);
    assert_true(
        str_contains($src, 'no deploy') || str_contains($src, 'no se despliega'),
        'must forbid deploy'
    );
});
```

- [ ] **Step 2: Run to verify fail until cleanup**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php FrameworkRootNotPortal 2>&1 | Select-Object -Last 3
```

Expected: FAIL until doc + vertical + assets cleanup.

- [ ] **Step 3: Write `docs/PACKAGE-ROOT.md`**

Create `docs/PACKAGE-ROOT.md`:

```markdown
# Package root layout

Este repositorio es la **fuente del paquete** `lebytek/framework`, no el sitio lebytek.com.

| Path | Uso |
|------|-----|
| `src/` | Código del paquete |
| `skeleton/` | Plantilla mínima para nuevos tenants |
| `database/`, `scripts/` | SQL/scripts de plataforma shippeados en el paquete |
| `config/`, `public/`, stub `app/` | **Harness de tests / smoke local del mantenedor** |
| Portal | Repo hermano `Lebytek_Portal` |

**Política:** este árbol **no se despliega** en VPS. Deploy = Portal (o tenant desde skeleton) + `composer install`.

Deuda aceptada A7: el harness permanece hasta CI contra `skeleton/` instalado.
```

- [ ] **Step 4: Clean harness root**

In `config/vertical.php`:

```php
'marketing' => false,
```

Remove `public/assets/publico/` if present. Keep canonical platform assets:

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

Remove any remaining `require` of `marketing.php` in `routes/web.php`.

- [ ] **Step 5: Re-run FrameworkRootNotPortal + Kernel**

Run:

```powershell
php tests/run.php FrameworkRootNotPortal 2>&1 | Select-Object -Last 3
php tests/run.php Kernel 2>&1 | Select-Object -Last 3
php tests/run.php Auth 2>&1 | Select-Object -Last 3
```

Expected: all `0 failed`.

- [ ] **Step 6: Commit harness cleanup**

```powershell
git add docs/PACKAGE-ROOT.md tests/Kernel/FrameworkRootNotPortalTest.php config public routes
git commit -m "chore: mark Framework root as package harness, not a site"
```

---

### Task 4: Gates cruzados Framework + Portal

**Files:**
- Modify: `.superpowers/sdd/progress.md` (Framework)

**Interfaces:**
- Consumes: Tasks 1–3; Portal estable.
- Produces: evidencia gates verdes; registro SDD Plan 06.

- [ ] **Step 1: Framework full platform gate**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php PackageAutoloadBoundary 2>&1 | Select-Object -Last 1
php tests/run.php PackagePaths 2>&1 | Select-Object -Last 1
php tests/run.php SkeletonPurity 2>&1 | Select-Object -Last 1
php tests/run.php PlatformSqlResolve 2>&1 | Select-Object -Last 1
php tests/run.php FrameworkRootNotPortal 2>&1 | Select-Object -Last 1
php tests/run.php Kernel 2>&1 | Select-Object -Last 1
php tests/run.php 2>&1 | Select-Object -Last 1
```

Expected: cada línea termina con `0 failed`.

- [ ] **Step 2: Portal gates**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer validate
php tests/run.php Marketing 2>&1 | Select-Object -Last 1
php tests/run.php PortalOwnership 2>&1 | Select-Object -Last 1
```

Expected: `composer validate` exit 0; tests `0 failed`.

- [ ] **Step 3: Document cross-tree smoke (manual, no prod)**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
php -r "require 'vendor/autoload.php'; echo class_exists('Lebytek\\Framework\\Domain\\Payments\\PaymentEventType') ? 'OK_PAYMENTS_PLATFORM' : 'FAIL'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('App\\Application\\Marketing\\ActivateMembershipFromOrderService') ? 'OK_MARKETING_PORTAL' : 'FAIL'; echo PHP_EOL;"
```

Expected:

```text
OK_PAYMENTS_PLATFORM
OK_MARKETING_PORTAL
```

Confirms: Payments genérico en vendor Framework; Marketing en Portal `App\`.

- [ ] **Step 4: Register SDD + commit progress**

Append to `Lebytek_Framework/.superpowers/sdd/progress.md`:

```markdown
## Plan 06 — Boundary cutover local (2026-07-17)

- [x] App\\ removed from package autoload
- [x] Marketing/Portal business removed from Framework root
- [x] PACKAGE-ROOT harness documented
- [x] Framework platform suite 0 failed
- [x] Portal composer validate + Marketing 0 failed
- Siguiente: Plan 07 documentation and agent rules
```

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 06 cross-tree gates green"
```

---

## Self-review (author)

| Requisito roadmap Plan 06 | Task |
|---------------------------|------|
| Quitar App\\ autoload | Task 1 |
| README/composer-setup only (CLAUDE/rules → Plan 07) | Task 1 |
| Retirar Marketing/negocio Framework | Task 2 |
| Raíz package + harness | Task 3 |
| Pruebas cruzadas | Task 4 |
| No reintroducir Marketing | Global Constraints + Task 2 Step 5 |
| Gates PackageAutoloadBoundary, Kernel, suite | Task 4 Step 1 |
| Gate composer validate + Marketing Portal | Task 4 Step 2 |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: Payments (`Lebytek\Framework\Domain\Payments\`) en Framework; Marketing (`App\Application\Marketing\`) en Portal.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-06-local-boundary-cutover.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
