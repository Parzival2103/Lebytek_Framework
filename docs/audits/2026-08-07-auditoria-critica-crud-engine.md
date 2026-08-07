# Auditoría técnica / crítica — CRUD Engine

**Alcance:** módulo CRUD Engine del paquete `lebytek/framework` (`src/`, configs, vistas, assets, schema demo, tests, docs canónicas).  
**Tipo:** crítica estructural + inventario de hallazgos verificables (dos pasadas).  
**No incluye:** implementación, fix, plan de remediación ejecutable.

---

## Automation provenance

| Campo | Valor |
|-------|-------|
| Artifact type | audit |
| Repositorio | `Parzival2103/Lebytek_Framework` |
| Base branch | `main` |
| SHA `origin/main` inspeccionado | `8e6ed487e8d40fd59100d1a5541f6a0d05929923` |
| Rama generada | `cursor/audit-critica-crud-engine-3644` |
| Timestamp UTC | `2026-08-07T04:18:27Z` |
| Pasadas | P1 arquitectura/seguridad/orquestación · P2 vistas/assets/transitions/validación/schema/DI/docs |

---

## Evidencia de preflight

```console
$ git fetch origin main
$ git fetch origin feature/backoffice-api-integration
$ git rev-parse --verify origin/main
8e6ed487e8d40fd59100d1a5541f6a0d05929923

$ git merge-base --is-ancestor origin/main HEAD
(exit 0)

$ git rev-parse --verify --quiet 'refs/tags/archive/backoffice-api-integration^{commit}'
4789f953ef746d17bae2e6b50c85504782d306e3

$ git rev-list --count origin/main..refs/tags/archive/backoffice-api-integration
53
LEGACY_ANCESTOR_CHECK=PASS

$ git status --porcelain   # antes de escribir
(vacío)
```

---

## Resumen ejecutivo

El CRUD Engine es un **framework-dentro-del-framework**: ~4.7k LOC entre servicios Application, entidades Domain, repo genérico, controller, vistas y assets. Vendió «tabla + JSON = CRUD»; el coste real es un esquema mental de ~10 bloques JSON, whitelist de handlers, 4 permisos, menú, schema `dom_*` y validación online contra `information_schema` en cada `load()`.

Desde la auditoría de 2026-04-28 endureció lo ruidoso (whitelist de handlers, `security.mode`, validación backend, owner scope, aggregations). Esta crítica encuentra **fallos más sutiles y peores**: autorización asimétrica, state machine bypasseable por formulario, transitions sin CAS, validación de opciones/UI no reaplicada en servidor, y una suite de tests que no ejercita el camino HTTP→SQL.

**Conteo (esta corrida):** 4 críticos · 11 graves · 13 medios · 3 bajos.  
**Owner:** Framework (`src/`, configs plataforma, docs, tests). Ningún hallazgo de esta auditoría exige cambio de negocio Portal.

---

## Cobertura de revisión

### Pasada 1 (cubierta)

Arquitectura de capas; `CrudDataService` / `CrudConfigValidator` / `CrudResourceService`; `CrudActionService` run/runBulk; `CrudScopeResolver`; `CrudHandlerRegistry`; interfaces Domain + contexts; acoplamiento a `GenericCrudRepository`; gaps de tests de orquestación; promesa DX vs complejidad.

### Pasada 2 (áreas no cubiertas en P1 — ahora auditadas)

Vistas `admin/crud/*` (XSS/CSRF/readonly/DataTables CDN); `crud-engine.js` / CSS + mirror skeleton; rutas `routes/web.php`; `CrudController` error handling; `CrudTransitionService` + state machine; `CrudFieldValidationService` (select/options/`datetime-local`); `exists` vs soft-delete; `allow_core_table` / prefijos; `CrudReporteDataSource` + Calendar DI; schema bitácora/ledger INT vs BIGINT; uploads ledger; docs `docs/modules/crud/*`; `CrudActionOwnershipTest` límites; TODO/FIXME en código CRUD.

---

## Catálogo estructurado de hallazgos

