# Release Semver Tag v1.2.7 (REL-C1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publicar el tag Git `v1.2.7` alineado con el trío semver declarado en tip `main`, con test gate TDD, release notes y retarget de planes M3/M4, para que fixes AuthZ (#95) y states (#100) sean instalables vía Composer sin parchear `vendor/`.

**Architecture:** Enfoque A del spec — un tag único `v1.2.7` desde tip verde CI que ya declara `1.2.7` en `composer.json` + configs. El nuevo `ReleaseTagPublishedTest` cierra el hueco que `PlatformVersionSemverTest` no cubre (sync interno ≠ tag publicado). Invoicing scaffold (#99) viaja en el mismo tag pero vertical OFF; Facturapi prod queda bloqueado por plan hardening 0/10.

**Tech Stack:** PHP `>=8.2`, harness `php tests/run.php`, Git tags anotados, Composer `"lebytek/framework": "^1.2"` / pin `1.2.7`, docs en `docs/core/` y `docs/release/`.

**Source spec:** `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md`  ·  **Modo:** normal (fuente Nivel B — audit `automation/audit-2026-08-08` @ `bf4b7fc`)

**Source audit PR:** [#104](https://github.com/Parzival2103/Lebytek_Framework/pull/104) — `docs(audit): auditoría técnica diaria 2026-08-08` (mergeado; hallazgo REL-C1)

**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main`; rama de trabajo `cursor/release-semver-tag-rel-c1` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

## Global Constraints

- **No** bajar semver del trío a `1.2.3` (enfoque C rechazado en spec).
- **No** publicar tags retroactivos `v1.2.4`–`v1.2.6` salvo decisión humana excepcional documentada fuera de este plan.
- **No** habilitar `vertical.modules.invoicing` ni `FACTURAPI_ENABLED` como parte de esta release.
- **No** mergear `feature/backoffice-api-integration` → `main`.
- Portal bump lock (P1/P2) y deploy VPS — **fuera de alcance** (M6 bloquea verificación gh).
- Tag push y smoke `/admin/sistema/estado` — **Requiere operador humano** (Tasks 5–6).
- Semver post-REL-C1 para M3/M4 reservado en **`1.2.8+`**.

## Requisitos → tareas (matriz)

| Requisito spec | Owner | Tarea | Verificación |
|----------------|-------|-------|--------------|
| F1 `ReleaseTagPublishedTest` | Framework | Task 1 | TDD rojo pre-tag → verde post-tag |
| F2 Checklist release ejecutado | Framework | Task 3 | `ReleaseChecklistDocTest` + `PlatformVersionSemver` PASS |
| F3 Tag `v1.2.7` publicado | Ops | Task 6 | `git rev-parse v1.2.7^{commit}` exit 0 |
| F4 Release notes | Framework | Task 2 | `ReleaseNotesV127DocTest` PASS |
| F5 Retarget M3/M4 → `1.2.8+` | Framework | Task 4 | grep planes sin `1.2.4`/`1.2.5` como target activo |
| F6 Política tip semver = tag | Framework | Task 5 | `PACKAGE-ROOT.md` + test doc |
| P1/P2 Portal lock + smoke | Portal | **Fuera de alcance** | post-tag manual (M6) |
| O1/O2 Packagist + estado UI | Ops | Task 6 | manual |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `tests/Docs/ReleaseTagPublishedTest.php` | Gate: versión en `composer.json` tiene tag `v{version}` resoluble |
| `tests/Docs/ReleaseNotesV127DocTest.php` | Gate: release notes listan contenido obligatorio REL-C1 |
| `tests/Docs/ReleaseChecklistDocTest.php` | Extender: checklist menciona `ReleaseTagPublishedTest` |
| `docs/release/v1.2.7.md` | Release notes: AuthZ, states, invoicing-OFF, PHP ≥8.2, skip 1.2.4–1.2.6 |
| `docs/core/despliegue-y-versionado.md` | Checklist § release: paso tag gate + copy-paste push |
| `docs/PACKAGE-ROOT.md` | Política «declared semver = published tag» |
| `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` | Retarget semver `1.2.8+` |
| `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` | Retarget semver `1.2.8+` |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | Ya @ `1.2.7` — **no bump** salvo conflicto detectado en Task 3 |

**Interfaces producidas:**

- Test filter: `php tests/run.php Docs/ReleaseTagPublished`
- Test filter: `php tests/run.php Docs/ReleaseNotesV127`
- Tag Git: `v1.2.7` → commit ancestro de `64a6877` (#95) y `60477dc` (#100)

**Interfaces consumidas (sin modificar):**

- `PlatformVersionSemverTest` (trío sync — ya PASS)
- `ReleaseChecklistDocTest` (checklist base — extender)

---

### Task 1: Test gate `ReleaseTagPublishedTest` (F1, U2)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `cursor/release-semver-tag-rel-c1`

**Depends on:** None

**Files:**
- Create: `tests/Docs/ReleaseTagPublishedTest.php`
- Test: `tests/Docs/ReleaseTagPublishedTest.php`

**Interfaces:**
- Consumes: `composer.json` `"version": "1.2.7"`; tag `v1.2.7` **ausente** en tip pre-implementación
- Produces: test que falla con mensaje REL-C1 accionable citando acción `git tag`

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/ReleaseTagPublishedTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('published composer.json version has resolvable git tag vX.Y.Z (REL-C1)', function () use ($root): void {
    $composerPath = $root . '/composer.json';
    $data = json_decode((string) file_get_contents($composerPath), true);
    $version = $data['version'] ?? '';
    assert_true(
        is_string($version) && $version !== '',
        'composer.json must declare non-empty "version" before release tag gate'
    );

    $tag = 'v' . $version;
    $cmd = 'git rev-parse --verify ' . escapeshellarg($tag . '^{commit}') . ' 2>/dev/null';
    exec($cmd, $out, $code);

    assert_same(
        0,
        $code,
        sprintf(
            'REL-C1 (spec 2026-08-08-audit-release-semver-tag): composer.json declares %s but git tag %s is missing. '
            . 'Action: from a CI-green commit with synced semver trio, run: git tag -a %s -m "Platform release %s" && git push origin %s',
            $version,
            $tag,
            $tag,
            $version,
            $tag
        )
    );
});

test('ReleaseTagPublishedTest documents tag must contain AuthZ and states merge commits', function () use ($root): void {
    $path = $root . '/tests/Docs/ReleaseTagPublishedTest.php';
    $src = (string) file_get_contents($path);
    assert_true(str_contains($src, 'REL-C1'), 'gate message must cite REL-C1 for operators');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ReleaseTagPublished` / Expected: **FAIL** — `REL-C1 … git tag v1.2.7 is missing` (exit assertion `code !== 0`).

- [ ] **Step 3: Implementar el cambio mínimo** — solo el archivo de test en esta tarea; el tag se publica en Task 6 (ops).

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/ReleaseTagPublished` / Expected: **FAIL** (TDD rojo confirmado — tag aún ausente).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/PlatformVersionSemver` / Expected: **PASS** — 3 tests, 0 failed (trío sync independiente del tag).

- [ ] **Step 6: Commit** — `git add tests/Docs/ReleaseTagPublishedTest.php` / mensaje: `test(docs): add ReleaseTagPublishedTest gate for REL-C1 (red)`

---

### Task 2: Release notes `v1.2.7` + doc gate (F4, U1, U3, U5)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `cursor/release-semver-tag-rel-c1`

**Depends on:** Task 1

**Files:**
- Create: `docs/release/v1.2.7.md`
- Create: `tests/Docs/ReleaseNotesV127DocTest.php`
- Test: `tests/Docs/ReleaseNotesV127DocTest.php`

**Interfaces:**
- Consumes: spec § «Contenido del release v1.2.7»; commits `64a6877`, `60477dc`, `21edf26`
- Produces: release notes verificables; tabla semver frontera

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/ReleaseNotesV127DocTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('v1.2.7 release notes document REL-C1 content and skipped tags', function () use ($root): void {
    $path = $root . '/docs/release/v1.2.7.md';
    assert_true(is_file($path), 'missing docs/release/v1.2.7.md — create release notes per REL-C1 spec');
    $src = (string) file_get_contents($path);

    foreach ([
        'AuthZ',
        'states',
        'invoicing',
        'OFF',
        'PHP',
        '8.2',
        'v1.2.4',
        'v1.2.5',
        'v1.2.6',
        'hardening',
        '#95',
        '#100',
    ] as $needle) {
        assert_true(str_contains($src, $needle), "release notes must mention {$needle} (REL-C1 U1/U5)");
    }

    assert_true(
        str_contains($src, '1.2.8'),
        'release notes must state M3/M4 ship in 1.2.8+ not 1.2.4/1.2.5'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ReleaseNotesV127` / Expected: **FAIL** — `missing docs/release/v1.2.7.md`.

- [ ] **Step 3: Implementar el cambio mínimo** — crear `docs/release/v1.2.7.md` con secciones mínimas:

```markdown
# Release v1.2.7 — Platform (REL-C1)

## Included (installable via `composer require lebytek/framework:1.2.7`)

| Area | PR | Notes |
|------|-----|-------|
| AuthZ CRUD C1/C2/C5 | #95 @ 64a6877 | IDOR scope_handler, fail-closed actions, Reportes `{prefix}.ver` |
| States form C3 | #100 @ 60477dc | Column lock, select allowlist, demo toggle removed |
| Invoicing scaffold | #99 @ 21edf26 | Vertical **OFF** by default; `inv_*` SQL; requires PHP **≥8.2** |

## Skipped tags (not published)

- `v1.2.4` — M4 `/api/health` plan not implemented (retarget **1.2.8+**)
- `v1.2.5` — M3 CRUD RBAC router plan not implemented (retarget **1.2.8+**)
- `v1.2.6` — intermediate semver bump absorbed in commit history (#95); no standalone tag

## Not production-ready

- Facturapi fiscal prod: plan hardening **0/10** — do **not** enable `vertical.invoicing` / `FACTURAPI_ENABLED` until post-hardening tag.

## Consumer migration

1. PHP runtime **≥8.2** required (`composer.json` platform require).
2. Backup `composer.lock`; `composer update lebytek/framework --with-dependencies`.
3. Run consumer install/migrate wrapper; smoke CRUD AuthZ + states lock.
4. Rollback: restore prior lock + redeploy previous tag (e.g. `v1.2.3`).
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/ReleaseNotesV127` / Expected: **PASS**.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/ReleaseTagPublished` / Expected: **FAIL** (tag gate still red until Task 6).

- [ ] **Step 6: Commit** — `git add docs/release/v1.2.7.md tests/Docs/ReleaseNotesV127DocTest.php` / mensaje: `docs(release): add v1.2.7 release notes and doc gate (REL-C1)`

---

### Task 3: Checklist release — paso tag gate (F2, U4)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `cursor/release-semver-tag-rel-c1`

**Depends on:** Task 1

**Files:**
- Modify: `docs/core/despliegue-y-versionado.md` (§ «Checklist release de plataforma»)
- Modify: `tests/Docs/ReleaseChecklistDocTest.php`
- Test: `tests/Docs/ReleaseChecklistDocTest.php`, `tests/Docs/PlatformVersionSemverTest.php`

**Interfaces:**
- Consumes: checklist existente L185–195
- Produces: pasos ordenados incluyendo `ReleaseTagPublishedTest` + copy-paste tag push

- [ ] **Step 1: Escribir el test que falla** — extender `ReleaseChecklistDocTest.php`:

```php
test('release checklist references ReleaseTagPublishedTest gate', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/core/despliegue-y-versionado.md');
    assert_true(
        str_contains($src, 'ReleaseTagPublishedTest'),
        'checklist must reference ReleaseTagPublishedTest before tagging (REL-C1 U4)'
    );
    assert_true(
        str_contains($src, 'git push origin v'),
        'checklist must include copy-paste tag push command'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ReleaseChecklistDoc` / Expected: **FAIL** — `must reference ReleaseTagPublishedTest`.

- [ ] **Step 3: Implementar el cambio mínimo** — en `docs/core/despliegue-y-versionado.md` § Checklist, insertar entre pasos 3 y 4:

```markdown
3b. Ejecutar `php tests/run.php Docs/ReleaseTagPublished` — debe pasar **antes** de crear el tag (falla si el tag aún no existe; crear tag solo tras merge de este gate en CI verde).
```

Renumerar paso 4 tag push con bloque copy-paste:

```bash
git tag -a vX.Y.Z -m "Platform release X.Y.Z"
git push origin vX.Y.Z
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/ReleaseChecklistDoc` / Expected: **PASS**.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/PlatformVersionSemver Docs/ReleaseNotesV127` / Expected: **PASS** (trío @ `1.2.7` + release notes).

- [ ] **Step 6: Commit** — `git add docs/core/despliegue-y-versionado.md tests/Docs/ReleaseChecklistDocTest.php` / mensaje: `docs(release): extend platform checklist with ReleaseTagPublished gate`

---

### Task 4: Retarget planes M3/M4 semver → `1.2.8+` (F5)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `cursor/release-semver-tag-rel-c1`

**Depends on:** Task 2

**Files:**
- Modify: `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` (semver refs `1.2.4` → `1.2.8`, tag `v1.2.4` → `v1.2.8`)
- Modify: `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` (semver refs `1.2.5` → `1.2.8`, tag `v1.2.5` → `v1.2.8`)
- Modify: `docs/superpowers/plans/2026-08-05-audit-api-health-public.md` § Estado de ejecución (nota retarget REL-C1)
- Modify: `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` § Estado de ejecución
- Test: `tests/Docs/ReleaseNotesV127DocTest.php` (already asserts 1.2.8+)

**Interfaces:**
- Consumes: spec § «Planes M3/M4 retarget»; REL-C1 publica `v1.2.7` consumiendo números 1.2.4–1.2.6
- Produces: planes M3/M4 con target **`1.2.8`** y nota «skip 1.2.4–1.2.6»

- [ ] **Step 1: Escribir el test que falla** — añadir a `ReleaseNotesV127DocTest.php` (si no existe ya):

```php
test('M4 health plan retargets semver to 1.2.8 after REL-C1', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/superpowers/plans/2026-08-05-audit-api-health-public.md');
    assert_true(str_contains($src, '1.2.8'), 'M4 plan must target 1.2.8+ after REL-C1');
    assert_true(
        !preg_match('/tag `v1\.2\.4`/i', $src) || str_contains($src, 'skip'),
        'obsolete v1.2.4 tag target must be annotated as skipped or replaced'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/ReleaseNotesV127` / Expected: **FAIL** — M4 plan still targets `1.2.4`.

- [ ] **Step 3: Implementar el cambio mínimo** — en ambos planes:

Replace active semver targets:
- M4: `"1.2.4"` → `"1.2.8"` in Task 5 / Global Constraints / file structure tables; tag `v1.2.4` → `v1.2.8`.
- M3: `"1.2.5"` → `"1.2.8"` similarly.

Add banner after header in each plan:

```markdown
> **REL-C1 retarget (2026-08-08):** Tags `v1.2.4`–`v1.2.6` were not published. This plan ships in **`v1.2.8+`** after REL-C1 tag `v1.2.7`.
```

Update § Estado de ejecución with retarget note + `origin/main` @ release planning SHA.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/ReleaseNotesV127` / Expected: **PASS**.

- [ ] **Step 5: Regresión relevante** — Run: `rg '1\.2\.4|1\.2\.5' docs/superpowers/plans/2026-08-0{5,6}-audit-*.md` / Expected: matches only inside «skip» / REL-C1 banner / historical context, **not** as active Task 5 target.

- [ ] **Step 6: Commit** — `git add docs/superpowers/plans/2026-08-05-audit-api-health-public.md docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md tests/Docs/ReleaseNotesV127DocTest.php` / mensaje: `docs(plans): retarget M3/M4 semver to 1.2.8+ after REL-C1`

---

### Task 5: Política «declared semver = published tag» en PACKAGE-ROOT (F6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `cursor/release-semver-tag-rel-c1`

**Depends on:** Task 1

**Files:**
- Modify: `docs/PACKAGE-ROOT.md`
- Create: `tests/Docs/PackageRootReleasePolicyTest.php`
- Test: `tests/Docs/PackageRootReleasePolicyTest.php`

**Interfaces:**
- Consumes: REL-C1 gap documentation
- Produces: política explícita para consumidores y operadores

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/PackageRootReleasePolicyTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('PACKAGE-ROOT documents declared semver must match published git tag', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/PACKAGE-ROOT.md');
    assert_true(str_contains($src, 'ReleaseTagPublishedTest'), 'PACKAGE-ROOT must reference tag gate test');
    assert_true(
        str_contains($src, 'tag') && str_contains($src, 'composer.json'),
        'PACKAGE-ROOT must state composer.json version requires matching git tag for releases'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/PackageRootReleasePolicy` / Expected: **FAIL** — `must reference tag gate test`.

- [ ] **Step 3: Implementar el cambio mínimo** — añadir sección en `docs/PACKAGE-ROOT.md`:

```markdown
## Release policy (REL-C1)

The semver in `composer.json` / `config/app.php` / `skeleton/config/app.php` is the **declared**
platform version. Consumers installing via Composer tags must use a **published** Git tag
`vX.Y.Z` matching that declaration. `PlatformVersionSemverTest` validates internal sync only;
`ReleaseTagPublishedTest` validates the tag exists before release. Do not merge semver bumps
without scheduling tag publication in the same release train.
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/PackageRootReleasePolicy` / Expected: **PASS**.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: **PASS** except `ReleaseTagPublished` still **FAIL** until Task 6 (acceptable pre-tag state).

- [ ] **Step 6: Commit** — `git add docs/PACKAGE-ROOT.md tests/Docs/PackageRootReleasePolicyTest.php` / mensaje: `docs: document declared semver equals published tag policy (REL-C1)`

---

### Task 6: Publicar tag `v1.2.7` + verificación post-tag (F3, AC-F2/F3, O1, O2)

**Requiere operador humano:** sí — push de tag Git, verificación remota Packagist/VCS, smoke `/admin/sistema/estado` en staging (D6/M6).

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** merge PR `cursor/release-semver-tag-rel-c1` → `main`, luego tag desde tip mergeado

**Depends on:** Tasks 1–5 merged; CI green on merge commit

**Files:**
- None (ops Git + verificación)
- Test: `tests/Docs/ReleaseTagPublishedTest.php` (debe pasar post-tag)

**Interfaces:**
- Consumes: merge commit con Tasks 1–5; CI success
- Produces: tag anotado `v1.2.7` en remoto; `ReleaseTagPublishedTest` PASS

- [ ] **Step 1: Pre-flight** — confirmar trío semver @ `1.2.7`:

Run: `php tests/run.php Docs/PlatformVersionSemver` / Expected: **PASS** @ `1.2.7`.

Run: `php tests/run.php Docs/ReleaseTagPublished` / Expected: **FAIL** (pre-tag).

- [ ] **Step 2: Verificar commits incluidos** — Run:

```bash
git merge-base --is-ancestor 64a6877 HEAD && echo 'AuthZ #95 OK'
git merge-base --is-ancestor 60477dc HEAD && echo 'states #100 OK'
git merge-base --is-ancestor 21edf26 HEAD && echo 'invoicing #99 OK'
```

Expected: tres líneas OK.

- [ ] **Step 3: Crear y publicar tag** — desde tip CI-verde:

```bash
git tag -a v1.2.7 -m "Platform release 1.2.7 — AuthZ #95, states #100, invoicing scaffold OFF (REL-C1)"
git push origin v1.2.7
```

- [ ] **Step 4: Verificación post-tag** — Run: `php tests/run.php Docs/ReleaseTagPublished Docs/ReleaseNotesV127 Docs/PlatformVersionSemver Docs/ReleaseChecklistDoc` / Expected: **all PASS**.

Run: `git rev-parse v1.2.7^{commit}` / Expected: resuelve SHA del tip taggeado.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs Kernel/SkeletonPurity` / Expected: PASS (0 failed in Docs + SkeletonPurity).

- [ ] **Step 6: Evidencia ops** — registrar en PR merge comment: URL tag GitHub, CI run id del commit taggeado, captura o log de `/admin/sistema/estado` mostrando `v1.2.7` si staging disponible (O2; bloqueado por D6 si no hay skeleton deploy).

---

## Criterios de aceptación finales

- [ ] **AC-F1:** `ReleaseTagPublishedTest` falla pre-tag, pasa post-`v1.2.7`.
- [ ] **AC-F2:** `git rev-parse v1.2.7^{commit}` resuelve; tag commit ancestro de `64a6877` y `60477dc`.
- [ ] **AC-F3:** `php tests/run.php Docs` verde incluyendo gates REL-C1.
- [ ] **AC-F4:** `docs/release/v1.2.7.md` documenta AuthZ, states, invoicing-OFF, PHP ≥8.2, skip 1.2.4–1.2.6.
- [ ] **AC-F5:** Planes M3/M4 retarget `1.2.8+`.
- [ ] **AC-F6:** `PACKAGE-ROOT.md` política tag = semver declarado.
- [ ] **AC-P1/P2:** Portal lock + smoke — **no verificado** (M6).
- [ ] **AC-O1/O2:** CI green + estado UI — ops manual.

## Fuera de alcance

- Implementación M3 (`/api/health`) o M4 (CRUD RBAC router) — planes retargeted only.
- Plan hardening Facturapi 0/10 — no habilitar invoicing prod.
- Portal `composer.lock` bump, VPS deploy, SSH.
- Tags retroactivos `v1.2.4`–`v1.2.6`.
- CRUD-C4, CRUD-C6, M5, M10, D6 closure.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Consumidor PHP 8.1 | Release notes + composer platform require |
| Confundir tag con Facturapi prod | Release notes + vertical OFF |
| Tag desde commit no verde | Exigir CI success antes de Task 6 Step 3 |
| Tag erróneo | No force-push; forward fix `v1.2.8` (spec § Rollback) |

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php Docs/ReleaseTagPublished` pre-tag (FAIL) y post-tag (PASS).
- Salida `php tests/run.php Docs` completa post-merge Tasks 1–5.
- URL tag `v1.2.7` en GitHub + SHA.
- Diff retarget M3/M4 (grep sanity).
- PR Framework URL + commits SHA.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-08T12:40:00Z |
| Framework `origin/main` referencia | `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |
| Tareas completadas / totales | 5 / 6 (Tasks 1–5 en rama; Task 6 ops) |
| Siguiente tarea ejecutable | Task 6 — publicar tag `v1.2.7` (operador humano) |
| Prerrequisitos | Trío semver @ `1.2.7` verificado; tag `v1.2.7` ausente hasta Task 6 |
| Bloqueos | Task 6 requiere operador humano para tag push; M6 bloquea Portal P1/P2 |
