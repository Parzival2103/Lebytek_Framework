# FPS Plan 07 — Documentación y reglas permanentes

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publicar documentación de arquitectura consumidor/paquete, ownership SQL, assets, guía tenants y README en ambos repos; actualizar `CLAUDE.md` y reglas Cursor para que IA y humanos no reconstruyan el monorepo desplegable. Declarar explícitamente: **Payments genérico → Framework**; **Marketing → Portal**.

**Architecture:** La documentación es el guardrail permanente post-cutover (cierra **D6**). Cada doc incluye tests documentales (grep/assert) verificables en CI local. Las reglas Cursor del Framework dejan de describir monorepo desplegable; las del Portal heredan el patrón `framework-en-vendor` del skeleton. Ningún cambio runtime en este plan salvo archivos markdown/mdc.

**Tech Stack:** Markdown, Cursor rules (`.mdc`), microtest documental vía PowerShell `Select-String`.

**Reemplaza para ejecución (parcial):** Tasks 10–11 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`.

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 07.

**Predecesor obligatorio:** Plan 06 (`2026-07-17-fps-06-local-boundary-cutover.md`) — árboles independientes verdes.

**Sucesor:** Plan 08 (`2026-07-17-fps-08-publication-cutover-readiness.md`).

## Global Constraints

- Repos: **`Lebytek_Framework`** (`consolidation/framework-portal-separation`) y **`Lebytek_Portal`** (sibling).
- **Prohibido:** merge feature→main; deploy, SSH, push remoto, editar `vendor/`, secretos en commits.
- Este plan es **docs + rules only** — no modifica PHP/SQL/config runtime.
- **Ownership exclusivo:** Plan 07 es el **único** plan que reescribe `CLAUDE.md` y `.cursor/rules/` en Framework y Portal. Plan 06 solo toca `README.md` y `docs/composer-setup.md` (encabezados package-only).
- Gates documentales (todos deben PASS):
  - Framework: archivos listados en Task 1 existen y contienen frases clave.
  - Portal: `docs/database/SCHEMA-OWNERSHIP.md` + README/CLAUDE actualizados.
  - Reglas: Framework `CLAUDE.md` **no** dice "monorepo desplegable"; Portal rules exigen vendor read-only.

---

### Task 1: Documentación Framework (arquitectura, tenants, schema, assets)

**Files:**
- Create: `docs/ARCHITECTURE-CONSUMER.md`
- Create: `docs/TENANTS.md`
- Create: `docs/database/SCHEMA-OWNERSHIP.md`
- Create: `docs/ASSETS-PLATFORM.md`
- Create: `tests/Docs/FpsDocumentationTest.php`

**Interfaces:**
- Consumes: `docs/PACKAGE-ROOT.md` (Plan 06); BOUNDARY manifest.
- Produces: guía humana/IA; test `FpsDocumentation` verificando presencia y frases obligatorias.

- [ ] **Step 1: Write the failing documentation test**

Create `tests/Docs/FpsDocumentationTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('ARCHITECTURE-CONSUMER forbids deploying Framework root', function () use ($root): void {
    $path = $root . '/docs/ARCHITECTURE-CONSUMER.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'Never edit `vendor/`'));
    assert_true(str_contains($src, 'never deploy Framework root'));
});

test('TENANTS distinguishes Framework Portal and customer skeleton', function () use ($root): void {
    $path = $root . '/docs/TENANTS.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'Lebytek_Portal'));
    assert_true(str_contains($src, 'never start a customer project by cloning'));
});

test('SCHEMA-OWNERSHIP assigns Payments to Framework and Marketing to Portal', function () use ($root): void {
    $path = $root . '/docs/database/SCHEMA-OWNERSHIP.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'payments'));
    assert_true(str_contains($src, 'Marketing'));
    assert_true(str_contains($src, 'Portal'));
    assert_true(str_contains($src, 'PackagePaths'));
});

