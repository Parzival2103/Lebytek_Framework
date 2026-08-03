# Platform Version Semver Sync (post-v1.2.1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alinear la versión de plataforma visible (`config/app.php`, skeleton, admin estado, install wizard, CLI) con el release semver publicado `v1.2.1`, usando `composer.json` como fuente canónica y un test gate que impida drift futuro.

**Architecture:** Enfoque A del spec — `"version"` semver en `composer.json` del paquete; `config/app.php` (harness) y `skeleton/config/app.php` (plantilla) se sincronizan manualmente en cada release. `DeploymentStatus` y las vistas existentes siguen leyendo `Config::get('app.version')` vía DI (`FrameworkServiceProvider` inyecta `plataformaVersion` desde config). Sin cambios en `src/` ni lectura runtime desde `InstalledVersions` (rechazado: harness pre-vendor y path-repo devuelven valores incorrectos).

**Tech Stack:** PHP 8.1+, Composer 2.x, harness microtest (`tests/lib/microtest.php`, `php tests/run.php`), tags Git `v1.2.0`/`v1.2.1` @ `fba3e03`, Bootstrap 5.3 admin/install (display only).

**Source spec:** `docs/superpowers/specs/2026-07-29-audit-config-version-semver-sync-design.md`  ·  **Modo:** normal

**Source audit PR:** #48 — https://github.com/Parzival2103/Lebytek_Framework/pull/48 (cerrado; diff único `docs/audits/2026-07-29-auditoria-tecnica-diaria.md`)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`0ec722bc38258b2e479d30cafd59940aa44d558e`); rama de trabajo `feature/platform-version-semver-sync` (creable desde `main`)

## Global Constraints

- Rama `feature/platform-version-semver-sync` verificada creable: `git ls-remote origin refs/heads/main` resuelve.
- Versión objetivo al merge: **`1.2.1`** (tag `v1.2.1` @ `fba3e03`, commit actual de release en `main`). Si un tag más reciente existe al ejecutar, alinear al tag vigente.
- No editar `src/Application/Install/DeploymentStatus.php` ni controladores — el fix es config + test + docs.
- No mezclar en el mismo PR la purga `.env.example` (Fase 2 spec 2026-07-27) ni docs integration (Fase 2b).
- Cloud agent puede carecer de PHP CLI: el ejecutor debe correr tests en entorno con PHP ≥ 8.1 antes de merge.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| D1 drift semver M1 | Framework | Task 1–2 | `PlatformVersionSemverTest` PASS |
| D4 test gate ausente | Framework | Task 1 | test falla pre-fix, pasa post-fix |
| D13 checklist release | Framework | Task 3 | § Versionado en `despliegue-y-versionado.md` |
| K3/U1 display alineado | Framework | Task 2 | smoke manual estado + install resultado |
| D2/D5 env purge M2 | Framework | **Fuera de alcance** | spec archivado 2026-07-27 |
| D3/D14/D15 Portal | Portal/Ops | **Fuera de alcance** | gh 404 Portal |
| D6–D12 backlog | Framework | **Fuera de alcance** | specs futuros |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `composer.json` | Fuente canónica semver — añadir `"version": "1.2.1"` |
| `config/app.php:7` | Versión plataforma harness — `'version' => '1.2.1'` |
| `skeleton/config/app.php:7` | Versión plataforma plantilla — mismo valor |
| `tests/Docs/PlatformVersionSemverTest.php` | Gate: igualdad entre tres fuentes |
| `docs/core/despliegue-y-versionado.md` | Checklist release 5 pasos en § Versionado |

**Sin modificar:** `src/Application/Install/DeploymentStatus.php`, `src/Kernel/Container/FrameworkServiceProvider.php` (ya leen `app.version`), `public/install/*`, `scripts/status.php`.

---

### Task 1: Test gate `PlatformVersionSemverTest` (TDD — falla antes del fix)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-version-semver-sync`

**Depends on:** None

**Files:**
- Create: `tests/Docs/PlatformVersionSemverTest.php`
- Test: `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: `composer.json`, `config/app.php`, `skeleton/config/app.php` (estado actual: sin `version` en composer; configs en `1.0.0`)
- Produces: test que falla con mensaje explícito de drift vs tag `v1.2.1`

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
        'composer.json must declare a non-empty "version" field (semver without v prefix)'
    );
});

test('harness config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $rootConfig = require $root . '/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same($composer['version'], $rootConfig['version'] ?? null);
});

test('skeleton config/app.php version matches composer.json', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    $skelConfig = require $root . '/skeleton/config/app.php';
    assert_true(isset($composer['version']), 'composer.json must declare version first');
    assert_same($composer['version'], $skelConfig['version'] ?? null);
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` / Expected: **FAIL** — al menos 3 tests; primer fallo típico: `composer.json must declare a non-empty "version" field` o `expected '1.2.1', got '1.0.0'` tras añadir composer sin sync configs.

