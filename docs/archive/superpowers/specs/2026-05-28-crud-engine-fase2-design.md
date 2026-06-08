# CRUD Engine — Fase 2 (Acciones, Estados, Validaciones, Relaciones, Tabs, Bulk) — Design Spec

**Fecha:** 2026-05-28
**Estado:** Aprobado por usuario (todas las secciones de diseño en brainstorming)
**Enfoque elegido:** A — Extensión en sitio + contexto tipado + despacho genérico de acciones con handlers externos.

**Decisiones de encuadre (fijadas con el usuario):**
1. **Filosofía:** *escape hatch*. El motor resuelve el ~80% declarativo y delega lo complejo a handlers/controllers/componentes vía metadata. No "motor todopoderoso", no hardcodear módulos.
2. **Relaciones:** `belongsTo` (selects) + `hasMany` read-only en tabs. `manyToMany` diferido.
3. **Estados/transiciones:** una transición es una acción custom con guard; historial en `log_bitacora`. Cero tablas nuevas en el core.
4. **Prioridad tras la fundación:** Fase 1 = acciones custom + bulk.

**Objetivo:** evolucionar el CRUD Engine para soportar acciones personalizadas (fila/módulo/bulk), estados y transiciones, validaciones declarativas y complejas, hooks ampliados, relaciones y tabs en detalle — manteniéndolo genérico, metadata-driven, compatible con MVC + Onion, PHP vanilla, JS vanilla, MySQL y Bootstrap, sin introducir lógica de módulos reales en el core.

---

## 1. Diagnóstico del CRUD Engine actual

**Flujo y capas (Onion respetado):**
`routes/web.php` → `CrudController` → `CrudResourceService` (RBAC + orquesta) → `CrudDataService` (payload, uploads, bitácora) → `CrudFormBuilder` / `CrudTableBuilder` → `GenericCrudRepository` (PDO, prepared statements, `quoteIdentifier` whitelist).

**Metadata:** JSON en `config/cruds/{resource}.json` (no `.php`). `CrudConfigLoader` (carga + cache) → `CrudConfigValidator` (tabla existe, columnas existen, permisos `{prefix}.ver/crear/editar/eliminar` existen, prefijos `auth_/cfg_/core_/log_` bloqueados, handlers en whitelist) → entidades `CrudResourceDefinition` / `CrudFieldDefinition`.

**Validación declarativa actual** (`CrudFieldValidationService`, pipeline sanitize→normalize→validate→toStorage): `required, minlength, maxlength, email, integer (min/max), numeric/decimal/money (min/max), string, boolean, date, datetime, in, regex` (regex con guard de seguridad).

**Hooks actuales:** `CrudHookRunner` + `CrudHandlerRegistry` (whitelist clave→FQCN en `config/crud_handlers.php`; nunca FQCN en JSON) + `CrudHookHandlerInterface` con **6 hooks**: `beforeStore/afterStore/beforeUpdate/afterUpdate/beforeDelete/afterDelete`. **Un solo handler por recurso**. El runner invoca por `method_exists` y re-lanza excepciones (un hook puede abortar).

**Listado:** columnas (sortable/searchable/format date|datetime|money/badge), filtros de igualdad (`f_{field}`), búsqueda LIKE sobre `searchable`, paginación, `list.group_by` + `list.summaries` (sum/count) con guard de coste (`list.aggregation`), `table_compact`, borrado lógico, auditoría `crud.create/update/delete` en `log_bitacora`.

**Seguridad:** CSRF en mutaciones, RBAC por acción, columnas protegidas (`id, created_at, created_by, updated_at, updated_by, deleted, deleted_at, deleted_by`), prefijos de tabla, soft delete.

**Vistas:** `index.php`, `form.php`, `show.php`. `show.php` es un `dl` plano con botones Editar/Eliminar hardcodeados.

**Inventario de archivos del motor:**
- `app/Presentation/Controllers/Admin/CrudController.php`
- `app/Application/Services/`: `CrudResourceService, CrudConfigLoader, CrudConfigValidator, CrudFormBuilder, CrudTableBuilder, CrudDataService, CrudHookRunner, CrudHandlerRegistry, CrudFieldValidationService`
- `app/Domain/Entities/`: `CrudResourceDefinition, CrudFieldDefinition`
- `app/Domain/Interfaces/CrudHookHandlerInterface.php`
- `app/Application/Crud/Handlers/AbstractCrudHookHandler.php`
- `app/Infrastructure/Repositories/GenericCrudRepository.php`
- `app/Presentation/Views/admin/crud/`: `index.php, form.php, show.php`
- `config/cruds/*.json`, `config/crud_handlers.php` (vacío), `public/assets/css/crud-engine.css`
- Docs: `docs/modules/crud/modulo-crud-engine.md` (+ history y audit)

