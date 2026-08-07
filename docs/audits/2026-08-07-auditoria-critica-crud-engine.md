# Auditoría técnica / crítica — CRUD Engine

**Alcance:** módulo CRUD Engine del paquete `lebytek/framework` (`src/`, configs, vistas, assets, schema demo, tests, docs canónicas, adaptadores Reportes/Calendario).  
**Tipo:** crítica estructural + inventario de hallazgos verificables (**tres pasadas**).  
**No incluye:** implementación, fix, ni plan ejecutable tipo AUTOMATION-04.

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `8e6ed487e8d40fd59100d1a5541f6a0d05929923` |
| Rama generada | `cursor/audit-critica-crud-engine-3644` |
| Timestamp UTC (P1–P2) | `2026-08-07T04:18:27Z` |
| Timestamp UTC (P3 + prioridad confirmada) | `2026-08-07T04:25:00Z` |
| Pasadas | P1 arquitectura/authZ · P2 vistas/transitions/validación/schema · P3 reportes/uploads/aggregations/actions/demos/módulo |

---

## Evidencia de preflight

```console
$ git fetch origin main
$ git rev-parse --verify origin/main
8e6ed487e8d40fd59100d1a5541f6a0d05929923

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
4789f953ef746d17bae2e6b50c85504782d306e3

$ git rev-list --count origin/main..refs/tags/archive/backoffice-api-integration
53
LEGACY_ANCESTOR_CHECK=PASS

$ git status --porcelain   # antes de escribir / tras cada commit de artefacto
(vacío salvo el propio archivo de auditoría)
```

---

## Resumen ejecutivo

El CRUD Engine es un **framework-dentro-del-framework**: ~4.7k LOC entre Application, Domain, repo genérico, controller, vistas y assets. Vendió «tabla + JSON = CRUD»; el coste real es un esquema mental de ~10 bloques JSON, whitelist de handlers, 4 permisos, menú, schema `dom_*` y validación online contra `information_schema`.

Endureció lo ruidoso post-2026-04 (whitelist, `security.mode`, validación backend, owner scope). Tras tres pasadas el patrón dominante es **autorización y mutación asimétricas**: listado ≠ show/acción; UI ≠ servidor; transición ≠ formulario/handler; CRUD ≠ Reportes; circuit breaker ≠ “apagado seguro”.

**Conteo consolidado (P1+P2+P3):** 6 críticos · 16 graves · 20 medios · 4 bajos (**46**).  
**Owner:** Framework. Ningún hallazgo exige cambio de negocio Portal.

**Lo que cambió con P3:** aparecen dos críticos nuevos (exfil vía Reportes sin `{resource}.ver`; uploads sin allowlist + path configurable) y se **eleva** C3 (demo toggle oficial bypasea la state machine). La prioridad de remediación se reordena: AuthZ multi-canal primero, luego integridad de estados/uploads, luego concurrencia/bulk, luego validación/config, y deuda estructural al final.

---

## Cobertura de revisión

### Pasada 1

Arquitectura de capas; god services; onion Domain→Application; `CrudActionService` run/runBulk; `CrudScopeResolver`; registry sin DI; hooks muertos; tests unitarios vs integración; DX.

### Pasada 2

Vistas CRUD (XSS/CSRF/CDN); assets + mirror skeleton; rutas; transitions TOCTOU; field validation (select/`datetime-local`); `exists` vs soft-delete; prefijos; docs `app/`; ownership test; bitácora INT/BIGINT; uploads ledger; Calendar DI.

### Pasada 3 (esta corrida)

`CrudReporteDataSource` AuthZ; uploads/`UploadValidator`/`FileUploadService`; aggregations `enabled=false`; race soft-delete en UPDATE; `equalityMatches` fail-open; demo handlers (toggle SM, WhatsApp); módulo opcional vs rutas siempre on; interfaces de action/guard en validator; orderBy holes; bitácora PII/IP; config loader cache/`listResources`; virtual+searchable; returnUrl calendario; CSRF GET (limpio); skeleton drift (limpio).

---

## Catálogo estructurado de hallazgos

Leyenda: **C** crítico · **G** grave · **M** medio · **B** bajo.  
Columna **P** = pasada. **↑** = severidad/blast radius elevada por evidencia posterior.

