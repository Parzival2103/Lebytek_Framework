# Estructura de planes — Remediación CRUD Engine (12 puntos)

**Propósito:** plantilla + protocolo operativo para producir **un plan por punto de orden** (1…12) y ejecutarlo con un segundo agente.  
**No es un plan de implementación.** No contiene tareas checkbox ejecutables de producto.  
**Fuente canónica de hallazgos y orden:** [`docs/audits/2026-08-07-auditoria-critica-crud-engine.md`](../audits/2026-08-07-auditoria-critica-crud-engine.md) § «Prioridad de remediación CONFIRMADA (post-P3)».  
**Skills:** escritor → `writing-plans`; ejecutor → `subagent-driven-development` (preferido) o `executing-plans`.  
**Owner:** `Parzival2103/Lebytek_Framework` @ `main`. Portal fuera de alcance salvo nota explícita.

---

## 1. Modelo de trabajo

```text
┌─────────────────────────────┐
│  Auditoría crítica (única)  │
│  2026-08-07-…crud-engine.md │
└──────────────┬──────────────┘
               │ orden 1..12
               ▼
┌─────────────────────────────┐     por cada punto N
│  AGENTE PLANIFICADOR        │──────────────────────────────┐
│  1. Leer auditoría          │                              │
│  2. Tomar punto N asignado  │                              │
│  3. Leer ESTA estructura    │                              │
│  4. Leer planes 1..(N-1)    │                              │
│     + verificar código real │                              │
│  5. Leer código del punto N │                              │
│  6. Escribir plan N         │                              │
│     (writing-plans)         │                              │
└─────────────────────────────┘                              │
               │ plan N commit/PR                            │
               ▼                                             │
┌─────────────────────────────┐                              │
│  AGENTE EJECUTOR            │◄─────────────────────────────┘
│  subagent-driven-development│
│  (un subagente por Task)    │
│  → código + tests + commits │
└─────────────────────────────┘
               │
               ▼
         punto N = DONE en main
         (baseline para N+1)
```

**Regla de oro:** el plan del punto **N** se escribe **solo** para ese punto, pero el planificador **debe** asumir el código **después** de los puntos `1..(N-1)` ya mergeados/aplicados. Si `N-1` no está en el árbol de trabajo, **STOP** o modo degradado documentado (ver §6).

**Prohibido:** un plan monolítico de los 12 puntos; saltar puntos; reordenar el backlog sin actualizar la auditoría; planificar Portal en `src/` / stub `app/`.

---

## 2. Catálogo de puntos (1 plan = 1 fila)

| Punto | IDs auditoría | Tema corto (slug) | Archivo de plan esperado |
|------:|---------------|-------------------|--------------------------|
| 1 | C1 + C2 + C5 | `authz-multi-canal` | `docs/superpowers/plans/2026-08-07-crud-p01-authz-multi-canal.md` |
| 2 | C3 + G15 + G6 | `states-form-options` | `docs/superpowers/plans/2026-08-07-crud-p02-states-form-options.md` |
| 3 | C6 | `uploads-hardening` | `docs/superpowers/plans/2026-08-07-crud-p03-uploads-hardening.md` |
| 4 | C4 + G13 + G1 + G14 | `cas-bulk-equality` | `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` |
| 5 | G12 | `aggregation-breaker` | `docs/superpowers/plans/2026-08-07-crud-p05-aggregation-breaker.md` |
| 6 | G4 + G10 + G16 | `router-rbac-vertical` | `docs/superpowers/plans/2026-08-07-crud-p06-router-rbac-vertical.md` |
| 7 | G7 + G8 + G9 | `validation-prefixes` | `docs/superpowers/plans/2026-08-07-crud-p07-validation-prefixes.md` |
| 8 | M14 + G5 | `handlers-di-interfaces` | `docs/superpowers/plans/2026-08-07-crud-p08-handlers-di-interfaces.md` |
| 9 | M16 + M5 + M6 | `bitacora-ledger-bigint` | `docs/superpowers/plans/2026-08-07-crud-p09-bitacora-ledger-bigint.md` |
| 10 | M15 + M18 + M19 + M13 | `listado-perf-schema` | `docs/superpowers/plans/2026-08-07-crud-p10-listado-perf-schema.md` |
| 11 | G2 + G3 + M2 + M8 | `puertos-tests-orquestacion` | `docs/superpowers/plans/2026-08-07-crud-p11-puertos-tests-orquestacion.md` |
| 12 | G11 + M7 + M10 + M20 + M1 + M4 + M9 + M11 + M12 + M17 + B* | `higiene-docs-dx` | `docs/superpowers/plans/2026-08-07-crud-p12-higiene-docs-dx.md` |

