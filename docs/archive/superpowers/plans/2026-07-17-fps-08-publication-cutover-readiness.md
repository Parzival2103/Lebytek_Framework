# FPS Plan 08 — Preparación publicación y cutover (solo revisión humana)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar checklist de revisión humana, validación de manifiestos/`composer.lock`, propuesta documentada de remotes/repos futuros y runbook VPS con rollback/smoke — **sin ejecutar** `gh repo create`, push, merge, deploy, SSH, DNS ni operaciones de producción.

**Architecture:** Plan terminal del roadmap FPS. Consolida evidencia de Plans 00–07 en artefactos auditables: checklist de gates, diff de manifiestos, propuesta de remotes GitHub y runbook operativo marcado como **requiere orden explícita** para cada paso externo. Cualquier cutover real exige plan operativo separado aprobado por el usuario.

**Tech Stack:** Markdown, Composer (`composer validate`, `composer install --dry-run`), PowerShell, Git (local read-only).

**Reemplaza para ejecución (parcial):** Task 12 de `docs/superpowers/plans/2026-07-15-framework-portal-separation.md` (versión **sin** `gh repo create` ni push).

**Roadmap:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md` — Plan 08.

**Predecesor obligatorio:** Plan 07 (`2026-07-17-fps-07-documentation-agent-rules.md`).

**Sucesor:** ninguno en roadmap FPS; cutover producción = plan operativo nuevo + orden explícita.

## Global Constraints

- **Prohibido en este plan (hard stop):**
  - `gh repo create`, `git push`, merge PRs, merge `feature/backoffice-api-integration` → `main`
  - SSH, deploy VPS, DNS, migraciones producción, editar `.env` prod
  - Cualquier comando que modifique servidores remotos
- **Permitido:** validación local, docs, checklists, propuestas, dry-run Composer, commits **solo documentación** en repos locales.
- Repos locales: `Lebytek_Framework` (`consolidation/framework-portal-separation`), `Lebytek_Portal` (sibling).
- Gates previos deben estar verdes antes de marcar checklist final (evidencia en `.superpowers/sdd/progress.md`).

---

### Task 1: Validación de manifiestos y composer.lock

**Files:**
- Create: `Lebytek_Framework/docs/superpowers/FPS-publication-manifest-checklist.md`
- Create: `Lebytek_Portal/docs/superpowers/FPS-portal-composer-checklist.md`
- Create: `tests/Docs/FpsPublicationReadinessTest.php`

**Interfaces:**
- Consumes: `composer.json` / `composer.lock` en ambos repos; BOUNDARY manifest.
- Produces: checklist firmable; test documental `FpsPublicationReadiness`.

- [ ] **Step 1: Write failing publication readiness test**

Create `tests/Docs/FpsPublicationReadinessTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('FPS publication manifest checklist exists', function () use ($root): void {
    $path = $root . '/docs/superpowers/FPS-publication-manifest-checklist.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'PackageAutoloadBoundary'));
    assert_true(str_contains($src, 'SkeletonPurity'));
    assert_true(str_contains($src, 'FrameworkRootNotPortal'));
    assert_true(str_contains($src, 'NO PRODUCTION EXECUTION'));
});

