# Continuidad de la cadena de artefactos de auditoría diaria

Fecha: 2026-07-30  
Estado: diseño (AUTOMATION-01) — sin implementación en esta corrida  
Modo: normal (fuente Nivel A)

## Problema

La cadena diaria de seis automations (00→05) produce un reporte de auditoría en
`docs/audits/` y, en etapas posteriores, un spec, pases de deuda/UX y un plan.
Hoy la continuidad entre días depende de **PRs draft abiertos** o de ramas
`automation/audit-*`, porque los reportes **no quedan en `main`** hasta que alguien
los mergee manualmente o la cadena completa cierre bien.

El incidente operativo **M7** (auditoría 2026-07-30) lo demuestra:

| Hecho verificado | Evidencia |
|------------------|-----------|
| PR #48 (`docs(audit): auditoría técnica diaria 2026-07-29`) cerrado sin merge | `state=CLOSED`, `mergedAt=null`, `closedAt=2026-07-29T23:41:33Z` |
| Comentario del owner | «Cerrado: continúa en #50» (spec semver, rama distinta) |
| Artefacto ausente en `main` | `docs/audits/` en `origin/main` no contiene `2026-07-29-auditoria-tecnica-diaria.md` |
| Última auditoría mergeada | `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (PR #37) |
| Cadena del 2026-07-30 | PR #51 abierto y MERGEABLE @ `dc9996e` — fuente Nivel A de este spec |

Impacto: las etapas 01–04 degradan a Nivel B/C cuando falta el PR de auditoría;
la deuda diaria no queda versionada en `main`; un operador puede cerrar el draft
al abrir otro PR (spec/plan) rompiendo la ancla canónica. El incidente
2026-07-25 (`INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md`) ya documentó contaminación
de lineage; M7 es una **regresión de proceso**, no de código de producto.

### Deuda arrastrada relevante (contexto, no alcance principal)

| ID | Tema | Owner | Spec/plan existente |
|----|------|-------|---------------------|
| M1 | `config/app.php` version `1.0.0` vs tag `v1.2.1` | Framework | PR #50 / spec `2026-07-29-audit-config-version-semver-sync-design.md` (rama `automation/spec-2026-07-29`, no mergeado) |
| M2 | `.env.example` root con vars Portal/Marketing | Framework harness | Spec archivado `docs/archive/superpowers/specs/2026-07-27-audit-harness-portal-env-purge-design.md` |
| M3 | CRUD/calendario sin `RbacMiddleware` en router | Framework | Backlog; sin spec activo |
| M4 | `/api/*` autenticada por sesión | Framework | Backlog documentación |
| M5 | `permisos.gestionar` ausente en seeds | Framework | Backlog RBAC |
| M6 | Token automation sin lectura de Portal | Ops | Bloqueador entorno |
| C2 ops | Stripe QA Portal antes de `PAYMENTS_SUBSCRIPTION_CHECKOUT=true` | Portal | Framework v1.2.1 publicado; Portal **no verificado** en esta corrida |

Este spec **no duplica** M1 ni M2; se centra en M7 y en los contratos de proceso
que evitan repetirlo.

## Comportamiento esperado

Tras implementar este diseño:

1. **Cada día con corrida 00–05** deja al menos un reporte bajo `docs/audits/`
   accesible por la cadena sin intervención manual ad hoc.
2. **Ningún cierre de PR `docs(audit):`** ocurre sin merge del artefacto a `main`
   o sin política explícita documentada de retención hasta AUTOMATION-03.
3. **AUTOMATION-01** (spec) siempre resuelve fuente Nivel A o B el mismo día;
   Nivel C sólo para días sin corrida 00 o con fallo documentado.
4. **Operadores humanos** no cierran PRs de auditoría al abrir PRs de spec/plan
   de otra rama o fecha.
5. **Regresión detectable:** un test Docs falla si la auditoría mergeada más
   reciente en `main` supera un umbral de antigüedad configurable mientras existen
   PRs `docs(audit):` abiertos más nuevos (condición de M7).

## Alcance

### Framework — plataforma y automation (`Parzival2103/Lebytek_Framework`, base `main`)

| # | Entregable | Capa / ruta |
|---|------------|-------------|
| F1 | Política de ciclo de vida de artefactos audit/spec/plan en `docs/automation/README.md` | Docs |
| F2 | Actualizar `AUTOMATION-03-audit-ux.md`: merge obligatorio del PR audit antes de cerrar (squash o merge commit) | Docs automation |
| F3 | Actualizar `AUTOMATION-01-daily-spec.md`: registrar explícitamente prohibición de cierre manual cross-PR | Docs automation |
| F4 | Test `tests/Docs/AuditArtifactFreshnessTest.php` — gate de frescura del artefacto mergeado | Tests harness |
| F5 | Test `tests/Docs/AutomationPromptInvariantTest.php` — strings invariantes en prompts 01 y 03 | Tests harness |
| F6 | Entrada en `INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md` o addendum M7 con procedimiento de recuperación | Docs |

### Ops — credenciales automation (fuera de código producto)

| # | Entregable | Owner |
|---|------------|-------|
| O1 | Conceder al token `gh` de Cursor Automations lectura de `Parzival2103/Lebytek_Portal` | Ops |
| O2 | Sincronizar prompts pegados en Cursor UI con `docs/automation/*` tras F2–F3 | Ops |

### Staging / implementación (AUTOMATION-04+, no producción)

- Aplicar cambios F1–F6 en rama `automation/spec-2026-07-30` (o sucesora) vía PR
  `docs(spec):` → `main`.
- Recuperación retroactiva: **opcional** cherry-pick del reporte 2026-07-29 desde
  `origin/automation/audit-2026-07-29` en PR docs-only separado (staging); no
  bloqueante para F1–F6.

## No-alcance

- Implementación de M1 (semver UI), M2 (purge `.env.example`), M3–M5 (RBAC/router/API).
- Merge de `feature/backoffice-api-integration` → `main` (legacy archivado en tag
  `archive/backoffice-api-integration` @ `4789f95` — evidencia histórica).
- Deploy, SSH, migraciones producción, cambios `.env` en VPS.
- Cierre del PR #51 de auditoría (reservado a AUTOMATION-03).
- Apertura del PR de spec (reservado a AUTOMATION-03).
- Marketing, membresías, checkout Portal (`Parzival2103/Lebytek_Portal`).

## Ownership map

| Requisito | Repositorio | Rama base | Consumidor |
|-----------|-------------|-----------|------------|
| Política lifecycle artefactos | `Lebytek_Framework` | `main` | Automations 00–05, maintainers |
| Prompts AUTOMATION-01/03 | `Lebytek_Framework` | `main` | Cursor Automations UI |
| Tests Docs frescura/invariantes | `Lebytek_Framework` | `main` | CI / `php tests/run.php Docs` |
| Reportes `docs/audits/` | `Lebytek_Framework` | `main` (post-merge) | Spec/plan writers |
| Specs/planes | `Lebytek_Framework` | `main` | Implementación platform |
| Portal SHA / composer.lock | `Lebytek_Portal` | `main` | Verificación cruzada C2 ops |
| Token gh Portal | Cursor cloud / GitHub | — | Automations (M6) |

## Enfoques considerados

### Enfoque A — Merge inmediato tras AUTOMATION-00

Mergear cada PR `docs(audit):` a `main` en cuanto termina la etapa 00.

| Pros | Contras |
|------|---------|
| Nivel C siempre resuelve | Ruido en historial de `main` (1 merge/día) |
| Sin dependencia de PR abierto | Contradice rol de draft hasta revisión ligera |
| | No protege si operador cierra sin merge igualmente |

### Enfoque B — Retención draft + cierre sólo en AUTOMATION-03 con merge (recomendado)

Mantener PR audit abierto como fuente Nivel A para 01–02; en AUTOMATION-03:
(1) abrir/actualizar PR spec, (2) **mergear** PR audit a `main`, (3) cerrar PR audit
con enlace al PR spec.

| Pros | Contras |
|------|---------|
| Alineado con cadena de 6 etapas existente | Requiere cambio de prompt 03 (hoy sólo «cierra») |
| Un merge audit/día agrupado con entrega visible | Ventana Nivel B entre 00 y 03 |
| Recuperación: tests detectan staleness | |

### Enfoque C — Artefacto único en rama `automation/spec-*`

Copiar reporte audit a la rama spec al iniciar 01; una sola PR docs al final.

| Pros | Contras |
|------|---------|
| Un solo PR | Viola invariante «dos ramas por día» (`README.md`) |
| | Mezcla responsabilidades 00 vs 01 |
| | Reintroduce riesgo lineage si spec hereda audit branch |

**Recomendación:** **Enfoque B**. Es el menor cambio respecto al diseño actual,
corrige la causa de M7 (cierre sin merge) y se refuerza con tests Docs. Enfoque A
queda como fallback documentado si 03 falla repetidamente.

## Diseño

### Ciclo de vida canónico (estado objetivo)

```mermaid
sequenceDiagram
    participant A00 as AUTOMATION-00
    participant Main as main
    participant A01 as AUTOMATION-01
    participant A03 as AUTOMATION-03
    participant A04 as AUTOMATION-04

    A00->>Main: PR draft docs(audit) desde origin/main
    Note over A00,Main: PR abierto = fuente Nivel A
    A01->>A01: Rama automation/spec-* desde origin/main
    A01->>A01: Lee audit vía PR API (no checkout audit branch)
    A03->>Main: Abre PR docs(spec)
    A03->>Main: Merge PR docs(audit)
    A03->>A03: Cierra PR audit (merged)
    A04->>Main: Plan en misma rama spec; PR actualizado
```

### Reglas invariantes (añadir a `docs/automation/README.md`)

1. **Prohibido** cerrar un PR `docs(audit):` sin `mergedAt` salvo cancelación
   explícita del día (incidente, corrida abortada) documentada en el PR.
2. **Prohibido** enlazar «continúa en #N» entre PR audit y PR spec de ramas
   distintas como sustituto del merge.
3. AUTOMATION-03 **debe** ejecutar `gh pr merge` (squash recomendado) del audit
   del día **antes** de `gh pr close`.
4. Si AUTOMATION-03 falla, AUTOMATION-04 intenta abrir PR spec **y** reporta
   audit sin merge; el test F4 queda rojo hasta recuperación.
5. Modo degradado (Nivel D) no autoriza inventar hallazgos; sólo carry-forward
   verificado.

### Cambios concretos en prompts

**AUTOMATION-03**, sección «Cerrar el PR de auditoría» — sustituir por:

- Identificar PR `docs(audit):` del **mismo** `YYYY-MM-DD`.
- Verificar `mergeable=MERGEABLE`; si no, abortar cierre y reportar.
- `gh pr merge <n> --squash` (o merge commit si hay política repo).
- Comentario con enlace al PR spec; **no** usar `close` sin merge previo.

**AUTOMATION-01** — añadir a prohibiciones:

- No cerrar ni comentar cierre en PRs `docs(audit):` de ninguna fecha.

### Tests (fallan antes de implementar)

#### `AuditArtifactFreshnessTest`

```php
// Pseudocódigo — implementación en plan
public function test_merged_audit_report_is_not_stale_when_open_audit_pr_exists(): void
{
    $merged = $this->latestMergedAuditDateInMain(); // glob docs/audits/ en main
    $openAuditPrs = $this->openAuditPullRequests(); // gh API mock o fixture
    if ($openAuditPrs === []) {
        $this->markTestSkipped('No open audit PRs');
    }
    $newestOpen = max(array_map(fn ($pr) => $this->dateFromTitle($pr), $openAuditPrs));
    $this->assertLessThanOrEqual(
        2, // días
        $newestOpen->diffInDays($merged),
        'Merged audit in main is stale while a newer open audit PR exists (M7)'
    );
}
```

**Estado esperado pre-implementación:** test descubierto, **FAIL** en main porque
merged=`2026-07-27` y PR #51 es `2026-07-30` (delta > 2 días).

#### `AutomationPromptInvariantTest`

Assert que `docs/automation/AUTOMATION-03-audit-ux.md` contiene
`gh pr merge` y que `AUTOMATION-01-daily-spec.md` contiene prohibición de cerrar
PR audit.

**Estado esperado pre-implementación:** **FAIL** (prompt 03 no exige merge hoy).

### Migración segura

| Base | Acción |
|------|--------|
| `main` limpio @ `0ec722b` | PR docs-only con F1–F6; sin tocar `src/` |
| Rama con spec previo (#50) | Sin merge de ramas; M1 sigue su PR propio |
| Reporte 2026-07-29 huérfano | PR opcional docs-only desde `origin/automation/audit-2026-07-29` |
| Portal existente | Sin impacto — cambio sólo automation Framework |
| Consumidor skeleton | Sin impacto semver |

### Semver / release Framework

Este diseño **no** requiere tag semver nuevo: sólo docs + tests harness. Portal
no consume estos archivos vía Composer.

Si en el futuro se empaquetan prompts en el artefacto Composer, sería minor bump
documentado; **fuera de alcance** hoy.

## Dependencias y compatibilidad

| Dependencia | Estado verificado |
|-------------|-------------------|
| `origin/main` @ `0ec722bc38258b2e479d30cafd59940aa44d558e` | OK |
| Cadena 6 etapas documentada (`docs/automation/README.md`) | OK |
| PR #51 audit MERGEABLE @ `dc9996eb8ab934a428dbfc2f15bff1dacf3320a5` | OK |
| PR #48 cerrado sin merge | Confirma M7 |
| Legacy tag `archive/backoffice-api-integration` @ `4789f95` (53 commits exclusivos) | Ninguno ancestro de HEAD |
| `php tests/run.php Docs` | 13/13 PASS (sin tests F4/F5 aún) |
| Portal `main` SHA | **No verificado** — `gh api` HTTP 404 |
| Portal `composer.lock` ≥ v1.2.1 | **No verificado** (depende Portal) |
| Tag Framework `v1.2.1` @ `fba3e03` | OK |

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Operador repite cierre manual (#48) | Prompt + README + test F4 |
| Prompts UI desincronizados del repo | O2 checklist post-merge; incidente 2026-07-25 |
| Merge audit conflictivo | Audit es archivo único additive; bajo riesgo |
| Falso positivo F4 en día sin corrida 00 | Skip si no hay PR audit abierto |
| Portal inaccesible (M6) | Declarar no verificado; no bloquear F1–F6 |

## Rollback

1. Revert del PR docs que introduce F1–F6 (sin impacto runtime).
2. Restaurar texto anterior de prompts en repo **y** en Cursor UI.
3. Eliminar tests F4/F5 si generan ruido; cadena vuelve al comportamiento previo
   (con riesgo M7).
4. No revertir merges audit ya hechos — son documentación histórica valiosa.

## Criterios de aceptación

- [ ] **AC1:** `docs/automation/README.md` documenta ciclo de vida Enfoque B y
  prohibiciones de cierre manual cross-PR.
- [ ] **AC2:** `AUTOMATION-03-audit-ux.md` exige merge antes de close del PR audit.
- [ ] **AC3:** `AUTOMATION-01-daily-spec.md` prohíbe cerrar PRs audit.
- [ ] **AC4:** `AuditArtifactFreshnessTest` existe y **falla** en main pre-fix con
  mensaje que cite staleness M7 (merged 2026-07-27 vs open PR 2026-07-30).
- [ ] **AC5:** `AutomationPromptInvariantTest` existe y **falla** pre-fix.
- [ ] **AC6:** Tras implementación, ambos tests PASS; `php tests/run.php Docs` verde.
- [ ] **AC7:** Ningún archivo fuera de docs/tests harness en el PR de implementación.
- [ ] **AC8:** PR #51 permanece abierto hasta AUTOMATION-03 (no cerrado en esta corrida).
- [ ] **AC9:** Requisitos Portal (C2 ops, M6) marcados **no verificados** hasta
  evidencia `gh` de Portal.

## Operaciones

| Operación | Entorno | Esta corrida |
|-----------|---------|--------------|
| Escribir spec | Cloud agent | Sí (este archivo) |
| Editar prompts/tests | Staging PR → `main` | No (AUTOMATION-04+) |
| Merge PR audit #51 | Staging | No (AUTOMATION-03) |
| Sync token gh Portal | Producción ops | No — manual O1 |
| Deploy VPS | Producción | **Prohibido** |

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `0ec722bc38258b2e479d30cafd59940aa44d558e` |
| SHA Portal inspeccionado | **No disponible** — `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404 (repo privado o token sin acceso). Última evidencia documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27). |
| Rama generada | `automation/spec-2026-07-30` |
| Timestamp UTC | `2026-07-30T12:30:00Z` (trigger cron) |
| Nivel fuente | **A** — PR abierto #51 `docs(audit): auditoría técnica diaria 2026-07-30`, `mergeable=MERGEABLE`, `headRefOid=dc9996eb8ab934a428dbfc2f15bff1dacf3320a5`, diff único `docs/audits/2026-07-30-auditoria-tecnica-diaria.md` |
| PR auditoría fuente | #51 |
| headRefOid fuente | `dc9996eb8ab934a428dbfc2f15bff1dacf3320a5` |
| Hallazgo principal | M7 — artefacto audit cerrado sin merge (#48) |
| Requisitos no verificados | Portal SHA; Portal `composer.lock` vs v1.2.1; C2 ops Stripe QA Portal |

---

*Design-only. Ningún archivo de código de producto, config, rutas, migraciones ni
tests fue modificado en AUTOMATION-01.*