Fecha del prefijo: usar la fecha UTC del día en que se **escribe** el plan si difiere; el slug `crud-p0N-…` es estable. **Nunca** crear `-v2` / `-final`: actualizar el mismo archivo.

### Alcance fijo por punto (no diluir)

El plan N **solo** cierra los IDs de su fila. Hallazgos de otras filas van en su punto, aunque el planificador los vea. Excepción: tests de regresión **mínimos** del punto N que protejan el cierre de N (la auditoría exige tests con lotes 1–4; el punto 11 no los sustituye).

---

## 3. Flujo del AGENTE PLANIFICADOR (writing-plans)

Anunciar al inicio: *«Using writing-plans to create the implementation plan for CRUD remediation point N.»*

### 3.1 Checklist obligatorio (en orden)

1. **Leer auditoría** completa — al menos: catálogo de IDs del punto N, descripción técnica de cada ID, § prioridad confirmada, no-alcance.
2. **Tomar tarea asignada** — parámetro `PUNTO=N` (1…12). Rechazar si N inválido o si ya existe plan N con estado «completo» sin pedido de replan.
3. **Leer esta estructura** (§2–§5) y la plantilla §5.
4. **Baseline acumulativa:**
   - Listar planes `p01…p(N-1)` y su `Estado de ejecución`.
   - Verificar en el árbol de trabajo (rama base del plan) que los entregables de `1..(N-1)` existen o están mergeados en `origin/main`.
   - Si falta un prerrequisito: **STOP** con bloqueo, o documentar `Modo: degradado — baseline incompleta` + qué se asume (solo si el operador lo autorizó en el prompt).
5. **Leer código** de los archivos citados por los IDs del punto N **en el estado actual** (no citas de la auditoría sin re-leer líneas).
6. **Escribir plan N** con §5, skill `writing-plans`, calidad AUTOMATION-04 (sin placeholders).
7. **Auto-revisión** §3.3 → commit **solo** el archivo del plan N → actualizar PR de la rama de planes/implementación según protocolo del run.

### 3.2 Prompt canónico — Agente planificador

Copiar y rellenar `PUNTO`:

```text
Eres el AGENTE PLANIFICADOR de remediación CRUD Engine en
Parzival2103/Lebytek_Framework.

PUNTO ASIGNADO: <N>   # entero 1..12

Skills: reading writing-plans BEFORE writing. No implementes código.

Orden de lectura (obligatorio):
1. docs/audits/2026-08-07-auditoria-critica-crud-engine.md
2. docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md
3. Planes docs/superpowers/plans/2026-08-07-crud-p0{1..(N-1)}-*.md si existen
4. Código real de los IDs del punto N (re-leer archivos/líneas; no confiar solo en la auditoría)

Reglas:
- Un plan = un punto. No mezclar IDs de otros puntos salvo tests de regresión del propio N.
- El plan debe asumir los cambios YA realizados de los puntos 1..(N-1).
  Documenta en «Baseline asumida» SHA/rama y evidencia de cada prerrequisito.
- Si 1..(N-1) no están en el árbol: STOP o Modo degradado explícito.
- TDD, checkboxes `- [ ]`, sin TBD/TODO/«por definir».
- Ownership Framework en src/; espejo skeleton cuando toque rutas/config/assets.
- No editar vendor/; no Portal business; no merge legacy backoffice.
- Archivo de salida: docs/superpowers/plans/2026-08-07-crud-p0N-<slug>.md
  (slug de la tabla §2 de la estructura).
- Commit exclusivo de ese archivo. No ejecutes el plan.
```

### 3.3 Auto-revisión antes de commit del plan

| # | Check |
|---|--------|
| 1 | Todos los IDs del punto N tienen tarea o están en «Fuera de alcance» con motivo |
| 2 | Ningún ID de otro punto se «cuela» como entregable obligatorio |
| 3 | Baseline 1..(N-1) documentada y verificada en código |
| 4 | Firmas/rutas verificadas contra el árbol actual (no fantasma `app/`) |
| 5 | Cada Task: Files + Interfaces + Steps 1–6 (test rojo → verde → commit) |
| 6 | Comandos `php tests/run.php …` concretos; cero tests ≠ PASS |
| 7 | Sin placeholders |
| 8 | Criterios de aceptación trazables a IDs |
| 9 | Riesgos/rollback y evidencia para el ejecutor |

---

## 4. Flujo del AGENTE EJECUTOR (subagent-driven-development)

Anunciar: *«Using subagent-driven-development to execute CRUD remediation point N.»*

### 4.1 Checklist obligatorio

