# Diseño — Módulo Calendario (capa sobre CRUD Engine)

**Fecha:** 2026-06-09
**Estado:** Diseño aprobado para planificación
**Origen:** Área de oportunidad #2 del informe `docs/audits/informe-capacidades-framework-examen-dominio-ficticio.md` ("Calendario como capacidad de plataforma o módulo reutilizable", prioridad alta).

---

## 1. Resumen

Módulo **opcional y habilitable desde la instalación** que añade vistas de calendario al panel administrativo. Un calendario es una **vista alternativa sobre un recurso CRUD existente**: no posee tabla, formularios, permisos ni scope propios — los hereda del recurso al que apunta. Su responsabilidad se limita a leer filas en un rango de fechas (con el scope del CRUD ya aplicado), normalizarlas a eventos y pintarlas en un componente de calendario propio de Lebytek.

El objetivo es dar al programador/IA que arma el sistema final **mucha flexibilidad declarativa** (mapeo de campos, vistas, colores, filtros, interacción) sin escribir código de dominio y **sin perder la marca/estilo de la UI Lebytek**.

### Decisiones de diseño (tomadas en brainstorming)

| Decisión | Elección |
|---|---|
| Arquitectura base | **Capa sobre CRUD Engine** (reutiliza tabla, permisos, scope, formularios, validaciones) |
| Frontend | **Componente propio Lebytek** (vanilla JS + Bootstrap 5, sin dependencias externas) |
| Config & multiplicidad | **`config/calendars/{key}.json` separado**, N calendarios por deploy, cada uno referencia un recurso CRUD |
| Granularidad temporal | **Configurable por calendario** (`timed` con rejilla horaria / `all_day` por fecha) |
| Interacciones de edición | Crear-desde-slot, ver detalle, editar/eliminar desde el evento. **Sin drag & drop** |
| Dashboard | Widget mini-calendario **solo-vista**, única acción: redirigir al calendario grande |

---

## 2. Arquitectura

**Tipo:** módulo `calendario`, `requiere: ['core', 'crud-engine']`, opcional (`obligatorio: false`).

**Flujo de petición:**

```
GET /admin/calendario/{key}              → CalendarioController::index  → shell HTML (toolbar + contenedor + leyenda)
GET /admin/calendario/{key}/eventos      → CalendarioController::events → JSON [{id,title,start,end,allDay,color,url}]
(interacciones de edición)               → reutilizan rutas CRUD existentes /admin/crud/{resource}/...
```

**Regla de dependencia:** el módulo respeta la cebolla de 5 capas. El calendario nunca consulta la BD directamente: delega en `GenericCrudRepository` (Infrastructure) a través de un caso de uso (Application), de modo que el **scope row-level** y el RBAC del recurso CRUD se aplican automáticamente.

---

## 3. Esquema de configuración — `config/calendars/{key}.json`

```json
{
  "calendar": {
    "key": "citas",
    "title": "Agenda de Citas",
    "resource": "demo_citas",
    "icon": "bi-calendar-event"
  },
  "mapping": {
    "start":   "fecha_inicio",
    "end":     "fecha_fin",
    "all_day": false,
    "title":   "{cliente} — {servicio}",
    "color": {
      "by": "estado",
      "map": { "pendiente": "warning", "confirmada": "success", "cancelada": "secondary" }
    }
  },
  "views":       { "default": "month", "enabled": ["month", "week", "day", "table"] },
  "interaction": { "create_on_slot": true, "open_detail": true, "edit_from_event": true },
  "filters":     [ { "field": "estado", "label": "Estado" } ]
}
```

### Contrato de campos

| Bloque | Campo | Regla |
|---|---|---|
| `calendar.key` | — | Único; usado en la ruta `/admin/calendario/{key}`. |
| `calendar.resource` | — | Debe existir como recurso CRUD. De él se heredan tabla, `permission_prefix`, scope, formularios. |
| `mapping.start` | columna | Requerida. `DATE` → all-day; `DATETIME` → timed. Es la columna del `BETWEEN` de rango. |
| `mapping.end` | columna | Opcional. Si falta, evento puntual (duración por defecto en vistas timed). |
| `mapping.all_day` | bool | Opcional. Fuerza modo; si se omite se infiere del tipo de `start`. |
| `mapping.title` | columna o plantilla | Plantilla con `{columna}`; cae a la PK si falta. |
| `mapping.color.by` | `estado` \| `field` \| `fixed` | `estado` reutiliza los badges del bloque `states` del recurso CRUD. |
| `views.enabled` | `month`,`week`,`day`,`table` | Lista blanca de vistas disponibles. `default` debe estar incluida. |
| `interaction.*` | bool | Gates de UI; la autorización real siempre la decide RBAC sobre el recurso. |
| `filters[]` | `{field,label}` | Filtros expuestos en la toolbar; aplicados como `WHERE` adicional. |

---

## 4. Vistas (layouts) e interacción

### Layouts