test('ASSETS-PLATFORM lists canonical platform asset files', function () use ($root): void {
    $path = $root . '/docs/ASSETS-PLATFORM.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'app.css'));
    assert_true(str_contains($src, 'crud-engine.js'));
    assert_true(str_contains($src, 'public/assets/'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php FpsDocumentation 2>&1 | Select-Object -Last 3
```

Expected: FAIL — archivos ausentes.

- [ ] **Step 3: Write `docs/ARCHITECTURE-CONSUMER.md`**

Create `docs/ARCHITECTURE-CONSUMER.md`:

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

## Ownership: Payments vs Marketing

| Concern | Owner | Namespace / path |
|---------|-------|------------------|
| Payments generic (Stripe gateway, event log, registry) | **Framework** | `Lebytek\Framework\Domain\Payments\`, toggled OFF by default |
| Checkout, orders, memberships, Stripe business rules | **Portal** | `App\Application\Marketing\`, `*mkt*` SQL |

## Build a system

1. Copy `skeleton/` to a new git repo
2. `composer require lebytek/framework` (path locally; VCS+tag in shared envs)
3. `php scripts/migrate.php` / `seed.php` / `install.php` (wrappers)
4. Implement `App\` modules; toggle `config/vertical.php`
5. Never edit `vendor/`; never clone Portal to start a customer; **never deploy Framework root**

## Anti-patterns

- Dual git pull into one web root
- Copying platform `schema.sql` into the consumer as SoT
- Shipping Lebytek marketing inside skeleton
- Path-autoloading Framework `src/` from the consumer composer.json
- Reintroducing Marketing into the framework package to fix integration tests
```

- [ ] **Step 4: Write `docs/TENANTS.md`**

Create `docs/TENANTS.md`:

```markdown
# Tenants vs Framework vs Portal

| Name | Repo | Role |
|------|------|------|
| Lebytek Framework | `Lebytek_Framework` | Reusable platform package — **not a deployable site** |
| Lebytek Portal | `Lebytek_Portal` | Company tenant (lebytek.com / waapi) |
| Customer X | new repo from `skeleton/` | Other client app |

Rule: **never start a customer project by cloning `Lebytek_Portal`.**

Local maintainer: Portal/skeleton `repositories` path → `../Lebytek_Framework`.
Production: VCS + semver/branch + committed `composer.lock` (see Plan 08 runbook).

Marketing (CRM, leads, memberships, landing) lives **only** in Portal until a second tenant justifies `lebytek/module-marketing` (YAGNI).
```

- [ ] **Step 5: Write `docs/database/SCHEMA-OWNERSHIP.md`**

Create `docs/database/SCHEMA-OWNERSHIP.md`:

```markdown
# Schema ownership

| Layer | Owner | Apply with |
|-------|-------|------------|
| Platform `auth_*`, `cfg_*`, `core_*`, … | Framework package | Consumer wrapper → package scripts (`PackagePaths`) |
| Platform modules calendario, pdf-kit, reportes, crud-engine, integrations, **payments** | Framework package | `resolveDataFile` / `moduleSchema` |
| Marketing / `dom_mkt_*` / `*mkt*` migrations | **Portal** | `php scripts/migrate-marketing.php` + Portal `database/migrations/*mkt*` |

Installer resolves migration/seed **filenames** package-first, then ROOT_PATH.

Portal must **not** vendor a fork of platform `schema.sql` as source of truth.
```

- [ ] **Step 6: Write `docs/ASSETS-PLATFORM.md`**

Create `docs/ASSETS-PLATFORM.md`:

```markdown
# Platform UI assets (accepted debt A6)

`ViewHelper::asset()` serves URLs under the **consumer** `public/assets/`.
The package does not publish assets via Composer plugin in this cycle.

## Canonical files (must exist in skeleton + Portal)

- css: `app.css`, `lebytek-ui.css`, `crud-engine.css`
- js: `app.js`, `crud-engine.js`, `calendar.js`, `avatar-manager.js`, `reportes-builder.js`
- `icons/app-icon.svg`, `images/logo.png`

## Product-only assets (Portal)

- `public/assets/publico/**` — landing Lebytek; **never** in skeleton

## On framework UI bumps

1. Diff those files in Framework harness / `skeleton/public/assets`
2. Copy into each consumer (`Portal`, other tenants)
3. Bump `config app.asset_version` in the consumer

Follow-up (out of scope): Composer plugin or `scripts/publish-assets.php`.
```

- [ ] **Step 7: Run documentation test — expect PASS**

Run:

```powershell
php tests/run.php FpsDocumentation 2>&1 | Select-Object -Last 3
```

Expected: `0 failed`.

- [ ] **Step 8: Commit Framework docs**

```powershell
git add docs/ARCHITECTURE-CONSUMER.md docs/TENANTS.md docs/database/SCHEMA-OWNERSHIP.md docs/ASSETS-PLATFORM.md tests/Docs/FpsDocumentationTest.php
git commit -m "docs: consumer architecture, schema ownership, platform assets"
```

---

### Task 2: Documentación Portal + README

**Files:**
- Create: `Lebytek_Portal/docs/database/SCHEMA-OWNERSHIP.md`
- Modify: `Lebytek_Portal/README.md`, `Lebytek_Portal/CLAUDE.md`

**Interfaces:**
- Consumes: Framework docs Task 1 (misma tabla ownership).
- Produces: Portal doc mirror; grep gate Payments/Marketing ownership.

- [ ] **Step 1: Write Portal schema ownership doc**

Create `Lebytek_Portal/docs/database/SCHEMA-OWNERSHIP.md`:

```markdown
# Schema ownership — Lebytek Portal

| Layer | Owner | This repo |
|-------|-------|-----------|
| Platform SQL (`schema.sql`, modules plataforma, payments.sql) | Framework package in `vendor/lebytek/framework` | Applied via `php scripts/migrate.php` (wrapper) |
| Marketing SQL (`marketing.sql`, `*mkt*` migrations) | **This Portal repo** | `php scripts/migrate-marketing.php` + `database/migrations/*mkt*` |

**Never** copy platform `schema.sql` into this repo as source of truth.

Payments infrastructure is generic platform code (`Lebytek\Framework\Domain\Payments\`).
Membership checkout and Stripe business translation live in `App\Application\Marketing\`.
```

- [ ] **Step 2: Update Portal README with doc links**

Append to `Lebytek_Portal/README.md`:

```markdown
## Documentation

- Schema: `docs/database/SCHEMA-OWNERSHIP.md`
- Framework consumer model: `../Lebytek_Framework/docs/ARCHITECTURE-CONSUMER.md`
- Platform assets sync: `../Lebytek_Framework/docs/ASSETS-PLATFORM.md`
```

- [ ] **Step 3: Documental grep gate**

Run:

```powershell
$portal = "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
Select-String -Path "$portal\docs\database\SCHEMA-OWNERSHIP.md" -Pattern "Marketing" -SimpleMatch
Select-String -Path "$portal\docs\database\SCHEMA-OWNERSHIP.md" -Pattern "vendor/lebytek/framework" -SimpleMatch
Select-String -Path "$portal\CLAUDE.md" -Pattern "solo lectura" -SimpleMatch
```

Expected: tres coincidencias (Marketing, vendor path, solo lectura).

- [ ] **Step 4: Commit Portal docs**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/database/SCHEMA-OWNERSHIP.md README.md CLAUDE.md
git commit -m "docs: schema ownership and consumer links for Portal"
```

---

### Task 3: Reglas Cursor y CLAUDE — Framework (package source)

**Files:**
- Modify: `Lebytek_Framework/CLAUDE.md`
- Modify: `Lebytek_Framework/.cursor/rules/framework-en-vendor.mdc`
- Modify: `Lebytek_Framework/.cursor/rules/reglas-para-ia.mdc`

**Interfaces:**
- Consumes: Task 1 docs.
- Produces: IA trata Framework como fuente de paquete; negocio fuera de `app/`.

- [ ] **Step 1: Rewrite Framework `CLAUDE.md`**

Replace content with:

```markdown
# CLAUDE.md — Lebytek Framework (package source)

**This repo ships the Composer library `lebytek/framework`. It is NOT the deployable lebytek.com site.**

| Path | Role |
|------|------|
| `src/` | Framework package (`Lebytek\Framework\`) |
| `skeleton/` | Minimal consumer template for new tenants |
| `database/`, `scripts/` | Platform SQL shipped in the package |
| `tests/` | Platform test harness |
| Root `config/`, `public/`, stub `app/` | **Test harness only — no deploy** |
| Portal Lebytek | Separate repo `Lebytek_Portal` |

## Commands

```bash
composer install
php tests/run.php
php tests/run.php Kernel
php tests/run.php Payments
php tests/run.php SkeletonPurity
```

## Architecture

- Platform changes → `src/`, platform SQL, `skeleton/` template
- **No** Marketing / LebytekApi / Publico in this repo (Portal owns that)
- Payments generic → `src/Domain/Payments/` (OFF by default via vertical)
- Consumers install via Composer; never path-autoload `src/` from Portal

## Branches

- `consolidation/framework-portal-separation` — FPS consolidation work
- **Do not merge** `feature/backoffice-api-integration` → `main` without explicit user order

## Docs

- `docs/ARCHITECTURE-CONSUMER.md`
- `docs/PACKAGE-ROOT.md`
- `docs/database/SCHEMA-OWNERSHIP.md`
```

- [ ] **Step 2: Update `.cursor/rules/framework-en-vendor.mdc`**

Replace body key section:

```markdown
# Monorepo → package source

Este repo **publica** el paquete `lebytek/framework` (`src/`). **No** es el sitio desplegable.

| Código | Ruta | Namespace |
|-----|---|-----|
| Framework (plataforma) | `src/` | `Lebytek\Framework\` |
| App consumidora | **fuera** — `Lebytek_Portal/` o `skeleton/` | `App\` |

- Cambios de plataforma → **`src/`**, SQL plataforma, `skeleton/`
- Cambios de negocio Lebytek → repo **`Lebytek_Portal`**
- Root `app/` es stub/harness — no añadir Marketing aquí
- **NUNCA** editar `vendor/` en consumidores desde este flujo de trabajo
```

- [ ] **Step 3: Update `.cursor/rules/reglas-para-ia.mdc`**

Add after first paragraph:

```markdown
## Modelo post-separación FPS

- Trabajar plataforma en `src/`, `skeleton/`, tests plataforma.
- **No** implementar Marketing, leads, membresías ni `LebytekApiClient` en este repo.
- Si la tarea es negocio Portal → indicar que el cambio va en `Lebytek_Portal`.
- Payments genérico (`Lebytek\Framework\Domain\Payments\`) sí vive aquí; checkout de membresía no.
```

- [ ] **Step 4: Grep gate — CLAUDE must not claim deployable monorepo**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
Select-String -Path CLAUDE.md -Pattern "monorepo desplegable" -SimpleMatch
if ($LASTEXITCODE -eq 0) { Write-Error "CLAUDE still says deployable monorepo"; exit 1 }
Select-String -Path CLAUDE.md -Pattern "NOT the deployable" -SimpleMatch
Select-String -Path CLAUDE.md -Pattern "Lebytek_Portal" -SimpleMatch
```

Expected: segunda y tercera búsqueda encuentran coincidencias; primera **no** encuentra "monorepo desplegable".

- [ ] **Step 5: Commit Framework rules**

```powershell
git add CLAUDE.md .cursor/rules/framework-en-vendor.mdc .cursor/rules/reglas-para-ia.mdc
git commit -m "docs(rules): package-source model after Portal split"
```

---

### Task 4: Reglas Cursor — Portal + cierre SDD

**Files:**
- Create: `Lebytek_Portal/.cursor/rules/framework-en-vendor.mdc` (from skeleton)
- Create: `Lebytek_Portal/.cursor/rules/reglas-para-ia.mdc`
- Modify: `Lebytek_Framework/.superpowers/sdd/progress.md`

**Interfaces:**
- Consumes: `skeleton/.cursor/rules/framework-en-vendor.mdc`.
- Produces: Portal IA guardrails; SDD Plan 07 complete.

- [ ] **Step 1: Copy and adapt Portal vendor rule**

Create `Lebytek_Portal/.cursor/rules/framework-en-vendor.mdc`:

```markdown
---
description: El framework se consume desde vendor/. Cómo (no) modificarlo.
alwaysApply: true
---

# Framework en vendor — Lebytek Portal

Esta es la **app consumidora Portal** (`lebytek/portal`). El framework vive en
`vendor/lebytek/framework/` y **es solo lectura**.

| Área | Dónde editar |
|------|--------------|
| Marketing, leads, membresías, landing | `app/`, `config/`, `routes/`, `database/` |
| Plataforma (Kernel, CRUD, Payments genérico) | repo `Lebytek_Framework` → `composer update lebytek/framework` |

## Reglas duras

- **NUNCA** editar, crear ni borrar archivos bajo `vendor/`.
- Marketing pertenece a **este** repo, no al paquete framework.
- Payments genérico (`Lebytek\Framework\Domain\Payments\`) se extiende vía config/DI en Portal, no parcheando vendor.
```

- [ ] **Step 2: Create Portal `reglas-para-ia.mdc`**

Create `Lebytek_Portal/.cursor/rules/reglas-para-ia.mdc`:

```markdown
---
description: Reglas para IA en Lebytek Portal
alwaysApply: true
---

# Reglas para IA — Portal

- Trabajar en `app/`, `config/`, `routes/`, `database/`, `tests/Marketing/`.
- Respetar capas Presentation / Application / Domain / Infrastructure.
- Integración api: `app/Infrastructure/Integrations/LebytekApi/` — contrato en `docs/integration/`.
- Cambios de plataforma → spec/plan en `Lebytek_Framework`, luego `composer update`.
- No copiar `schema.sql` plataforma; no clonar este repo para clientes externos.
```

- [ ] **Step 3: Final documentation gates both repos**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php FpsDocumentation 2>&1 | Select-Object -Last 1

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
Test-Path docs/database/SCHEMA-OWNERSHIP.md
Test-Path .cursor/rules/framework-en-vendor.mdc
```

Expected: FpsDocumentation `0 failed`; ambos `Test-Path` → `True`.

- [ ] **Step 4: SDD progress + commits**

Append to `Lebytek_Framework/.superpowers/sdd/progress.md`:

```markdown
## Plan 07 — Documentation and agent rules (2026-07-17)

- [x] ARCHITECTURE-CONSUMER, TENANTS, SCHEMA-OWNERSHIP, ASSETS-PLATFORM
- [x] Portal schema ownership mirror
- [x] CLAUDE + Cursor rules updated (Framework package source, Portal consumer)
- [x] Payments → Framework; Marketing → Portal documented
- Gate FpsDocumentation: 0 failed
- Siguiente: Plan 08 publication readiness (docs only)
```

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add .cursor/rules
git commit -m "docs(rules): consumer vendor-readonly guardrails for Portal"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add .superpowers/sdd/progress.md
git commit -m "docs(fps): record Plan 07 completion in SDD progress"
```

---

## Self-review (author)

| Requisito roadmap Plan 07 | Task |
|---------------------------|------|
| Arquitectura consumidor/paquete | Task 1 ARCHITECTURE-CONSUMER |
| Ownership SQL | Task 1 + Task 2 SCHEMA-OWNERSHIP |
| Assets sync | Task 1 ASSETS-PLATFORM |
| Guía tenants | Task 1 TENANTS |
| README ambos árboles | Task 1 + Task 2 |
| Reglas Cursor + CLAUDE | Task 3 + Task 4 |
| Payments Framework / Marketing Portal | SCHEMA-OWNERSHIP + CLAUDE |
| No merge/deploy/vendor/secrets | Global Constraints |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: mismos nombres de paths canónicos assets que Plan 04; Portal apunta a `vendor/lebytek/framework`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-07-documentation-agent-rules.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**
