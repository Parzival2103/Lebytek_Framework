# Harness Hygiene Unblock (semver 1.2.1 + Portal env purge) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Sincronizar la versión de plataforma visible con el release `v1.2.1` y purgar variables Portal/Marketing del root `.env.example`, con tests gate TDD que impidan regresión — sin tocar `src/`, `database/` ni negocio Portal.

**Architecture:** Enfoque A+C del spec: `"version": "1.2.1"` en `composer.json` como fuente canónica; `config/app.php` y `skeleton/config/app.php` copian el mismo valor; `DeploymentStatus` y vistas siguen leyendo `Config::get('app.version')` sin cambios en `src/`. Root `.env.example` pierde prefijos `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`; vars de negocio viven solo en `Lebytek_Portal/.env.example`. Tests gate en `tests/Docs/` y extensión de `FrameworkRootNotPortalTest`.

**Tech Stack:** PHP 8.1+, Composer 2.x, harness microtest (`tests/lib/microtest.php`, `php tests/run.php`), Git tag `v1.2.1` @ `fba3e03`, Bootstrap 5.3 admin/install (display only).

**Source spec:** `docs/superpowers/specs/2026-08-01-audit-harness-hygiene-unblock-design.md`  ·  **Modo:** normal

**Source audit PR:** ninguno — auditoría en rama `automation/audit-2026-08-01` @ `ff5cd0d` sin PR abierto (AUTOMATION-03 pendiente); diff único `docs/audits/2026-08-01-auditoria-tecnica-diaria.md`

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`7ad72247c8799d827080252b020831c2bb8a6820`); rama de trabajo `feature/harness-hygiene-unblock` (creable desde `main` — verificado: `git ls-remote origin refs/heads/main` resuelve; rama aún no existe)

## Global Constraints

- Rama `feature/harness-hygiene-unblock` creable desde `origin/main` (no existe en remoto al planificar).
- Versión objetivo: **`1.2.1`** alineada con tag Git `v1.2.1` @ `fba3e03`. No requiere tag `v1.2.2` post-merge (PATCH config/docs/tests).
- No editar `src/`, `database/`, `routes/`, ni `skeleton/` excepto `skeleton/config/app.php` línea `version`.
- No mezclar deploy skeleton (`2026-07-26-skeleton-package-staging.md`) ni bump Portal `composer.lock`.
- Cloud agent puede carecer de PHP CLI: el ejecutor debe correr tests con PHP ≥ 8.1 antes de merge. Fallos por `pdo_mysql` ausente en tests Integrations son **fallo preexistente de entorno**, no gate verde para M1/M2.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| H1/H2 M1 semver sync | Framework | Task 1–2 | `PlatformVersionSemver` PASS |
| H4 test gate semver | Framework | Task 1 | FAIL pre-fix |
| H3 M2 env purge | Framework | Task 3–4 | `FrameworkRootNotPortal` PASS |
| H5 test gate env | Framework | Task 3 | FAIL pre-fix por prefijos |
| H6/H7 checklist release | Framework | Task 5 | `ReleaseChecklistDoc` PASS |
| D3/D14/D15 Portal QA | Portal/Ops | **Fuera de alcance** | gh 404 Portal |
| D6–D9 backlog | Framework/Ops | **Fuera de alcance** | specs futuros |
| Plan skeleton Tasks 2–10 | Framework/Ops | **Fuera de alcance** | plan `2026-07-26` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `composer.json` | Fuente canónica semver — añadir `"version": "1.2.1"` tras `"license"` |
| `config/app.php:7` | Versión plataforma harness |
| `skeleton/config/app.php:7` | Versión plataforma plantilla |
| `.env.example` | Purga vars Portal; comentario ref `Lebytek_Portal/.env.example` |
| `tests/Docs/PlatformVersionSemverTest.php` | Gate: igualdad entre tres fuentes semver |
| `tests/Kernel/FrameworkRootNotPortalTest.php` | Extensión: assert prefijos prohibidos en root `.env.example` |
| `tests/Docs/ReleaseChecklistDocTest.php` | Gate: checklist en `despliegue-y-versionado.md` |
| `docs/core/despliegue-y-versionado.md` | Checklist release 5 pasos en § Versionado |

**Sin modificar:** `src/Application/Install/DeploymentStatus.php`, `src/Kernel/Container/FrameworkServiceProvider.php`, `skeleton/.env.example`, `database/`, `routes/`.