| ID | Sev | P | Título corto | Área |
|----|-----|---|--------------|------|
| C1 | C | 1 | IDOR con `list.scope_handler` custom | AuthZ / scope |
| C2 | C | 1 | Acción sin `permission` salta RBAC en servidor | AuthZ / actions |
| C3 | C↑ | 2 | Columna de states editable por formulario (+ demo toggle P3) | State machine |
| C4 | C | 2 | Transitions sin CAS / TOCTOU | Concurrencia |
| C5 | C | 3 | `CrudReporteDataSource` no exige `{resource}.ver` | AuthZ / Reportes |
| C6 | C | 3 | Uploads sin allowlist obligatoria + `public_path` sin normalizar | Uploads |
| G1 | G↑ | 1 | Bulk no revalida `visible_when` / `enabled_when` | Actions |
| G2 | G | 1 | God services + acoplamiento a repo concreto | Arquitectura |
| G3 | G | 1 | Onion rota (Domain → Application Contexts) | Arquitectura |
| G4 | G | 1 | Sin `RbacMiddleware` en router CRUD | AuthZ / router |
| G5 | G | 1 | Handlers `new $class()` sin DI | Extensibilidad |
| G6 | G | 2 | Opciones `select`/`relation` no revalidadas en servidor | Validación |
| G7 | G | 2 | `exists` FK ignora soft-delete | Integridad |
| G8 | G | 2 | Tipo `datetime-local` sin reglas tipadas | Validación |
| G9 | G | 2 | `BLOCKED_PREFIXES` incompleto vs convenciones | Seguridad config |
| G10 | G | 2 | AuthZ después de `load()` + coste `information_schema` | AuthZ / perf |
| G11 | G | 2 | DataTables/jQuery CDN sin SRI | Supply-chain |
| G12 | G | 3 | `list.aggregation.enabled=false` apaga el breaker, no las agregaciones | Perf / DoS |
| G13 | G | 3 | Race soft-delete: assert luego `UPDATE` sin `deleted=0` | Concurrencia |
| G14 | G | 3 | `equalityMatches` fail-open con `false`/`""` y columnas ausentes | Actions |
| G15 | G | 3 | Demo `toggle` escribe `status` fuera de `CrudTransitionService` | State machine |
| G16 | G | 3 | Módulo `crud-engine` opcional; rutas/menú siempre vivos | Vertical / superficie |
| M1 | M | 1 | Hooks documentados no cableados | Extensibilidad |
| M2 | M | 1 | Tests densos sin integración HTTP→persistencia | Tests |
| M3 | M | 1 | Identifiers SQL en Application fuera de `quoteIdentifier` | SQL hygiene |
| M4 | M | 1 | Complejidad accidental / DX frágil | DX |
| M5 | M | 2 | Soft-delete sin restore; uploads create sin backfill `entidad_id` | Persistencia |
| M6 | M | 2 | Bitácora/ledger `INT` vs PK demo `BIGINT` | Schema |
| M7 | M | 2 | Docs canónicas aún en `app/` post-FPS | Docs |
| M8 | M | 2 | `CrudActionOwnershipTest` no cubre superficie real | Tests |
| M9 | M | 2 | Validador no exige columnas soft-delete/auditoría | Config |
| M10 | M | 2 | Cancelar en `form.php` ignora `returnUrl` | UX / Calendar |
| M11 | M | 2 | `CalendarEventSourceInterface` sin binding | DI |
| M12 | M | 2 | Tab `component` include desde config | Seguridad config |
| M13 | M | 2 | `information_schema` en cada cold load FPM | Perf |
| M14 | M | 3 | Actions/guards sin check de interfaz; bulk demo pedidos roto | Config / demos |
| M15 | M | 3 | Agujeros `orderBy` (`deleted`) + grouped ignora alias pedido | Listado |
| M16 | M | 3 | Bitácora PII completa, IP spoofable, `accion` VARCHAR(50) | Auditoría |
| M17 | M | 3 | Cache proceso + `listResources` traga excepciones | Ops / config |
| M18 | M | 3 | Reportes: N+1 `optionsFor` + `SELECT *` | Perf |
| M19 | M | 3 | `virtual` + `searchable` sin rechazo → SQL 500 | Config |
| M20 | M | 3 | Recurso con calendario siempre redirige a calendario | UX |
| B1 | B | 2 | `CrudController` sin catch genérico → 500 crudo | Presentation |
| B2 | B | 2 | Comentarios/docblocks obsoletos en servicios | Deuda menor |
| B3 | B | 1 | Duplicación `PROTECTED_COLUMNS` | Deuda menor |
| B4 | B | 3 | `EnviarWhatsappDemoHandler` acoplado al package + factory estática | Demos / package |