Cuatro vistas alternables desde una **toolbar** (prev / hoy / siguiente · selector de vista · filtros · leyenda de colores):

| Vista | Descripción |
|---|---|
| **Mensual** (`month`) | Rejilla de semanas; eventos como píldoras; indicador "+N más" por día con overflow. |
| **Semanal** (`week`) | `timed`: rejilla horaria de 7 columnas. `all_day`: lista por día. |
| **Diaria** (`day`) | Día único; rejilla horaria o lista según modo. |
| **Tabla** (`table`) | Lista plana ordenada por fecha (la "vista en tablas"); reutiliza la estética de tablas Lebytek. |

### Nivel de interacción (capacidad, no una vista aparte)

"Solo ver" vs "ver y editar" es una **capacidad derivada de RBAC** sobre el recurso CRUD, no un layout:

| Interacción | Gate config | Gate RBAC | Comportamiento |
|---|---|---|---|
| Ver / abrir detalle | `open_detail` | `{prefix}.ver` | Popover con datos + enlace a `show` del CRUD. Disponible siempre que se vea el calendario. |
| Crear desde slot vacío | `create_on_slot` | `{prefix}.crear` | Click en día/franja vacía abre form CRUD `crear` (modal) con la fecha precargada. |
| Editar / eliminar / acciones | `edit_from_event` | `{prefix}.editar` / `.eliminar` | Desde el popover: botones que abren el form de edición o disparan eliminar / transitions / handlers, reutilizando endpoints CRUD + `#confirmModal`. |

**Sin drag & drop / resize** para reprogramar — fuera de alcance en esta iteración.

---

## 5. Componentes por capa

| Capa | Pieza | Responsabilidad |
|---|---|---|
| **Presentation** | `CalendarioController` | `index` (render shell), `events` (JSON feed por rango). |
| | `admin/calendario/index.php` + partials | Shell, toolbar, leyenda, estado vacío. |
| | `public/assets/js/calendar.js` | Render de los 4 layouts, navegación, fetch de eventos, popover, disparo de modales CRUD. Vanilla JS + Bootstrap 5. |
| | estilos en `lebytek-ui.css` | Rejilla, píldoras, marcadores, leyenda — tokens del design system. |
| **Application** | `CalendarConfigLoader` | Carga y cachea `config/calendars/*.json`. |
| | `CalendarConfigValidator` | Valida el contrato (recurso existe, columnas de `mapping` existen, vistas/colores válidos). |
| | `ListarEventosCalendarioUseCase` | Orquesta consulta por rango + scope + mapeo a eventos. |
| | `CalendarEventMapper` | Normaliza fila → `CalendarEvent` (fecha, all-day, color, plantilla de título, url a `show`). |
| | `CalendarViewModel` | Datos del shell (vistas habilitadas, filtros, leyenda, capacidades según RBAC). |
| **Domain** | `CalendarEvent`, `DateRange` (value objects) | Modelo inmutable de evento y rango. |
| | regla de resolución de color | `estado`/`field`/`fixed` → tono Lebytek. |
| **Infrastructure** | `GenericCrudRepository` (reuso/extensión) | Consulta `BETWEEN` sobre `mapping.start` con el `WHERE` de scope CRUD inyectado. |
| **Config/Kernel** | `config/calendars/*.json`, `config/modules/calendario.php` | Definición de calendarios y manifiesto del módulo. |

---

## 6. Flujo de datos detallado

1. `GET /admin/calendario/{key}` → `CalendarioController::index`:
   - `CalendarConfigLoader` carga el calendario; resuelve el recurso CRUD asociado.
   - `RbacMiddleware` exige `{prefix}.ver` (permiso del recurso).
   - Render del shell con `CalendarViewModel` (vistas habilitadas, filtros, leyenda, capacidades según permisos del usuario).
2. `calendar.js` pide `GET /admin/calendario/{key}/eventos?desde=&hasta=&<filtros>`:
   - `ListarEventosCalendarioUseCase` consulta vía `GenericCrudRepository` **con el scope del CRUD aplicado** (un usuario solo ve sus filas si el recurso declara scope `owner`).
   - `CalendarEventMapper` produce `[{ id, title, start, end, allDay, color, url }]` → JSON.
3. Interacciones de edición → rutas CRUD existentes (`/admin/crud/{resource}/crear`, `/{id}/editar`, `/{id}/eliminar`, `/{id}/accion/{action}`), con CSRF y confirmación unificada.

---

## 7. Integración con Dashboard (widget mini-calendario)

### Requisito

Un contenedor reducido en el dashboard que muestre una **mini rejilla mensual con marcadores** en los días que tienen eventos. **Solo-vista, sin opciones**; su única acción es **redirigir al calendario grande** (`/admin/calendario/{key}`).

### Extensión necesaria del contrato de dashboard

