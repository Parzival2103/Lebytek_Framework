# FPS — Baseline Git (Framework ↔ Portal)

**Registrado:** 2026-07-17  
**Repo:** `Lebytek_Framework`

## SHAs de referencia

| Ref | SHA | Notas |
|-----|-----|-------|
| `main` | `2c71d3f7f75eea2ee746bc271b9a3907dbbdd9cd` | Merge PR #5 — **ya contiene código Portal** |
| `feature/backoffice-api-integration` | `dad059056d26b6eb527815f85cf71ecd507a57fe` | SHA congelado para copia Portal (Plan 05) |
| merge-base | `f4d82ffa7413035040643a5b6b32137b33f49112` | Ancestro común |
| `docs/framework-portal-separation-plans` | `7237fd8e88a575ba9ac86cb99e354575759816b6` | Fuente spec + planes FPS 00–08 (resolver con `git rev-parse docs/framework-portal-separation-plans`) |

## Delta feature exclusivo

- Comando: `git diff --name-only main..feature/backoffice-api-integration`
- Aproximadamente **194 archivos** (plataforma genérica + Marketing + landing + membresías).
- **No** se transfiere con merge; los planes 01–06 usan `git checkout <sha> -- <path>` selectivo.

## Rama de trabajo FPS

| Rama | Base | Rol |
|------|------|-----|
| `consolidation/framework-portal-separation` | `main` | Consolidación incremental Plans 01–06 |
| `docs/framework-portal-separation-plans` | feature o `main` | PR documentación; **no** base de runtime |

## Continuidad documentación → consolidación

1. Merge del PR de docs (opcional para el equipo) deja planes en `feature` o `main`; antes del merge siguen en la rama docs.
2. Plan 00 Step 4 busca, en orden, la rama docs, la feature y `main`, y copia solo spec + planes a `consolidation/framework-portal-separation`.
3. **No** mergear `feature/backoffice-api-integration` → `main` para obtener docs.

## Tests baseline en `main` (anotación)

- Comando: `php tests/run.php`
- Resultado al registrar: `615 passed, 6 failed` — rellenar con valores reales del Step 2.

## Política operativa

- **Nunca** merge `feature/backoffice-api-integration` → `main` sin orden explícita.
- Deploy VPS sigue pull de feature hasta nuevo aviso; la consolidación es local/package-first.