test('CUTOVER runbook exists and forbids implicit merge to main', function () use ($root): void {
    $path = $root . '/docs/CUTOVER-PORTAL.md';
    assert_true(is_readable($path));
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'rollback'));
    assert_true(str_contains($src, 'feature/backoffice-api-integration'));
    assert_true(str_contains($src, 'explicit user order'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php FpsPublicationReadiness 2>&1 | Select-Object -Last 3
```

Expected: FAIL — archivos ausentes.

- [ ] **Step 3: Capture Framework manifest evidence**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git branch --show-current
composer validate 2>&1
php tests/run.php PackageAutoloadBoundary 2>&1 | Select-Object -Last 1
php tests/run.php PackagePaths 2>&1 | Select-Object -Last 1
php tests/run.php SkeletonPurity 2>&1 | Select-Object -Last 1
php tests/run.php PlatformSqlResolve 2>&1 | Select-Object -Last 1
php tests/run.php FrameworkRootNotPortal 2>&1 | Select-Object -Last 1
php tests/run.php Kernel 2>&1 | Select-Object -Last 1
php tests/run.php Payments 2>&1 | Select-Object -Last 1
php tests/run.php 2>&1 | Select-Object -Last 1
(Get-Content composer.json -Raw | ConvertFrom-Json).autoload.'psr-4'.'App\'
```

Expected: rama `consolidation/framework-portal-separation`; `composer validate` OK; cada test `0 failed`; propiedad `App\` **null/ausente** en composer.json.

- [ ] **Step 4: Capture Portal composer evidence**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
composer validate 2>&1
if (-not (Test-Path composer.lock)) { composer update --lock --no-install 2>&1 }
composer install --dry-run 2>&1 | Select-Object -Last 5
php tests/run.php Marketing 2>&1 | Select-Object -Last 1
php tests/run.php PortalOwnership 2>&1 | Select-Object -Last 1
(Get-Content composer.json -Raw | ConvertFrom-Json).repositories[0].type
```

Expected: `composer validate` OK; `composer.lock` presente; dry-run sin errores; Marketing y PortalOwnership `0 failed`; repository type `path`.

- [ ] **Step 5: Write Framework manifest checklist**

Create `Lebytek_Framework/docs/superpowers/FPS-publication-manifest-checklist.md`:

```markdown
# FPS — Publication manifest checklist (Framework)

**Date:** 2026-07-17  
**Branch:** `consolidation/framework-portal-separation`  
**Status:** local validation only — **NO PRODUCTION EXECUTION**

## Composer package purity

- [ ] `composer.json` name = `lebytek/framework`
- [ ] autoload **only** `Lebytek\Framework\` → `src/` (no `App\`)
- [ ] `composer validate` passes
- [ ] `stripe/stripe-php` present (Payments platform module)

## Test gates (record last line output)

| Filter | Required |
|--------|----------|
| PackageAutoloadBoundary | 0 failed |
| PackagePaths | 0 failed |
| SkeletonPurity | 0 failed |
| PlatformSqlResolve | 0 failed |
| FrameworkRootNotPortal | 0 failed |
| Kernel | 0 failed |
| Payments | 0 failed |
| Full suite | 0 failed |

## Boundary

- [ ] No `app/Domain/Marketing` in Framework root
- [ ] No `database/schema/modules/marketing.sql` in Framework
- [ ] `docs/PACKAGE-ROOT.md` forbids deploy
- [ ] `docs/ARCHITECTURE-CONSUMER.md` present

## Explicit NO

- [ ] No merge `feature/backoffice-api-integration` → `main` without user order
- [ ] No VPS deploy from this plan
```

Fill checkboxes with actual command output from Step 3.

- [ ] **Step 6: Write Portal composer checklist**

Create `Lebytek_Portal/docs/superpowers/FPS-portal-composer-checklist.md`:

```markdown
# FPS — Portal Composer checklist

**Date:** 2026-07-17  
**Local path repo:** `../Lebytek_Framework`  
**Status:** pre-publication — **NO PRODUCTION EXECUTION**

## Composer project

- [ ] `composer.json` name = `lebytek/portal`
- [ ] autoload only `App\` → `app/`
- [ ] `composer validate` passes
- [ ] `composer.lock` committed (generate locally if missing)
- [ ] `composer install --dry-run` succeeds

## Test gates

| Filter | Required |
|--------|----------|
| Marketing | 0 failed |
| PortalOwnership | 0 failed |

## Ownership

- [ ] `app/Domain/Marketing` present
- [ ] No `src/` directory
- [ ] No platform `database/schema/schema.sql` as SoT
- [ ] `docs/database/SCHEMA-OWNERSHIP.md` present

## Pre-prod switch (document only — do not run here)

Before VPS cutover, human must change `repositories` from `path` to `vcs` and pin framework version — see `docs/DEPLOY-VPS.md` draft in Plan 08 Task 3.
```

- [ ] **Step 7: Commit Framework checklist + test**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/superpowers/FPS-publication-manifest-checklist.md tests/Docs/FpsPublicationReadinessTest.php
git commit -m "docs(fps): Framework publication manifest checklist"
```

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/superpowers/FPS-portal-composer-checklist.md
git commit -m "docs(fps): Portal composer pre-publication checklist"
```

---

### Task 2: Propuesta de remotes y repos (documento only)

**Files:**
- Create: `Lebytek_Framework/docs/superpowers/FPS-remote-repo-proposal.md`
- Create: `Lebytek_Portal/docs/superpowers/FPS-remote-repo-proposal.md`

**Interfaces:**
- Consumes: manifest checklists Task 1.
- Produces: propuesta revisable; **no** ejecución `gh repo create`.

- [ ] **Step 1: Write Framework remote proposal**

Create `Lebytek_Framework/docs/superpowers/FPS-remote-repo-proposal.md`:

```markdown
# FPS — Remote repository proposal (Framework)

**Status:** PROPOSAL ONLY — requires explicit user approval before any remote operation.

## Current state

| Item | Value |
|------|-------|
| Existing remote | `https://github.com/Parzival2103/Lebytek_Framework` |
| Publication branch candidate | `consolidation/framework-portal-separation` |
| Package name | `lebytek/framework` |
| Tag policy | Do **not** overwrite `v1.0.0`; new semver only on user order |

## Proposed actions (NOT executed in Plan 08)

1. Open PR: `consolidation/framework-portal-separation` → `main` — **only if user explicitly orders merge**
2. Tag `v1.1.0` (or approved version) after PR merge + full CI green
3. Configure Composer VCS auth on VPS for private repo (document in Portal DEPLOY-VPS)

## Framework consumer contract after publish

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
  "lebytek/framework": "^1.1"
}
```

Exact constraint follows user-approved branch/tag — do not invent tags in automation.

## Explicit prohibitions

- No `gh repo create` for Framework (already exists)
- No force-push to `main`
- No merge `feature/backoffice-api-integration` → `main` without explicit user chat order
```

- [ ] **Step 2: Write Portal remote proposal**

Create `Lebytek_Portal/docs/superpowers/FPS-remote-repo-proposal.md`:

```markdown
# FPS — Remote repository proposal (Portal)

**Status:** PROPOSAL ONLY — requires explicit user approval before any remote operation.

## Proposed new remote

| Item | Proposed value |
|------|----------------|
| GitHub org/user | `Parzival2103` |
| Repository name | `Lebytek_Portal` |
| Visibility | private |
| Default branch | `main` |
| Deploy targets | lebytek.com, waapi.lebytek.com (same tree) |

## Commands deferred (DO NOT RUN in Plan 08)

```powershell
# DEFERRED — human gate required:
# cd c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal
# gh repo create Parzival2103/Lebytek_Portal --private --source=. --remote=origin
# git push -u origin main
```

## Pre-push checklist

- [ ] `FPS-portal-composer-checklist.md` all green
- [ ] `composer.lock` committed
- [ ] No secrets in git history (`.env` gitignored)
- [ ] Framework package gates green on pinned version
- [ ] User explicit approval for `gh repo create` and first push

## Post-create (still deferred)

- Add GitHub Actions CI mirroring `php tests/run.php Marketing`
- Switch VPS from monorepo pull to Portal repo + `composer install --no-dev`
```

- [ ] **Step 3: Documental gate — proposals must say PROPOSAL ONLY**

Run:

```powershell
Select-String -Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework\docs\superpowers\FPS-remote-repo-proposal.md" -Pattern "PROPOSAL ONLY" -SimpleMatch
Select-String -Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\docs\superpowers\FPS-remote-repo-proposal.md" -Pattern "DO NOT RUN" -SimpleMatch
Select-String -Path "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal\docs\superpowers\FPS-remote-repo-proposal.md" -Pattern "gh repo create" -SimpleMatch
```

Expected: PROPOSAL ONLY found; DO NOT RUN found; `gh repo create` appears only inside commented/deferred block.

- [ ] **Step 4: Commit proposals (both repos)**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/superpowers/FPS-remote-repo-proposal.md
git commit -m "docs(fps): Framework remote publication proposal (no execution)"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/superpowers/FPS-remote-repo-proposal.md
git commit -m "docs(fps): Portal GitHub repo proposal (deferred)"
```

---

### Task 3: Runbook VPS, rollback y smoke (docs only)

**Files:**
- Create: `Lebytek_Portal/docs/DEPLOY-VPS.md`
- Create: `Lebytek_Framework/docs/CUTOVER-PORTAL.md`
- Modify: `Lebytek_Framework/.superpowers/sdd/progress.md`

**Interfaces:**
- Consumes: Plans 00–07 gates; SCHEMA-OWNERSHIP; ASSETS-PLATFORM; remote proposals.
- Produces: runbook humano; test `FpsPublicationReadiness` verde; SDD roadmap FPS completo.

- [ ] **Step 1: Write Portal DEPLOY-VPS runbook (no prod commands executed)**

Create `Lebytek_Portal/docs/DEPLOY-VPS.md`:

```markdown
# VPS deploy runbook — Lebytek Portal

**Status:** DOCUMENTATION ONLY. Executing these steps on production requires explicit user order and a separate ops plan.

## Target layout

```text
/home/<cloudpanel-user>/htdocs/lebytek.com/
  app/ config/ routes/ public/ database/ storage/ .env
  composer.json / composer.lock
  vendor/   ← includes lebytek/framework
```

Document root: `public/` of Portal checkout — **never** Framework monorepo root.

## Pre-cutover checklist

- [ ] Portal `php tests/run.php Marketing` → 0 failed (staging/local)
- [ ] Framework platform suite → 0 failed on pinned framework version
- [ ] DB backup + `.env` backup on VPS
- [ ] `composer.lock` committed in Portal
- [ ] Assets platform copied per `../Lebytek_Framework/docs/ASSETS-PLATFORM.md`

## Composer switch (staging first)

Replace local path repo with VCS before production:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/Parzival2103/Lebytek_Framework" }
],
"require": {
  "lebytek/framework": "dev-consolidation/framework-portal-separation"
}
```

Exact version/branch follows user-approved tag — update after Framework publish decision.

## Deploy sequence (human executes on VPS)

1. Clone `Parzival2103/Lebytek_Portal` to site path (new directory — do not overlay monorepo)
2. Copy existing `.env` from current site (do not commit)
3. `composer install --no-dev --no-interaction`
4. `php scripts/migrate.php` (platform SQL via package)
5. `php scripts/migrate-marketing.php` (Portal business SQL)
6. `php scripts/seed.php` if greenfield; otherwise skip destructive seeds
7. Reload PHP-FPM / web server
8. Run smoke tests (below)

## Smoke tests (post-deploy)

| Check | Command / URL | Expected |
|-------|---------------|----------|
| Landing | `curl -s -o /dev/null -w "%{http_code}" https://lebytek.com/` | `200` |
| Admin login | Browser `/admin/login` | form loads, login succeeds |
| API integration | `php scripts/lebytek-api-health.php` | OK status |
| Marketing suite | `php tests/run.php Marketing` on staging clone | 0 failed |

