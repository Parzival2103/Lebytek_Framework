# Módulo Calendario

Capa **opcional y de solo-lectura** sobre el CRUD Engine. Renderiza recursos CRUD
existentes como vistas de calendario (mes / semana / día / tabla) y aporta un
widget mini-calendario al dashboard. No introduce lógica de dominio ni endpoints
de escritura propios: toda edición reutiliza los endpoints CRUD existentes.

> Estado: módulo `calendario` (manifiesto `config/modules/calendario.php`).
> Requiere los módulos `core` y `crud-engine`.

---

## 1. Concepto

Un **calendario** se define en `config/calendars/{key}.json` y apunta a un
recurso CRUD por su `key`. Hereda del recurso: tabla, permisos, scope (row-level),
estados y formularios. El feed de eventos consulta filas por rango de fechas
aplicando el **mismo scope** que el listado CRUD, las normaliza a eventos y las
sirve como JSON a un componente JS propio (`public/assets/js/calendar.js`).

```
config/calendars/{key}.json ──► CalendarConfigLoader ──► CalendarDefinition
                                        │
config/cruds/{recurso}.json ───────────┘ (columnas, prefijo de permisos, estados)

GET /admin/calendario/{key}          → shell (toolbar + vistas + leyenda)
GET /admin/calendario/{key}/eventos  → JSON { eventos: [...] } (scoped + filtrado)
```

---

## 2. Esquema de `config/calendars/{key}.json`

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `calendar.key` | string | sí | Debe coincidir con el nombre del archivo. |
| `calendar.title` | string | sí | Título mostrado. |
| `calendar.resource` | string | sí | `key` del recurso CRUD subyacente (`config/cruds/`). |
| `calendar.icon` | string | no | Icono Bootstrap (por defecto `bi-calendar3`). |
| `mapping.start` | string | sí | Columna de fecha/hora de inicio. Debe existir en el recurso. |
| `mapping.end` | string | no | Columna de fin (puede ser nula). |
| `mapping.all_day` | bool | no | Fuerza eventos de día completo. Si se omite, se infiere por ausencia de hora. |
| `mapping.title` | string | sí | Plantilla del título con marcadores `{columna}`. |
| `mapping.color.by` | enum | no | `estado` \| `field` \| `fixed` (por defecto `fixed`). |
| `mapping.color.map` | objeto | cond. | Para `by=estado`: `valor → tono` Bootstrap. |
| `mapping.color.field` | string | cond. | Para `by=field`: columna que contiene el tono. |
| `mapping.color.value` | string | cond. | Para `by=fixed`: tono fijo (por defecto `primary`). |
| `views.default` | enum | sí | Vista inicial; debe estar en `views.enabled`. |
| `views.enabled` | lista | sí | Subconjunto de `month`, `week`, `day`, `table`. |
| `interaction.create_on_slot` | bool | no | Permite crear desde un día/columna vacía. |
| `interaction.open_detail` | bool | no | Muestra "Ver" en el popover (por defecto `true`). |
| `interaction.edit_from_event` | bool | no | Muestra "Editar" en el popover. |
| `filters[]` | lista | no | `{ field, label }` — filtros de igualdad sobre columnas declaradas. |
| `dashboard_widget` | bool | no | Expone el widget mini-calendario en el dashboard. |

### Ejemplo (`config/calendars/demo_citas.json`)

```json
{
  "calendar": { "key": "demo_citas", "title": "Agenda de Citas", "resource": "demo_citas", "icon": "bi-calendar-event" },
  "mapping": {
    "start": "fecha_inicio", "end": "fecha_fin", "all_day": false,
    "title": "{cliente} — {servicio}",
    "color": { "by": "estado", "map": { "pendiente": "warning", "confirmada": "success", "cancelada": "secondary" } }
  },
  "views": { "default": "month", "enabled": ["month", "week", "day", "table"] },
  "interaction": { "create_on_slot": true, "open_detail": true, "edit_from_event": true },
  "filters": [ { "field": "estado", "label": "Estado" } ],
  "dashboard_widget": true
}
```

---

## 3. Las cuatro vistas

- **month** — rejilla mensual (semanas completas, lunes a domingo). Hasta 3 píldoras
  por día + "+N más".
- **week** — 7 columnas (una por día) con eventos ordenados por hora.
- **day** — una columna con los eventos del día.
- **table** — lista ordenada por fecha (inicio / fin).

La navegación (anterior / hoy / siguiente) recalcula el rango según la vista y
re-consulta el feed. El selector de vista cambia el render sin recargar la página.

---

## 4. Modelo de interacción y RBAC

El calendario **no añade endpoints de escritura**. Las capacidades se derivan de
los permisos del recurso CRUD (`{prefijo}.ver|crear|editar|eliminar`) y de los
flags de `interaction`:

| Acción | Condición | Destino |
|---|---|---|
| Ver detalle | `open_detail` + permiso `ver` | `/admin/crud/{recurso}/{id}` |
| Editar | `edit_from_event` + permiso `editar` | `/admin/crud/{recurso}/{id}/editar` |
| Eliminar | permiso `eliminar` | `POST /admin/crud/{recurso}/{id}/eliminar` (CSRF + `#confirmModal`) |
| Crear en slot | `create_on_slot` + permiso `crear` | `/admin/crud/{recurso}/crear?{start}=YYYY-MM-DD` |

El borrado reutiliza el **modal de confirmación global** (`#confirmModal`) mediante
un `<form data-confirm>` con token CSRF; no usa `window.confirm`. La precarga de la
fecha al crear usa `CrudResourceService::buildCreateData($resource, $prefill)`, que
acota el prefill a columnas de formulario declaradas.

El feed (`/eventos`) aplica el permiso `ver`, el **scope row-level** del recurso
(`CrudResourceService::eventosCalendario` → `CrudDataService::eventsInRange`,
mismo mecanismo que `buildIndexData`) y los filtros declarados (`f_campo`).

---

## 5. Integración con el dashboard (slot `widgets`)

El contrato de contribución del dashboard expone un slot **`widgets`**
(retrocompatible, por defecto `[]`):

```php
new DashboardContribution(
    kpis: [], activityItems: [], quickAccess: [], statusBlock: null,
    widgets: [['partial' => 'dashboard/calendar_mini', 'data' => ['key' => 'demo_citas', ...]]]
);
```

`BuildDashboardViewModelUseCase` fusiona los `widgets` de todos los proveedores y
`DashboardViewModel::$widgets` los expone. La vista `admin/dashboard/index.php`
los renderiza con una **whitelist** (solo parciales bajo `dashboard/`, sin
recorridos de ruta, archivo existente).

`CalendarDashboardProvider` (prioridad 60) aporta un widget
`dashboard/calendar_mini` por cada calendario con `dashboard_widget=true` que el
usuario pueda ver. El partial es **solo vista**: mini rejilla del mes actual,
enlazada al calendario completo, con puntos en los días que tienen eventos.

---

## 6. Instalación

1. El módulo `calendario` se declara en `config/modules/calendario.php`
   (`bootstrap_sql: database/schema/modules/calendario.sql`, `requiere: [core, crud-engine]`).
2. El bootstrap SQL crea la tabla demo `dom_demo_citas`, los permisos
   `demo_citas.*`, su asignación al rol `administrador`, la entrada de menú a
   `/admin/calendario/demo_citas` y citas de ejemplo en el mes actual.
3. El toggle `modules.calendario` en `config/vertical.php` habilita/inhabilita la
   entrada de menú (por defecto activo).

---

## 7. Añadir un calendario propio

1. Asegura un recurso CRUD con columnas de fecha (ver `docs/modules/uso-de-modulo-dominio.md`).
2. Crea `config/calendars/{key}.json` con el esquema de la sección 2.
3. (Opcional) `dashboard_widget: true` para el widget del dashboard.
4. Añade una entrada de menú a `/admin/calendario/{key}` y los permisos del recurso.

No se requiere código: el controlador, las rutas y el JS son genéricos.

---

## 8. Arquitectura (archivos)

| Capa | Archivo |
|---|---|
| Domain | `app/Domain/Calendar/DateRange.php`, `CalendarEvent.php`, `Entities/CalendarDefinition.php`, `Interfaces/CalendarEventSourceInterface.php` |
| Application | `Services/CalendarConfigValidator.php`, `CalendarConfigLoader.php`, `CalendarEventMapper.php`, `CalendarViewModelBuilder.php`, `UseCases/Calendar/ListarEventosCalendarioUseCase.php` |
| Infrastructure | `Repositories/GenericCrudRepository::selectInDateRange`, `Dashboard/CalendarDashboardProvider.php` |
| Presentation | `Controllers/Admin/CalendarioController.php`, `Views/admin/calendario/index.php`, `Views/partials/dashboard/calendar_mini.php` |
| Assets | `public/assets/js/calendar.js`, estilos `.lebytek-calendar*` en `lebytek-ui.css` |
| Config / schema | `config/calendars/demo_citas.json`, `config/modules/calendario.php`, `database/schema/modules/calendario.sql` |

**Principios:** la lógica de scope vive una sola vez en `CrudDataService`
(compartida por `list()` y `eventsInRange()`); el calendario nunca arma SQL ni
reimplementa permisos. Sin drag&drop, recurrencia ni multi-fuente (fuera de alcance).