Leyenda: **C** crítico · **G** grave · **M** medio · **B** bajo.  
Columna **P** = pasada que lo descubrió.

| ID | Sev | P | Título corto | Área |
|----|-----|---|--------------|------|
| C1 | C | 1 | IDOR con `list.scope_handler` custom | AuthZ / scope |
| C2 | C | 1 | Acción sin `permission` salta RBAC en servidor | AuthZ / actions |
| C3 | C | 2 | Columna de states editable por formulario | State machine |
| C4 | C | 2 | Transitions sin CAS / TOCTOU | Concurrencia |
| G1 | G | 1 | Bulk no revalida `visible_when` / `enabled_when` | Actions |
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
| M12 | M | 2 | Tab `component` include desde config (confianza en disco) | Seguridad config |
| M13 | M | 2 | `information_schema` en cada cold load FPM | Perf |
| B1 | B | 2 | `CrudController` sin catch genérico → 500 crudo | Presentation |
| B2 | B | 2 | Comentarios/docblocks obsoletos en servicios | Deuda menor |
| B3 | B | 1 | Duplicación `PROTECTED_COLUMNS` | Deuda menor |

---

## Hallazgos críticos — descripción técnica

### C1 — IDOR con `list.scope_handler` custom

| Campo | Valor |
|-------|-------|
| Archivos | `src/Application/Services/CrudScopeResolver.php` L54–67, L75–79; uso en listado vía `applyScopeConditions` |
| Evidencia | `assertOwnedBy()` solo actúa si `list.scope.type === 'owner'`. Si el recurso usa `scope_handler` custom, el listado sí filtra, pero `show`/`edit`/`update`/`delete`/acciones por ID **no** reaplican el scope. |
| Impacto | Usuario autenticado con `{recurso}.ver` que conozca un ID ajeno accede/muta el registro. Escape hatch documentado rompe el modelo de seguridad. |
| Owner | Framework |

### C2 — Acción sin `permission` ejecuta sin RBAC

| Campo | Valor |
|-------|-------|
| Archivos | `src/Application/Services/CrudActionService.php` L91–94, L153–156 |
| Evidencia | `resolvePermission()` puede devolver `null`; `verificar()` solo corre si hay slug. El config validator no exige `permission` en actions. |
| Impacto | Acción `handler`/`transition` sin permiso declarado es ejecutable por cualquier sesión autenticada que llegue al POST (CSRF aparte). La UI puede ocultarla; el servidor no. |
| Owner | Framework |

### C3 — Columna de máquina de estados editable por formulario

| Campo | Valor |
|-------|-------|
| Archivos | `config/cruds/demo_pedidos.json` (`states.column=status` + `form.fields` con `status` select); `config/cruds/demo_citas.json` (`estado`); `CrudConfigValidator.php` L244–251 / L668 (no prohíbe colisión) |
| Evidencia | El validador comprueba existencia de `states.column` y que no sea protegida; **no** impide que esa columna esté en `form.fields`. `update` vía formulario escribe el valor sin pasar por `CrudTransitionService::authorize()`. |
| Impacto | Con permiso `.editar` se fuerza cualquier estado (`pagado`, `confirmada`, …) sin transición, sin guard, sin `visible_when`, sin bitácora `crud.transition`. La state machine queda cosmética. |
| Owner | Framework (motor + demos que normalizan el antipatrón) |

### C4 — Transitions sin CAS / TOCTOU

| Campo | Valor |
|-------|-------|
| Archivos | `src/Application/Services/CrudTransitionService.php` L93–108; `GenericCrudRepository::updateRecord` L230–244 |
| Evidencia | `authorize()` usa el `$record` en memoria; el `UPDATE` es `WHERE pk = ?` sin `AND status = :from`, sin transacción ni `FOR UPDATE`. |
| Impacto | Dos POSTs concurrentes (doble “Pagar”) o transición vs `update` de formulario pueden aplicar efectos/bitácora dos veces o pisar estado. No hay compare-and-swap. |
| Owner | Framework |