---

## 2. Problemas detectados

1. **Acciones custom por fila/módulo:** no existen. `list.actions` solo acepta los strings `show/edit/delete`. No hay declaración de icono, ruta, método HTTP, confirmación, RBAC ni visibilidad condicional, ni endpoint de ejecución.
2. **Estados/transiciones:** no existen. Solo hay convención de columna `status` + badges. No hay grafo de estados válidos, ni guard de transiciones inválidas, ni validadores/handlers pre/post transición.
3. **Validaciones declarativas incompletas:** faltan `unique`, `exists`, validación a nivel de formulario (cross-field) y **mensajes personalizados**.
4. **Validadores complejos externos:** solo se pueden simular lanzando excepción dentro de un hook; no hay cableado declarativo de clases validadoras (`FechaDisponibleValidator`, `AnticipoMinimoValidator`, etc.).
5. **Hooks limitados:** faltan `beforeTransition/afterTransition, beforeRenderForm, beforeListQuery, afterUpload`; nombres `Store` vs el vocabulario `Create`; el payload es `array` por valor y **`beforeStore` no puede mutar `data`** antes del insert (bug real — `CrudDataService::store` usa el `$payload` original, no `$hookPayload`); el contexto no transporta `user/request/ip` de forma limpia.
6. **Relaciones:** ninguna. El selector producto→categoría está marcado como gap en `docs/superpowers/specs/2026-05-15-vertical-inventario-design.md` (riesgo #1). No hay `belongsTo/hasMany/manyToMany`.
7. **Tabs en detalle:** no existen. `show.php` es plano.
8. **Bulk actions:** ninguna.

---

## 3. Propuesta de arquitectura

### 3.1 Familia de contextos tipados (capa Application)
Reemplaza el `array` suelto de los hooks. Cada contexto tiene una responsabilidad única (unidades pequeñas y testeables). Comparten una base con la identidad común.

- `CrudContext` (base, inmutable): `resourceKey, table, primaryKey, userId, ip`.
- `CrudWriteContext` (create/update/delete): `input` (read-only), `record` (fila actual o `null`), y **`data` mutable** (`data(): array`, `setData(array)`, `mergeData(array)`). El motor **relee `data()`** tras `beforeCreate/beforeUpdate` → permite inyectar/transformar campos antes de persistir (arregla el bug del #5).
- `CrudActionContext`: `recordId, record, action, input`.
- `CrudTransitionContext`: `record, statusColumn, from, to, input`.
- `CrudValidationContext`: `input, normalized, record, isEdit` + colector `addError(field, message)`.
- `CrudListContext`: `query` + colector `addCondition(column, op, value)` con ops en whitelist (`= != < > <= >= LIKE IN`); el motor arma SQL con `quoteIdentifier` + params (nunca SQL crudo del handler).
- `CrudFormContext`: `isEdit, record` + overrides `setFieldOptions(field, options)`, `setFieldValue(field, value)`.

### 3.2 Contratos (interfaces segregadas)
```php
interface CrudActionHandlerInterface   { public function handle(CrudActionContext $ctx): void; }
interface CrudTransitionGuardInterface  { public function authorize(CrudTransitionContext $ctx): void; } // throw = bloquea
interface CrudValidatorInterface        { public function validate(CrudValidationContext $ctx): void; }   // ctx->addError(...)
interface CrudListScopeInterface        { public function apply(CrudListContext $ctx): void; }
```
- `CrudHookHandlerInterface` **se conserva**; sus firmas migran de `array` a los contextos tipados. Como `config/crud_handlers.php` está **vacío** (cero handlers en producción), no se rompe nada real.
- `CrudHandlerRegistry` gana `resolve(string $key, string $expectedInterface)` que valida la interfaz esperada y devuelve error de configuración (no crash) si la clase no la implementa.

### 3.3 Eventos de hook (sin interfaz rígida)
El runner ya invoca por `method_exists`, así que se amplían eventos sin tocar una interfaz fija. Vocabulario canónico:
`beforeCreate/afterCreate`, `beforeUpdate/afterUpdate`, `beforeDelete/afterDelete`, `beforeTransition/afterTransition`, `beforeRenderForm`, `beforeListQuery`, `afterUpload`.
**Compat:** el runner también dispara los legacy `beforeStore/afterStore` si el handler los define.

### 3.4 Flujos de ejecución
**Acción de fila** → `POST /admin/crud/{resource}/{id}/accion/{action}` (CSRF):
`CrudController::action` → `CrudActionService`: carga definición → resuelve la acción en `actions.row` → RBAC (`action.permission`) → carga registro → **re-chequea `visible_when` en server** (nunca confía en la UI) → ejecuta por `type`:
- `handler` → resuelve `CrudActionHandlerInterface`, arma `CrudActionContext`, ejecuta.
- `transition` → delega en `CrudTransitionService`.
- `link` → **no se ejecuta en server** (el botón solo navega a `route`).
→ auditoría `crud.action:{name}` en `log_bitacora` → redirect con flash.

**Acción masiva** → `POST /admin/crud/{resource}/accion-masiva/{action}` (CSRF) con `ids[]`:
RBAC global + por id; iteración best-effort; recolecta éxitos/fallos; audita cada ítem; flash con resumen. Tope de `ids` por request.

**Transición** → `CrudTransitionService::apply`: `from = record[column]` → valida `to ∈ transitions[from]` (si no, `ValidationException`) → `beforeTransition` guard (`authorize` lanza para bloquear) + evento `beforeTransition` → update de la columna (+ `updated_at/by`) → bitácora `crud.transition` → evento `afterTransition`.

**Validación** (en `CrudDataService`, orden): reglas de campo (`CrudFieldValidationService`, ahora con mensajes custom) → constraints DB `unique/exists` (`CrudDbConstraintValidator`) → validadores de formulario externos (`CrudValidatorInterface` en `form.validators`) → persistencia. Todos los errores se acumulan en una sola `ValidationException`.

**Relaciones** (`CrudRelationService`): options de selects `belongsTo` (tabla relacionada, label/value, filtro **estructurado** `{columna: valor}`) y filas hijas `hasMany` read-only para tabs. Las tablas relacionadas pasan las mismas reglas de prefijo/seguridad (validadas en config).

**Detalle con tabs** (`CrudDetailBuilder`): view-model de pestañas; `show.php` se reescribe con nav-tabs Bootstrap. Tipos: `fields`, `relation` (hasMany read-only), `component` (vista custom whitelisteada — escape hatch), `history` (bitácora del registro). Sin bloque `detail`, se genera una sola tab "Datos generales" idéntica al `show.php` actual.

### 3.5 Principios invariables
- Cero `if ($module === 'x')` en el core. Todo se decide por metadata + handlers whitelisteados.
- Prepared statements + `quoteIdentifier` en toda query; identificadores y ops siempre desde whitelist.
- Bloques de metadata nuevos **opcionales** → compatibilidad con módulos simples.
- La lógica de negocio vive en handlers/validators/guards externos (Application/Crud/Handlers), nunca en los servicios `Crud*`.

---

## 4. Estructura de archivos sugerida

### Reutilizar sin cambios
`CrudConfigLoader`, `GenericCrudRepository` (base + `quoteIdentifier`), `RbacService`, `CsrfMiddleware`, `BitacoraRepository`, `Paginator`, `CrudTableBuilder` (núcleo de columnas/format/badge).

### Refactorizar (acotado)
| Archivo | Cambio |
|---|---|
| `Domain/Interfaces/CrudHookHandlerInterface.php` | Firmas `array` → contextos tipados |
| `Application/Crud/Handlers/AbstractCrudHookHandler.php` | No-op con contextos + nuevos eventos opcionales |
| `Application/Services/CrudHookRunner.php` | Pasa contexto (por referencia), relee `data()`, eventos extendidos + legacy |
| `Application/Services/CrudHandlerRegistry.php` | `resolve(key, expectedInterface)` con type-check |
| `Application/Services/CrudDataService.php` | Nueva cadena de validación + read-back de `beforeCreate/Update` + `afterUpload` |
| `Application/Services/CrudFieldValidationService.php` | Mensajes custom por regla |
| `Application/Services/CrudConfigValidator.php` | Valida `actions/states/relations/detail/validators` + prefijos de tablas relacionadas/`exists` |
| `Domain/Entities/CrudResourceDefinition.php` | Parseo de bloques nuevos → VOs |
| `Application/Services/CrudResourceService.php` | Datos para detalle con tabs y acciones |
| `Presentation/Controllers/Admin/CrudController.php` | Métodos `action()` y `bulkAction()` |
| `Presentation/Views/admin/crud/show.php` | Reescritura con nav-tabs (default = tab general) |
| `Presentation/Views/admin/crud/index.php` | Render de acciones custom + barra bulk |
| `routes/web.php` | 2 rutas nuevas |
| `config/container.php` | Bindings nuevos (additivos) |
| `config/crud_handlers.php` | Doc: admite handlers de acción/validación/scope |

### Agregar
```
app/Application/Crud/Context/
    CrudContext.php
    CrudWriteContext.php
    CrudActionContext.php
    CrudTransitionContext.php
    CrudValidationContext.php
    CrudListContext.php
    CrudFormContext.php

app/Domain/Interfaces/
    CrudActionHandlerInterface.php
    CrudTransitionGuardInterface.php
    CrudValidatorInterface.php
    CrudListScopeInterface.php

app/Domain/Entities/Crud/
    CrudActionDefinition.php
    CrudStateMachine.php
    CrudRelationDefinition.php
    CrudTabDefinition.php

app/Application/Services/
    CrudActionService.php          (despacho fila + bulk)
    CrudTransitionService.php
    CrudRelationService.php
    CrudDbConstraintValidator.php  (unique/exists)
    CrudDetailBuilder.php          (view-model de tabs)

app/Infrastructure/Repositories/  (métodos nuevos en GenericCrudRepository)
    existsWhere(table, column, value, exceptId)
    distinctOptions(table, valueCol, labelCol, filter, orderBy)   // belongsTo
    childrenBy(table, fk, parentId, columns, orderBy, dir, limit) // hasMany

app/Presentation/Views/admin/crud/partials/
    actions_row.php
    actions_bulk.php
    tab_fields.php / tab_relation.php / tab_history.php

public/assets/js/crud-engine.js   (selección bulk, confirms genéricos)
```

### Endpoints / rutas nuevas (grupo admin en `routes/web.php`)
```php
$router->post('/crud/{resource}/{id}/accion/{action}',   [CrudController::class, 'action'],     [CsrfMiddleware::class]);
$router->post('/crud/{resource}/accion-masiva/{action}', [CrudController::class, 'bulkAction'], [CsrfMiddleware::class]);
```
- Conviven con las rutas literales actuales (`/editar`, `/eliminar`); el segmento `accion`/`accion-masiva` las distingue (se verifica con tests).
- Las acciones `link` GET **no** crean ruta en el engine: navegan a `route` declarada (escape hatch).

### Bindings de container (additivos)
`CrudActionService`, `CrudTransitionService`, `CrudRelationService`, `CrudDbConstraintValidator`, `CrudDetailBuilder` como singletons con sus deps (registry, repo, rbac, bitácora, hook runner). `CrudController` recibe además `CrudActionService`. Ningún binding existente se modifica.

---

## 5. Cambios en metadata JSON

Todos los bloques nuevos son **opcionales**.

### `actions` (extiende/reemplaza `list.actions`)
- `type`: `builtin` (show/edit/delete) · `handler` · `transition` · `link`.
- `visible_when` / `enabled_when`: mapa de **igualdad** sobre campos del registro (`{ "status": "pendiente" }` o `{ "status": ["pendiente","revision"] }`). Sin lenguaje de expresiones ni `eval`. Evaluado al render y **re-validado en server**.
- `permission`: slug completo o sufijo resuelto contra `permission_prefix`.
- `method`: HTTP (`POST` para handler/transition; `GET` para link).
- `confirm`: texto de confirmación (modal genérico).
- `icon`: clase de icono Bootstrap.
- Compat: sin `actions`, se usa `list.actions` (strings) actual.

### `states` (máquina de estados)
- `column`: columna de estado (no protegida).
- `values`: `estado → { label, badge }`.
- `transitions`: `estado → [estados destino permitidos]`.

### `validation` por campo (extensión) + `form.validators`
- Nuevas reglas: `unique` (`true` o `{ "ignore_self": true }`), `exists` (`{ "table", "column" }`).
- `messages`: `regla → mensaje` (override de mensajes por defecto).
- `form.validators`: `[clave, ...]` de validadores externos `CrudValidatorInterface`.

### `relations`
- `belongsTo`: `{ table, foreign_key, value, label, filter:{col:val}, order_by }`. Alimenta campos `type: relation`.
- `hasMany`: `{ table, foreign_key, columns:[...], order_by, direction, limit }`. Consumido por tabs read-only.
- `manyToMany`: diferido (no-goal de esta fase).

### `detail.tabs`
- Tipos: `fields` (`columns`), `relation` (`relation`), `component` (`view` whitelisteada bajo `Views/`), `history`.
- Sin `detail` → una tab "Datos generales" (comportamiento actual).

---

## 6. Contratos / interfaces necesarias

```php
// app/Domain/Interfaces
interface CrudActionHandlerInterface  { public function handle(CrudActionContext $ctx): void; }
interface CrudTransitionGuardInterface { public function authorize(CrudTransitionContext $ctx): void; }
interface CrudValidatorInterface       { public function validate(CrudValidationContext $ctx): void; }
interface CrudListScopeInterface       { public function apply(CrudListContext $ctx): void; }

// app/Domain/Interfaces/CrudHookHandlerInterface (conservado, firmas tipadas)
interface CrudHookHandlerInterface {
    public function beforeCreate(CrudWriteContext $ctx): void;
    public function afterCreate(CrudWriteContext $ctx): void;
    public function beforeUpdate(CrudWriteContext $ctx): void;
    public function afterUpdate(CrudWriteContext $ctx): void;
    public function beforeDelete(CrudWriteContext $ctx): void;
    public function afterDelete(CrudWriteContext $ctx): void;
    // Eventos extendidos opcionales (invocados por method_exists, no obligatorios en la interfaz):
    //   beforeTransition/afterTransition(CrudTransitionContext)
    //   beforeRenderForm(CrudFormContext)
    //   beforeListQuery(CrudListContext)
    //   afterUpload(CrudWriteContext)
}
```

**Registro:** `CrudHandlerRegistry::resolve(string $key, string $expectedInterface): object` — valida whitelist + interfaz. Una clave puede mapear a una clase que implemente varias interfaces (p. ej. acción + guard).

**Definiciones (VOs, Domain):** `CrudActionDefinition`, `CrudStateMachine` (`canTransition(from,to): bool`, `allowedFrom(state): array`), `CrudRelationDefinition`, `CrudTabDefinition` — parseadas desde metadata, con validación de forma.

---

## 7. Plan de implementación por fases

Cada fase es shippeable, additiva y detrás de metadata opcional.

- **Fase 0 — Fundación** (sin feature visible): familia `CrudContext`, interfaces segregadas, `CrudHandlerRegistry::resolve(key, interface)`, `CrudHookRunner` con contexto tipado + **read-back** (arregla mutación) + eventos extendidos/legacy, `AbstractCrudHookHandler`, scaffolding del validador de config para bloques nuevos. Refactor cubierto por tests; 100% compatible.
- **Fase 1 — Acciones custom + bulk**: `CrudActionDefinition`, `CrudActionService` (fila+masiva), 2 endpoints, `CrudActionHandlerInterface`, render en index/show con RBAC + `visible_when`, barra bulk (checkboxes, JS vanilla), auditoría, tipos `handler`/`link`/`builtin`.
- **Fase 2 — Estados/transiciones**: `CrudStateMachine`, `CrudTransitionService`, acción `type: transition`, `CrudTransitionGuardInterface`, bitácora `crud.transition`, badges desde `states`, render en header de detalle.
- **Fase 3 — Validaciones**: mensajes custom, `unique`/`exists` vía `CrudDbConstraintValidator`, validadores de formulario externos (`CrudValidatorInterface` + `form.validators`), validación de tablas destino en config.
- **Fase 4 — Relaciones + tabs**: `CrudRelationDefinition` + `CrudRelationService`, campo `type: relation` (resuelve producto→categoría), `hasMany` children, `CrudTabDefinition` + `CrudDetailBuilder`, reescritura de `show.php` con nav-tabs, validación de config de relations/tabs.

Cada fase es un checkpoint válido para commit / paralelización.

---

## 8. Riesgos y puntos que podrían romper compatibilidad

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Firma de hooks cambia (`array`→contexto) | Media | `crud_handlers.php` vacío hoy → ningún handler real se rompe; se actualizan `AbstractCrudHookHandler` + doc |
| Reescritura de `show.php` regresa módulos simples | Media | Sin bloque `detail` → tab "general" idéntico al actual; prueba snapshot/manual de los JSON existentes |
| Router: nuevas rutas dinámicas chocan con `/editar`/`/eliminar` | Media | Segmento `accion`/`accion-masiva` las separa; tests de matching/precedencia |
| `belongsTo.filter` o `exists` apuntando a tablas bloqueadas o SQL crudo | Alta | `filter` estructurado (`{col:val}`); config valida prefijos `dom_*` y existencia tabla/columna |
| Tab `component` con path traversal | Alta | Vista whitelisteada bajo `Views/`, normalización de ruta, sin input de usuario |
| Bulk: fallos parciales / coste | Media | Best-effort por ítem + resumen en flash; tope de `ids`; RBAC por ítem |
| `visible_when` burlado desde la UI | Alta | Re-chequeo server-side en `CrudActionService` antes de ejecutar |
| Crecimiento de bindings/servicios `Crud*` | Baja | Additivo; se registra en friction log para el futuro CLI |
| Sin sistema de migraciones formal | Media | Cambios de metadata no tocan DB; nuevas tablas de demo siguen patrón `database/migrations/*` |

---

## 9. Ejemplos concretos de metadata

### 9.1 Acciones (fila + bulk) con estados
```json
"actions": {
  "row": [
    { "name": "show",   "type": "builtin" },
    { "name": "edit",   "type": "builtin" },
    { "name": "delete", "type": "builtin" },

    { "name": "autorizar", "type": "transition", "to": "autorizado",
      "label": "Autorizar", "icon": "bi-check2-circle", "method": "POST",
      "permission": "eventos.autorizar", "confirm": "¿Autorizar este evento?",
      "visible_when": { "status": "pendiente" }, "guard": "evento_autorizacion" },

    { "name": "rechazar", "type": "transition", "to": "rechazado",
      "label": "Rechazar", "icon": "bi-x-circle", "method": "POST",
      "permission": "eventos.rechazar", "confirm": "¿Rechazar este evento?",
      "visible_when": { "status": "pendiente" } },

    { "name": "contrato", "type": "link",
      "label": "Ver contrato", "icon": "bi-file-earmark-text", "method": "GET",
      "route": "/admin/eventos/{id}/contrato", "permission": "eventos.contrato.ver" },

    { "name": "regenerar_doc", "type": "handler", "handler": "evento_regenerar_doc",
      "label": "Regenerar documento", "icon": "bi-arrow-repeat", "method": "POST",
      "permission": "eventos.doc.regenerar", "confirm": "¿Regenerar el documento?" }
  ],
  "bulk": [
    { "name": "activar",  "type": "handler", "handler": "eventos_bulk_estado",
      "label": "Activar", "permission": "eventos.editar", "confirm": "¿Activar seleccionados?" },
    { "name": "exportar", "type": "link", "route": "/admin/crud/eventos/exportar",
      "label": "Exportar CSV", "permission": "eventos.ver" }
  ]
}
```

### 9.2 Estados
```json
"states": {
  "column": "status",
  "values": {
    "pendiente":  { "label": "Pendiente",  "badge": "warning" },
    "autorizado": { "label": "Autorizado", "badge": "success" },
    "rechazado":  { "label": "Rechazado",  "badge": "danger" }
  },
  "transitions": {
    "pendiente":  ["autorizado", "rechazado"],
    "autorizado": [],
    "rechazado":  []
  }
}
```

### 9.3 Validaciones (simples + DB + formulario)
```json
"form": {
  "fields": [
    { "name": "codigo", "label": "Código", "type": "text", "required": true,
      "validation": {
        "maxlength": 60,
        "unique": { "ignore_self": true },
        "regex": "/^[A-Z0-9_-]+$/",
        "messages": { "required": "El código es obligatorio", "unique": "Ese código ya existe" }
      }
    },
    { "name": "categoria_id", "label": "Categoría", "type": "relation", "relation": "categoria",
      "required": true,
      "validation": { "exists": { "table": "dom_inv_categorias", "column": "id" } }
    }
  ],
  "validators": ["anticipo_minimo", "fecha_disponible"]
}
```

### 9.4 Relaciones
```json
"relations": {
  "categoria": { "type": "belongsTo", "table": "dom_inv_categorias",
    "foreign_key": "categoria_id", "value": "id", "label": "nombre",
    "filter": { "activa": 1 }, "order_by": "nombre" },

  "abonos": { "type": "hasMany", "table": "dom_eventos_abonos",
    "foreign_key": "evento_id",
    "columns": [
      { "name": "monto",      "label": "Monto", "format": "money" },
      { "name": "created_at", "label": "Fecha", "format": "datetime" }
    ],
    "order_by": "created_at", "direction": "DESC", "limit": 50 }
}
```

### 9.5 Detalle con tabs
```json
"detail": {
  "tabs": [
    { "key": "general",   "label": "Datos generales", "type": "fields",
      "columns": ["nombre","email","status","created_at"] },
    { "key": "abonos",    "label": "Abonos",     "type": "relation", "relation": "abonos" },
    { "key": "archivos",  "label": "Archivos",   "type": "component", "view": "admin/eventos/tabs/archivos" },
    { "key": "historial", "label": "Historial",  "type": "history" }
  ]
}
```

### 9.6 Handler de acción (escape hatch, ejemplo ilustrativo)
```php
// config/crud_handlers.php
return [
    'evento_autorizacion'   => \App\Application\Crud\Handlers\EventoAutorizacionGuard::class,
    'evento_regenerar_doc'  => \App\Application\Crud\Handlers\EventoRegenerarDocHandler::class,
    'eventos_bulk_estado'   => \App\Application\Crud\Handlers\EventosBulkEstadoHandler::class,
    'anticipo_minimo'       => \App\Application\Crud\Handlers\AnticipoMinimoValidator::class,
    'fecha_disponible'      => \App\Application\Crud\Handlers\FechaDisponibleValidator::class,
];

// El core solo invoca; la lógica vive aquí:
final class EventoRegenerarDocHandler implements CrudActionHandlerInterface {
    public function handle(CrudActionContext $ctx): void { /* genera doc usando repos del módulo */ }
}
```

---

## 10. Checklist de pruebas

### Unit
- [ ] `CrudStateMachine`: transición válida / inválida; estado terminal sin salidas.
- [ ] `CrudFieldValidationService`: mensaje custom sobrescribe el default.
- [ ] `CrudDbConstraintValidator`: `unique` con `ignore_self` en update; `exists` ok/fallo.
- [ ] `visible_when`/`enabled_when`: igualdad simple y lista de valores.
- [ ] `CrudHandlerRegistry::resolve`: rechaza clase que no implementa la interfaz pedida.
- [ ] Resolución de `permission` (slug completo vs sufijo + prefix).

### Integración
- [ ] `GenericCrudRepository`: `existsWhere`, `distinctOptions`, `childrenBy`.
- [ ] Endpoint acción: RBAC denegado/permitido, CSRF ausente bloquea, fila de bitácora escrita.
- [ ] Endpoint bulk: mezcla éxito/fallo → resumen correcto; RBAC por ítem.
- [ ] Endpoint transición: transición inválida bloqueada; guard que lanza bloquea; `crud.transition` registrado.
- [ ] Relación: options de select belongsTo; children hasMany.

### Compatibilidad (regresión)
- [ ] Los JSON actuales (`clientes`, `demo_*`) cargan y renderizan igual.
- [ ] `show` sin `detail` = salida previa.
- [ ] create/update siguen disparando hooks y persistiendo.
- [ ] **`beforeCreate` mutando `data` cambia lo persistido** (verifica el read-back).

### Seguridad
- [ ] Tabla con prefijo bloqueado en `exists`/relación → rechazada en validación de config.
- [ ] Tab `component` con `../` → rechazada.
- [ ] Acción sobre registro oculto por `visible_when` → 403/422.
- [ ] CSRF ausente en acción/bulk/transición → bloqueado.

### Manual / golden path
- [ ] Autorizar/rechazar un registro con estados.
- [ ] Bulk activar varios.
- [ ] Producto con select de categoría (belongsTo).
- [ ] Detalle con tab de abonos (hasMany) + tab historial (bitácora).

---

## 11. No-goals explícitos
- `manyToMany` (diferido a una fase posterior).
- Tabla dedicada de historial de transiciones (se usa `log_bitacora`).
- Constructor visual de CRUDs / versionado de configuraciones.
- Sistema de migraciones (decisión separada; este spec no lo introduce).
- Reorganización de los bindings existentes de `config/container.php` (solo se añaden nuevos).
- Edición/alta inline de hijos en tabs (los `hasMany` son read-only en esta fase).
