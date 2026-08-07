# Plan readiness — 2026-08-03

**Re-run local (operador):** Cloud Agents sin PHP CLI → reevaluación en workstation con PHP 8.5.1 + Composer 2.9.5. Supersede el veredicto `BLOCKED` de #76 para autorización 07.

**Veredicto:** READY_PARTIAL
**Plan objetivo:** `docs/superpowers/plans/2026-08-03-audit-mkt-leads-after-list-rows.md` @ `ada7ce2ce9ed78048097358a2db0e4b470ccdf2e` (`origin/main`, vía PR #75)
**Modo (04):** normal
**Clasificación (05):** PLAN CONTINUACIÓN *(plan Portal activo; plan Framework 08-02 archivado 4/4)*
**Siguiente tarea (04):** Task 1 — gap-fill Portal *(bump framework ya en `Lebytek_Portal/main` @ `5d31469` / #27; handler batch ya en `75554de`)*

**Resumen plan:** 5 tareas Portal (bump + TDD + handler + registro/JSON + PR/smoke). Evidencia local: implementación base **ya en Portal `main`** con clave `mkt_leads` + `MktLeadsListEnrichHandler` + `POST /admin/demo-leads-snapshot` (varianza intencional vs nombres del plan `mkt_leads_enrich` / `getTenant` — alinea enfoque A del spec: batch por página). Gaps restantes para 07: `FrameworkVersionGateTest`, degradación U2/U3 en fallo API, columna virtual `tenant_actividad`. Smoke R1 → DEFERRED operador.

## Preflight

| Paso | Resultado | Evidencia |
|------|-----------|-----------|
| `git fetch origin --prune --tags` | OK | `origin/main` → `2e90ab5d3fa0c716c68e7cc407accb4dbdaeb434` |
| `git rev-parse --verify origin/main` | OK | resuelve |
| `<LEGACY_REF>` | OK | `refs/tags/archive/backoffice-api-integration` @ `4789f953`; 0 commits `origin/main..LEGACY` ancestros de HEAD |
| `merge-base --is-ancestor origin/main HEAD` | OK | exit 0 |
| `git status --porcelain` | OK | vacío (pre-artefacto) |

## Checklist A–F

| ID | Estado | Evidencia |
|----|--------|-----------|
| **A1** | OK | Último audit mergeado: `docs/audits/2026-08-02-auditoria-tecnica-diaria.md` (PR #67). Sin PR `docs(audit):` abierto. Delta &lt; 2 días → M7 N/A. |
| **A2** | OK | Spec del día en `main`: `docs/superpowers/specs/2026-08-03-audit-mkt-leads-after-list-rows-design.md` (PR #75). |
| **A3** | OK | PR spec #75 **MERGED** @ 2026-08-03T13:26:10Z. |
| **A4** | OK | Plan ↔ spec alineados; varianza Portal (clave `mkt_leads`, batch snapshot) documentada como equivalente funcional del enfoque A. |
| **B1** | OK | `php --version` → PHP 8.5.1 (cli). `php tests/run.php Docs` → **24 passed, 0 failed**. |
| **B2** | OK | `gh auth status` → logged in (`Parzival2103`, scopes repo/workflow). |
| **B3** | OK | Plan TDD documenta Expected FAIL por tarea; Task 1 gate pendiente de archivo de test (código lock ya ≥ 1.2.3). |
| **B4** | OK | Rama `feature/mkt-leads-after-list-rows` creable desde `Lebytek_Portal` `origin/main` @ `5d31469`. |
| **C1** | DEFERRED | Smoke R1 + login admin (**Requiere operador humano:** sí) — Task 5; fuera camino crítico Tasks 1–4 gap-fill. |
| **C2** | OK | M6 gh 404 Portal **resuelto en workstation** — clone local `Lebytek_Portal` + `gh api …/Lebytek_Portal` → `main`. |
| **D1** | OK | PRs Framework abiertos previos #71–#73 cerrados (duplicados 06). Sin PR `feat/*` Portal abierto del tema. |
| **E1** | OK | `gh api repos/Parzival2103/Lebytek_Portal --jq .default_branch` → `main`. |
| **E2** | OK | WhatsApiLebytek `main` accesible. |
| **F1** | OK | Sin PR feat abierto que pise; implementación base ya mergeada en Portal `main` (`75554de`, `5d31469`). |
| **F2** | OK | 07 trabajará rama `feature/mkt-leads-after-list-rows` solo para gaps; no reescribe batch snapshot. |

**Contador:** OK 13 · BLOCKED 0 · DEFERRED 1 · SKIP 0

## PRs abiertos relevantes

| # | título | acción recomendada para 07/08 |
|---|--------|-------------------------------|
| — | *(ninguno Framework tras cerrar #71–#73)* | — |
| Portal (nuevo) | feat gap-fill afterListRows | 07 abre; 08 merge si CI green |

**Referencia mergeada:** Framework #74/#75/#76; Portal #27 (framework 1.2.3).

## Remediación (si BLOCKED)

N/A — sin BLOCKED.

## Autorización 07

- **Ejecutar:** parcial hasta Task 4 (gap-fill) + Task 5 sin smoke humano
- **Motivo:** B1 PHP OK en workstation; plan Portal activo; base ya en `main` — 07 no debe reemplazar `getDemoLeadsSnapshot` por N×`getTenant`
- **Rama base:** `Lebytek_Portal` `main` @ `5d31469168ac14a6704ba93d1e20149b24846d6f`
- **Rama implementación:** `feature/mkt-leads-after-list-rows`
- **Alcance autorizado:**
  1. `FrameworkVersionGateTest` (Task 1 restante)
  2. Degradación U2/U3 en fallo API + columna virtual `tenant_actividad` (gaps Tasks 2–4)
  3. Tests verdes + PR Portal
  4. **Omitir:** smoke admin R1 (DEFERRED operador); rename clave `mkt_leads`→`mkt_leads_enrich` (no regresión)

---

*Generado por AUTOMATION-06 re-run local 2026-08-03 (workstation PHP). Cloud report #76 queda histórico como BLOCKED por B1.*