---

## Hallazgos graves — descripción técnica

### G1 — Bulk sin re-check `visible_when` / `enabled_when`

| Campo | Valor |
|-------|-------|
| Archivos | `CrudActionService::run` L104–106 vs `runBulk` L166–184 |
| Evidencia | Docblock del servicio afirma re-chequeo en orquestación; `run()` lo hace; `runBulk()` carga registro, ownership y `dispatch` **sin** `isVisibleFor`/`isEnabledFor`. |
| Impacto | Acción masiva ejecuta sobre filas donde la UI (y el servidor en fila unitaria) la denegarían. |
| Owner | Framework |

### G2 — God services + acoplamiento a `GenericCrudRepository`

| Campo | Valor |
|-------|-------|
| Archivos | `CrudConfigValidator.php` (~847 LOC); `CrudDataService.php` (~723); imports concretos en Data/Validator/Transition |
| Evidencia | Application depende del repo de Infrastructure, no de un puerto Domain de persistencia CRUD. Relations/constraints sí usan interfaces — inconsistencia selectiva. |
| Impacto | Testabilidad de orquestación baja; cambios de persistencia contagian tres servicios; violación de dependencia hacia afuera. |
| Owner | Framework |

### G3 — Onion rota por contrato

| Campo | Valor |
|-------|-------|
| Archivos | `src/Domain/Interfaces/CrudHookHandlerInterface.php` L7–24; doc `modulo-crud-engine.md` nota de capas |
| Evidencia | Interfaces Domain importan `Application\Crud\Context\*`. Dominio anémico: definiciones son bags `fromArray` + getters. |
| Impacto | Capas no invierten dependencias; Domain no es núcleo estable. Documentar la excepción no la elimina. |
| Owner | Framework |

### G4 — Sin `RbacMiddleware` en router CRUD (deuda M3 arrastrada)

| Campo | Valor |
|-------|-------|
| Archivos | `routes/web.php` L52–55 (Auth del grupo `/admin`), L114–122 (CRUD sin RBAC de ruta) |
| Evidencia | Contrasta con `/administracion/*` y reportes que sí llevan `RbacMiddleware`. RBAC ocurre dentro de servicios tras `load()`. |
| Impacto | Defensa en profundidad inconsistente; cualquier autenticado entra al controller. Relacionado con G10. |
| Owner | Framework |

### G5 — Registry instancia handlers sin contenedor

| Campo | Valor |
|-------|-------|
| Archivos | `CrudHandlerRegistry.php` L46 |
| Evidencia | `$instance = new $class();` — sin constructor args, sin DI. |
| Impacto | Handlers reales en consumidores no pueden inyectar repos/servicios sin statics o service locator. Techo de extensibilidad. |
| Owner | Framework |

### G6 — Opciones de `select` / relation no revalidadas en servidor

| Campo | Valor |
|-------|-------|
| Archivos | `CrudFieldValidationService::validateValue` L236–239; demos sin regla `"in"` en campos de estado |
| Evidencia | Solo aplica `rules['in']` si está declarado; no usa `field->options()` automáticamente. |
| Impacto | POST con valor fuera del catálogo UI (p. ej. `status=hack`) persiste. Amplifica C3. |
| Owner | Framework |

### G7 — `exists` FK ignora soft-delete

| Campo | Valor |
|-------|-------|
| Archivos | `GenericCrudRepository::existsForReference` L265–275 vs `distinctOptions` L284 |
| Evidencia | Options UI filtran `` `deleted` = 0 ``; `exists` cuenta cualquier fila con el valor. |
| Impacto | Se puede asociar FK a registro soft-deleted conocido. Integridad lógica asimétrica UI vs servidor. |
| Owner | Framework |

### G8 — `datetime-local` sin validación tipada