- [x] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 2 aplica el fix. Este step confirma que el test descubre ≥1 archivo y falla (gate rojo).

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php PlatformVersionSemver` / Expected: FAIL (confirmación TDD).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/DeployScriptsRemoved` / Expected: PASS — 3 tests, 0 failed (C1 sigue resuelto).

- [x] **Step 6: Commit** — archivos: `tests/Docs/PlatformVersionSemverTest.php` / mensaje: `test(docs): add PlatformVersionSemverTest gate (red)`

---

### Task 2: Sincronizar versión `1.2.1` en composer.json y configs

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-version-semver-sync`

**Depends on:** Task 1

**Files:**
- Modify: `composer.json` (insertar `"version": "1.2.1"` tras `"license"`)
- Modify: `config/app.php:7`
- Modify: `skeleton/config/app.php:7`
- Test: `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: test rojo de Task 1
- Produces: `"version": "1.2.1"` idéntico en tres archivos; `Config::get('app.version')` → `1.2.1`; UI `/admin/sistema/estado` muestra `v1.2.1` (prefijo `v` solo en vista L10 de `estado.php`)

- [x] **Step 1: Escribir el test que falla** — ya verde tras Task 1 commit; re-ejecutar para confirmar rojo pre-fix.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` / Expected: FAIL antes de editar configs.

- [x] **Step 3: Implementar el cambio mínimo**

En `composer.json`, añadir después de `"license": "proprietary",`:

```json
    "version": "1.2.1",
```

En `config/app.php` y `skeleton/config/app.php`, línea `'version'`:

```php
    'version'  => '1.2.1',
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS — 3 tests, 0 failed.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php SkeletonPurity` / Expected: PASS — 13 tests, 0 failed (sin regresión skeleton).

Run: `php tests/run.php Kernel/FrameworkRootNotPortal` / Expected: PASS — 3 tests, 0 failed.

- [x] **Step 6: Commit** — archivos: `composer.json`, `config/app.php`, `skeleton/config/app.php` / mensaje: `fix(config): sync platform version 1.2.1 with composer.json`

---

### Task 3: Documentar checklist de release semver

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-version-semver-sync`

**Depends on:** Task 2

**Files:**
- Modify: `docs/core/despliegue-y-versionado.md` — insertar subsección tras «Dos números independientes con semver» (~L183)

**Interfaces:**
- Consumes: versión sincronizada Task 2
- Produces: checklist numerado de 5 pasos referenciado por ops en cada tag `vX.Y.Z`

- [x] **Step 1: Escribir el test que falla** — extender `tests/Docs/FpsPublicationReadinessTest.php` o crear assert en `PlatformVersionSemverTest` hermano `ReleaseChecklistDocTest.php`:

```php
test('despliegue-y-versionado documents three-file semver sync on release', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/core/despliegue-y-versionado.md');
    assert_true(str_contains($src, 'composer.json'), 'checklist must mention composer.json version');
    assert_true(str_contains($src, 'skeleton/config/app.php'), 'checklist must mention skeleton config');
    assert_true(str_contains($src, 'PlatformVersionSemver'), 'checklist must reference PlatformVersionSemver test');
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php ReleaseChecklistDoc` (o filtro equivalente) / Expected: FAIL — doc sin checklist.

- [x] **Step 3: Implementar el cambio mínimo** — insertar en `docs/core/despliegue-y-versionado.md` tras el párrafo «Activar o actualizar un módulo…»:

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

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php ReleaseChecklistDoc` / Expected: PASS.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS.

- [x] **Step 6: Commit** — archivos: `docs/core/despliegue-y-versionado.md`, test doc / mensaje: `docs: add platform release semver sync checklist`

---

### Task 4: Regresión completa y smoke display

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/platform-version-semver-sync`

**Depends on:** Task 3

**Files:**
- Test: suite completa harness

**Interfaces:**
- Consumes: Tasks 1–3 mergeables
- Produces: evidencia de no-regresión; PR listo para review

- [x] **Step 1: Escribir el test que falla** — N/A (verificación integrada).

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — N/A.

