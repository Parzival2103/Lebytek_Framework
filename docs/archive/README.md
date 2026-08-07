# Archivo histórico

Artefactos de planificación, auditorías one-off y reportes de automation **ya
cerrados**. No son documentación operativa del framework.

**No viajan en el paquete Composer** (`.gitattributes` → `export-ignore`).
Siguen versionados en este repo para trazabilidad del equipo y los agents.

## Contenido

| Carpeta | Descripción |
|---------|-------------|
| [`superpowers/`](superpowers/) | Specs/plans consumidos + inventarios FPS one-shot |
| [`audits/`](audits/) | Auditorías y correcciones históricas (no diarias vigentes) |
| [`automation-reports/`](automation-reports/) | Readiness/closure de ciclos ya cerrados |
| [`_SNAPSHOT_CONTEXTO.md`](_SNAPSHOT_CONTEXTO.md) | Snapshot de contexto local |
| [`plan_proyecto.md`](plan_proyecto.md) | Plan de proyecto histórico |

## Documentación activa (sí consultar / sí en dist)

| Ámbito | Ruta |
|--------|------|
| Guías de uso | [`docs/core/`](../core/), [`docs/modules/`](../modules/) |
| Contratos paquete | [`ARCHITECTURE-CONSUMER.md`](../ARCHITECTURE-CONSUMER.md), [`ENVIRONMENTS.md`](../ENVIRONMENTS.md), … |
| Specs/plans en curso | [`docs/superpowers/`](../superpowers/) |
| Auditorías diarias vigentes | [`docs/audits/`](../audits/) |
| Prompts automation | [`docs/automation/`](../automation/) |

## Política de archivo

1. Spec/plan **implementado y mergeado** → `docs/archive/superpowers/{specs,plans}/`.
2. Auditoría one-off o corrección histórica → `docs/archive/audits/`.
3. Reportes readiness/closure del ciclo cerrado → `docs/archive/automation-reports/`.
4. **No** archivar: `*-auditoria-tecnica-diaria.md` recientes, prompts `AUTOMATION-*`, ni guías `core/` / `modules/`.