1. Leer plan N completo + esta estructura §4–§6.
2. Confirmar baseline: puntos `1..(N-1)` presentes en la rama de implementación.
3. Por cada Task del plan: despachar **subagente implementador fresco** → review de task → fix si Critical/Important → marcar checkbox.
4. Al cerrar el plan: suite de verificación del plan + actualizar `Estado de ejecución` del plan N.
5. No empezar punto N+1 en el mismo run salvo instrucción explícita.

### 4.2 Prompt canónico — Agente ejecutor

```text
Eres el AGENTE EJECUTOR de remediación CRUD Engine.

PUNTO: <N>
PLAN: docs/superpowers/plans/2026-08-07-crud-p0N-<slug>.md

Skill: subagent-driven-development (un subagente fresco por Task;
reviewer tras cada Task; review final de rama).

Reglas:
- Ejecuta SOLO el plan N. No replanifiques ni amplíes a otros puntos.
- Respeta Global Constraints y Baseline asumida del plan.
- TDD según steps del plan; commits frecuentes por Task.
- Si una Task está BLOCKED, registra el bloqueo en Estado de ejecución y STOP.
- Tras completar: actualizar checkboxes y Estado de ejecución del plan N
  (commit docs separado o junto al cierre, según protocolo del run).
- Verificación final: comandos listados en el plan + evidencia pedida.
- No merge a main salvo que el operador lo pida.
```

### 4.3 Qué NO hace el ejecutor

- Reescribir el plan salvo marcar checkboxes / Estado de ejecución.
- «Aprovechar» para G2/G3/refactor (punto 11) durante puntos 1–10.
- Cerrar un ID de otro punto «de pasada» sin que el plan N lo liste.

---

## 5. Plantilla obligatoria del plan por punto

El agente planificador copia esta plantilla y la rellena. Encabezado alineado a `writing-plans` + campos del programa CRUD.

````markdown
# CRUD Engine P0N — <Título concreto> Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** <resultado observable en una frase — cierra IDs …>

**Architecture:** <2–3 frases: enfoque, límites, flujo; cómo encaja con puntos 1..(N-1)>

**Tech Stack:** PHP 8.x del `composer.json`, harness `tests/run.php`, capas `Lebytek\Framework\…`

**Programa:** Remediación CRUD Engine · **Punto:** N/12 · **IDs:** <lista>

**Source audit:** `docs/audits/2026-08-07-auditoria-critica-crud-engine.md`  
**Estructura programa:** `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`  
**Modo:** normal | degradado — baseline incompleta

**Source audit PR:** #90 (o el mergeado equivalente)  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main`; rama de trabajo `feature/crud-p0N-<slug>` (creable desde main tras verificar `git ls-remote`)

## Baseline asumida (puntos 1..N-1)

| Punto | Plan | Estado verificado | Evidencia (SHA / PR / archivos) |
|------:|------|-------------------|----------------------------------|
| 1 | … | aplicado en árbol / ausente | … |
| … | … | … | … |

**Implicaciones para este plan:** <APIs nuevas, tests ya existentes, demos cambiados, rutas, etc.>

## Global Constraints

- Solo IDs de este punto como entregables.
- No editar `vendor/`; no negocio Portal en este repo.
- No debilitar RBAC, CSRF, soft-delete ni validación ya cerrada en puntos previos.
- Espejo `skeleton/` si se tocan rutas/config/assets públicos del módulo.
- Semver: declarar PATCH/MINOR según contrato consumidor; sincronizar trío `composer.json` / `config/app.php` / `skeleton/config/app.php` si el plan publica API.
- Idioma y capas: convenciones del paquete (`Presentation` / `Application` / `Domain` / `Infrastructure`).

## Requisitos → tareas (matriz)

| ID auditoría | Requisito | Owner | Tarea | Verificación |
|--------------|-----------|-------|-------|--------------|
| C… | … | Framework | Task K | `php tests/run.php …` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/…` | … |
| `tests/…` | … |

**Interfaces producidas / consumidas:** <firmas exactas>

---

### Task 1: <resultado concreto>

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p0N-<slug>`  
**Depends on:** None | Task K  
**Files:**
- Create: `…`
- Modify: `…`
- Test: `…`
**Interfaces:**
- Consumes: `…`
- Produces: `…`

- [ ] **Step 1: Escribir el test que falla** — código concreto.
- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `…` / Expected: `…`
- [ ] **Step 3: Implementar el cambio mínimo** — pseudodiff / firmas.
- [ ] **Step 4: Verificación enfocada** — Run: `…` / Expected: `PASS`
- [ ] **Step 5: Regresión relevante** — Run: `…` / Expected: `…`
- [ ] **Step 6: Commit** — archivos + mensaje sugerido.

### Task 2: …

---

## Criterios de aceptación (punto N)

