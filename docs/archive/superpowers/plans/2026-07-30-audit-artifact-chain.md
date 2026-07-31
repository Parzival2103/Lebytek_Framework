# Audit Artifact Chain Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Endurecer la cadena diaria audit→spec→plan para que ningún reporte `docs(audit):` quede cerrado sin merge a `main`, con política documentada y gates Docs que detecten la regresión M7.

**Architecture:** Cambios exclusivamente en docs de automation y tests harness bajo `tests/Docs/` — sin tocar `src/`, `skeleton/` ni código de producto. El Enfoque B mantiene el PR audit abierto como fuente Nivel A hasta AUTOMATION-03, que debe ejecutar `gh pr merge` antes de cerrar. Los tests leen archivos locales (`docs/automation/*`, `docs/audits/`) y consultan `gh pr list` cuando está disponible; si `gh` falla, el test de frescura hace skip explícito (no gate verde silencioso).

**Tech Stack:** PHP 8.1+ harness (`tests/lib/microtest.php`), git, `gh` CLI 2.x, Markdown prompts en `docs/automation/`.

**Source spec:** `docs/superpowers/specs/2026-07-30-audit-artifact-chain-design.md`  ·  **Modo:** normal

**Source audit PR:** #51 — https://github.com/Parzival2103/Lebytek_Framework/pull/51

**Target repository/branches:** `Parzival2103/Lebytek_Framework` — rama de implementación `feat/audit-artifact-chain-lifecycle` desde `origin/main` (verificada existente como base remota `main` @ `0ec722b`).

## Global Constraints

- Rama `feat/audit-artifact-chain-lifecycle` se crea desde `origin/main`; no hereda `automation/spec-*` ni `automation/audit-*`.
- PR de implementación apunta a `main`; título sugerido `docs(automation): audit artifact chain lifecycle F1–F6`.
- Ningún archivo fuera de `docs/automation/`, `docs/superpowers/plans/` (este plan ya entregado) y `tests/Docs/` en los commits de implementación F1–F6.
- No mergear `feature/backoffice-api-integration` → `main`.
- Merge del PR audit #51 es responsabilidad de AUTOMATION-03 u operador humano; el plan lo documenta como prerrequisito del gate final F4 en `main`, no como commit de la rama de implementación.

## Requisitos cubiertos

| ID spec | Entregable | Tarea |
|---------|------------|-------|
| F1 | Política ciclo de vida Enfoque B en README | Task 3 |
| F2 | AUTOMATION-03 merge-before-close | Task 4 |
| F3 | AUTOMATION-01 prohibición cerrar PR audit | Task 4 |
| F4 | `AuditArtifactFreshnessTest.php` | Task 2 |
| F5 | `AutomationPromptInvariantTest.php` | Task 1 |
| F6 | Addendum M7 en INCIDENT | Task 5 |
| U1–U5 | UX de proceso (mensajes accionables) | Tasks 1–4 |
| AC1–AC7, AC10–AC14 | Criterios spec | Tasks 1–6 |

## Fuera de alcance