---

## Hallazgos críticos — descripción técnica

### C1 — IDOR con `list.scope_handler` custom

| Campo | Valor |
|-------|-------|
| Archivos | `CrudScopeResolver.php` L54–67, L75–79 |
| Evidencia | `assertOwnedBy()` solo actúa si `list.scope.type === 'owner'`. Con `scope_handler` custom, el listado filtra; show/edit/update/delete/acciones por ID no reaplica el scope. |
| Impacto | IDOR clásico vía ID conocido si hay `.ver`. |
| Owner | Framework |

### C2 — Acción sin `permission` ejecuta sin RBAC

| Campo | Valor |
|-------|-------|
| Archivos | `CrudActionService.php` L91–94, L153–156 |
| Evidencia | `verificar()` solo si `resolvePermission()` ≠ null. Validator no exige `permission` en actions. |
| Impacto | POST de acción ejecutable por cualquier autenticado (CSRF aparte). |
| Owner | Framework |

### C3 — Columna de máquina de estados editable por formulario ↑

| Campo | Valor |
|-------|-------|
| Archivos | `demo_pedidos.json` / `demo_citas.json`; `CrudConfigValidator` no prohíbe colisión `states.column` ∈ `form.fields` |
| Elevación P3 | **G15**: `DemoProductoToggleStatusHandler` escribe `status` con `GenericCrudRepository` fuera de transitions. El showcase oficial enseña el bypass. |
| Impacto | State machine cosmética: form + handler demo saltan guards, `visible_when` y bitácora `crud.transition`. |
| Owner | Framework |

### C4 — Transitions sin CAS / TOCTOU

| Campo | Valor |
|-------|-------|
| Archivos | `CrudTransitionService.php` L93–108; `GenericCrudRepository::updateRecord` |
| Evidencia | `UPDATE ... WHERE pk = ?` sin `AND status = :from`. |
| Impacto | Doble transición / carrera form vs transition. |
| Owner | Framework |

### C5 — Exfil vía Reportes sin `{resource}.ver` (P3)

| Campo | Valor |
|-------|-------|
| Archivos | `CrudReporteDataSource.php` L22–39, L42–51; contraste `CrudResourceService::eventosCalendario` que sí hace `verificar(.ver)`; ruta `GET /admin/reportes/documento` con solo `reportes.generar` |
| Evidencia | `rows()`/`findRecord()` delegan a `eventsInRange`/`findInScope` **sin** chequear permiso del recurso CRUD. `demo_productos` sin `list.scope` → tabla completa; `SELECT *`. |
| Impacto | Quien tenga `reportes.generar` y **no** `{recurso}.ver` lee filas/PII vía PDF/documento. Canal AuthZ paralelo al CRUD. |
| Owner | Framework |
| Nota | Un fix solo en `CrudController`/scope **no** cierra este vector. |

### C6 — Uploads: allowlist opcional + path sin normalizar (P3)

| Campo | Valor |
|-------|-------|
| Archivos | `CrudDataService::handleUpload` L701–716; `UploadValidator` L63–67 (null/[] no bloquea); `FileUploadService` L62–66 (`directorio` sin negar `..`); validator CRUD no valida bloque `uploads` |
| Evidencia | Con `uploads.enabled=true` y campo `file` sin `allowed_extensions`, no hay deny de extensión. `public_path` del JSON se concatena a `PUBLIC_PATH`. SVG en mapa MIME → stored XSS si se sirve inline. |
| Impacto | Superficie de webshell / escritura fuera de árbol previsto vía JSON. Demos suelen tener uploads off; el motor deja el arma cargada. |
| Owner | Framework |

---

## Hallazgos graves — descripción técnica

### G1 — Bulk sin re-check `visible_when` / `enabled_when` ↑

