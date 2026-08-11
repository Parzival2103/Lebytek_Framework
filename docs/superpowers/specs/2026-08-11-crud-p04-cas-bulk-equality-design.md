# Design: CAS transitions + bulk equality (CRUD punto 4 / C4+G13+G1+G14)

**Fecha:** 2026-08-11  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (brainstorming colaborativo → spec)

**Auditoría fuente:** `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` (§ C4, G13, G1, G14; prioridad orden **4**)  
**Carry-forward:** auditorías diarias 2026-08-08…2026-08-11 mantienen C4 abierto; tip actual `1.2.10` (M3/M4 cerrados; C6 cerrado en `v1.2.8`).

**Specs/planes relacionados (no duplicar):**

- Estructura programa: `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md` — punto **4** → plan esperado `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` (**aún no escrito**; siguiente paso tras aprobación de este spec)
- p01 AuthZ (C1+C2+C5): plan mergeado `#95`
- p02 states (C3+G15+G6): plan mergeado `#100`
- p03 uploads (C6): spec `2026-08-09-audit-crud-uploads-hardening-design.md` · merge `#111` · tag `v1.2.8`
- M3 router RBAC / M4 health: `#114` · tag `v1.2.10` (baseline tip; no sustituyen CAS)
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec (design) |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `23e1dd219d5b2383ac6cbb02ca6681ad01638932` |
| Semver tip | `1.2.10` (trío sincronizado; tag `v1.2.10` publicado) |
| Rama generada | `cursor/crud-p04-cas-bulk-spec-c292` |
| Timestamp UTC | 2026-08-11 (brainstorming → design) |
| Portal | **No verificado** (gh 404 / M6) — fuera de alcance de implementación |
| Issues abiertos Framework | 0 al inicio de la corrida de diseño |

---

## Problema

El punto **4** del programa CRUD agrupa una familia de mutaciones concurrentes / gating fallido:

| ID | Síntoma verificado en tip `23e1dd2` | Impacto |
|----|-------------------------------------|---------|
| **C4** | `CrudTransitionService::apply` llama `updateRecord` solo con `WHERE pk = ?` — sin `AND states.column = :from` ni `deleted = 0` | Doble transición / carrera form vs transition |
| **G13** | `CrudDataService` comprueba `deleted` en memoria; SQL de update/soft-delete no exige `deleted = 0` | Mutación / bitácora sobre fila ya soft-borrada |
| **G1** | `CrudActionService::run()` revalida `visible_when`/`enabled_when`; `runBulk()` no | Acciones masivas sobre filas que la UI no debería habilitar |
| **G14** | `CrudActionDefinition::equalityMatches` usa `(string) $actual === (string) $expected` → `(string) null === ''`, `(string) false === ''`; list SELECT omite columnas fuera de `list.columns` | Condiciones fail-open; bulk/UI ven acciones “habilitadas” |

**Baseline cumplida:** puntos 1–3 del programa cerrados en tip (AuthZ, states, uploads). M3 añade RBAC de router pero **no** CAS.

**Owner:** Framework (`src/`, tests, docs plataforma). Consumidores Portal/CRM solo bump de lock tras tag.

---

## Brainstorm y decisión de diseño

### Decisiones de producto (aprobadas)

| Decisión | Elección |
|----------|----------|
| Alcance del spec/plan | **A** — lote completo C4 + G13 + G1 + G14 |
| Conflicto CAS (0 filas) | **B** — reintento automático **1 vez** (re-leer + re-autorizar / re-write) y luego conflicto accionable |
| Persistencia | **A** — API general de predicados en `GenericCrudRepository` (no solo transitions; no SQL ad-hoc solo en Application) |
| Enfoque técnico | **1** — `updateWhere` / `expected` + helpers CAS (rechazados: columna `version` schema-wide; SQL ad-hoc sin contrato repo) |

### Enfoques evaluados

| # | Enfoque | Veredicto |
|---|---------|-----------|
| **1** | Predicados `expected` en repo; transitions CAS `pk+deleted+status=:from`; update/delete con `deleted=0`; bulk parity + equality fail-closed + SELECT de columnas de condiciones | **Elegido** |
| **2** | Optimistic lock `version` / `updated_at` en todas las tablas CRUD | Rechazado — migración schema transversal fuera del lote 4 |
| **3** | SELECT FOR UPDATE / SQL solo en Application | Rechazado — deja G13 inconsistente; peor reutilización |