---

### Task 1: Test gate `PlatformVersionSemverTest` (TDD — falla antes del fix)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** None

**Files:**
- Create: `tests/Docs/PlatformVersionSemverTest.php`
- Test: `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: `composer.json` (sin `"version"`), `config/app.php:7` (`'1.0.0'`), `skeleton/config/app.php:7` (`'1.0.0'`) — estado actual @ `origin/main` `7ad72247`
- Produces: test que falla con mensaje explícito de drift vs release `1.2.1`

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/PlatformVersionSemverTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('composer.json declares platform version semver', function () use ($root): void {
    $composerPath = $root . '/composer.json';
    $data = json_decode((string) file_get_contents($composerPath), true);
    assert_true(
        isset($data['version']) && is_string($data['version']) && $data['version'] !== '',
        'composer.json must declare a non-empty "version" field (semver without v prefix). Action: sync three files semver — add "version": "1.2.1" aligned with tag v1.2.1'
    );
});

test('harness config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $rootConfig = require $root . '/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same(
        $composer['version'],
        $rootConfig['version'] ?? null,
        'config/app.php version must match composer.json. Action: sync three files semver'
    );
});

test('skeleton config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $skelConfig = require $root . '/skeleton/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same(
        $composer['version'],
        $skelConfig['version'] ?? null,
        'skeleton/config/app.php version must match composer.json. Action: sync three files semver'
    );
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` / Expected: **FAIL** — 3 tests descubiertos; primer fallo: `composer.json must declare a non-empty "version" field` o `expected '1.2.1', got '1.0.0'`.

- [x] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 2 aplica el fix semver. Este step confirma gate rojo.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php PlatformVersionSemver` / Expected: FAIL (confirmación TDD).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/OpsDocsFpsAlignment` / Expected: PASS — al menos 1 test, 0 failed (M8 no regresa).

- [x] **Step 6: Commit** — archivos: `tests/Docs/PlatformVersionSemverTest.php` / mensaje: `test(docs): add PlatformVersionSemverTest gate (red)`

---

### Task 2: Sincronizar versión `1.2.1` en composer.json y configs

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** Task 1

**Files:**
- Modify: `composer.json` (insertar `"version": "1.2.1"` tras `"license": "proprietary",`)
- Modify: `config/app.php:7`
- Modify: `skeleton/config/app.php:7`
- Test: `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: test rojo de Task 1
- Produces: `"version": "1.2.1"` idéntico en tres archivos; `Config::get('app.version')` → `1.2.1`; `/admin/sistema/estado` muestra `v1.2.1`; `php scripts/status.php` imprime `Plataforma: v1.2.1`

- [x] **Step 1: Escribir el test que falla** — ya existe tras Task 1; re-ejecutar para confirmar rojo pre-fix.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` / Expected: FAIL antes de editar configs.

- [x] **Step 3: Implementar el cambio mínimo**

En `composer.json`, después de `"license": "proprietary",`:

```json
    "version": "1.2.1",
```

En `config/app.php` y `skeleton/config/app.php`, línea `'version'`:

```php
    'version'  => '1.2.1',
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS — 3 tests, 0 failed.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php SkeletonPurity` / Expected: PASS — 13 tests, 0 failed.

Run: `php tests/run.php Kernel/FrameworkRootNotPortal` / Expected: PASS — 3 tests, 0 failed (env assert aún no existe; pasa con tests actuales).

- [x] **Step 6: Commit** — archivos: `composer.json`, `config/app.php`, `skeleton/config/app.php` / mensaje: `fix(config): sync platform version 1.2.1 with composer.json`

---

### Task 3: Extender `FrameworkRootNotPortalTest` — gate env root (TDD rojo)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** None (paralelo a Task 1–2; no consume semver fix)

**Files:**
- Modify: `tests/Kernel/FrameworkRootNotPortalTest.php` (añadir test al final, tras L27)
- Test: `tests/Kernel/FrameworkRootNotPortalTest.php`

**Interfaces:**
- Consumes: root `.env.example` con **16** keys activas `MKT_*` / `LEBYTEK_API_*` / `WAAPI_PORTAL_*` (L53–102 @ `origin/main`)
- Produces: test que falla citando prefijo prohibido y acción «eliminar prefijo Portal en root .env.example»

- [x] **Step 1: Escribir el test que falla** — añadir al final de `tests/Kernel/FrameworkRootNotPortalTest.php`:

```php
test('framework root .env.example does not ship Portal or Marketing env keys', function () use ($root): void {
    $path = $root . '/.env.example';
    assert_true(is_readable($path), 'root .env.example must exist');
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $forbiddenPrefixes = ['MKT_', 'LEBYTEK_API_', 'WAAPI_PORTAL_'];
    foreach ($lines as $lineNum => $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (!str_contains($trimmed, '=')) {
            continue;
        }
        $key = trim(explode('=', $trimmed, 2)[0]);
        foreach ($forbiddenPrefixes as $prefix) {
            assert_true(
                !str_starts_with($key, $prefix),
                ".env.example line " . ($lineNum + 1) . ": key «{$key}» uses forbidden prefix «{$prefix}». "
                . 'Action: remove Portal/Marketing vars from root .env.example; see Lebytek_Portal/.env.example'
            );
        }
    }
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php FrameworkRootNotPortal` / Expected: **FAIL** — 4 tests descubiertos; fallo en el nuevo test citando p. ej. `MKT_EMAIL_DOCS_URL` o `LEBYTEK_API_URL`.

- [x] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 4 purga `.env.example`.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php FrameworkRootNotPortal` / Expected: FAIL (gate rojo confirmado).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS si Task 2 mergeada en rama; FAIL si solo Task 3 committeada (orden flexible en rama, ambos verdes antes de PR).

- [x] **Step 6: Commit** — archivos: `tests/Kernel/FrameworkRootNotPortalTest.php` / mensaje: `test(kernel): assert root .env.example has no Portal env prefixes (red)`

---

### Task 4: Purga root `.env.example` — vars Portal/Marketing

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** Task 3

**Files:**
- Modify: `.env.example` (eliminar bloques L53–55, L70–82, L92–102; insertar comentario ref Portal)
- Test: `tests/Kernel/FrameworkRootNotPortalTest.php`

**Interfaces:**
- Consumes: test rojo Task 3; `skeleton/.env.example` limpio (sin cambio — referencia)
- Produces: root `.env.example` sin keys `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`; conserva `REGISTRO_*`, `LOGIN_*`, `INSTALL_TOKEN`, `GREEN_API_*`, `INTEGRATIONS_API_DOCS_URL`, `STRIPE_*`, `PAYMENTS_*`

- [x] **Step 1: Escribir el test que falla** — test de Task 3 ya rojo; re-ejecutar.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php FrameworkRootNotPortal` / Expected: FAIL pre-purga.

- [x] **Step 3: Implementar el cambio mínimo** — en `.env.example`:

1. **Eliminar** líneas 53–55 (comentario + `MKT_EMAIL_DOCS_URL`, `MKT_EMAIL_DASHBOARD_URL`).
2. **Eliminar** bloque L70–82 (`# API MOTOR` … `WAAPI_PORTAL_ENABLED`).
3. **Eliminar** líneas L92–102 (`MKT_ALERT_WHATSAPP_NUMBERS` … `MKT_MEMBERSHIP_AUTHORIZE_ENABLED`).
4. **Insertar** tras `MAIL_FROM_NAME` (después de la sección CORREO):

```dotenv
# Variables de negocio Portal (Marketing, Lebytek API client, panel waapi):
# definir en Parzival2103/Lebytek_Portal — .env.example
# No duplicar MKT_*, LEBYTEK_API_* ni WAAPI_PORTAL_* en el harness del package source.
```

5. **Conservar** bloque `# ── Integraciones locales` (`GREEN_API_*`, `INTEGRATIONS_API_DOCS_URL`) y `# Pagos (plataforma)`.

Verificación estática post-edición:

```bash
grep -E '^(MKT_|LEBYTEK_API_|WAAPI_PORTAL_)' .env.example ; echo "grep_exit=$?"
```

Expected: sin líneas; `grep_exit=1`.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php FrameworkRootNotPortal` / Expected: PASS — 4 tests, 0 failed.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php SkeletonPurity` / Expected: PASS — 13 tests (skeleton `.env.example` sin regresión).

- [x] **Step 6: Commit** — archivos: `.env.example` / mensaje: `chore(env): purge Portal marketing vars from harness .env.example`

---

### Task 5: Checklist release semver + test `ReleaseChecklistDocTest`

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** Task 2

