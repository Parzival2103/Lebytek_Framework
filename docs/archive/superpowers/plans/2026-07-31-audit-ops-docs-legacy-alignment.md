# Ops Docs FPS Legacy Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alinear los runbooks operativos (`docs/composer-setup.md`, `docs/integration/*`) con el mapa post-FPS en `docs/ENVIRONMENTS.md`, eliminando referencias a la rama congelada `feature/backoffice-api-integration` y scripts `vps-deploy-*.sh` eliminados, con test gate que impida regresión silenciosa (M8 / D5–D12 / D22).

**Architecture:** Enfoque A del spec — PR docs-only quirúrgico en cuatro rutas operativas bajo gate T1 más párrafo D5 en `seguridad_secretos_deploy.md`. Sin cambios en `src/`, `skeleton/`, `config/`, `database/` ni harness `.env.example`. Referencias históricas en `docs/superpowers/`, `docs/CUTOVER-PORTAL.md` y tag `archive/backoffice-api-integration` permanecen intactas (registro de migración FPS). App desplegable lebytek.com/waapi → `Parzival2103/Lebytek_Portal` @ `main` + `composer.lock`; Framework → paquete Composer semver.

**Tech Stack:** PHP 8.1+ harness (`tests/lib/microtest.php`, `php tests/run.php`), Markdown runbooks, git, `gh` CLI 2.x; tag Framework `v1.2.1` @ `fba3e03` (sin nuevo release semver por este PR).

**Source spec:** `docs/superpowers/specs/2026-07-31-audit-ops-docs-legacy-alignment-design.md`  ·  **Modo:** normal (Nivel B — spec en rama `automation/spec-2026-07-31`; audit fuente `automation/audit-2026-07-31` @ `af0c5df`)

**Source audit PR:** #55 — https://github.com/Parzival2103/Lebytek_Framework/pull/55

**Target repository/branches:** `Parzival2103/Lebytek_Framework` — rama `feat/ops-docs-fps-alignment` creable desde `origin/main` @ `e19fa25c7c96560462f60c31b56b99c8d7eaf619` (`git ls-remote origin refs/heads/main` resuelve; rama implementación aún no existe en remoto)

## Global Constraints

- Rama `feat/ops-docs-fps-alignment` se crea desde `origin/main`; no hereda `automation/audit-*`, `automation/spec-*` ni ancestry legacy.
- PR implementación apunta a `main`; título sugerido `docs(ops): align integration runbooks with FPS environments`.
- Archivos tocados en commits de implementación: **solo** D1–D5 + T1 (lista cerrada abajo). Sin edits en `tests/run.php` — auto-descubre `*Test.php`.
- No mergear `feature/backoffice-api-integration` → `main` (legacy archivado @ tag `archive/backoffice-api-integration`).
- Cloud agent puede carecer de PHP CLI: el ejecutor debe correr `php tests/run.php` en entorno con PHP ≥ 8.1 antes de merge.
- Merge del PR audit #55 es responsabilidad de AUTOMATION-03 — prerrequisito operativo, no commit de esta rama.

## Requisitos cubiertos

| ID spec | Entregable | Tarea |
|---------|------------|-------|
| D1 / D10 | `docs/composer-setup.md` §6 semver | Task 2 |
| D2 / D11 | `docs/integration/VPS_CHECKLIST.md` sección lebytek.com | Task 3 |
| D3 / D12 | `docs/integration/lebytek-implementation-real.md` cabecera + banner | Task 4 |
| D4 / D12 | `docs/integration/role-delegation-lebytek-api.md` L195 | Task 5 |
| D5 / D16 | `docs/core/seguridad_secretos_deploy.md` Portal vs harness | Task 6 |
| T1 / D22 | `tests/Docs/OpsDocsFpsAlignmentTest.php` | Task 1 |
| U1–U6 | UX docs operativos | Tasks 2–6 |
| AC1–AC5, AC-UX1–3, AC-D1–4 | Criterios spec | Tasks 1–7 |