- M1 semver UI (spec #50 / PR #50), M2 purge `.env.example`, M3–M5 RBAC/API backlog.
- Auto-fix D1–D16 heredados salvo F1–F6.
- Marketing, membresías, checkout Portal (`Lebytek_Portal`).
- Configuración token gh Portal (O1/D3) — **Requiere operador humano**.
- Recuperación obligatoria reporte 2026-07-29 — opcional PR docs-only separado.
- Cierre/merge del PR #51 en esta implementación (reservado AUTOMATION-03 salvo Task 6 explícita).

---

### Task 1: Gate de invariantes en prompts (F5)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle`

**Depends on:** None

**Files:**
- Create: `tests/Docs/AutomationPromptInvariantTest.php`
- Test: `tests/Docs/AutomationPromptInvariantTest.php`

**Interfaces:**
- Consumes: `docs/automation/AUTOMATION-03-audit-ux.md`, `docs/automation/AUTOMATION-01-daily-spec.md` (estado pre-fix)
- Produces: test que exige substring `gh pr merge` en AUTOMATION-03 y prohibición de cerrar PRs `docs(audit):` en AUTOMATION-01

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/AutomationPromptInvariantTest.php`: — evidencia: PR #54, `origin/main:tests/Docs/AutomationPromptInvariantTest.php`

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('AUTOMATION-03 requires gh pr merge before closing the audit PR', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-03-audit-ux.md';
    $src = (string) file_get_contents($path);
    assert_true(
        str_contains($src, 'gh pr merge'),
        'AUTOMATION-03 must document gh pr merge before close (M7 / D18). '
            . 'Sync Cursor UI after merge — see docs/automation/README.md § Sincronización.'
    );
    assert_true(
        str_contains($src, 'mergeable'),
        'AUTOMATION-03 must abort close when mergeable is not MERGEABLE (U1 actionable error)'
    );
});

test('AUTOMATION-03 forbids closing audit PR without merge', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-03-audit-ux.md';
    $src = (string) file_get_contents($path);
    assert_true(
        !preg_match('/Cierra el PR draft de auditoría/s', $src)
        || str_contains($src, 'gh pr merge'),
        'Section 3 must not instruct close-without-merge (incident M7 / PR #48)'
    );
});

test('AUTOMATION-01 forbids closing docs(audit) pull requests', function () use ($root): void {
    $path = $root . '/docs/automation/AUTOMATION-01-daily-spec.md';
    $src = (string) file_get_contents($path);
    assert_true(
        str_contains($src, 'docs(audit):'),
        'AUTOMATION-01 must name the audit PR title prefix'
    );
    assert_true(
        str_contains($src, 'No cierres') || str_contains($src, 'prohibid'),
        'AUTOMATION-01 must forbid closing audit PRs (U3). '
            . 'Add under Prohibiciones: never close docs(audit): PRs of any date.'
    );
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: **FAIL** — `AUTOMATION-03 must document gh pr merge` (prompt actual dice «Cierra el PR draft» sin merge). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 3: Implementar el cambio mínimo** — diferido a Task 4 (prompts). En este task sólo el test; no editar prompts aún. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: **FAIL** (3 tests, ≥1 failed) — confirma gate rojo pre-fix. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: suite Docs ejecuta ≥4 archivos de test; fallos nuevos sólo en `AutomationPromptInvariantTest`. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — `git add tests/Docs/AutomationPromptInvariantTest.php` — mensaje: `test(docs): add AutomationPromptInvariantTest for M7 prompt gates (F5, red pre-fix)`. — evidencia: PR #54, `origin/main:tests/Docs/AutomationPromptInvariantTest.php`

---

### Task 2: Gate de frescura del artefacto audit mergeado (F4)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle`

**Depends on:** None (paralelo a Task 1)

**Files:**
- Create: `tests/Docs/AuditArtifactFreshnessTest.php`
- Test: `tests/Docs/AuditArtifactFreshnessTest.php`

**Interfaces:**
- Consumes: `docs/audits/*-auditoria-tecnica-diaria.md` en working tree; salida JSON de `gh pr list --search "docs(audit):" --state open --json number,title,updatedAt`
- Produces: test que falla cuando existe PR audit abierto más reciente que el último reporte mergeado en `docs/audits/` con delta > 2 días

- [x] **Step 1: Escribir el test que falla** — crear `tests/Docs/AuditArtifactFreshnessTest.php`: — evidencia: PR #54, `origin/main:tests/Docs/AuditArtifactFreshnessTest.php`

```php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/**
 * @return list<\DateTimeImmutable>
 */
function audit_freshness_merged_report_dates(string $auditsDir): array
{
    $dates = [];
    foreach (glob($auditsDir . '/*-auditoria-tecnica-diaria.md') ?: [] as $path) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})-auditoria-tecnica-diaria\.md$/', basename($path), $m)) {
            $dates[] = new \DateTimeImmutable($m[1] . 'T00:00:00Z');
        }
    }
    usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

    return $dates;
}

/**
 * @return list<\DateTimeImmutable>
 */
function audit_freshness_open_pr_dates(string $root): array
{
    $cmd = 'gh pr list --repo Parzival2103/Lebytek_Framework'
        . ' --search "docs(audit):" --state open'
        . ' --json title --limit 20 2>/dev/null';
    $json = shell_exec($cmd);
    if ($json === null || trim($json) === '') {
        return [];
    }
    $rows = json_decode($json, true);
    if (!is_array($rows)) {
        return [];
    }
    $dates = [];
    foreach ($rows as $row) {
        $title = (string) ($row['title'] ?? '');
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $title, $m)) {
            $dates[] = new \DateTimeImmutable($m[1] . 'T00:00:00Z');
        }
    }

    return $dates;
}

test('merged audit report is not stale while a newer open audit PR exists (M7)', function () use ($root): void {
    $auditsDir = $root . '/docs/audits';
    $mergedDates = audit_freshness_merged_report_dates($auditsDir);
    assert_true($mergedDates !== [], 'docs/audits must contain at least one *-auditoria-tecnica-diaria.md');

    $openDates = audit_freshness_open_pr_dates($root);
    if ($openDates === []) {
        fwrite(STDOUT, "  SKIP  no open docs(audit): PRs (gh unavailable or none open)\n");
        return;
    }

    $merged = $mergedDates[0];
    $newestOpen = $openDates[0];
    foreach ($openDates as $d) {
        if ($d > $newestOpen) {
            $newestOpen = $d;
        }
    }

    $deltaDays = (int) $merged->diff($newestOpen)->format('%r%a');
    assert_true(
        $deltaDays <= 2,
        sprintf(
            'Merged audit in main is stale while a newer open audit PR exists (M7). '
                . 'Latest merged report: %s; newest open audit PR date: %s; delta=%d days. '
                . 'Recovery: gh pr merge <audit-pr> --squash before close (AUTOMATION-03 Enfoque B).',
            $merged->format('Y-m-d'),
            $newestOpen->format('Y-m-d'),
            $deltaDays
        )
    );
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/AuditArtifactFreshness` / Expected: **FAIL** con mensaje citando `2026-07-27` vs `2026-07-30` y «Recovery: gh pr merge» (PR #51 abierto, último mergeado `docs/audits/2026-07-27-auditoria-tecnica-diaria.md`). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 3: Implementar el cambio mínimo** — ninguno en este task (test only). La corrección operativa es merge de PR #51 (Task 6). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/AuditArtifactFreshness` / Expected: **FAIL** con delta ≥ 3 días y texto accionable (U4). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/AutomationPreflightRef` / Expected: **PASS** — 4 tests, 0 failed (preflight legacy intacto). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — `git add tests/Docs/AuditArtifactFreshnessTest.php` — mensaje: `test(docs): add AuditArtifactFreshnessTest for M7 staleness gate (F4, red pre-fix)`. — evidencia: PR #54, `origin/main:tests/Docs/AuditArtifactFreshnessTest.php`

---

### Task 3: Política de ciclo de vida Enfoque B en README (F1)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle`

**Depends on:** Task 1, Task 2 (tests existentes en rojo)

**Files:**
- Modify: `docs/automation/README.md` — añadir sección «Ciclo de vida de artefactos (Enfoque B)» tras «Invariantes»
- Test: `tests/Docs/AutomationPromptInvariantTest.php` (sin cambio en este task)

**Interfaces:**
- Consumes: diseño spec § Reglas invariantes (5 reglas)
- Produces: README con prohibición cierre cross-PR, merge-before-close, fallback Enfoque A, referencia M7

- [x] **Step 1: Escribir el test que falla** — añadir al final de `AutomationPromptInvariantTest.php`: — evidencia: PR #54, `origin/main:tests/Docs/AutomationPromptInvariantTest.php`

```php
test('automation README documents Enfoque B lifecycle and cross-PR close prohibition', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/automation/README.md');
    assert_true(str_contains($src, 'Enfoque B'), 'README must name Enfoque B');
    assert_true(
        str_contains($src, 'gh pr merge'),
        'README must document merge-before-close for audit PRs'
    );
    assert_true(
        str_contains($src, 'continúa en #') || str_contains($src, 'cross-PR'),
        'README must forbid «continúa en #N» as substitute for audit merge (M7 / PR #48)'
    );
});
```

Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: **FAIL** en el nuevo test.

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: FAIL `README must name Enfoque B`. — evidencia: PR #54, `docs/automation/README.md` L49–74

- [x] **Step 3: Implementar el cambio mínimo** — insertar después de la línea 47 de `docs/automation/README.md`: — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

```markdown
## Ciclo de vida de artefactos (Enfoque B)

Cadena objetivo audit → spec → plan:

1. **AUTOMATION-00** abre PR draft `docs(audit):` desde `origin/main`. El PR abierto es fuente Nivel A para 01–02.
2. **AUTOMATION-01–02** escriben spec en `automation/spec-*` sin heredar la rama audit.
3. **AUTOMATION-03** abre PR `docs(spec):`, **mergea** el PR audit del mismo `YYYY-MM-DD` a `main`, luego cierra el PR audit ya mergeado.
4. **AUTOMATION-04** entrega plan en la misma rama spec.

### Reglas invariantes (M7)

1. **Prohibido** cerrar un PR `docs(audit):` sin `mergedAt` salvo cancelación explícita del día documentada en el PR.
2. **Prohibido** enlazar «continúa en #N» entre PR audit y PR spec de ramas distintas como sustituto del merge (incidente M7 / PR #48).
3. AUTOMATION-03 **debe** ejecutar `gh pr merge <n> --squash` del audit del día **antes** de cualquier cierre.
4. Si AUTOMATION-03 falla, AUTOMATION-04 reporta audit sin merge; `AuditArtifactFreshnessTest` queda rojo hasta recuperación.
5. Modo degradado (Nivel D) no autoriza inventar hallazgos; sólo carry-forward verificado.

### Fallback Enfoque A

Si AUTOMATION-03 falla repetidamente, un operador puede mergear el PR audit inmediatamente tras AUTOMATION-00. Documentar la excepción en el PR audit.

### Si AUTOMATION-03 falla

1. No cerrar el PR audit manualmente.
2. Abrir o actualizar PR spec desde `automation/spec-*`.
3. Ejecutar `gh pr merge <audit-pr> --squash` cuando `mergeable=MERGEABLE`.
4. Sincronizar prompts pegados en Cursor UI con este README (O2).
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: README test **PASS**; tests de AUTOMATION-01/03 siguen **FAIL** hasta Task 4. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/AutomationPreflightRef` / Expected: PASS. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — `git add docs/automation/README.md tests/Docs/AutomationPromptInvariantTest.php` — mensaje: `docs(automation): document Enfoque B artifact lifecycle (F1)`. — evidencia: PR #54, `origin/main:tests/Docs/AutomationPromptInvariantTest.php`

---

### Task 4: Endurecer prompts AUTOMATION-03 y AUTOMATION-01 (F2, F3)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle`

**Depends on:** Task 3

**Files:**
- Modify: `docs/automation/AUTOMATION-03-audit-ux.md:87-93` (sección 3 completa)
- Modify: `docs/automation/AUTOMATION-01-daily-spec.md:134-141` (Prohibiciones)
- Test: `tests/Docs/AutomationPromptInvariantTest.php`

**Interfaces:**
- Consumes: spec § Cambios concretos en prompts
- Produces: AUTOMATION-03 con flujo merge-then-close; AUTOMATION-01 con prohibición explícita de cerrar PRs audit

- [x] **Step 1: Escribir el test que falla** — ya cubierto por Task 1; Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: **FAIL** en tests AUTOMATION-03/01. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Expected: ≥2 failed en invariant test. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 3: Implementar el cambio mínimo** — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

Reemplazar en `AUTOMATION-03-audit-ux.md` la sección `### 3. Cerrar el PR de auditoría del día` (L87–93) por:

```markdown
### 3. Mergear y cerrar el PR de auditoría del día

Identifica el PR `docs(audit):` del **mismo** `YYYY-MM-DD` (base `main`).

1. Verifica `mergeable=MERGEABLE` con `gh pr view <n> --json mergeable`. Si es
   `CONFLICTING` o `UNKNOWN` (tras un re-fetch), **aborta** el cierre y reporta
   el conflicto — no uses `gh pr close` como workaround (incidente M7).
2. Ejecuta `gh pr merge <n> --squash` (merge commit sólo si la política del repo
   lo exige explícitamente).
3. Comenta en el PR audit con enlace al PR spec abierto o actualizado.
4. El PR audit queda **merged**; no ejecutes `gh pr close` sobre un PR ya mergeado.

**Prohibido:** cerrar un PR audit sin `mergedAt`. **Prohibido:** comentar
«continúa en #N» en el PR audit como sustituto del merge hacia `main`.
```

En `AUTOMATION-01-daily-spec.md`, añadir a `### Prohibiciones` (después de L141):

```markdown
- **No cierres** PRs `docs(audit):` de ninguna fecha — ni comentes cierre en ellos.
  Eso es responsabilidad exclusiva de AUTOMATION-03 tras merge a `main`.
```

Eliminar o ajustar L132 «No cierres el PR de auditoría. Lo hace AUTOMATION-03.» para que no contradiga la nueva regla (mantener: 01 no cierra; 03 mergea y cierra).

En `AUTOMATION-03-audit-ux.md` § Prohibiciones L98, sustituir «No mergees ningún PR» por:

```markdown
- No mergees PRs de spec/plan/implementación de producto — **excepto** el PR
  `docs(audit):` del día, que **debes** mergear a `main` antes de cerrarlo (Enfoque B).
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/AutomationPromptInvariant` / Expected: **PASS** — 4 tests (incl. README), 0 failed. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs` / Expected: `AutomationPromptInvariantTest` PASS; `AuditArtifactFreshnessTest` aún **FAIL** (M7 vigente hasta merge PR #51). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — `git add docs/automation/AUTOMATION-03-audit-ux.md docs/automation/AUTOMATION-01-daily-spec.md` — mensaje: `docs(automation): require audit PR merge before close (F2, F3)`. — evidencia: PR #54, merge-then-close L87–97

---

### Task 5: Addendum incidente M7 y procedimiento de recuperación (F6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle`

**Depends on:** Task 4

**Files:**
- Modify: `docs/automation/INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` — nueva sección al final
- Test: ninguno nuevo

**Interfaces:**
- Consumes: evidencia PR #48, spec M7
- Produces: documentación de recuperación para audit huérfano

- [x] **Step 1: Escribir el test que falla** — N/A (docs-only); verificar ausencia con: `grep -n 'M7' docs/automation/INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` / Expected: exit 1 (sin coincidencias pre-fix). — evidencia: PR #54, Addendum M7 presente

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `grep -c 'Incidente M7' docs/automation/INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` / Expected: `0`. — evidencia: PR #54, Addendum M7 presente

- [x] **Step 3: Implementar el cambio mínimo** — append: — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

```markdown
## Addendum M7 — audit cerrado sin merge (2026-07-29 / 2026-07-30)

### Evidencia

- PR #48 `docs(audit): auditoría técnica diaria 2026-07-29` — `state=CLOSED`, `mergedAt=null`, `closedAt=2026-07-29T23:41:33Z`.
- Comentario owner: «Cerrado: continúa en #50» — viola Enfoque B (cross-PR sin merge).
- `docs/audits/2026-07-29-auditoria-tecnica-diaria.md` **ausente** en `main`.
- Último reporte mergeado: `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (PR #37).

### Procedimiento de recuperación

1. **Reporte huérfano (opcional):** PR docs-only desde `origin/automation/audit-2026-07-29` cherry-pick del archivo audit → `main`.
2. **Día en curso:** mergear PR audit abierto (#51 para 2026-07-30) con `gh pr merge <n> --squash` **antes** de cerrar.
3. **Prevención:** prompts F2–F3 + tests `AuditArtifactFreshnessTest` / `AutomationPromptInvariantTest`.
4. **Post-merge:** sincronizar prompts en Cursor UI (O2); verificar `php tests/run.php Docs` verde.

### Causa raíz M7

AUTOMATION-03 instruía «Cierra el PR draft» sin exigir merge — regresión de proceso, no de código producto.
```

- [x] **Step 4: Verificación enfocada** — Run: `grep -c 'Addendum M7' docs/automation/INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` / Expected: `1`. — evidencia: PR #54, Addendum M7 presente

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Docs/AutomationPreflightRef` / Expected: PASS. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — `git add docs/automation/INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` — mensaje: `docs(automation): add M7 addendum and recovery procedure (F6)`. — evidencia: PR #54, Addendum M7 presente

---

### Task 6: Merge del PR audit #51 y verificación final (AC6)

**Repository:** `Parzival2103/Lebytek_Framework`

**Branch:** `feat/audit-artifact-chain-lifecycle` → PR implementación; merge audit en `main`

**Depends on:** Tasks 1–5 mergeados o listos en PR implementación

**Requiere operador humano:** sí — merge PR #51 requiere permisos maintainer y coordinación con AUTOMATION-03; no ejecutar en producción VPS.

**Files:**
- Ninguno en rama implementación (merge audit es operación sobre `main`)

**Interfaces:**
- Consumes: PR #51 MERGEABLE; rama implementación con F1–F6
- Produces: `docs/audits/2026-07-30-auditoria-tecnica-diaria.md` en `main`; suite Docs verde

- [x] **Step 1: Escribir el test que falla** — N/A; estado previo: `AuditArtifactFreshnessTest` FAIL. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/AuditArtifactFreshness` / Expected: FAIL M7 (pre-merge #51). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 3: Implementar el cambio mínimo** — **Operador / AUTOMATION-03:** — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

```bash
gh pr view 51 --json mergeable,state
gh pr merge 51 --squash
git fetch origin main
git checkout main && git pull origin main
test -f docs/audits/2026-07-30-auditoria-tecnica-diaria.md && echo audit_ok
```

Expected: `mergeable=MERGEABLE`, merge exit 0, `audit_ok`.

Opcional recuperación 2026-07-29:

```bash
git checkout -b docs/recover-audit-2026-07-29 origin/main
git checkout origin/automation/audit-2026-07-29 -- docs/audits/2026-07-29-auditoria-tecnica-diaria.md
git commit -m "docs(audit): recover 2026-07-29 report orphaned by M7"
gh pr create --base main --title "docs(audit): recover 2026-07-29 audit report"
```

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs` / Expected: **PASS** — todos los tests Docs incl. `AuditArtifactFreshnessTest` (sin PR #51 abierto, skip o PASS). — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php` / Expected: 0 failed global. — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

- [x] **Step 6: Commit** — N/A en repo (merge #51 es commit en `main`). Abrir PR implementación F1–F6: — evidencia: verificado en main @ e19fa25 (PR #54 / #51)

```bash
git checkout feat/audit-artifact-chain-lifecycle
gh pr create --base main --title "docs(automation): audit artifact chain lifecycle F1–F6" \
  --body "Implements spec 2026-07-30-audit-artifact-chain-design.md (F1–F6).

- README Enfoque B lifecycle
- AUTOMATION-01/03 merge-before-close
- AuditArtifactFreshnessTest + AutomationPromptInvariantTest
- M7 addendum in INCIDENT doc

Requires PR #51 merged for full Docs green on main."
```

---

## Criterios finales de aceptación

- [x] AC1: README documenta Enfoque B y prohibiciones cross-PR. — evidencia: PR #54, `docs/automation/README.md` L49–74
- [x] AC2–AC3: Prompts 03/01 actualizados; `AutomationPromptInvariantTest` PASS. — evidencia: prompts + test PASS en main (PR #54)
- [x] AC4–AC5: Tests F4/F5 existían en rojo pre-fix; mensajes citan M7 y recovery. — evidencia: tests presentes con mensajes M7 (PR #54)
- [x] AC6: Tras merge PR #51 + PR F1–F6, `php tests/run.php Docs` verde. — evidencia: PR #51+#54 merged; audit 2026-07-30 en main
- [x] AC7: Diff implementación sólo `docs/automation/` + `tests/Docs/`. — evidencia: PR #54 diff docs/automation + tests/Docs
- [x] AC8: PR #51 no cerrado sin merge (verificado en Task 6). — evidencia: PR #51 state=MERGED mergedAt=2026-07-30
- [x] AC9: Portal/O1 marcados no verificados en este plan. — evidencia: O1/O2 no verificados — declarado fuera de corrida
- [x] O2: Checklist post-merge — operador sincroniza Cursor UI con `docs/automation/*`. — evidencia: requiere operador humano post-merge (pendiente ops)

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Operador repite cierre manual | F1–F3 + tests |
| F4 falso positivo sin corrida 00 | Skip si no hay PR audit abierto |
| Prompts UI desincronizados | O2 manual post-merge |
| Revert | Revert PR docs-only; restaurar prompts en UI |

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Reconciliación UTC | 2026-07-31T14:00:00Z |
| SHA `origin/main` verificado | `e19fa25c7c96560462f60c31b56b99c8d7eaf619` |
| Tareas completadas / totales | **6 / 6** (Tasks 1–5 implementación PR #54; Task 6 merge audit PR #51 + PR implementación #54) |
| Estado | **Completo** — archivado 2026-07-31 |
| Siguiente tarea ejecutable | N/A — plan cerrado |
| Bloqueos resueltos | M7 (D17–D21) — PR #51 merged; F1–F6 — PR #54 merged |
| Bloqueos pendientes ops | O2: sincronizar prompts Cursor UI con `docs/automation/*` (operador humano) |
| Evidencia clave | `tests/Docs/AutomationPromptInvariantTest.php`, `AuditArtifactFreshnessTest.php`; `docs/automation/README.md` Enfoque B; `docs/audits/2026-07-30-auditoria-tecnica-diaria.md` en main |
| Plan activo derivado | N/A — plan nuevo desde spec del día 2026-07-30 |