**Files:**
- Create: `tests/Docs/ReleaseChecklistDocTest.php`
- Modify: `docs/core/despliegue-y-versionado.md` (insertar subsección tras párrafo «Activar o actualizar un módulo…», ~L183)
- Test: `tests/Docs/ReleaseChecklistDocTest.php`

**Interfaces:**
- Consumes: versión sincronizada Task 2
- Produces: checklist numerado 5 pasos; test que exige menciones `composer.json`, `skeleton/config/app.php`, `PlatformVersionSemver`

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/ReleaseChecklistDocTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('despliegue-y-versionado documents three-file semver sync on release', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/core/despliegue-y-versionado.md');
    assert_true(str_contains($src, 'composer.json'), 'checklist must mention composer.json version');
    assert_true(str_contains($src, 'skeleton/config/app.php'), 'checklist must mention skeleton config');
    assert_true(str_contains($src, 'PlatformVersionSemver'), 'checklist must reference PlatformVersionSemver test');
    assert_true(str_contains($src, 'config/app.php'), 'checklist must mention harness config/app.php');
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php ReleaseChecklistDoc` / Expected: FAIL — doc sin checklist.

- [x] **Step 3: Implementar el cambio mínimo** — insertar en `docs/core/despliegue-y-versionado.md` tras el párrafo «Activar o actualizar un módulo **no** obliga a subir la versión de plataforma…»:

````markdown
### Checklist release de plataforma (tag `vX.Y.Z`)

Al publicar un release del paquete `lebytek/framework`, sincronizar **en el mismo commit** que precede al tag:

1. Actualizar `"version"` en `composer.json` (semver sin prefijo `v`, p. ej. `1.2.1`).
2. Actualizar `'version'` en `config/app.php` (harness) y `skeleton/config/app.php` (plantilla) al **mismo** valor.
3. Ejecutar `php tests/run.php PlatformVersionSemver` — debe pasar.
4. Crear y publicar el tag Git `vX.Y.Z` apuntando a ese commit.
5. Post-deploy smoke: `/admin/sistema/estado` muestra `vX.Y.Z` y `php scripts/status.php` imprime `Plataforma: vX.Y.Z`.

La versión mostrada en UI y CLI proviene de `config/app.php` → `Config::get('app.version')`, **no** de `git describe` ni de `InstalledVersions` en runtime.
````

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php ReleaseChecklistDoc` / Expected: PASS — 1 test, 0 failed.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS.

- [x] **Step 6: Commit** — archivos: `docs/core/despliegue-y-versionado.md`, `tests/Docs/ReleaseChecklistDocTest.php` / mensaje: `docs: add platform release semver sync checklist`

---

### Task 6: Regresión completa, smoke display y PR

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/harness-hygiene-unblock`

**Depends on:** Tasks 2, 4, 5

**Files:**
- Test: suite harness (sin edits adicionales si regresión verde)

**Interfaces:**
- Consumes: Tasks 1–5 mergeables en rama
- Produces: evidencia pre/post fix; PR hacia `main`

- [x] **Step 1: Escribir el test que falla** — N/A (verificación integrada).

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` sobre commit **anterior** a Task 2 / Expected: FAIL (evidencia TDD para PR body).

- [x] **Step 3: Implementar el cambio mínimo** — N/A.

- [x] **Step 4: Verificación enfocada** — Run:

```bash
php tests/run.php PlatformVersionSemver
php tests/run.php FrameworkRootNotPortal
php tests/run.php ReleaseChecklistDoc
php tests/run.php SkeletonPurity
php tests/run.php Docs/OpsDocsFpsAlignment
php tests/run.php Kernel/FrameworkRootNotPortal
```

Expected: cada filtro PASS con ≥1 test descubierto; totales 0 failed.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php` / Expected: 0 failed en gates M1/M2; distinguir fallos Integrations por MySQL ausente (no gate verde).

Smoke manual (harness local con `.env`):

```bash
php -S localhost:8000 -t public
# login → /admin/sistema/estado → tarjeta versión v1.2.1 (320–768px: legible sin truncar)
php scripts/status.php | grep 'Plataforma:'
grep -E '^(MKT_|LEBYTEK_API_|WAAPI_PORTAL_)' .env.example ; echo "grep_exit=$?"
```

Expected: `Plataforma: v1.2.1`; `grep_exit=1`.

- [x] **Step 6: Commit / PR** — push y abrir PR:

```bash
git push -u origin feature/harness-hygiene-unblock
gh pr create --base main --title "fix(harness): sync platform semver 1.2.1 and purge Portal env vars" \
  --body "Cierra M1+M2 del spec 2026-08-01 (D1, D2, D4, D5, D13).

- composer.json + config/app.php + skeleton/config/app.php → 1.2.1
- PlatformVersionSemverTest + FrameworkRootNotPortal env gate + ReleaseChecklistDocTest
- Purga MKT_*/LEBYTEK_API_*/WAAPI_PORTAL_* en root .env.example
- Sin cambios src/; Portal lock bump en PR separado"
```

---

## Fuera de alcance (spec 2026-08-01)

| ID | Tema | Owner | Motivo |
|----|------|-------|--------|
| D3, D14–D15 | Portal SHA / Stripe QA / bootstrap marketing | Portal/Ops | gh 404 — no verificado |
| D6–D8 | RBAC router, `/api/health`, `permisos.gestionar` | Framework | Backlog producto |
| D9 | GitHub Actions CI | Ops | Spec futuro |
| D10–D12, D16, D22 | Docs ops legacy | Framework | **Resueltos** M8 #56/#57 |
| Plan skeleton | `skeleton.lebytek.com` Tasks 2–10 | Framework/Ops | Plan `2026-07-26` |
| Tag `v1.2.2` obligatorio | — | Maintainer | PATCH config; tag opcional |
| Cambios `src/` runtime `InstalledVersions` | Framework | Enfoque B rechazado en spec |
| CF3–CF10 responsive/RBAC/health | Framework | Carry-forward specs posteriores |

## Criterios finales de aceptación

- [x] `composer.json` contiene `"version": "1.2.1"`.
- [x] `config/app.php` y `skeleton/config/app.php` tienen `'version' => '1.2.1'`.
- [x] Root `.env.example` sin keys `MKT_*`, `LEBYTEK_API_*`, `WAAPI_PORTAL_*`.
- [x] `php tests/run.php PlatformVersionSemver` — FAIL pre-fix, PASS post-fix (≥ 3 tests).
- [x] `php tests/run.php FrameworkRootNotPortal` — FAIL pre-fix por env, PASS post-fix (4 tests).
- [x] `php tests/run.php ReleaseChecklistDoc` — FAIL pre-fix, PASS post-fix.
- [x] `php tests/run.php SkeletonPurity` y `Docs/OpsDocsFpsAlignment` sin regresión.
- [x] Sin edits en `src/`, `database/`, `routes/` para este alcance.
- [x] Smoke: `/admin/sistema/estado` y `scripts/status.php` muestran `v1.2.1`.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Drift semver reintroducido | `PlatformVersionSemverTest` + checklist + `ReleaseChecklistDocTest` |
| Operador copia vars Portal desde harness antiguo | Purga + comentario ref Portal + test env |
| Confundir versión Portal vs framework | Documentado en checklist — dos números independientes |
| Cloud agent sin PHP | Ejecutor corre tests localmente antes de merge |

**Rollback:** revert commits Tasks 2, 4, 5; tags Git no se reescriben.

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php PlatformVersionSemver` pre-fix (FAIL) y post-fix (PASS).
- Salida `php tests/run.php FrameworkRootNotPortal` pre-fix (FAIL por `MKT_EMAIL_DOCS_URL`) y post-fix (PASS).
- `grep -E '^(MKT_|LEBYTEK_API_|WAAPI_PORTAL_)' .env.example` → vacío post-fix.
- Captura o curl de `/admin/sistema/estado` mostrando `v1.2.1`.
- Salida `php scripts/status.php` con `Plataforma: v1.2.1`.
- SHA commit y número PR hacia `main`.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-01T14:01:00Z |
| Reconciliación UTC | 2026-08-01T17:20:00Z |
| `origin/main` verificado | `2135953b0b250ad13f43dbd10af53e6f05b37a2e` |
| Tareas completadas / totales | **6 / 6** (Framework) |
| Modo fuente | normal (`docs/superpowers/specs/2026-08-01-audit-harness-hygiene-unblock-design.md`) |
| Siguiente tarea ejecutable | N/A — plan cerrado |
| Prerrequisitos | Cumplidos |
| Bloqueos pendientes ops | D3/D14/D15 Portal QA — **Requiere operador humano** (fuera de alcance Framework) |
| Evidencia | PR #60 audit, #61 spec+plan, #62 implementación merged; tests gate 27 passed / 0 failed en `main` |
| Estado | **Completo** — archivado 2026-08-01 |