## Fuera de alcance

- M1 semver UI (`config/app.php` `1.0.0` vs tag `v1.2.1`) — plan `2026-07-29-audit-config-version-semver-sync.md`.
- M2 purge root `.env.example` — spec 2026-07-29 Fase 2.
- M3–M5 RBAC router, health API pública, slug `permisos.gestionar` — backlog producto.
- `skeleton.lebytek.com` deploy — plan `2026-07-26-skeleton-package-staging.md`.
- Marketing, membresías, checkout Portal — `Lebytek_Portal`.
- Deploy VPS, SSH, migraciones producción, cambios `.env` en servidores.
- Barrido histórico en `docs/superpowers/`, `docs/CUTOVER-PORTAL.md` (Enfoque C rechazado).
- Portal SHA / Stripe QA — D3, D14–D15 (gh 404 Portal; **Requiere operador humano**).

---

### Task 1: Test gate `OpsDocsFpsAlignmentTest` (T1 — rojo pre-fix)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** None

**Files:**
- Create: `tests/Docs/OpsDocsFpsAlignmentTest.php`
- Test: `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Interfaces:**
- Consumes: cuatro runbooks operativos en estado pre-fix (`dev-feature/backoffice-api-integration`, `feature/backoffice-api-integration`, `vps-deploy-*.sh` presentes)
- Produces: test que falla con mensaje accionable citando `$rel` y `$needle` (U5)

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/OpsDocsFpsAlignmentTest.php`:

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('operational runbooks must not reference frozen legacy branch or removed deploy scripts (M8)', function () use ($root): void {
    $operationalPaths = [
        'docs/composer-setup.md',
        'docs/integration/VPS_CHECKLIST.md',
        'docs/integration/lebytek-implementation-real.md',
        'docs/integration/role-delegation-lebytek-api.md',
    ];
    $forbidden = [
        'dev-feature/backoffice-api-integration' => 'replace with semver constraint ^1.2 in composer.json require',
        'feature/backoffice-api-integration' => 'replace with Lebytek_Portal @ main — see docs/ENVIRONMENTS.md',
        'vps-deploy-lebytek-com.sh' => 'script removed PR #36 — use Portal git pull',
        'vps-deploy-waapi.sh' => 'script removed PR #36 — use Portal git pull',
        'vps-deploy-skeleton.sh' => 'script removed PR #36 — use publish-skeleton.sh + create-project',
    ];

    foreach ($operationalPaths as $rel) {
        $path = $root . '/' . $rel;
        assert_true(is_file($path), "Operational runbook missing: {$rel}");
        $src = (string) file_get_contents($path);
        foreach ($forbidden as $needle => $action) {
            assert_true(
                !str_contains($src, $needle),
                "{$rel} must not reference «{$needle}». Action: {$action}. Canonical map: docs/ENVIRONMENTS.md"
            );
        }
    }
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** — al menos un assert citando `docs/composer-setup.md must not reference «dev-feature/backoffice-api-integration»` (grep confirma L127 pre-fix).

- [x] **Step 3: Implementar el cambio mínimo** — ninguno en este task (test only). Corrección en Tasks 2–5.

- [x] **Step 4: Verificación enfocada** — Run: `grep -c 'dev-feature/backoffice-api-integration' docs/composer-setup.md docs/integration/VPS_CHECKLIST.md docs/integration/lebytek-implementation-real.md docs/integration/role-delegation-lebytek-api.md` / Expected: `1` en composer-setup; `1` o más en integration files (confirmación pre-fix).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php DeployScriptsRemoved` / Expected: **PASS** — 3 tests, 0 failed (sin regresión PR #36).

- [x] **Step 6: Commit** — `git add tests/Docs/OpsDocsFpsAlignmentTest.php` — mensaje: `test(docs): add OpsDocsFpsAlignmentTest for M8 runbook gate (T1, red pre-fix)`.

---

### Task 2: Corregir `docs/composer-setup.md` §6 semver (D1 / U1)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Task 1

**Files:**
- Modify: `docs/composer-setup.md:121-137` (sección §6 completa)
- Test: `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Interfaces:**
- Consumes: spec § D1; bloque `path` repository existente L131–137 (preservar)
- Produces: sección «Versión semver en consumidores» con `^1.2` y enlace `docs/ENVIRONMENTS.md`

- [x] **Step 1: Escribir el test que falla** — ya cubierto por Task 1; Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** incluyendo `docs/composer-setup.md`.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: FAIL en cadena `dev-feature/backoffice-api-integration`.

- [x] **Step 3: Implementar el cambio mínimo** — reemplazar §6 «Pin a branch de feature (desarrollo)» (L121–137) por:

```markdown
## 6. Versión semver en consumidores

Instala el paquete plataforma por **tag semver** publicado — no por branch VCS del monolito legacy (congelada en tag `archive/backoffice-api-integration`):

```json
"require": {
    "lebytek/framework": "^1.2"
}
```

Comando concreto en un consumidor existente:

```bash
composer require lebytek/framework:^1.2
```

Ver mapa de entornos en [`docs/ENVIRONMENTS.md`](ENVIRONMENTS.md).

**Desarrollo local del paquete** (mantenedor Framework, no deploy VPS): path repository no versionado en el consumidor:

```json
"repositories": [
    { "type": "path", "url": "../Lebytek_Framework" }
]
```
```

- [x] **Step 4: Verificación enfocada** — Run: `grep -c 'dev-feature/backoffice-api-integration' docs/composer-setup.md` / Expected: `0`. Run: `grep -c '\\^1.2' docs/composer-setup.md` / Expected: `≥1`.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** solo en archivos integration restantes (composer-setup ya limpio).

- [x] **Step 6: Commit** — `git add docs/composer-setup.md` — mensaje: `docs(composer): replace legacy branch pin with semver ^1.2 (D1, M8)`.

---

### Task 3: Actualizar sección lebytek.com en `VPS_CHECKLIST.md` (D2 / U2–U3)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Task 2

**Files:**
- Modify: `docs/integration/VPS_CHECKLIST.md:13` (nota histórica E2E)
- Modify: `docs/integration/VPS_CHECKLIST.md:85-98` (sección lebytek.com)
- Test: `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Interfaces:**
- Consumes: `docs/ENVIRONMENTS.md` L19–20 (Portal @ main); scripts eliminados PR #36
- Produces: checklist deploy Portal con `git pull origin main` y verificación `curl -sf https://lebytek.com/up`

- [x] **Step 1: Escribir el test que falla** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** en `docs/integration/VPS_CHECKLIST.md` citando `vps-deploy-lebytek-com.sh` o `feature/backoffice-api-integration`.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: FAIL con needle `vps-deploy-lebytek-com.sh` (L13 pre-fix).

- [x] **Step 3: Implementar el cambio mínimo**

Reemplazar L13:

```markdown
- [x] Deploy lebytek ≥ `c2d51cd` — **histórico 2026-07-01** — `vps-deploy-lebytek-com.sh` eliminado PR #36; deploy actual vía `Lebytek_Portal` git pull @ `main` (no ejecutar script)
```

Reemplazar sección `## lebytek.com (VPS target)` (L85–98) por:

```markdown
## lebytek.com (VPS target)

> **Package source ≠ app desplegable.** Este hostname corre **`Parzival2103/Lebytek_Portal`** @ `main` + `composer.lock` — no clonar `Lebytek_Framework` como sitio. Ver [`docs/ENVIRONMENTS.md`](../ENVIRONMENTS.md).

Ruta: `/home/lebytek/htdocs/lebytek.com`  
Usuario CloudPanel: `lebytek`  
Repo: `https://github.com/Parzival2103/Lebytek_Portal.git`  
Branch: `main`

### Código

- [x] `git pull origin main` en checkout Portal existente (no clone Framework)
- [x] `composer install --no-dev` (instala `lebytek/framework` desde lock)
- [x] Document root → `public/`
- [x] `.env`: DB, MAIL_*, LEBYTEK_API_URL, LEBYTEK_API_TOKEN
- [x] `LEBYTEK_API_TOKEN` + `MAIL_*` smtp configurados (2026-07-01)
- [x] Post-deploy: `curl -sf https://lebytek.com/up` → exit 0 (o ruta health documentada en Portal)
```

Preservar intacta la sección `## api.lebytek.com` (WhatsApiLebytek).

- [x] **Step 4: Verificación enfocada** — Run: `grep -E 'feature/backoffice-api-integration|vps-deploy-lebytek' docs/integration/VPS_CHECKLIST.md` / Expected: exit 1 (sin coincidencias) **o** solo apariciones dentro de texto «histórico — no ejecutar» en L13.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** solo en `lebytek-implementation-real.md` y/o `role-delegation-lebytek-api.md`.

- [x] **Step 6: Commit** — `git add docs/integration/VPS_CHECKLIST.md` — mensaje: `docs(integration): align VPS_CHECKLIST lebytek.com with Portal main (D2, M8)`.

---

### Task 4: Corregir cabecera `lebytek-implementation-real.md` (D3 / U3–U4)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Task 3

**Files:**
- Modify: `docs/integration/lebytek-implementation-real.md:1-6` (banner + cabecera)
- Modify: `docs/integration/lebytek-implementation-real.md:434` (prompt Cursor — repo target)
- Test: `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Interfaces:**
- Consumes: namespaces `App\Application\Marketing\` (viven en Portal, no Framework)
- Produces: guía operativa con target Portal; banner FPS

- [x] **Step 1: Escribir el test que falla** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** — `lebytek-implementation-real.md must not reference «feature/backoffice-api-integration»`.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: FAIL L3 pre-fix.

- [x] **Step 3: Implementar el cambio mínimo**

Insertar banner tras el título (L1):

```markdown
# lebytek.com — implementación real (espejo del contrato api)

> **Package source ≠ app desplegable.** `lebytek/framework` se consume vía Composer lock en Portal — no clonar `Lebytek_Framework` como sitio web. Mapa canónico: [`docs/ENVIRONMENTS.md`](../ENVIRONMENTS.md).

Guía operativa para **`Parzival2103/Lebytek_Portal`** (`lebytek.com` VPS). Repo: `main`.
```

Eliminar la línea antigua L3 (`Lebytek_Framework` + `feature/backoffice-api-integration`).

Reemplazar L434:

```markdown
## 13. Prompt listo para Cursor (repo Lebytek_Portal)
```

Añadir nota breve antes de §13 si el texto asume árbol Framework para Marketing:

```markdown
> Los namespaces `App\Application\Marketing\` y módulos `dom_mkt_*` viven en **Portal**, no en el package source Framework.
```

- [x] **Step 4: Verificación enfocada** — Run: `grep -n 'feature/backoffice-api-integration\|Lebytek_Framework.*lebytek.com' docs/integration/lebytek-implementation-real.md` / Expected: exit 1.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** solo en `role-delegation-lebytek-api.md` (si L195 aún legacy).

- [x] **Step 6: Commit** — `git add docs/integration/lebytek-implementation-real.md` — mensaje: `docs(integration): retarget lebytek-implementation-real to Portal (D3, M8)`.

---

### Task 5: Corregir target back-office en `role-delegation-lebytek-api.md` (D4)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Task 4

**Files:**
- Modify: `docs/integration/role-delegation-lebytek-api.md:195`
- Test: `tests/Docs/OpsDocsFpsAlignmentTest.php`

**Interfaces:**
- Consumes: contrato api WhatsApi (sin cambio); solo target consumidor
- Produces: L195 → `Parzival2103/Lebytek_Portal`, branch `main`

- [x] **Step 1: Escribir el test que falla** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **FAIL** — `role-delegation-lebytek-api.md must not reference «feature/backoffice-api-integration»`.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: FAIL L195 pre-fix.

- [x] **Step 3: Implementar el cambio mínimo** — reemplazar L195:

```markdown
Repo back-office: `Parzival2103/Lebytek_Portal`, branch `main`.
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **PASS** — 1 test, 0 failed (gate T1 verde).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: suite Docs PASS incl. `OpsDocsFpsAlignmentTest`, `DeployScriptsRemovedTest`, `FpsPublicationReadinessTest`, `AutomationPromptInvariantTest`, `AuditArtifactFreshnessTest` (6+ archivos).

- [x] **Step 6: Commit** — `git add docs/integration/role-delegation-lebytek-api.md` — mensaje: `docs(integration): point role-delegation back-office to Portal main (D4, M8)`.

---

### Task 6: Distinguir secretos Portal vs harness en `seguridad_secretos_deploy.md` (D5 / U6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Task 5

**Files:**
- Modify: `docs/core/seguridad_secretos_deploy.md:6-7` (párrafo tras regla `.env`)
- Test: ninguno nuevo (D5 fuera de gate T1 — verificación grep manual)

**Interfaces:**
- Consumes: `docs/ENVIRONMENTS.md` § producción Portal
- Produces: párrafo que distingue `.env` VPS lebytek.com (Portal) vs harness Framework package source

- [x] **Step 1: Escribir el test que falla** — Run: `grep -c 'Lebytek_Portal\|package source' docs/core/seguridad_secretos_deploy.md` / Expected: `0` pre-fix.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: `0` (ausencia de distinción Portal).

- [x] **Step 3: Implementar el cambio mínimo** — insertar después de L6 (`El VPS hace auto-pull de main…`):

```markdown

### Portal vs package source (post-FPS)

- **lebytek.com / waapi.lebytek.com:** secretos de producción en el `.env` del VPS del checkout **`Lebytek_Portal`** (`DB_*`, `MKT_*`, `LEBYTEK_API_*`, `STRIPE_*`). Rotación y deploy siguen el runbook Portal — ver [`docs/ENVIRONMENTS.md`](../ENVIRONMENTS.md).
- **Este repositorio (`Lebytek_Framework`):** es el **package source** publicado como `lebytek/framework`; su root `.env.example` es harness de mantenedor, **no** el `.env` de producción Portal. No desplegar este repo como sitio web.
```

Ajustar L6 para no implicar que «el VPS» genérico es Framework:

```markdown
El VPS de producción Portal hace auto-pull de `main` en **`Lebytek_Portal`**; cualquier secreto commiteado se considera comprometido.
```

- [x] **Step 4: Verificación enfocada** — Run: `grep -c 'Lebytek_Portal' docs/core/seguridad_secretos_deploy.md` / Expected: `≥2`.

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: **PASS** — 0 failed global en suite Docs.

- [x] **Step 6: Commit** — `git add docs/core/seguridad_secretos_deploy.md` — mensaje: `docs(core): distinguish Portal deploy secrets from Framework harness (D5, M8)`.

---

### Task 7: PR implementación y verificación final

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/ops-docs-fps-alignment`

**Depends on:** Tasks 1–6

**Requiere operador humano:** parcial — O1–O3 verificación VPS/Portal post-merge (fuera corrida desatendida)

**Files:**
- Ninguno adicional

**Interfaces:**
- Consumes: rama con D1–D5 + T1; PR audit #55 (merge AUTOMATION-03)
- Produces: PR `docs(ops): align integration runbooks with FPS environments` → `main`

- [x] **Step 1: Escribir el test que falla** — N/A; gate T1 ya verde tras Task 5.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php OpsDocsFpsAlignment` / Expected: **PASS**.

- [x] **Step 3: Implementar el cambio mínimo** — abrir PR:

```bash
git checkout feat/ops-docs-fps-alignment
git push -u origin feat/ops-docs-fps-alignment
gh pr create --base main --title "docs(ops): align integration runbooks with FPS environments" \
  --body "Implements spec 2026-07-31-audit-ops-docs-legacy-alignment-design.md (M8, D1–D5, T1).

- composer-setup §6 semver ^1.2
- VPS_CHECKLIST / lebytek-implementation-real / role-delegation → Portal @ main
- seguridad_secretos_deploy Portal vs harness
- OpsDocsFpsAlignmentTest gate

Source audit PR #55. No semver release required."
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php` / Expected: **0 failed** global (harness completo).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php FpsPublicationReadiness` / Expected: **PASS** — CUTOVER sigue exigiendo referencia histórica legacy (Enfoque A no barre históricos).

- [x] **Step 6: Commit** — N/A (PR metadata). Verificar diff: solo `docs/composer-setup.md`, `docs/integration/*` (3 archivos), `docs/core/seguridad_secretos_deploy.md`, `tests/Docs/OpsDocsFpsAlignmentTest.php`.

---

## Criterios finales de aceptación

- [x] AC1: `docs/composer-setup.md` §6 sin `dev-feature/backoffice-api-integration`; semver `^1.2` documentado.
- [x] AC2: `VPS_CHECKLIST.md` lebytek.com → Portal @ `main`; sin `vps-deploy-*.sh` como instrucción vigente.
- [x] AC3: `lebytek-implementation-real.md` identifica Portal como app desplegable.
- [x] AC4: `role-delegation-lebytek-api.md` L195 → Portal @ `main`.
- [x] AC5: `seguridad_secretos_deploy.md` distingue Portal vs harness.
- [x] AC6: `OpsDocsFpsAlignmentTest` existía rojo pre-fix; verde post-fix.
- [x] AC7: `php tests/run.php Docs` verde; `DeployScriptsRemovedTest` + `FpsPublicationReadinessTest` sin regresión.
- [x] AC8: Diff implementación solo rutas D1–D5 + T1.
- [x] AC9: O1–O3 Portal VPS — **Requiere operador humano** (no verificado esta corrida).

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Operador sigue runbook impreso obsoleto | Banner «actualizado 2026-07-31» en VPS_CHECKLIST; test gate T1 |
| Test demasiado amplio (falsos positivos históricos) | Lista cerrada de 4 paths operativos |
| Confundir edición Portal con Framework | Ownership map + ENVIRONMENTS.md |
| Portal prod desalineado (lock antiguo) | O3 manual post-merge — fuera de alcance |
| Regresión reintroducir pin legacy | T1 en suite Docs |

**Rollback:** `git revert` del merge commit docs-only — restaura textos legacy (no recomendado salvo error factual).

## Evidencia que debe recopilar el ejecutor

- Salida `php tests/run.php OpsDocsFpsAlignment` pre-fix (FAIL) y post-fix (PASS).
- Salida `grep` pre-fix confirmando cadenas prohibidas en los cuatro runbooks.
- SHA commit y número PR hacia `main`.
- Confirmación diff no toca `src/`, `config/app.php`, `.env.example`.

## Estado de ejecución (plan del día)

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-08-01T00:50:00Z |
| SHA `origin/main` verificado | `562c6ab3434ef435be966882affbf0e98dd037b9` |
| Tareas completadas / totales | **7 / 7** |
| Estado | **Completo** — archivado 2026-08-01 |
| Siguiente tarea ejecutable | N/A — plan cerrado |
| Evidencia | PR #57 merged; `tests/Docs/OpsDocsFpsAlignmentTest.php`; runbooks D1–D5 en main |
| Bloqueos pendientes ops | AC9 O1–O3 Portal VPS — **Requiere operador humano** (fuera de corrida) |