| Campo | Valor |
|-------|-------|
| Archivos | `CrudFieldValidationService` L224–234, `effectiveValidationType()`; demos calendario con `type: datetime-local` |
| Evidencia | Ramas tipadas cubren `date` y `datetime`, no `datetime-local`. El tipo efectivo del campo no entra en validación de fecha. |
| Impacto | Strings arbitrarios o formato HTML `YYYY-MM-DDTHH:mm` llegan a columnas DATETIME sin normalizar/validar. |
| Owner | Framework |

### G9 — Prefijos bloqueados incompletos

| Campo | Valor |
|-------|-------|
| Archivos | `CrudConfigValidator` `BLOCKED_PREFIXES = ['auth_','cfg_','core_','log_']` |
| Evidencia | Con `security.allow_core_table=true` + `mode=restricted` quedan abiertos `sys_*`, `int_*`, `rep_*`, `tmp_*` y otras tablas no-`dom_*`. |
| Impacto | JSON comprometido o mal configurado puede apuntar el motor a tablas de plataforma “secundarias” según convenciones del repo. |
| Owner | Framework |

### G10 — Enumeración + coste IS antes de RBAC

| Campo | Valor |
|-------|-------|
| Archivos | `CrudResourceService` entrypoints: `load()` luego `verificar()` (p. ej. `buildIndexData` L46–49) |
| Evidencia | Usuario autenticado sin permiso distingue recurso inexistente/JSON inválido (flash dashboard) vs 403; cada intento dispara `information_schema`. |
| Impacto | Side-channel leve + DoS ligero sobre workers FPM. |
| Owner | Framework |

### G11 — CDN DataTables/jQuery sin SRI

| Campo | Valor |
|-------|-------|
| Archivos | `src/Presentation/Views/admin/crud/index.php` ~L231–237 |
| Evidencia | `<script>`/`<link>` a cdn.datatables.net y code.jquery.com sin `integrity`/`crossorigin`. |
| Impacto | Supply-chain XSS en listados admin si el CDN se compromete. |
| Owner | Framework |

---

## Hallazgos medios — descripción técnica

### M1 — Hooks API fantasma

Documentados / stub en `AbstractCrudHookHandler` e interfaz: `beforeListQuery`, `beforeRenderForm`, `afterUpload`. Solo `afterListRows` se invoca desde `CrudResourceService`. Extensibilidad anunciada que no existe en runtime.

### M2 — Tests sin orquestación real

~43 archivos / ~160 casos bajo `tests/Crud/**` + ownership unitario. Casi nulos: `CrudController`, `CrudDataService` store/update/delete con repo, `CrudActionService::run`/`runBulk` end-to-end, scope custom en show. Suite verde ≠ seguridad de superficie.

### M3 — Identifiers SQL en Application

`CrudDataService` interpola columnas de search/filter como `` `$field` `` confiando en JSON ya validado, fuera de `GenericCrudRepository::quoteIdentifier`. Riesgo residual si hay bypass del validador o columnas virtuales mal usadas.

### M4 — DX / complejidad accidental

17 servicios `Crud*`, 8 contexts, JSON de 10 bloques, validación DB-dependent. Curva «CRUD mínimo» vs showcase demo abismal; el motor no distingue perfiles. Errores de config en request → flash a dashboard.

### M5 — Soft-delete y uploads

`findById` no filtra `deleted` (gate en servicio). No hay restore. Upload en create deja ledger con `entidad_id NULL` sin backfill post-insert (contrato fijado en `CrudUploadLedgerTest`).

### M6 — INT vs BIGINT

`log_bitacora.registro_id` y `core_archivos.entidad_id` son `INT UNSIGNED`; demos CRUD usan PK `BIGINT UNSIGNED`. Interface tipa `?int`. Riesgo de truncamiento a escala.

### M7 — Docs drift post-FPS

`docs/modules/crud/modulo-crud-engine.md` y `uso-crud-engine.md` listan rutas bajo `app/Application`, `app/Presentation`, `config/container.php`. Código real: `src/` + `FrameworkServiceProvider`. Guía de uso omite rutas `accion` / `accion-masiva`.

### M8 — Ownership test estrecho