## Rollback

1. Keep previous monorepo checkout directory renamed (e.g. `lebytek.com.monorepo-backup`)
2. Re-point nginx document root to backup path
3. Restore DB from pre-cutover dump if migrations ran
4. Reload web stack
5. Record incident in ops log; do **not** merge Framework branches as rollback step

## Forbidden without explicit user order

- Merge `feature/backoffice-api-integration` → `main`
- `migrate --force` on production without backup
- Disabling RBAC, webhook signatures, or idempotency guards
- Editing production `.env` in git
```

- [ ] **Step 2: Write Framework CUTOVER-PORTAL checklist**

Create `Lebytek_Framework/docs/CUTOVER-PORTAL.md`:

```markdown
# Portal cutover checklist (Framework maintainer view)

**NO PRODUCTION EXECUTION in FPS Plan 08.** Human sign-off required for each section.

## Evidence gates (must be green before cutover)

| Gate | Command | Owner repo |
|------|---------|------------|
| Package autoload purity | `php tests/run.php PackageAutoloadBoundary` | Framework |
| SQL resolution | `php tests/run.php PlatformSqlResolve` | Framework |
| Skeleton purity | `php tests/run.php SkeletonPurity` | Framework |
| Root not portal | `php tests/run.php FrameworkRootNotPortal` | Framework |
| Platform suite | `php tests/run.php` | Framework |
| Portal marketing | `php tests/run.php Marketing` | Portal |
| Portal ownership | `php tests/run.php PortalOwnership` | Portal |
| Composer | `composer validate` | Both |