- [ ] <trazable a ID …>
- [ ] Tests nuevos del plan en verde
- [ ] No regresión de puntos 1..(N-1) (comandos listados)
- [ ] Skeleton espejo si aplica
- [ ] Docs tocadas solo si el punto lo exige (punto 12 concentra docs FPS)

## Fuera de alcance

- IDs de otros puntos del programa
- Portal / WhatsApi / legacy `feature/backoffice-api-integration`
- …

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| … | … |

**Rollback:** revertir PR del punto N; puntos 1..(N-1) permanecen.

## Evidencia que debe recopilar el ejecutor

- Salida de comandos de test del plan
- PR URL + SHA final
- Contraste breve: antes/después para cada ID

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | … |
| Framework `origin/main` referencia al planificar | … |
| Tareas completadas / totales | 0 / K |
| Siguiente tarea ejecutable | Task 1 |
| Prerrequisitos | Puntos 1..(N-1) en árbol |
| Bloqueos | … |
| Estado | Pendiente de implementación |
````

---

## 6. Dependencias y modos

| Situación | Acción del planificador |
|-----------|-------------------------|
| Puntos 1..(N-1) verificados en árbol | `Modo: normal` |
| Falta un prerrequisito y el operador no autorizó avanzar | **STOP** — no escribir plan N |
| Operador autoriza planificar sobre main sin N-1 | `Modo: degradado — baseline incompleta` + riesgos; el ejecutor puede quedar bloqueado |
| Plan N ya existe incompleto | Actualizar in-place (idempotente); no `-v2` |
| Plan N completo y mergeado | No reescribir salvo replan explícito post-regresión |

**Dependencia dura implícita (además del orden numérico):**

- Punto **4** (CAS) asume que el punto **2** ya impide escribir `states.column` por form/handler demo; si no, el CAS es incompleto frente a C3.
- Punto **6** (router RBAC) **no** sustituye punto **1** (C5 Reportes).
- Punto **11** no reemplaza tests de seguridad de los puntos **1–4**.

---

## 7. Tracking del programa

Mantener esta tabla actualizada en commits de docs cuando un punto cambia de estado (el planificador al crear; el ejecutor al cerrar).

| Punto | Plan | Estado | PR plan | PR impl | Notas |
|------:|------|--------|---------|---------|-------|
| 1 | `…-p01-authz-multi-canal.md` | no iniciado | — | — | |
| 2 | `…-p02-states-form-options.md` | no iniciado | — | — | |
| 3 | `…-p03-uploads-hardening.md` | no iniciado | — | — | |
| 4 | `…-p04-cas-bulk-equality.md` | no iniciado | — | — | |
| 5 | `…-p05-aggregation-breaker.md` | no iniciado | — | — | |
| 6 | `…-p06-router-rbac-vertical.md` | no iniciado | — | — | |
| 7 | `…-p07-validation-prefixes.md` | no iniciado | — | — | |
| 8 | `…-p08-handlers-di-interfaces.md` | no iniciado | — | — | |
| 9 | `…-p09-bitacora-ledger-bigint.md` | no iniciado | — | — | |
| 10 | `…-p10-listado-perf-schema.md` | no iniciado | — | — | |
| 11 | `…-p11-puertos-tests-orquestacion.md` | no iniciado | — | — | |
| 12 | `…-p12-higiene-docs-dx.md` | no iniciado | — | — | |

Estados admitidos: `no iniciado` · `plan listo` · `en implementación` · `bloqueado` · `completo en main`.

---

## 8. Relación con automatizaciones diarias

Este programa es **manual / under agent** sobre la auditoría #90, no sustituye AUTOMATION-00…08.  
Si un plan diario AUTOMATION-04 solapa (p. ej. M3 router = punto 6), el plan del programa **debe** reconciliar ese plan activo: no duplicar trabajo; referenciar o archivar el solape en «Baseline / Fuera de alcance».

Plan histórico solapado conocido: `docs/superpowers/plans/2026-08-06-audit-crud-rbac-router.md` → absorber o declarar superseded al escribir **punto 6**.

---

## 9. Definition of Done del programa

El programa termina cuando los 12 planes están `completo en main`, la tabla §7 refleja ese estado, y la auditoría puede marcarse en una corrida posterior como remediación aplicada (sin reescribir hallazgos históricos: se registra resolución por ID).

---

## 10. Prohibiciones globales

- Estimar calendario (días/semanas) en los planes.
- Placeholders (`TBD`, «implementar según corresponda»).
- Implementar en el mismo commit que el plan (roles separados: planificador ≠ ejecutor).
- Planificar merge de `feature/backoffice-api-integration` → `main`.
- Editar `vendor/` o meter Marketing/leads en este repo.