`tests/Security/CrudActionOwnershipTest.php` caracteriza el guard puro `assertOwnedBy`. No HTTP, no transitions, no update/delete E2E, no scope_handler, no bulk conditions.

### M9 — Schema de columnas de plataforma no exigido

Validador no exige `deleted` / columnas de auditoría en la tabla física. `list()` asume `deleted = 0` → fallo runtime tras “config válida”.

### M10 — Cancelar ignora `returnUrl`

En `form.php`, el enlace “Volver” usa `$returnUrl`; “Cancelar” hardcodea `/admin/crud/{resource}` — rompe flujo calendario.

### M11 — DI Calendar asimétrica

`CrudReporteDataSource` bound a interfaces de reporte; `CalendarEventSourceInterface` no tiene alias — se inyecta `CrudResourceService` concreto. Frágil ante resolución por interfaz.

### M12 — Tab `component` desde config

`show.php` hace `ViewHelper::partial($componentView)` con rechazo de `..` pero sin allowlist. Confianza = integridad del JSON en disco.

### M13 — `information_schema` por cold load

Cada request FPM sin cache de proceso: `tableExists` + `getTableColumns` (+ N por relación). Correcto para fail-closed; caro bajo tráfico admin.

---

## Hallazgos bajos

### B1 — Controller sin catch genérico

`CrudController` atrapa `AccesoException` / `ValidationException`. Wiring roto (`LogicException` en transition sin repo) → 500 sin mensaje operativo.

### B2 — Docblocks obsoletos

p. ej. `CrudActionService` aún menciona «`transition` llega en Fase 2» con transitions ya cableadas.

### B3 — `PROTECTED_COLUMNS` duplicado

Lista hardcodeada en `CrudDataService` y `CrudConfigValidator` — riesgo de divergencia.

---

## Áreas revisadas sin hallazgo material nuevo

| Área | Resultado |
|------|-----------|
| Escape HTML en vistas CRUD | Consistente `ViewHelper::e` |
| CSRF en mutaciones | `csrfField` + `CsrfMiddleware` en POSTs |
| Mass assignment servidor | Payload desde `form.fields`; columnas protegidas; readonly reinyectado |
| Soft-delete en list/calendar feed | `deleted = 0` en list/events |
| Open redirect `return_to` | `CrudReturnUrlResolver` acota a calendario validado |
| Orden rutas `accion-masiva` | Evita colisión con `{id}` |
| Mirror harness ↔ skeleton (JS/CSS/modules/handlers) | md5 alineado; sin drift |
| TODO/FIXME en `src/` CRUD | 0 en código de producto |

---

## Fortalezas (contraste)

- Controller delgado; Presentation sin SQL de negocio.
- Endurecimiento real post-2026-04: whitelist handlers, `security.mode`, validación de campos, aggregations con circuit breaker, owner scope + tests IDOR del built-in.
- Soft-delete + bitácora en create/update/delete/action/transition.
- Prefill de create filtrado; `visible_when` revalidado en `run()` (no en bulk).
- Assets mínimos (`crud-engine.js` ~47 LOC) sin lógica de auth en cliente.

---

## Prioridad de remediación sugerida (no es plan)

1. **C1 + C2 + C3** — cerrar superficie de autorización/estado antes de cualquier feature nueva del motor.
2. **C4 + G1** — consistencia de mutaciones (CAS transitions; parity run/runBulk).
3. **G6 + G7 + G8** — validación servidor alineada con UI/catálogos/soft-delete/tipos.
4. **G4 + G10** — RBAC temprano / orden load→authz.
5. **G2 + G3 + G5** — deuda estructural (puertos, DI handlers, contexts).
6. **M2 + M8** — tests de integración que fallen si regresan C1–C3/G1.
7. **M7** — alinear docs canónicas a `src/` (FPS).
8. Resto M/B según coste.

---

## No-alcance de este artefacto

- No modifica `src/`, `tests/`, configs ni schema.
- No abre issues ni implementa fixes.
- No audita Portal consumidor ni checkout de membresías.
- No re-audita el monolitico legacy `feature/backoffice-api-integration`.