## Publication readiness

- [ ] `docs/superpowers/FPS-publication-manifest-checklist.md` completed
- [ ] `Lebytek_Portal/docs/superpowers/FPS-portal-composer-checklist.md` completed
- [ ] Remote proposals reviewed (`FPS-remote-repo-proposal.md` both repos)
- [ ] `composer.lock` in Portal pins reproducible framework build

## VPS cutover (deferred)

- [ ] GitHub Portal repo created — **explicit user order only**
- [ ] VPS Composer auth for private `Lebytek_Framework`
- [ ] Staging smoke passed (landing, admin, api health)
- [ ] Rollback path documented in Portal `docs/DEPLOY-VPS.md` validated on staging
- [ ] Retire monorepo auto-pull on lebytek.com document root

## Rollback triggers

- Marketing suite fails on staging Portal after migrate
- Admin login broken after asset sync
- API health script fails against api.lebytek.com
- Unexpected platform SQL drift (consumer copied schema.sql)

Rollback procedure: Portal DEPLOY-VPS § Rollback — restore previous web root + DB backup.

## Policy reminders

- **Never** merge `feature/backoffice-api-integration` → `main` without **explicit user order**
- Framework is not a deployable site; Portal is the consumer for lebytek.com
- Accepted debt A1–A8 remain until follow-up plans

