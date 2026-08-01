# Cursor Automations — Lebytek Framework

Prompts canónicos de la cadena diaria de auditoría. **Nueve etapas** en dos
fases: creación de artefactos (00–05) y ciclo plan→implementación→cierre
(06–08). Las etapas 00–05 corren cada **30 minutos**; 06–08 están en
**modo verificación** (no desatendidas hasta auditar 06 contra el outcome de
04/05 con un plan real).

Repositorio: `Parzival2103/Lebytek_Framework`, branch `main`.

## Fase 1 — Audit → spec → plan (00–05)

| # | Archivo | Entrega | Rama |
|---|---------|---------|------|
| 00 | `AUTOMATION-00-check-code-main-framework.md` | reporte de auditoría + PR draft `docs(audit):` | `automation/audit-YYYY-MM-DD` |
| 01 | `AUTOMATION-01-daily-spec.md` | design spec | `automation/spec-YYYY-MM-DD` |
| 02 | `AUTOMATION-02-audit-tech-debt.md` | pase de deuda técnica sobre el spec | `automation/spec-YYYY-MM-DD` |
| 03 | `AUTOMATION-03-audit-ux.md` | pase compat/UX + **PR de la rama diaria** + merge audit | `automation/spec-YYYY-MM-DD` |
| 04 | `AUTOMATION-04-plan-writer.md` | reconciliación del plan activo + plan del día | `automation/spec-YYYY-MM-DD` |
| 05 | `AUTOMATION-05-wha-notify.md` | aviso WhatsApp del estado real | — |

## Fase 2 — Readiness → ejecución → cierre (06–08)

| # | Archivo | Entrega | Cuándo |
|---|---------|---------|--------|
| 06 | `AUTOMATION-06-plan-readiness-gate.md` | reporte `docs/automation-reports/*-plan-readiness.md` + veredicto READY/BLOCKED | Tras 05 |
| 07 | `AUTOMATION-07-plan-executor.md` | implementación del plan (rama `feat/*`, PR producto) | Solo si 06 autoriza |
| 08 | `AUTOMATION-08-plan-closure.md` | merges/cierre PRs + plan archivado + reporte closure + **WhatsApp cierre** | Tras 07 |

**WhatsApp:** AUTOMATION-05 avisa «plan listo»; AUTOMATION-08 avisa «ciclo cerrado»
(qué se mergeó, SHA `main`, PRs aún abiertos). Mismas variables de entorno.

### Permisos Cursor (07–08)

| Etapa | Git write | `gh pr merge` | WhatsApp |
|-------|-----------|---------------|----------|
| 06 | opcional (reporte) | no | no |
| 07 | sí (rama `feat/*`) | no (abre PR) | no |
| 08 | sí (`main` docs) | **sí** | **sí** |

Detalle de configuración en `AUTOMATION-08-plan-closure.md` § Permisos Cursor
Automations. Sin permisos de merge, 08 entra en dry-run pero **igual envía**
WhatsApp con checklist para el operador.

### Por qué 06–08

La fase 1 deja habitualmente **PRs spec abiertos**, **plan sin ejecutar** y
**implementación manual** (p. ej. SDD interactivo). 06 evita ejecutar con M7,
PHP ausente o bloqueos humanos no leídos; 07 acota el alcance a implementar; 08
cierra el ciclo (merge implementación/spec, reconciliar plan, reducir PRs
huérfanos).

### Alineación 06 ↔ 04 ↔ 05 (checklist de verificación)

Cuando exista un plan nuevo del día, contrastar:

| Campo | AUTOMATION-04 | AUTOMATION-05 | AUTOMATION-06 debe |
|-------|---------------|---------------|-------------------|
| Modo plan | `Modo:` normal/degradado/continuación | Clasificación WhatsApp equivalente | Mismo modo; BLOCKED si 05 = PIPELINE ROTO |
| Progreso | `Estado de ejecución` tareas N/M | Bullet «Tareas: N/M · Siguiente: …» | Misma siguiente tarea |
| Bloqueos | Sección bloqueos / `Requiere operador humano` | Bullets bloqueos ops | BLOCKED vs DEFERRED coherente |
| PR del día | URL en salida 04 | Enlace en WhatsApp | Inventario PR coherente |
| Plan activo | Parte A reconciliación | Enlace plan activo en `main` | No contradecir checkboxes verificados |

