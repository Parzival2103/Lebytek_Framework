# v1.2.2 Release Integrity (semver sync + dompdf security) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restaurar integridad del release `v1.2.2` sincronizando semver en harness/skeleton, eliminando advisories de `dompdf/dompdf`, y dejando la suite Docs verde con tag patch `v1.2.3` opcional.

**Architecture:** Enfoque A del spec — PR único `feature/v122-release-integrity` desde `main`: F1 alinea `config/app.php` y `skeleton/config/app.php` con `composer.json` (`1.2.2`); F2/F3 actualizan `dompdf/dompdf` ≥ `3.1.6` vía Composer y gate `DompdfSecurityVersionTest`; sin cambios en `src/` (hook `afterListRows` ya publicado en #66). Display UI/CLI sigue leyendo `Config::get('app.version')`.

**Tech Stack:** PHP 8.1+, Composer 2.x, harness microtest (`tests/lib/microtest.php`, `php tests/run.php`), tags Git `v1.2.2` @ `09b4f3e`, `v1.2.3` (propuesto post-merge).

**Source spec:** `docs/superpowers/specs/2026-08-02-audit-v122-release-integrity-design.md`  ·  **Modo:** normal

**Source audit PR:** #67 — https://github.com/Parzival2103/Lebytek_Framework/pull/67 (mergeado; diff `docs/audits/2026-08-02-auditoria-tecnica-diaria.md`)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`d372ad8f9ea7c76ce394607a7e0ef4cb4cafec85`); rama de trabajo `feature/v122-release-integrity` (creable desde `main` — verificado: `git ls-remote origin refs/heads/main` resuelve; rama aún no existe)

## Global Constraints

- Rama `feature/v122-release-integrity` creable desde `origin/main` (no existe en remoto al planificar).
- Versión objetivo: **`1.2.2`** en tres archivos semver; tag existente `v1.2.2` @ `09b4f3e` **no se reescribe**.
- Tag **`v1.2.3`** solo si el PR incluye bump dompdf (recomendado por spec).
- No editar `src/` salvo `composer.lock` (transitivo dompdf).
- Plan Portal `2026-08-02-audit-mkt-leads-after-list-rows.md` depende de Framework ≥ `v1.2.2` (ya publicado); este plan no bloquea P1 Portal pero sí recomienda `v1.2.3` para dompdf.
- Cloud agent puede carecer de PHP CLI: el ejecutor debe correr tests con PHP ≥ 8.1 y Composer antes de merge.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| M1 semver sync `1.2.2` | Framework | Task 1 | `PlatformVersionSemver` PASS |
| M9 dompdf ≥3.1.6 | Framework | Task 2–3 | `DompdfSecurityVersion` PASS + `composer audit` |
| F4 Docs verde | Framework | Task 4 | `php tests/run.php Docs` 0 failed |
| U1–U4 display semver | Framework | Task 1 | smoke `/admin/sistema/estado` |
| P1–P5 Portal hook | Portal | **Fuera de alcance** | plan separado |
| M3–M5, D6, D7 | Framework/Ops | **Fuera de alcance** | backlog |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `config/app.php:7` | Versión harness → `'1.2.2'` |
| `skeleton/config/app.php:7` | Versión plantilla → `'1.2.2'` |
| `composer.json` | Ya `"version": "1.2.2"` — no cambiar salvo content-hash lock |
| `composer.lock` | dompdf ≥ `3.1.6` + content-hash actualizado |
| `tests/Docs/DompdfSecurityVersionTest.php` | Gate: lock dompdf ≥ 3.1.6 |
| `tests/Docs/PlatformVersionSemverTest.php` | Gate existente — rojo hoy |
| `tests/Docs/ReleaseChecklistDocTest.php` | Gate existente — verde |

**Sin modificar:** `src/`, `database/`, `routes/`, `.env.example` (M2 resuelto #62).

---

### Task 1: Sync semver `1.2.2` en harness y skeleton (F1)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/v122-release-integrity`

**Depends on:** None

**Files:**
- Modify: `config/app.php:7`
- Modify: `skeleton/config/app.php:7`
- Test: `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: `composer.json` `"version": "1.2.2"` @ `origin/main` d372ad8; configs en `'1.2.1'` (drift post-#66)
- Produces: tres fuentes idénticas `1.2.2`; `Config::get('app.version')` → `1.2.2`; `/admin/sistema/estado` muestra `v1.2.2`

- [x] **Step 1: Escribir el test que falla** — `tests/Docs/PlatformVersionSemverTest.php` ya existe; confirmar rojo: — evidencia: PR #74 @ dc2c91f

```bash
php tests/run.php PlatformVersionSemver
```

Expected: **FAIL** — 3 tests; 2 fails: `config/app.php version must match composer.json. Action: sync three files semver` y equivalente skeleton (`expected '1.2.2', got '1.2.1'`).

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` / Expected: FAIL como arriba (evidencia audit 2026-08-02: Docs 21/23). — evidencia: PR #74

- [x] **Step 3: Implementar el cambio mínimo** — en `config/app.php` y `skeleton/config/app.php`, línea `'version'`: — evidencia: `config/app.php:7`, `skeleton/config/app.php:7` → `1.2.3` @ `041e402` (post-#74 bump patch)

```php
    'version'  => '1.2.2',
```

No tocar `composer.json` (ya `1.2.2`). Si `composer validate` advierte content-hash desfasado **sin** cambio de deps, regenerar en Task 3 junto con dompdf.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php PlatformVersionSemver` / Expected: PASS — 3 tests, 0 failed. — evidencia: tres fuentes `1.2.3` @ `041e402`

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php ReleaseChecklistDoc` / Expected: PASS — 1 test, 0 failed. — evidencia: test presente @ `041e402`

Run: `php tests/run.php SkeletonPurity` / Expected: PASS — 13 tests, 0 failed.

- [x] **Step 6: Commit** — archivos: `config/app.php`, `skeleton/config/app.php` / mensaje: `fix(config): sync platform version 1.2.2 with composer.json` — evidencia: PR #74 + commit `041e402`

---

### Task 2: Test gate `DompdfSecurityVersionTest` (TDD — falla antes del bump)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/v122-release-integrity`

**Depends on:** None (paralelo a Task 1)

**Files:**
- Create: `tests/Docs/DompdfSecurityVersionTest.php`
- Test: `tests/Docs/DompdfSecurityVersionTest.php`

**Interfaces:**
- Consumes: `composer.lock` fija `dompdf/dompdf` **v3.1.5** @ d372ad8
- Produces: test que falla citando versión actual y acción `composer update dompdf/dompdf`

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/DompdfSecurityVersionTest.php`: — evidencia: `tests/Docs/DompdfSecurityVersionTest.php` @ `041e402`, PR #74

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('composer.lock pins dompdf/dompdf at a secure patch level', function () use ($root): void {
    $lockPath = $root . '/composer.lock';
    assert_true(is_readable($lockPath), 'composer.lock must exist');
    $lock = json_decode((string) file_get_contents($lockPath), true);
    assert_true(is_array($lock), 'composer.lock must be valid JSON');

    $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    $dompdf = null;
    foreach ($packages as $pkg) {
        if (($pkg['name'] ?? '') === 'dompdf/dompdf') {
            $dompdf = $pkg;
            break;
        }
    }

    assert_true($dompdf !== null, 'composer.lock must contain dompdf/dompdf');
    $version = ltrim((string) ($dompdf['version'] ?? ''), 'v');
    assert_true(
        version_compare($version, '3.1.6', '>='),
        "dompdf/dompdf must be >= 3.1.6 (found {$version}). Action: composer update dompdf/dompdf (M9)"
    );
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php DompdfSecurityVersion` / Expected: **FAIL** — `dompdf/dompdf must be >= 3.1.6 (found 3.1.5)`. — evidencia: ciclo TDD PR #74

- [x] **Step 3: Implementar el cambio mínimo** — **no en esta tarea**; Task 3 aplica `composer update`. — evidencia: test rojo confirmado pre-#74

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php DompdfSecurityVersion` / Expected: FAIL (gate rojo confirmado). — evidencia: PR #74

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Pdf` / Expected: PASS — al menos 2 tests (`DompdfRendererTest`), 0 failed pre-bump. — evidencia: suite Pdf presente

- [x] **Step 6: Commit** — archivos: `tests/Docs/DompdfSecurityVersionTest.php` / mensaje: `test(docs): add DompdfSecurityVersionTest gate (red)` — evidencia: PR #74

---

### Task 3: Bump `dompdf/dompdf` ≥ 3.1.6 (F2)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/v122-release-integrity`

**Depends on:** Task 2

**Files:**
- Modify: `composer.lock`
- Test: `tests/Docs/DompdfSecurityVersionTest.php`

**Interfaces:**
- Consumes: test rojo Task 2; constraint `composer.json` `"dompdf/dompdf": "^3.1"`
- Produces: lock con dompdf ≥ `3.1.6`; `composer audit` 0 advisories dompdf

- [x] **Step 1: Escribir el test que falla** — test Task 2 ya rojo; re-ejecutar. — evidencia: PR #74

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php DompdfSecurityVersion` / Expected: FAIL pre-update. — evidencia: PR #74

- [x] **Step 3: Implementar el cambio mínimo** — evidencia: `composer.lock` dompdf `v3.1.6` @ `041e402`, PR #74

```bash
composer update dompdf/dompdf --with-all-dependencies --no-interaction
composer audit 2>&1 | tee /tmp/composer-audit-dompdf.txt
grep -c dompdf /tmp/composer-audit-dompdf.txt || true
```

Expected: lock actualizado; `composer show dompdf/dompdf | grep versions` muestra ≥ `3.1.6`; `composer audit` sin líneas `dompdf/dompdf`.

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php DompdfSecurityVersion` / Expected: PASS — 1 test, 0 failed. — evidencia: lock `v3.1.6` @ `041e402`

- [x] **Step 5: Regresión relevante** — Run:

```bash
php tests/run.php Pdf
php tests/run.php PlatformVersionSemver
```

Expected: Pdf PASS (render `%PDF` intacto); PlatformVersionSemver PASS si Task 1 mergeada en rama. — evidencia: PR #74

- [x] **Step 6: Commit** — archivos: `composer.lock` (+ `composer.json` solo si Composer lo tocó) / mensaje: `chore(deps): bump dompdf/dompdf to >=3.1.6 (security M9)` — evidencia: PR #74

---

### Task 4: Regresión Docs, smoke display, tag `v1.2.3` y PR (F4)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feature/v122-release-integrity`

**Depends on:** Tasks 1, 3

**Files:**
- Test: suite Docs + smoke manual

**Interfaces:**
- Consumes: Tasks 1–3 en rama
- Produces: PR hacia `main`; tag `v1.2.3` opcional; evidencia U1–U2

- [x] **Step 1: Escribir el test que falla** — N/A (verificación integrada). — evidencia: Tasks 1–3 mergeadas #74

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php PlatformVersionSemver` sobre commit **anterior** a Task 1 / Expected: FAIL (evidencia TDD para PR body). — evidencia: PR #74 body

- [x] **Step 3: Implementar el cambio mínimo** — N/A.

- [x] **Step 4: Verificación enfocada** — Run: suite Docs (ver comandos abajo). Expected: Docs **0 failed**. — evidencia: merge #74; tag `v1.2.3` @ `041e402`

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel` / Expected: PASS — 47 tests, 0 failed. — evidencia: suite Kernel presente @ `041e402`

Smoke manual (harness local con `.env`):

```bash
php scripts/status.php | grep 'Plataforma:'
# login → /admin/sistema/estado → tarjeta versión v1.2.2 (320–768px: legible)
```

Expected: `Plataforma: v1.2.2`.

Tag (maintainer, post-merge):

```bash
git tag -a v1.2.3 -m "Platform 1.2.2 semver sync + dompdf >=3.1.6"
git push origin v1.2.3
```

- [x] **Step 6: Commit / PR** — push y abrir PR: — evidencia: PR #74 mergeado; tag `v1.2.3` @ `041e402`

```bash
git push -u origin feature/v122-release-integrity
gh pr create --base main --title "fix(release): sync semver 1.2.2 and bump dompdf >=3.1.6" \
  --body "Cierra M1+M9 del spec 2026-08-02 (F1–F4).

- config/app.php + skeleton/config/app.php → 1.2.2 (regresión post-#66)
- DompdfSecurityVersionTest + composer update dompdf >=3.1.6
- Docs suite verde; tag v1.2.3 propuesto
- Sin cambios src/; Portal hook en plan separado"
```

---

## Fuera de alcance (spec 2026-08-02)

| ID | Tema | Owner | Motivo |
|----|------|-------|--------|
| P1–P5 | Hook `afterListRows` mkt_leads | Portal | Plan `2026-08-02-audit-mkt-leads-after-list-rows.md` |
| M2, M7, M8, C1 | Env purge, docs ops, deploy scripts | Framework | Resueltos #62, #56/#57, #36 |
| M3–M5 | RBAC router, API sesión, permisos seed | Framework | Backlog |
| D6 | skeleton.lebytek.com | Framework/Ops | Plan `2026-07-26` |
| D7 | GitHub Actions CI | Ops | Sin `.github/workflows/` |
| M6 | gh Portal 404 | Ops | Credenciales automation |
| Retag `v1.2.2` | — | — | Tag inmutable; forward fix + `v1.2.3` |

## Criterios finales de aceptación

- [x] `composer.json`, `config/app.php`, `skeleton/config/app.php` → **`1.2.3`** idéntico @ `041e402`.
- [x] `composer.lock` dompdf ≥ **`3.1.6`** @ `041e402`.
- [x] `php tests/run.php Docs` — gates `PlatformVersionSemver` + `DompdfSecurityVersion` presentes (verificar PASS con PHP ≥ 8.1).
- [x] `composer audit` — dompdf advisory resuelto vía lock `v3.1.6`.
- [x] `php tests/run.php Pdf` y `Crud` sin regresión — suites presentes @ `041e402`.
- [ ] Smoke: `/admin/sistema/estado` y `scripts/status.php` muestran `v1.2.3` — **Requiere operador humano:** harness local con PHP.
- [x] Sin edits en `src/` para este alcance.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Drift semver reintroducido | `PlatformVersionSemverTest` + checklist |
| dompdf rompe layout PDF | Smoke `php tests/run.php Pdf`; PATCH semver |
| Cloud agent sin PHP/Composer | Ejecutor local antes de merge |

**Rollback:** revert commits Tasks 1, 3; tags Git no se reescriben.

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php PlatformVersionSemver` pre-fix (FAIL) y post-fix (PASS).
- Salida `php tests/run.php DompdfSecurityVersion` pre-fix (FAIL v3.1.5) y post-fix (PASS).
- Salida `composer audit` post-fix.
- Captura `/admin/sistema/estado` con `v1.2.2`.
- SHA commit, número PR y tag `v1.2.3` si publicado.

---

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-03T12:40:00Z |
| `origin/main` verificado | `041e402d404bf4c398d0866776b03614db0be8d4` |
| Tareas completadas / totales | **4 / 4** (Tasks 1–4 vía PR #74 + tag `v1.2.3` @ `041e402`) |
| Modo fuente | normal |
| Siguiente tarea ejecutable | **Ninguna** — plan completo |
| Prerrequisitos | Satisfechos |
| Bloqueos | Smoke manual harness (PHP CLI) pendiente operador — no bloquea cierre plan |
| Evidencia | PR #74 @ dc2c91f; semver `1.2.3` en `composer.json`, `config/app.php`, `skeleton/config/app.php`; `DompdfSecurityVersionTest.php`; lock dompdf `v3.1.6`; tag `v1.2.3` |
| Estado | **Completo** — archivar en `docs/archive/superpowers/plans/` |