## Sign-off

| Role | Name | Date | OK |
|------|------|------|-----|
| Maintainer | | | |
| Ops | | | |
```

- [ ] **Step 3: Run FpsPublicationReadiness — expect PASS**

Run:

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
php tests/run.php FpsPublicationReadiness 2>&1 | Select-Object -Last 3
```

Expected: `0 failed`.

- [ ] **Step 4: Final SDD progress — FPS roadmap complete**

Append to `Lebytek_Framework/.superpowers/sdd/progress.md`:

```markdown
## Plan 08 — Publication readiness (2026-07-17)

- [x] Framework + Portal manifest checklists
- [x] Remote repo proposals (deferred execution)
- [x] DEPLOY-VPS + CUTOVER-PORTAL runbooks (docs only)
- [x] FpsPublicationReadiness 0 failed
- [x] NO gh repo create / push / merge / deploy / SSH / DNS executed
- FPS roadmap Plans 00–08: **documentation and local separation complete**
- Next: explicit user order for GitHub publish + VPS cutover ops plan
```

- [ ] **Step 5: Commit runbooks + SDD**

```powershell
cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Portal"
git add docs/DEPLOY-VPS.md
git commit -m "docs: VPS deploy and rollback runbook (no prod execution)"

cd "c:\Users\User\OneDrive\Desktop\sistemas\Lebytek_Framework"
git add docs/CUTOVER-PORTAL.md .superpowers/sdd/progress.md
git commit -m "docs: Portal cutover checklist and FPS roadmap completion"
```

- [ ] **Step 6: Stop — human gate**

Print for the user:

```text
FPS Plan 08 complete. Local separation and documentation ready.
Do NOT run gh repo create, git push, VPS deploy, or merge to main until explicit user order.
Review: docs/CUTOVER-PORTAL.md and Lebytek_Portal/docs/DEPLOY-VPS.md
```

---

## Self-review (author)

| Requisito roadmap Plan 08 | Task |
|---------------------------|------|
| Validación manifiestos/composer.lock | Task 1 |
| Propuesta remotes/repos futuros | Task 2 |
| Runbook VPS rollback/smoke | Task 3 DEPLOY-VPS + CUTOVER |
| No gh repo create | Task 2 deferred block; Global Constraints |
| No push/merge/deploy/SSH/DNS/prod | Global Constraints + all docs marked NO EXECUTION |
| No merge feature→main | CUTOVER + proposals |
| Autocontenido sin TBD | full doc bodies inline |

Placeholder scan: sin TBD/TODO/"similar a".  
Consistencia: remotes `Parzival2103/Lebytek_Framework` y `Parzival2103/Lebytek_Portal`; gates alineados con Plan 06.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-17-fps-08-publication-cutover-readiness.md`. Two execution options:

**1. Subagent-Driven (recommended)** — fresh subagent per task, review between tasks

**2. Inline Execution** — executing-plans with checkpoints

**Which approach?**

**After Plan 08:** stop and wait for explicit user order before any remote or production operation.