`run()` revalida; `runBulk()` no. **P3 G14** sube explotabilidad si condiciones usan `false`/`""` sobre columnas ausentes en list SELECT (UI/bulk ven filas “habilitadas” por fail-open).

### G2 — God services + acoplamiento a `GenericCrudRepository`

`CrudConfigValidator` ~847 LOC; `CrudDataService` ~723; dependencia concreta Infra en Application.

### G3 — Onion rota por contrato

Interfaces Domain importan Application Contexts. Dominio anémico.

### G4 — Sin `RbacMiddleware` en router CRUD

Solo `AuthMiddleware` del grupo `/admin`. RBAC dentro del servicio tras `load()`.

### G5 — Registry `new $class()` sin DI

Handlers/guards sin inyección del contenedor.

### G6 — Opciones `select`/`relation` no revalidadas

Solo `rules['in']` si se declara; no usa `options()` automáticamente. Amplifica C3.

### G7 — `exists` FK ignora soft-delete

UI filtra `deleted=0`; `existsForReference` no.

### G8 — `datetime-local` sin reglas tipadas

Ramas solo `date`/`datetime`.

### G9 — `BLOCKED_PREFIXES` incompleto

Faltan `sys_`, `int_`, `rep_`, `tmp_` frente a convenciones plataforma.

### G10 — AuthZ después de `load()` + coste IS

Enumeración leve + DoS ligero FPM.

### G11 — CDN DataTables/jQuery sin SRI

Supply-chain XSS en listados admin.

### G12 — `aggregation.enabled=false` apaga el breaker, no las agregaciones (P3)

| Evidencia | `CrudDataService` L124: circuit breaker solo si `$aggConfig['enabled']`. Con `enabled=false` y `group_by`/`summaries` presentes, GROUP BY/SUM corren sin `max_rows` / `require_filter_above`. |
| Impacto | El “apagado” documentado como control de coste es el modo más peligroso (DoS DB). Footgun semántico. |

### G13 — Race soft-delete en UPDATE/action (P3)

| Evidencia | Servicio comprueba `deleted===1` en memoria; `updateRecord`/`delete` SQL no incluyen `AND deleted = 0`. |
| Impacto | Entre assert y write, otro request soft-borra → mutación sobre fila borrada / hooks/bitácora sobre cadáver. Familia TOCTOU con C4. |

### G14 — `equalityMatches` fail-open (P3)

| Evidencia | `(string) null === ''`, `(string) false === ''`. Listado no selecciona columnas fuera de `list.columns`. Condición `enabled_when: {flag: false}` sobre columna ausente **cumple**. |
| Impacto | UI/bulk muestran u operan acciones mal gated. `run()` mitiga con `SELECT *`; bulk (G1) no. |

### G15 — Demo toggle bypasea state machine (P3)

| Evidencia | `DemoProductoToggleStatusHandler` hace `updateRecord` de `status` directo. JSON declara `states` + transitions **y** este handler. Validator no exige `CrudActionHandlerInterface` en actions (sí en hooks). |
| Impacto | Eleva C3: el paquete normaliza mutar la columna de estados fuera del motor de transitions. |

### G16 — Módulo opcional; rutas siempre on (P3)

| Evidencia | `config/modules/crud-engine.php` `obligatorio: false`. Rutas CRUD sin `VerticalProfile::moduleEnabled`. Seeds menú con `vertical_module` NULL → menú visible. Contraste: Reportes sí chequea módulo; Calendario usa `vertical_module='calendario'`. |
| Impacto | “Apagar” el módulo no cierra `/admin/crud/*` ni el menú demo si el SQL se sembró. |

---

## Hallazgos medios — descripción técnica

### M1–M13 (P1–P2, resumen)

Hooks fantasma; tests sin HTTP→SQL; identifiers en Application; DX; soft-delete/ledger; INT vs BIGINT; docs `app/`; ownership test estrecho; columnas auditoría no exigidas; Cancel vs returnUrl; Calendar DI; tab component; IS cold load.

### M14 — Actions/guards sin interfaz en validator; bulk demo pedidos (P3)

`actionsBlockErrors` exige string `handler` pero no `CrudActionHandlerInterface` / guard interface. `demo_pedidos` bulk `cancelar` apunta a `demo_pedido_total` (validator, no action handler) → `RuntimeException` en runtime.