### Criterios de éxito

1. Transition no persiste si el estado en DB ≠ `:from` (tras reintento ×1).
2. Update/soft-delete no mutan filas con `deleted = 1`.
3. `runBulk` aplica las mismas guards `isVisibleFor` / `isEnabledFor` que `run`.
4. `equalityMatches` fail-closed; list SELECT incluye columnas referenciadas por condiciones.
5. Mensaje de conflicto único y accionable; tests TDD rojos→verdes; patch **`1.2.11`**.

---

## Comportamiento esperado

### Infra — `GenericCrudRepository`

1. Extender `updateRecord` (o añadir `updateRecordWhere`) con parámetro `array $expected = []` mapeado a `AND \`col\` = ?` (identificadores quotados; valores bound).
2. Retorno: `int` rowCount (como hoy `execute`).
3. Callers del motor de mutación **deben** pasar al menos `['deleted' => 0]` salvo writes que intencionalmente operen sobre filas borradas (ninguno en este lote).
4. Firma legacy sin `expected` permanece segura solo si los callers migran; en este lote **todos** los call sites Framework de mutación CRUD pasan predicados. No se exige cambio Portal (no llama el repo genérico directo).

### Application — transitions (C4)

`CrudTransitionService::apply`:

1. Resolver `$from` / `$to` / `$column` como hoy; `authorize(...)` **antes** de cualquier write.
2. `updateRecord(..., payload, expected: ['deleted' => 0, $column => $from])`.
3. Si `rowCount === 1`: bitácora `crud.transition` + `afterTransition` (como hoy; `beforeTransition` sigue antes del write).
4. Si `rowCount === 0`:
   - Re-`find` por pk (vía data service o repo).
   - Si ausente o `deleted === 1` → conflicto.
   - Si presente: recalcular `$from` desde fila fresca; `authorize` otra vez; **un** segundo `updateRecord` CAS.
   - Si el segundo también es 0 → conflicto.
5. Mensaje de conflicto (español, fijo):  
   `El registro cambió; recarga e inténtalo de nuevo.`  
   Tipo: `ValidationException` (canal HTML/JSON existente).

### Application — update / soft-delete (G13)

`CrudDataService` (y cualquier path Framework que use `updateRecord` para update/soft-delete):

1. Write con `expected['deleted'] = 0`.
2. Misma política de reintento ×1: re-find + un segundo write condicionado; si falla → mismo mensaje de conflicto.
3. Soft-delete payload (`deleted=1`, timestamps) **solo** aplica si el predicado `deleted=0` matcheó.

### Application — bulk (G1)

`CrudActionService::runBulk`, por cada id, **después** de `find` + ownership y **antes** de `dispatch`:

```text
if (!$action->isVisibleFor($record) || !$action->isEnabledFor($record)) {
    throw new ValidationException('La acción no está disponible para este registro.');
}
```

(mismo texto / semántica que `run()`). Bulk sigue best-effort (`ok` / `fail` / `errors[]`).

### Domain — equality (G14)

`CrudActionDefinition::equalityMatches`:

1. Si la columna **no existe** en `$row` (`array_key_exists` false) → **no match** (fail-closed).
2. No coercionar `null` ni `false` a `''` vía `(string)`.
3. Comparación:
   - `expected` array → pertenencia con igualdad escalar tipada/normalizada (bool/int/string) sin el bug `(string)null===''`.
   - escalar → igualdad con la misma normalización.
4. Mapa vacío de condiciones → `true` (sin cambio).

### List SELECT (soporte G14)

Al construir columnas de listado en `CrudDataService`, unir:

- columnas de `list.columns`
- primary key
- `deleted`
- **todas** las keys de `visible_when` y `enabled_when` de las actions del recurso

Así el fail-closed no oculta acciones legítimas por columnas omitidas del SELECT.

### Consumidores (Portal / CRM)

- Tras tag `v1.2.11`: `composer update lebytek/framework`.
- Sin cambios JSON obligatorios para este lote (a diferencia de C6 allowlist).
- Operadores pueden ver más 422/conflictos bajo carrera real — comportamiento correcto.
- **No** activar invoicing como parte de este trabajo.