- [x] **Step 3: Implementar el cambio mínimo** — N/A.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php` / Expected: 0 failed (entorno con PHP + extensiones; fallos preexistentes por `pdo_mysql` ausente en cloud agent **no** son gate verde — distinguir en PR body).

- [x] **Step 5: Regresión relevante** — Run:

```bash
php tests/run.php PlatformVersionSemver
php tests/run.php SkeletonPurity
php tests/run.php Docs/DeployScriptsRemoved
php tests/run.php Kernel/FrameworkRootNotPortal
php tests/run.php Docs/AutomationPreflightRef
```

Expected: cada filtro PASS con al menos 1 test descubierto; totales 0 failed.

Smoke manual (harness local con `.env`):

```bash
php -S localhost:8000 -t public
curl -s http://localhost:8000/admin/sistema/estado  # requiere login; verificar tarjeta versión
php scripts/status.php | grep 'Plataforma:'
```

Expected: `Plataforma: v1.2.1`.

- [x] **Step 6: Commit** — N/A si solo verificación; abrir PR:

```bash
git push -u origin feature/platform-version-semver-sync
gh pr create --base main --title "fix(config): sync platform version 1.2.1 (semver gate)" \
  --body "Cierra D1/D4/D13 del spec 2026-07-29.

- composer.json + config/app.php + skeleton/config/app.php → 1.2.1
- PlatformVersionSemverTest + checklist release
- Sin cambios src/; Fase 2 env purge en PR separado"
```

---

## Fuera de alcance (spec 2026-07-29)

| ID | Tema | Owner | Motivo |
|----|------|-------|--------|
| D2, D5, M2 | Purga root `.env.example` | Framework | Fase 2 — spec archivado `2026-07-27-audit-harness-portal-env-purge-design.md`; PR separado |
| D10–D12, D16 | Docs integration/composer drift | Framework | Fase 2b backlog docs |
| D3 | Token gh lectura Portal | Ops | Requiere operador humano |
| D6–D8 | RBAC router, `/api/health`, `permisos.gestionar` | Framework | Backlog producto |
| D9 | GitHub Actions CI | Ops | Evaluación independiente |
| D14, D15 | Stripe QA Portal, bootstrap marketing #4 | Portal | gh 404; no verificado |
| Fase 3 ops | Configuración automation | Ops | Fuera corrida desatendida |
| Tag nuevo obligatorio | — | — | PATCH config; tag `v1.2.2` solo si maintainer decide release post-merge |
| `skeleton.lebytek.com` deploy | Framework/VPS | Plan activo `2026-07-26-skeleton-package-staging.md` |
| Cambios `src/` runtime `InstalledVersions` | Framework | Enfoque C rechazado en spec |

## Criterios finales de aceptación

- [x] `composer.json` contiene `"version": "1.2.3"` @ `041e402` (supersedes objetivo `1.2.1`).
- [x] `config/app.php` y `skeleton/config/app.php` tienen el mismo `version` @ `041e402`.
- [x] `php tests/run.php PlatformVersionSemver` — gate presente (PASS con PHP ≥ 8.1).
- [x] Test TDD entregado vía PR #62.
- [x] `docs/core/despliegue-y-versionado.md` incluye checklist 5 pasos — PR #62.
- [x] `php tests/run.php SkeletonPurity` — suite presente @ `041e402`.
- [x] Sin edits en `src/` para este alcance.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Drift reintroducido en próximo release | `PlatformVersionSemverTest` + checklist D13 |
| Confundir versión app Portal vs framework | Documentado: dos números independientes en `despliegue-y-versionado.md` |
| Cloud agent sin PHP | Ejecutor corre tests localmente/CI antes de merge |

**Rollback:** revert commit Task 2; tags Git no se reescriben.

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php PlatformVersionSemver` pre-fix (FAIL) y post-fix (PASS).
- Captura o curl de `/admin/sistema/estado` mostrando `v1.2.1`.
- Salida `php scripts/status.php` con `Plataforma: v1.2.1`.
- SHA commit y número PR hacia `main`.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-03T12:40:00Z |
| `origin/main` verificado | `041e402d404bf4c398d0866776b03614db0be8d4` |
| Tareas completadas / totales | **4 / 4** (entregadas vía PR #62 semver gates + checklist; semver sync final PR #74 @ `1.2.3`) |
| Modo fuente | normal (spec `2026-07-29-audit-config-version-semver-sync-design.md`) |
| Siguiente tarea ejecutable | **Ninguna** — plan completo (supersedido por plans 2026-08-01/2026-08-02) |
| Prerrequisitos | Satisfechos |
| Bloqueos | Ninguno |
| Evidencia verificada | `tests/Docs/PlatformVersionSemverTest.php`, `ReleaseChecklistDocTest.php`, checklist § @ `041e402`; semver `1.2.3` |
| Nota | Spec 2026-08-01 agrupó M1+M2; este plan M1-only cumplido indirectamente |
| Estado | **Completo** — archivar en `docs/archive/superpowers/plans/` |