### M15 — `orderBy` holes + grouped ignora alias (P3)

Whitelist servidor incluye `deleted` (siempre en SELECT). Modo agrupado calcula aliases `crud_sum_*` pero el repo ordena solo por `groupBy`.

### M16 — Bitácora PII / IP / longitud acción (P3)

Create registra `json_encode($payload)` completo. `Request::ip()` confía en `X-Forwarded-For` sin trust list; list rows usa `REMOTE_ADDR` crudo. `accion` VARCHAR(50) truncable con `crud.action:` + nombre largo. Tab history expone detalle a quien tenga `.ver`.

### M17 — Loader cache + swallow en `listResources` (P3)

Cache de proceso oculta hot-fix JSON. Excepciones en listado de recursos → warning + omitir; la URL directa sigue existiendo.

### M18 — N+1 + `SELECT *` en reportes (P3)

`optionsFor` hasta 1000 filas por relación belongsTo; `selectInDateRange` proyecta `*`.

### M19 — `virtual` + `searchable` sin rechazo (P3)

Validator salta columnas virtuales; list LIKE interpola el nombre → SQL error 500 en búsqueda.

### M20 — Return URL fuerza calendario (P3)

Si el recurso tiene calendario, `CrudReturnUrlResolver` siempre vuelve al calendario (open-redirect de `return_to` sigue acotado). Complementa M10.

---

## Hallazgos bajos

### B1–B3 (P1–P2)

Catch genérico ausente; docblocks obsoletos; `PROTECTED_COLUMNS` duplicado.

### B4 — WhatsApp demo en el package (P3)

`EnviarWhatsappDemoHandler` + `IntegrationsFactory::dispatcher()` estático en `src/Application/Crud/Handlers`. Contamina plataforma con demo de integración; sin DI (G5).

---

## Áreas revisadas sin hallazgo material

| Área | Pasada | Resultado |
|------|--------|-----------|
| Escape HTML vistas CRUD | P2 | `ViewHelper::e` consistente |
| CSRF mutaciones CRUD | P2/P3 | Delete/acciones/bulk solo POST + CSRF |
| Mass assignment servidor | P2 | Payload acotado a form fields |
| Soft-delete list/calendar feed | P2 | `deleted = 0` |
| Open redirect `return_to` | P2/P3 | Regex + resource match OK |
| Orden rutas `accion-masiva` | P2 | Sin colisión `{id}` |
| Mirror harness ↔ skeleton | P2/P3 | md5 JSON/assets/modules alineados |
| TODO/FIXME código CRUD | P2 | 0 |
| Calendar path AuthZ `.ver` | P3 | Correcto (el hueco es Reportes = C5) |
| `CrudStateMachine::authorize` puro | P3 | Lógica OK; hueco es config/bypass C3/G15 |
| FormBuilder XSS | P3 | Sin hallazgo nuevo |

---

## Fortalezas (contraste)

- Controller delgado; Presentation sin SQL de negocio.
- Endurecimiento real post-2026-04: whitelist handlers, `security.mode`, validación de campos, aggregations con circuit breaker (cuando `enabled=true`), owner scope + tests IDOR del built-in.
- Soft-delete + bitácora en create/update/delete/action/transition.
- Prefill de create filtrado; `visible_when` revalidado en `run()` (no en bulk).
- Assets mínimos; CSRF en mutaciones CRUD sólido; mirror skeleton sin drift.

---

## Prioridad de remediación CONFIRMADA (post-P3)

Criterio: **explotabilidad real × blast radius × facilidad de dejar un fix a medias** (un parche que cierre solo un canal y deje otro abierto cuenta como fallo).  
Esta lista **sustituye** la prioridad sugerida de P1–P2.