---

## Arquitectura de componentes

```text
CrudActionService::run / runBulk
  └─ visible/enabled (equalityMatches fail-closed)
  └─ transition? → CrudTransitionService::apply
        └─ authorize → updateRecord(expected: deleted=0, status=:from)
        └─ rowCount 0 → re-find → authorize → retry once → conflict
  └─ handler? → dispatch (writes via CrudDataService / repo con deleted=0)

CrudDataService::update / delete
  └─ updateRecord(expected: deleted=0) + retry-once policy
```

**Capas:** Domain (equality) ← Application (orquestación CAS/bulk) ← Infrastructure (SQL predicados). Presentation sin lógica nueva (reusa `ValidationException`).

---

## Error handling

| Caso | Resultado |
|------|-----------|
| CAS ok (1 fila) | 200 / flujo normal + bitácora |
| CAS 0 → retry ok | Igual que ok (una bitácora) |
| CAS 0 → retry 0 / fila borrada | `ValidationException` mensaje conflicto |
| Bulk ítem no visible/enabled | ítem en `errors[]`; resto continúa |
| Equality columna ausente | condición false → acción no visible/enabled |

No se distingue en UX “borrado concurrente” vs “estado cambiado”.

---

## Testing (TDD)

Filtros orientativos (`php tests/run.php …`):

| Suite | Cubre |
|-------|--------|
| Repo / Kernel around `GenericCrudRepository` update expected | rowCount 1 vs 0 |
| `CrudTransitionService` (o suite Crud existente) | happy CAS; carrera → retry → conflict; bitácora solo tras éxito |
| `CrudDataService` update/delete | no muta `deleted=1` |
| `CrudActionDefinition` / unit equality | null≠''; ausente→false; bool |
| `CrudActionService` bulk | parity con `run` en visible/enabled |
| Docs o unit list columns | SELECT incluye keys de condiciones |

Regresión: `php tests/run.php` suites Crud/Kernel relevantes + `PlatformVersionSemver` tras bump.

---

## Docs y release

- Actualizar `docs/modules/crud/modulo-crud-engine.md` (§ states/transitions + actions/bulk).
- Patch semver **`1.2.11`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php`.
- `docs/release/v1.2.11.md`.
- Tag anotado `v1.2.11` post-merge (REL-C1 chicken-egg: publicar tag cuando Docs gate lo exija).

---

## No-alcance

- Columna `version` / migraciones schema transversales.
- Puntos programa 5+ (G12 aggregation, etc.).
- Portal business / `dom_*` / Marketing.
- Activar Facturapi / invoicing.
- Cambiar best-effort de bulk a all-or-nothing.
- Editar `vendor/` en consumidores.

---

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Call site olvidado sin `expected` | Grep + tests; migrar todos los `updateRecord` Framework del motor |
| Fail-closed oculta acciones si faltan columnas en SELECT | Unión explícita de keys de condiciones en list SELECT + test |
| Reintento doble-write bajo carga | Máximo 1 retry; segundo fallo → conflicto; sin loop |
| Mensaje genérico confunde ops | Doc + release notes citan CAS |

**Rollback:** revertir PR `1.2.11`; consumidores restauran lock previo.

---

## Criterios de aceptación

- [ ] **AC-C4:** Transition con estado DB ≠ `:from` no actualiza; tras retry×1 lanza conflicto.
- [ ] **AC-G13:** Update/soft-delete con `deleted=1` no mutan (rowCount 0 / conflicto).
- [ ] **AC-G1:** `runBulk` rechaza ítems que fallan `visible_when`/`enabled_when` (mismo criterio que `run`).
- [ ] **AC-G14:** `equalityMatches` fail-closed; list SELECT incluye columnas de condiciones.
- [ ] **AC-UX:** Mensaje conflicto fijo en español; bulk best-effort preservado.
- [ ] **AC-REL:** Semver `1.2.11` + notas + tag; sin Marketing/Portal en `src/`.

---

## Siguiente paso

Tras aprobación de este archivo: skill **writing-plans** →  
`docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` (slug del programa; fecha de prefijo estable `2026-08-07` según estructura §2).