Si 06 marca READY pero 05 dijo PIPELINE ROTO → fallo de diseño del prompt 06.

### Activación fase 2

1. **06–07:** validados en corrida manual (readiness + ejecutor).
2. **08:** habilitar permisos Git write + `gh pr merge` + secrets WhatsApp (ver
   prompt 08).
3. Reemplazar prompt pegado en Cursor UI tras cada cambio canónico (O2).

## Invariantes

- Dos ramas por día: `automation/audit-*` para la etapa 00, `automation/spec-*`
  compartida por las etapas 01–04. Ambas nacen de `origin/main`.
- Cada etapa commitea **exclusivamente su propio artefacto**, verificado con
  `git status --porcelain` antes y después del commit.
- La etapa 00 escribe sólo bajo `docs/audits/`. Las etapas 01–03 sólo bajo
  `docs/superpowers/specs/`. La etapa 04 sólo bajo `docs/superpowers/plans/`.
- **Ninguna rama con trabajo puede terminar el día sin PR.** La etapa 03 lo abre;
  la 04 lo recupera si la 03 falló.
- **Ninguna etapa hace «skip» silencioso.** Cada una degrada de forma explícita y
  entrega igualmente, marcando el modo en el artefacto. La única parada dura es
  un fallo de integridad del preflight, y entonces no se commitea nada.
- En modo degradado está prohibido inventar hallazgos, rutas, PRs, SHAs o
  resultados de tests. Todo se apoya en evidencia verificable del repositorio.
- El preflight de las **nueve** etapas exige: fetch verificado, `origin/main` ancestro
  de `HEAD`, ningún commit exclusivo del historial legacy en la ancestry, y
  working tree limpio.
- Si ni el tag `archive/backoffice-api-integration` ni la rama
  `feature/backoffice-api-integration` resuelven **y el fetch está verificado**,
  el historial legacy ya no es alcanzable: la comprobación es vacua y la etapa
  continúa. Esto evita que borrar la rama deje muerta la cadena entera.
- `feature/backoffice-api-integration` es evidencia histórica de migración. Nunca
  es base de auditoría, spec, plan, implementación ni deploy.
- Marketing, membresías, landing y trabajo de sitio desplegable pertenecen a
  `Parzival2103/Lebytek_Portal/main`.
- La evidencia entre repositorios se lee con llamadas autenticadas a la API de
  GitHub, sin checkout ni merge.
- Framework llega a Portal por tag semver y `composer.lock`.
- Las etapas **00–07** no mergean PRs de producto a `main` (08 sí mergea el
  ciclo del día con CI green y permisos configurados). Ninguna etapa despliega,
  usa SSH, edita `.env` prod ni ejecuta migraciones de producción.
- Un comando que descubre cero tests no es un gate verde.

## Ciclo de vida de artefactos (Enfoque B)

Cadena objetivo audit → spec → plan:

1. **AUTOMATION-00** abre PR draft `docs(audit):` desde `origin/main`. El PR abierto es fuente Nivel A para 01–02.
2. **AUTOMATION-01–02** escriben spec en `automation/spec-*` sin heredar la rama audit.
3. **AUTOMATION-03** abre PR `docs(spec):`, **mergea** el PR audit del mismo `YYYY-MM-DD` a `main`, luego cierra el PR audit ya mergeado.
4. **AUTOMATION-04** entrega plan en la misma rama spec.
5. **AUTOMATION-06** valida readiness; **AUTOMATION-07** implementa; **AUTOMATION-08**
   mergea/cierra PRs pendientes del ciclo y envía WhatsApp de cierre (ver fase 2).

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

## Sincronización con Cursor

**Cambiar estos archivos no actualiza una automation ya creada.** Cada vez que un
prompt canónico cambie, hay que reemplazar el texto pegado en el editor de
Automations y verificar repositorio y branch.

Esa desincronización fue la causa raíz del incidente del 25/07 y volvió a
producirse en julio de 2026: el repositorio tenía tres prompts endurecidos
mientras Cursor ejecutaba seis prompts antiguos sin preflight de ancestry.

Para un reset de lineage, deshabilita las automations anteriores o recréalas y no
reutilices su memoria de ejecución: puede conservar instrucciones de rama
obsoletas aunque el prompt pegado haya cambiado.

El incidente 2026-07-24/25 y su procedimiento de reset están en
`INCIDENT-2026-07-25-ARTIFACT-LINEAGE.md`.
