# Design: Transiciones CRUD con CAS + integridad bulk (CRUD-C4) y aislamiento de sesión en harness (M11)

**Fecha:** 2026-08-11  
**Repo spec:** `Parzival2103/Lebytek_Framework` (package source `lebytek/framework`)  
**Estado:** diseño — sin implementación en esta corrida  
**Modo:** normal (fuente Nivel A)

**Auditoría fuente:** `docs/audits/2026-08-11-auditoria-tecnica-diaria.md` (PR #115 `docs(audit): auditoría técnica diaria 2026-08-11`, head `c4c57e15a03fa007a10bd2c0b0eabf6e6531a18e`)  
**Hallazgo principal:** **CRUD-C4** — único crítico de código abierto en tip: `CrudTransitionService::apply` persiste transición con `UPDATE … WHERE pk = ?` sin predicado `AND {state_column} = :from`, dejando TOCTOU entre lectura en memoria y escritura. Carry-forward desde auditoría crítica `#90` / punto **4/12** del programa CRUD.  
**Hallazgo secundario (nuevo):** **M11** — suite monolítica `php tests/run.php` deja sesión autenticada residual tras tests Auth; `ApiHealthPublicDispatchTest` falla con falso negativo (302→200 en `/api/ping`).

**Contexto de cierre reciente (no reimplementar):** REL-C1, CRUD-C6, INV-E1/E2, M3, M4 **resueltos** en tip @ `c822196` con tags `v1.2.7`…`v1.2.10`. AuthZ C1/C2/C5 y states C3 consumibles por tag. Portal bump lock ≥ `v1.2.10` **no verificado** (M6).

**Specs/planes relacionados (no duplicar):**

- Uploads C6 (**resuelto** `#111` / `v1.2.8`): `docs/superpowers/specs/2026-08-09-audit-crud-uploads-hardening-design.md`
- Release semver REL-C1 (**resuelto** `#110` / tags publicados): `docs/superpowers/specs/2026-08-08-audit-release-semver-tag-design.md`
- AuthZ multi-canal (**resuelto** `#95`): plan `docs/superpowers/plans/2026-08-07-crud-p01-authz-multi-canal.md`
- States form (**resuelto** `#100`): plan `docs/superpowers/plans/2026-08-07-crud-p02-states-form-options.md`
- RBAC router M3 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-06-audit-crud-rbac-router-design.md`
- API health M4 (**resuelto** `#114` / `v1.2.10`): `docs/superpowers/specs/2026-08-05-audit-api-health-public-design.md`
- Auditoría crítica origen: `docs/audits/2026-08-07-auditoria-critica-crud-engine.md` § C4, G13, G1, G14 · programa § punto **4**
- Estructura programa: `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md` — plan esperado `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` (**no existe**; AUTOMATION-04)
- Frontera FPS: `docs/superpowers/BOUNDARY-framework-vs-portal-fps.md`

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | spec |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `c8221961e29a07e928a4a42a3a9e3ad88863f0a5` |
| SHA Portal inspeccionado | **No verificado** — `gh repo view Parzival2103/Lebytek_Portal` → GraphQL «Could not resolve to a Repository»; `gh api repos/Parzival2103/Lebytek_Portal/commits/main` → HTTP 404. Última evidencia operativa documentada: `a79d3ad` @ Portal `main` con `lebytek/framework` v1.1.0 (auditoría 2026-07-27, verificación SSH). |
| SHA WhatsApi inspeccionado | `f3f3ec79202b09fff947fa034e5beeb2b0aa12e3` @ `main` (sin cambio desde auditoría 2026-08-02) |
| Rama generada | `automation/spec-2026-08-11` |
| Timestamp UTC | trigger cron `2026-08-11T12:10:00Z` / corrida agente `2026-08-11T12:10:00Z` |
| Nivel de fuente | **A** — PR #115 abierto, título `docs(audit): auditoría técnica diaria 2026-08-11`, `baseRefName=main`, `mergeable=MERGEABLE`. Diff único: `docs/audits/2026-08-11-auditoria-tecnica-diaria.md`. Ancestry limpia; ningún commit legacy ancestro del head audit. |
| PR auditoría fuente | #115 — https://github.com/Parzival2103/Lebytek_Framework/pull/115 |
| headRefOid fuente | `c4c57e15a03fa007a10bd2c0b0eabf6e6531a18e` (rama audit; **no heredada**) |
| `<LEGACY_REF>` | `refs/tags/archive/backoffice-api-integration` @ `4789f953ef746d17bae2e6b50c85504782d306e3` — 53 commits exclusivos; ninguno ancestro de HEAD ni del head audit |
| Issues abiertos Framework | **0** (`gh issue list --repo Parzival2103/Lebytek_Framework --state open` → vacío) |
| Issues abiertos Portal | **No verificable** — mismo bloqueador gh 404 (M6) |
| PRs abiertos Framework (contexto) | **1** — PR #115 audit (este artefacto fuente) |
| CI tip `main` | **success** @ `c822196` (run `31456605262`) |

---

## Problema

La auditoría del 2026-08-11 confirma que, tras el cierre masivo de deuda (REL-C1, C6, INV, M3, M4), permanece **un crítico de integridad** y aparece **un medio nuevo de harness**:

### CRUD-C4 — Transiciones sin compare-and-swap (CAS)

**Evidencia verificada en tip `main` @ `c822196`:**

| Comprobación | Resultado |
|--------------|-----------|
| `CrudTransitionService::apply` L76–108 | Lee `$from` del `$record` en memoria; tras `authorize()`, llama `updateRecord` **sin** verificar que la fila sigue en `$from` |
| `GenericCrudRepository::updateRecord` L230–244 | SQL `UPDATE … SET … WHERE pk = ?` — sin `AND {state_column} = ?` ni chequeo de filas afectadas |
| `CrudTransitionServiceTest` | Cubre `authorize()` y bloqueo pre-DB en transiciones inválidas; **no** hay test de carrera / stale state |
| Programa CRUD punto 4 | IDs C4 + G13 + G1 + G14 agrupados; plan `crud-p04-cas-bulk-equality.md` **ausente** |

**Consecuencia:** dos peticiones concurrentes (p. ej. transición A→B y formulario que escribe estado, o doble clic en acción transition) pueden aplicar la segunda sobre un `$from` obsoleto, saltando guards de máquina de estados o pisando una transición intermedia.

### G13 — Race soft-delete en UPDATE (mismo lote punto 4)

`CrudDataService::update` L460–486 carga `$existing`, valida, luego `updateRecord` sin `deleted = 0` en el WHERE. Entre lectura y escritura un soft-delete concurrente puede dejar fila `deleted=1` actualizada.

### G1 — Bulk sin re-check `visible_when` / `enabled_when`

`CrudActionService::run` L119–122 revalida condiciones; `runBulk` L180–198 **no** llama `isVisibleFor` / `isEnabledFor` antes de `dispatch`.

### G14 — `equalityMatches` fail-open con columnas ausentes

`CrudActionDefinition::equalityMatches` L115–131 usa `$row[$column] ?? null`. Si la columna no está en el SELECT del listado bulk, `$actual = null` y `(string) null === (string) false === ""` → condición `enabled_when: false` puede evaluarse **true** (fail-open).

### M11 — Contaminación de sesión en harness (nuevo)

| Comprobación | Resultado |
|--------------|-----------|
| `tests/run.php` | Carga todos los `*Test.php` en un proceso, orden alfabético, **sin** reset de `$_SESSION` entre archivos |
| `AuthMiddleware` | Bloquea si `Session::has('auth_user')` — tests Auth que invocan `AuthService::login` dejan sesión |
| `tests/Kernel/ApiHealthPublicDispatchTest.php` L30–36, L55–67 | Esperan 302 / no-200 en `/api/ping` sin sesión — **fallan** en suite monolítica cuando Auth corrió antes |
| CI | Jobs aislados (`php tests/run.php Kernel`) → **success**; el fallo es DX local, no regresión M4 en producción |

**Clasificación:** C4/G13 = crítico/grave plataforma (integridad datos). G1/G14 = grave AuthZ/acciones. M11 = medio harness/DX. **Owner:** Framework para todos.

---

## Brainstorm y recomendación de diseño

### Contexto, propósito, restricciones y criterios de éxito

| Dimensión | Detalle |
|-----------|---------|
| Contexto | Punto **4/12** del programa CRUD; puntos 1–3 **cerrados** y tagueados hasta `v1.2.10`. Tip semver `1.2.10`; siguiente release será PATCH **`1.2.11`** post-implementación |
| Propósito | Cerrar TOCTOU en transiciones y updates; alinear bulk con `run()`; fail-closed en condiciones; harness monolítico honesto |
| Restriciones | Sin negocio Portal en `src/`; no editar `vendor/`; conservar contratos HTTP/JSON existentes; mensajes `ValidationException` en español; producción fuera de corrida desatendida; legacy tag solo como evidencia histórica |
| Criterios de éxito | Transición concurrente falla con error claro; bulk respeta visible/enabled; tests nuevos fallan pre-fix por motivo documentado; `php tests/run.php` monolítico verde (salvo MySQL ausente); tag `v1.2.11` consumible por Portal vía lock |

### Enfoques evaluados — CAS transiciones (C4)

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **`updateRecordIf` en repo:** `UPDATE … WHERE pk = ? AND state_col = ? [AND deleted = 0]`; si `rowCount() === 0` → `ValidationException` «El registro cambió de estado; recarga la página.» | Mínimo diff; reutilizable para G13; sin columna `version` nueva | **Recomendado** |
| **B** | Columna `row_version` / optimistic locking global en todas las tablas CRUD | Cierra más races | **Rechazado** — breaking schema masivo; fuera alcance C4 |
| **C** | Re-leer fila dentro de transacción SQL explícita (`BEGIN … SELECT FOR UPDATE`) | Fuerte consistencia | **Rechazado** — exige transacciones en repo genérico no usadas hoy; más invasivo |

### Enfoques evaluados — bulk + equality (G1, G14)

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **`runBulk` delega en `run()`** por id (o extrae bloque común) incluyendo visible/enabled; **`equalityMatches` fail-closed** si `!array_key_exists($column, $row)` | Duplica I/O en bulk aceptable (≤ MAX_BULK_IDS); comportamiento idéntico a fila | **Recomendado** |
| **B** | Solo parche G14 sin G1 | Menor diff | **Rechazado** — deja bulk ejecutando acciones «deshabilitadas» en UI |
| **C** | Deshabilitar bulk para acciones con `visible_when`/`enabled_when` | Simple | **Rechazado** — regresión funcional |

### Enfoques evaluados — M11 harness

| # | Enfoque | Trade-offs | Veredicto |
|---|---------|------------|-----------|
| **A** | **`microtest.php`:** tras cada test, `$_SESSION = []` (+ reiniciar flag Session si expuesto) | Protege todos los tests futuros | **Recomendado** |
| **B** | Solo tearDown en `ApiHealthPublicDispatchTest` | Parche local | **Rechazado** — próximo test Auth recontamina |
| **C** | Proceso por archivo en `run.php` | Aislamiento total | **Rechazado** — lento; rompe fixtures compartidos |

### Recomendación

Implementar **Enfoque A** en los tres frentes como release PATCH **`1.2.11`** desde tip post-merge del plan p04. Mensaje usuario final en conflicto CAS: español, accionable («recarga»), HTTP 422/redirect flash según patrón CRUD existente — **sin** nuevo endpoint público.

---

## Comportamiento esperado

### Plataforma Framework (`Lebytek_Framework` @ `main`)

1. **Transición (C4):** `CrudTransitionService::apply` persiste con predicado `WHERE pk = ? AND {state_column} = :expected_from`. Si 0 filas afectadas → `ValidationException` con mensaje de estado obsoleto; **no** escribe bitácora ni dispara `afterTransition`.
2. **Update genérico (G13):** `CrudDataService::update` (y soft paths que mutan filas activas) usan variante repo que incluye `deleted = 0` en WHERE; 0 filas → «El registro ya no está disponible» (o reutilizar mensaje existente de not-found).
3. **Bulk (G1):** Antes de `dispatch`, `runBulk` aplica las mismas comprobaciones que `run`: ownership, `isVisibleFor`, `isEnabledFor`. Transitions en bulk siguen por `transitionService->apply` (heredan CAS).
4. **Condiciones (G14):** `equalityMatches`: si `$conditions` no vacío y columna ausente en `$row` (`!array_key_exists`), retorna **false**.
5. **Repo (contrato interno):** Nuevo método documentado p. ej. `updateRecordWhere(string $table, string $pk, int $id, array $payload, array $whereEquals): int` retorna filas afectadas — **no** es API HTTP pública.
6. **Harness (M11):** Tras cada test en `microtest.php`, limpiar `$_SESSION`; suite monolítica `php tests/run.php` reporta mismos PASS/FAIL que jobs CI aislados para Kernel/Auth (salvo dependencias MySQL).

### Consumidor Portal (`Lebytek_Portal` @ `main`) — **no verificado**

1. Tras tag Framework **`v1.2.11`**, bump `composer.lock` desde staging (operador).
2. **Sin cambio JSON** esperado para C4/G1/G14 — comportamiento server-side.
3. UX: si usuario tiene pestaña stale y dispara transición, ve mensaje flash de recarga — igual patrón que validaciones CRUD actuales.
4. **Staging:** smoke transiciones en recursos `dom_*` con state machine (p. ej. leads/pedidos si existen).
5. **Producción:** fuera de alcance automation — operador valida tras staging.

### Contratos públicos ausentes (no asumir)

- No existe API REST de «optimistic lock» expuesta al cliente — el CAS es interno al POST de acción/transición CRUD existente.
- No existe header `If-Match` / ETag en formularios admin — diseño usa predicado SQL, no versioning HTTP.
- Legacy `archive/backoffice-api-integration` @ `4789f95` (**histórico**) puede tener reglas distintas; **no** es base de implementación ni producción.

---

## Alcance

| ID | Entregable Framework |
|----|----------------------|
| C4 | CAS en `CrudTransitionService::apply` vía repo con predicado de estado |
| G13 | WHERE `deleted = 0` en updates mutantes (`CrudDataService::update` mínimo; evaluar delete/restore si comparten path) |
| G1 | Re-check `visible_when` / `enabled_when` en `CrudActionService::runBulk` |
| G14 | Fail-closed en `CrudActionDefinition::equalityMatches` |
| C4+ | Tests concurrencia / stale state (Crud State + integración ligera) |
| G1+ | Tests bulk con condiciones y columnas ausentes |
| M11 | Reset sesión post-test en harness |
| Doc | Actualizar `docs/modules/crud/modulo-crud-engine.md` § transiciones/concurrencia y § acciones bulk |
| Plan | `docs/superpowers/plans/2026-08-07-crud-p04-cas-bulk-equality.md` (AUTOMATION-04) |

**Semver / release:** PATCH **`1.2.11`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php`; tag Git **`v1.2.11`** desde tip post-merge. **Prerrequisito:** tip actual **`1.2.10`** / tag `v1.2.10` ya publicado (REL-C1 + lotes intermedios cerrados).

---

## No-alcance

- Punto 5 aggregation breaker (G12).
- Punto 6+ router-rbac-vertical (M3/G4 **ya resuelto** en `v1.2.10`).
- Columna `version` global / migraciones schema consumidor.
- Cambios en JSON CRUD Portal (`dom_*`) salvo checklist operativo.
- Merge PR audit #115 (AUTOMATION-03).
- Operaciones producción: deploy VPS, bump lock Portal prod, SSH, `.env`.
- Reabrir REL-C1, C6, INV, M3, M4 ya cerrados.
- Legacy `feature/backoffice-api-integration` → `main`.

---

## Ownership map

| Requisito | Repositorio | Rama base | Capa / ruta |
|-----------|-------------|-----------|-------------|
| CAS transiciones | `Lebytek_Framework` | `main` | `src/Application/Services/CrudTransitionService.php` |
| Repo predicado | `Lebytek_Framework` | `main` | `src/Infrastructure/Repositories/GenericCrudRepository.php` |
| Update soft-delete race | `Lebytek_Framework` | `main` | `src/Application/Services/CrudDataService.php` |
| Bulk parity | `Lebytek_Framework` | `main` | `src/Application/Services/CrudActionService.php` |
| Equality fail-closed | `Lebytek_Framework` | `main` | `src/Domain/Entities/Crud/CrudActionDefinition.php` |
| Tests C4/G1/G14 | `Lebytek_Framework` | `main` | `tests/Crud/State/`, `tests/Crud/Action/` |
| Harness M11 | `Lebytek_Framework` | `main` | `tests/lib/microtest.php`, `tests/Kernel/ApiHealthPublicDispatchTest.php` |
| Docs módulo | `Lebytek_Framework` | `main` | `docs/modules/crud/modulo-crud-engine.md` |
| Tag `v1.2.11` | `Lebytek_Framework` | `main` | release ops post-merge plan |
| Bump lock / QA transiciones | `Lebytek_Portal` | `main` | post-tag (**no verificado**) |
| Staging QA | `Lebytek_Portal` | staging | operador humano |

---

## Dependencias y compatibilidad

### Orden de release

```text
main @ 1.2.10 (C1–C3, C6, M3, M4, INV ya tagueados)
  → implementar punto 4 (C4+G13+G1+G14) + M11 harness
  → tag v1.2.11
  → consumidores bump lock (Portal/CRM — no verificado)
```

### Compatibilidad hacia atrás

| Escenario | Impacto |
|-----------|---------|
| Transición sin concurrencia | Sin cambio observable — 1 fila afectada |
| Transición concurrente que hoy «gana» incorrectamente | **Corrección:** segunda petición falla con ValidationException |
| Bulk sobre filas visibles en UI con columnas completas | Sin cambio |
| Bulk sobre filas con condiciones y columnas ausentes en list SELECT | **Corrección:** acción rechazada (antes fail-open G14) |
| Update concurrente tras soft-delete | **Corrección:** update rechazado |
| Portal en lock `<1.2.11` | No recibe fix; no es regresión del tag viejo |
| PHP | `>=8.2` sin cambio |

### Migración segura

**Base nueva (skeleton / greenfield):** sin migración datos; comportamiento estricto desde primer deploy con `>=1.2.11`.

**Base Portal existente (no verificada):**

1. Staging: `composer update lebytek/framework` a `^1.2.11`.
2. Ejecutar `php tests/run.php Crud` en app consumidora si existe harness, o smoke manual de transición en recurso con states.
3. Comunicar a operadores: mensaje «recarga la página» es esperado en conflictos reales — no es bug.
4. Producción: ventana operador — **no** automatizar en esta corrida.

---

## Riesgos

| Riesgo | Severidad | Mitigación en diseño |
|--------|-----------|----------------------|
| Usuarios legítimos ven más errores «recarga» bajo concurrencia real | Media | Mensaje claro; idempotencia de transiciones inválidas |
| Bulk más lento (re-check + posible re-fetch) | Baja | MAX_BULK_IDS existente acota |
| G13 parcial si otros paths UPDATE omiten `deleted=0` | Media | Inventario grep `updateRecord` en implementación; test regresión |
| Portal sin bump sigue vulnerable C4 | Alta (consumo) | Documentar dependencia tag; M6 bloquea verificación lock |
| M11 fix enmascara tests que **dependían** de sesión cruzada | Baja | Revisar tests Auth que asuman estado previo — usar setup explícito por test |
| Falso positivo CAS si collation/trim en columna estado | Baja | Comparar como string igual que hoy `$from` |
| Portal SHA desconocido (M6) | Media | Marcar requisitos Portal **no verificados** |

---

## Rollback

| Ámbito | Procedimiento |
|--------|---------------|
| Framework código | Revert merge PR implementación p04; no publicar `v1.2.11` o publicar PATCH revert `1.2.12` si tag ya salió |
| Consumidor | Mantener `composer.lock` en `1.2.10` o anterior |
| Harness M11 | Revert independiente — bajo riesgo; no afecta producción |
| Producción | Solo operador — fuera de automation |

---

## Criterios de aceptación

### Funcionales

- [ ] **AC-C4:** Dos transiciones concurrentes simuladas (test con doble apply / mock repo) — solo una persiste; la segunda lanza `ValidationException`.
- [ ] **AC-G13:** Update sobre fila soft-deleted (mock) afecta 0 filas y falla sin mutar.
- [ ] **AC-G1:** Bulk no ejecuta acción cuando `enabled_when` no cumple en registro cargado.
- [ ] **AC-G14:** `equalityMatches` con columna ausente y condición no vacía retorna false.
- [ ] **AC-M11:** `php tests/run.php` (sin MySQL) — 0 fails en tests Kernel Auth ping/health por sesión residual; contador alineado con suma de suites aisladas modulo Integrations/MySQL.

### Tests que deben **fallar antes** de implementar (TDD)

| Test (nuevo) | Fallo esperado pre-fix | Motivo |
|--------------|------------------------|--------|
| `CrudTransitionService::apply rechaza cuando estado en DB difiere de $record` | No existe — o PASS si mock no simula race | Sin CAS (C4) |
| `GenericCrudRepository::updateRecordWhere retorna 0 sin mutar` | Método ausente | Repo sin predicado |
| `CrudActionService::runBulk respeta enabled_when` | Bulk ejecuta acción bloqueada en run() | G1 |
| `equalityMatches fail-closed si columna ausente` | Retorna true con `enabled_when: false` y row sin clave | G14 |
| `Suite monolítica: /api/ping sin sesión tras Auth tests` | FAIL expected 302 got 200 | M11 |

Cada test debe **existir** en plan p04 y ejecutarse con `php tests/run.php Crud` / `Kernel` / suite completa — ningún comando debe reportar «0 tests».

### Tests post-implementación

- `php tests/run.php Crud` — 0 failed.
- `php tests/run.php Kernel` — 0 failed (incl. ApiHealthPublicDispatchTest).
- `php tests/run.php` monolítico — 0 failed excl. Integrations MySQL si entorno sin servidor (documentado).
- `php tests/run.php SkeletonPurity` — 13/13 PASS.
- CI `platform-fast-gates` verde en tip post-merge.

### Release

- [ ] Trío semver **`1.2.11`** sincronizado; tag **`v1.2.11`** tree ≡ tip merge.
- [ ] `docs/release/v1.2.11.md` (notas mínimas C4 + harness) — hygiene REL-C1.

---

## Requisitos marcados como no verificados

| Requisito | Motivo |
|-----------|--------|
| SHA Portal `main` actual | gh 404 / repo privado (M6) |
| `composer.lock` Portal ≥ `v1.2.10` o futuro `1.2.11` | Depende M6 |
| Issues abiertos Portal | gh 404 |
| JSON CRUD `dom_*` con state machines en Portal | Sin acceso repo |
| Smoke transiciones staging/prod Portal | Operación humana; fuera automation |
| WhatsApp API cambios | Sin relevancia C4; SHA documentado por completitud |

---

*Design-only. Ningún archivo de código, config, rutas, migraciones, scripts ni planes fue modificado en esta corrida salvo este spec.*