| Orden | IDs | Qué cerrar | Por qué este orden |
|------:|-----|------------|--------------------|
| **1** | **C1 + C2 + C5** | Scope en acceso por ID (custom incluido); `permission` obligatorio en actions ejecutables; `{resource}.ver` dentro de `CrudReporteDataSource` / use cases de documento | Tres canales AuthZ independientes. Arreglar solo CRUD deja exfil PDF (C5). Arreglar solo Reportes deja IDOR/actions. |
| **2** | **C3 + G15 + G6** | Prohibir `states.column` en `form.fields`; rewire/eliminar toggle demo; revalidar opciones/`in` en servidor | Mientras el estado se pueda escribir por form/handler, CAS (orden 3) y guards son teatro. G6 es el amplificador inmediato de C3. |
| **3** | **C6** | `allowed_extensions` obligatorio si uploads+file; normalizar/jailar `public_path`; endurecer SVG/mime | Vector de escritura en disco; independiente del AuthZ de filas; no esperar al refactor de capas. |
| **4** | **C4 + G13 + G1 + G14** | CAS `WHERE pk AND deleted=0 AND status=:from`; parity `visible_when`/`enabled_when` en bulk; igualdad tipada fail-closed | Familia de mutación concurrente + bulk. G14 debe ir con G1 o el re-check bulk sigue fallando en condiciones booleanas. |
| **5** | **G12** | `enabled=false` anula group/summaries **o** renombrar a `circuit_breaker` sin apagar agregaciones por accidente | Footgun de producción/DoS; fix localizado y barato tras AuthZ. |
| **6** | **G4 + G10 + G16** | Gate vertical + RBAC temprano en rutas/controller; `vertical_module='crud-engine'` en menú | Defensa en profundidad y superficie apagable; no sustituye orden 1. |
| **7** | **G7 + G8 + G9** | `exists` respeta soft-delete; tipar/`normalize` `datetime-local`; ampliar prefijos bloqueados | Integridad de datos y config; menor urgencia que AuthZ/uploads/estado. |
| **8** | **M14 + G5** | Validar interfaces action/guard en loader; DI de handlers; arreglar demo pedidos | Extensibilidad segura; desbloquea handlers reales en Portal. |
| **9** | **M16 + M5 + M6** | Redacción bitácora; IP confiable; backfill ledger; tipos BIGINT | Higiene auditoría/persistencia. |
| **10** | **M15 + M18 + M19 + M13** | Whitelist order=sortable; proyección columnas; ban virtual+searchable; cache schema opcional | Perf/listado; no bloquean seguridad dura. |
| **11** | **G2 + G3 + M2 + M8** | Puertos Domain; contexts; tests HTTP→SQL que fallen si regresan C1/C2/C5/C3/G1 | Deuda estructural **después** de cerrar explotables; los tests del orden 1–4 deben nacer con esos fixes, no esperar al refactor completo. |
| **12** | **G11 + M7 + M10 + M20 + M1 + M4 + M9 + M11 + M12 + M17 + B*** | SRI CDN; docs FPS; returnUrl; hooks cablear o borrar; DX; resto bajos | Higiene y DX; no primero. |

### Confirmaciones explícitas (cambios vs prioridad P2)

1. **C5 entra en el lote #1** — no era visible en P2; es tan crítico como C1/C2 porque bypasea el permiso de recurso.
2. **C3 no va solo** — G15 (demo toggle) y G6 (options) van en el mismo lote #2; sin eso el fix de form es incompleto.
3. **C6 (uploads) sube delante de G4/router** — escritura en disco > defensa en profundidad de middleware.
4. **G12 (aggregation footgun) es #5** — no era “perf menor”; `enabled=false` es un interruptor invertido.
5. **G2/G3 (arquitectura) bajan a #11** — importantes, pero no paran exfil/IDOR/webshell; refactorizar primero suele retrasar los cierres explotables.
6. **Tests de regresión** deben acompañar los lotes 1–4, no esperarse al ítem 11.

### No reordenar por “facilidad percibida”

- No priorizar docs (M7) ni SRI (G11) antes de C5/C6.
- No “arreglar solo owner scope” y marcar C1 cerrado: el contract debe cubrir `scope_handler`.
- No añadir `RbacMiddleware` genérico sin C5: el middleware de ruta no ve el recurso CRUD dentro de Reportes.

---

## No-alcance de este artefacto

- No modifica `src/`, `tests/`, configs ni schema.
- No abre issues ni implementa fixes.
- No audita Portal consumidor ni checkout de membresías.
- No re-audita el monolito legacy `feature/backoffice-api-integration`.
- No es un plan AUTOMATION-04 (sin tareas checkbox ni estimación de esfuerzo en días).