`DashboardContribution` hoy solo expone `kpis`, `activityItems`, `quickAccess`, `statusBlock` — no hay slot para un widget arbitrario. Se añade un **slot opcional `widgets`** (lista de `{ partial, data }` con partial whitelisteado), siguiendo el mismo patrón que los tabs `component` del CRUD (sin permitir HTML/FQCN arbitrario desde config).

- `DashboardContribution` gana el parámetro `widgets` (default `[]`), retrocompatible.
- `BuildDashboardViewModelUseCase` concatena `widgets` de todos los proveedores.
- La vista del dashboard renderiza cada widget incluyendo su partial whitelisteado.

### Proveedor del calendario

`CalendarDashboardProvider implements DashboardContributionProviderInterface` (Infrastructure), registrado en `config/dashboard.php`:

- Lee qué calendario(s) exponer en dashboard (flag `dashboard_widget: true` en el config del calendario, o lista en el manifiesto del módulo).
- Respeta RBAC: si `!$context->tienePermiso('{prefix}.ver')` → `DashboardContribution::vacia()` (patrón de filtrado por permiso ya establecido).
- Aporta un widget `{ partial: 'dashboard/calendar_mini', data: { key, title, url, eventos_del_mes } }`.

### Partial `calendar_mini`

- Mini rejilla mensual (mes actual) con punto/contador en días con eventos; reutiliza el mismo feed/normalización de eventos.
- Todo el contenedor es un enlace a `/admin/calendario/{key}`. Sin navegación de meses ni interacción.

---

## 8. Instalación como módulo

- `config/modules/calendario.php`: manifiesto con `clave: 'calendario'`, `requiere: ['core', 'crud-engine']`, `obligatorio: false`, lista de `calendars`, `providers: [CalendarDashboardProvider::class]`, entradas de `menu`.
- Toggle en `config/vertical.php` → `modules.calendario`.
- Entradas de menú en `core_menu_items` (una por calendario), filtradas por RBAC (`{prefix}.ver`).
- **Sin permisos nuevos por defecto**: reutiliza los del recurso CRUD. (Opcional: documentar `{prefix}.ver` como gate del ítem de menú y del widget.)

---

## 9. Errores y validación

- `CalendarConfigValidator` al cargar: recurso CRUD existe; columnas de `mapping` (`start`, `end`, `title`, `color.by`) existen en la tabla del recurso; `views.enabled` válidas y `default` incluida; `color.map` con tonos válidos → error claro de administrador (mismo estándar que `CrudConfigValidator`).
- Sin permiso → `403` vía `RbacMiddleware`.
- Parámetros de rango inválidos/ausentes → cae al mes actual.
- Sin eventos en el rango → estado vacío con marca Lebytek.
- Plantilla de título con columna inexistente → se detecta en validación (no en runtime).

---

## 10. Pruebas

- **Unit:**
  - `CalendarConfigValidator`: recurso inexistente, columna de mapping inexistente, vista no soportada, `default` fuera de `enabled`, color inválido.
  - `CalendarEventMapper`: `DATE` vs `DATETIME` (all-day inferido), evento sin `end`, plantilla de título, resolución de color por `estado`/`field`/`fixed`, url a `show`.
  - `DateRange`: cálculo de rango por vista (mes/semana/día).
- **Integración:**
  - Endpoint `eventos` devuelve filas **scoped** en rango y respeta RBAC; forma del JSON correcta.
  - `CalendarDashboardProvider` devuelve `vacia()` sin permiso y widget con permiso.
- Espejo de la estructura existente: `tests/Calendar/` (Config, Mapper, Events, Dashboard).

---

## 11. Fases de implementación (para el plan)

1. **Núcleo de lectura:** config + `CalendarConfigLoader` + `CalendarConfigValidator` + `ListarEventosCalendarioUseCase` + `CalendarEventMapper` + endpoint `eventos` + **vista mensual** + **vista tabla** (solo lectura).
2. **Vistas temporales:** **semanal / diaria** (rejilla horaria `timed` + listas `all_day`) + selector de vista + filtros + leyenda.
3. **Interacciones de edición:** crear-en-slot, popover de evento con editar / eliminar / acciones (reutilizando endpoints CRUD + confirmación), gateadas por RBAC.
4. **Dashboard + módulo:** slot `widgets` en `DashboardContribution` + `CalendarDashboardProvider` + partial `calendar_mini`; manifiesto `config/modules/calendario.php` + instalación + menú + docs + calendario demo (`demo_citas`).

---

## 12. Fuera de alcance (YAGNI)

- Drag & drop / resize para reprogramar.
- Calendario sobre fuentes que no son recursos CRUD (vistas SQL crudas, joins, APIs) — el modelo es 1 calendario → 1 recurso CRUD. Un data-provider PHP whitelisteado quedaría para una iteración futura si surge la necesidad.
- Overlay de múltiples recursos en un mismo calendario.
- Recurrencia de eventos (RRULE), invitaciones, recordatorios.
- Personalización/persistencia de preferencias de vista por usuario.
- Navegación de meses dentro del widget de dashboard.
