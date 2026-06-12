# Informe de auditoría — Capacidades del framework (dominio ficticio)

**Fecha:** 2026-06-08  
**Alcance:** Evaluación de si el **Lebytek Framework** actual puede soportar flujos típicos de un sistema ficticio de examen, **sin implementar** tablas, migraciones ni código de dominio.  
**Metodología:** Revisión del código y documentación existentes (`CRUD Engine`, dashboard, RBAC, vistas, servicios).

---

## Resumen ejecutivo

| # | Pregunta | Veredicto | Conclusión breve |
|---|----------|-----------|------------------|
| 1 | Calendario poblado desde tabla | **No** | No hay componente, ruta ni widget de calendario en la plataforma. |
| 2 | Dashboard personalizable por perfil | **Parcial** | Extensible por proveedores; no hay layout/widgets por rol out-of-the-box. |
| 3 | CRUD filtrado por usuario creador | **Parcial** | RBAC por acción sí; row-level ownership no está cableado (interfaz existe sin uso). |
| 4 | Sumar columna al pie de tabla | **Parcial** | Motor de agregación sí; pie visible solo en vista agrupada (`group_by`). |
| 5 | Agrupar por semana/mes | **Parcial** | Solo `GROUP BY` columna literal; sin funciones de periodo (semana/mes). |
| 6 | Detalle CRUD con tablas relacionadas | **Sí (con límites)** | Tabs `fields`, `relation` (hasMany), `history`, `component`. |
| 7 | Acciones personalizadas en botones | **Sí** | `handler`, `transition`, `link` + RBAC, confirm, guards. |

**Leyenda:** **Sí** = usable hoy con configuración/código de extensión previsto. **Parcial** = base existe pero falta pieza clave. **No** = no hay soporte razonable.

---

## 1. ¿Existe manera de mostrar un calendario y poblarlo con datos desde una tabla?

### Veredicto: **No**

### Evidencia en el framework

- No hay vistas, partials, assets JS ni rutas admin que implementen un calendario (FullCalendar, similar, o propio).
- El dashboard fijo usa KPIs, actividad, accesos rápidos y estado (`admin/dashboard/index.php` + partials `partials/dashboard/*`).
- Bootstrap 5 está disponible, pero no hay abstracción de “vista calendario” en Application ni CRUD Engine.

### Qué haría falta

| Capa | Elemento ausente |
|------|------------------|
| **Presentation** | Vista `admin/<modulo>/calendario.php`, controlador con ruta GET, asset JS de calendario. |
| **Application** | Caso de uso `ListarEventosCalendarioUseCase` que devuelva eventos en formato `{ id, title, start, end, url? }`. |
| **Infrastructure** | Consulta a tabla `dom_*` (p. ej. por rango de fechas). |
| **Opcional API** | Endpoint JSON `/api/eventos/calendario?desde=&hasta=` para alimentar el front. |

### Implementación recomendada

1. Módulo vertical clásico (controlador + vista + repositorio), **o**
2. Widget de dashboard como `DashboardContributionProviderInterface` que solo muestre “próximos N eventos” (no calendario completo), **o**
3. Componente reutilizable en `Kernel`/partial `calendar.php` + convención de datos documentada.

**Prioridad sugerida:** media-alta si el dominio ficticio es operativo (eventos, citas, entregas).

---

## 2. ¿Existe manera de personalizar el dashboard principal y qué puede ver cada perfil?

### Veredicto: **Parcial**

### Lo que sí existe hoy

- **Arquitectura de proveedores:** [`DashboardContributionProviderInterface`](../../app/Domain/Interfaces/DashboardContributionProviderInterface.php) registrados en [`config/dashboard.php`](../../config/dashboard.php).
- **Fusión:** [`BuildDashboardViewModelUseCase`](../../app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php) concatena KPIs, actividad, accesos rápidos y bloque de estado.
- **Contexto con RBAC:** [`DashboardBuildContext`](../../app/Domain/Dashboard/DashboardBuildContext.php) expone `usuarioId`, `permisoSlugs`, `rolSlugs` y métodos `tienePermiso()` / `tieneRol()`.
- **Documentación con patrón:** [`docs/modules/modulo-dashboard.md`](../modules/modulo-dashboard.md) muestra que cada proveedor puede devolver `DashboardContribution::vacia()` si el usuario no tiene permiso.

### Lo que no existe hoy

- **Configuración declarativa** del tipo “rol X ve widgets A,B; rol Y ve C”.
- **Filtrado automático** de KPIs/enlaces: el proveedor por defecto [`DefaultPlatformDashboardProvider`](../../app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php) no filtra por permiso.
- **Personalización por usuario** (preferencias guardadas en BD).
- **Editor visual** de dashboard.

### Qué puede o no ver cada perfil hoy

| Mecanismo | Qué controla | Granularidad |
|-----------|--------------|--------------|
| `RbacMiddleware` + permisos | Acceso a rutas (`modulo.ver`) | Por módulo/acción |
| Menú (`core_menu_items` + RBAC) | Ítems de navegación | Por ítem de menú |
| Proveedor dashboard | Contenido del dashboard | **Manual en código PHP** por proveedor |
| `config/vertical.php` | Módulos habilitados en la instancia | Por deploy, no por usuario |

### Implementación recomendada

1. **Corto plazo:** En cada `DashboardContributionProvider`, usar `$context->tienePermiso()` antes de aportar KPIs/enlaces (patrón ya documentado).
2. **Mediano plazo:** Metadatos en config (`config/dashboard_widgets.php`) con `required_permission` por widget; un solo proveedor lee config y filtra.
3. **Largo plazo (opcional):** Tabla `cfg_dashboard_layout` por rol o usuario.

---

## 3. ¿CRUD filtrado por usuario que registró (admin ve todo, usuario solo lo suyo, sin eliminar)?

### Veredicto: **Parcial** (RBAC por acción sí; **row-level security no cableado**)

### Lo que sí existe hoy

- **RBAC por recurso:** permisos `{prefix}.ver`, `.crear`, `.editar`, `.eliminar` aplicados en [`CrudResourceService`](../../app/Application/Services/CrudResourceService.php) y acciones custom vía [`CrudActionResolver`](../../app/Application/Services/CrudActionResolver.php).
- **Auditoría de autor:** al crear, [`CrudDataService`](../../app/Application/Services/CrudDataService.php) asigna `created_by` automáticamente (columna protegida, no editable en formulario).
- **Ocultar eliminar:** no otorgar permiso `{prefix}.eliminar` al rol “usuario regular” → el botón delete no aparece (filtrado en resolver por `$can`).
- **Acciones condicionales:** `visible_when` / `enabled_when` en JSON de acciones (igualdad simple sobre columnas del registro).

### Lo que no existe hoy

- **Filtro automático** `WHERE created_by = :usuario` en listados CRUD.
- **Bloqueo server-side** en `show` / `edit` / `update` si el registro no pertenece al usuario (un usuario con `.ver` y URL directa podría ver/editar registros ajenos).
- **Cableado de [`CrudListScopeInterface`](../../app/Domain/Interfaces/CrudListScopeInterface.php):** la interfaz y [`CrudListContext`](../../app/Application/Crud/Context/CrudListContext.php) existen desde Fase 0, pero **ningún servicio del listado CRUD la invoca** (grep en `app/` sin referencias en `CrudDataService::list()`).

### Escenario examen (admin vs usuario regular)

| Requisito | ¿Se logra solo con config? | Notas |
|-----------|----------------------------|-------|
| Admin ve/modifica todos | Sí | Permisos completos al rol admin. |
| Usuario crea registros | Sí | Permiso `.crear`; `created_by` se guarda. |
| Usuario solo ve los suyos | **No** | Falta scope de listado + chequeo en show/edit. |
| Usuario no elimina | Sí | Quitar permiso `.eliminar` del rol. |

### Qué implementar

| Pieza | Ubicación sugerida |
|-------|-------------------|
| Declaración de scope en JSON CRUD | p. ej. `"list": { "scope": "owner" }` o `"scope_handler": "clientes_owner"` |
| Resolución en listado | `CrudDataService::list()` → instanciar `CrudListScopeInterface` vía `CrudHandlerRegistry` y aplicar condiciones al `$where` |
| Chequeo en lectura/escritura | `CrudResourceService::buildShowData` / `update` / `destroy` o policy en Application |
| Handler ejemplo | `OwnerListScope` que añade `` `created_by` = ? `` si no es administrador |
| Permiso atómico opcional | `{prefix}.ver_todos` para bypass de scope |

**Prioridad sugerida:** alta para multi-usuario con datos aislados.

---

## 4. ¿Se puede escoger una columna para sumar cantidades y mostrar al final de la tabla?

### Veredicto: **Parcial**

### Lo que sí existe hoy

- Configuración JSON **`list.summaries`** con `type: "sum"` o `"count"` y `column` (validado en [`CrudConfigValidator`](../../app/Application/Services/CrudConfigValidator.php)).
- Agregación SQL en [`GenericCrudRepository::selectGlobalAggregates`](../../app/Infrastructure/Repositories/GenericCrudRepository.php) y agrupada en `selectGroupedAggregates`.
- **Demo:** [`config/cruds/demo_pedidos.json`](../../config/cruds/demo_pedidos.json) suma `total`.

### Limitación importante (UI)

- El pie `<tfoot>` en [`admin/crud/index.php`](../../app/Presentation/Views/admin/crud/index.php) **solo se renderiza cuando `grouped === true`** (hay `list.group_by`).
- En listado **no agrupado**, `CrudDataService` **sí calcula** `summaryRow`, pero [`CrudTableBuilder`](../../app/Application/Services/CrudTableBuilder.php) no formatea ese pie salvo en modo agrupado → **totales globales no visibles** en la tabla plana habitual.

### Qué implementar

1. **Quick win:** Mostrar `summaryRow` en `<tfoot>` también cuando `!$grouped && !empty($summaryRow)` (Presentation + `CrudTableBuilder`).
2. **Opcional:** Columna virtual en filas vs. solo pie global (hoy el sum no aparece como columna en listado plano).

**Prioridad sugerida:** media (funcionalidad backend lista; falta capa de presentación).

---

## 5. ¿Se pueden agrupar por fecha para agrupar por semana o mes?

### Veredicto: **Parcial** (solo agrupación por valor de columna)

### Lo que sí existe hoy

- **`list.group_by`:** nombre de **una columna** existente en la tabla; SQL `GROUP BY` literal ([`GenericCrudRepository::selectGroupedAggregates`](../../app/Infrastructure/Repositories/GenericCrudRepository.php)).
- Con **`list.summaries`**, cada grupo muestra sumas/conteos.
- Vista agrupada sustituye columnas del listado por grupo + métricas agregadas.

### Lo que no existe hoy

- Expresiones **`DATE_FORMAT`**, **`YEARWEEK`**, **`MONTH`**, truncado de fecha en el motor CRUD.
- Agrupadores semánticos `"group_by_period": "week"` / `"month"`.
- Columnas calculadas en JSON.

### Workarounds posibles (sin cambiar core)

| Enfoque | Viabilidad | Coste |
|---------|------------|-------|
| Columna física `semana` / `mes` en tabla, mantenida por trigger o app | Sí | Alto mantenimiento |
| Vista SQL `dom_eventos_por_semana` como tabla del CRUD | Sí | Requiere vista en BD + recurso CRUD aparte |
| Agrupar por `DATE(fecha)` si la columna es DATE/DATETIME | Parcial | Un grupo por **día**, no por semana/mes |

### Qué implementar en el framework

- **`list.group_by_period`:** `{ "column": "fecha_evento", "granularity": "week|month|year" }` traducido a SQL seguro en repositorio.
- Validación en `CrudConfigValidator` (solo columnas datetime/date).
- Documentar interacción con acciones por fila (hoy desactivadas en vista agrupada).

**Prioridad sugerida:** media para reportes operativos.

---

## 6. ¿Modificar vista CRUD para mostrar más información en “ver registro” (relaciones: abonos, gastos, extras…)?

### Veredicto: **Sí, con límites de modelado**

### Lo que sí existe hoy

- Vista detalle [`admin/crud/show.php`](../../app/Presentation/Views/admin/crud/show.php) con pestañas.
- Bloque **`detail.tabs`** en JSON CRUD, construido por [`CrudDetailBuilder`](../../app/Application/Services/CrudDetailBuilder.php):

| Tipo tab | Uso | Ejemplo |
|----------|-----|---------|
| `fields` | Columnas del registro | Datos del evento |
| `relation` | Tabla hija **hasMany** | Items de pedido |
| `history` | Bitácora `log_bitacora` | Auditoría |
| `component` | Partial PHP whitelisteada | UI custom |

- **Demo completo:** [`demo_pedidos.json`](../../config/cruds/demo_pedidos.json) — tab `items` con relación `hasMany` `dom_demo_pedido_items`.

### Escenario examen (evento → abonos, gastos, extras)

**Factible declarando una relación hasMany por entidad hija:**

```json
"relations": {
  "abonos":  { "type": "hasMany", "table": "dom_abonos",  "foreign_key": "evento_id", "columns": [...] },
  "gastos":  { "type": "hasMany", "table": "dom_gastos",  "foreign_key": "evento_id", "columns": [...] },
  "extras":  { "type": "hasMany", "table": "dom_extras",  "foreign_key": "evento_id", "columns": [...] }
},
"detail": {
  "tabs": [
    { "key": "general", "type": "fields", "columns": [...] },
    { "key": "abonos", "type": "relation", "relation": "abonos" },
    { "key": "gastos", "type": "relation", "relation": "gastos" },
    { "key": "extras", "type": "relation", "relation": "extras" }
  ]
}
```

### Limitaciones

- **Un tab `relation` = una relación hasMany** (no varias tablas en un solo tab sin `component`).
- **`belongsTo`** se usa en formularios (select), no como grid en detalle salvo tab `fields` o `component`.
- **Sin CRUD anidado** en celdas de relación (solo lectura en tab; editar hijos requiere otro recurso CRUD o tab `component`).
- **Agregados en detalle** (total abonos vs total evento) requieren handler, guard o partial `component`.

### Qué implementar (mejoras opcionales)

- Tab tipo `relation_summary` con subtotales.
- Enlaces desde filas hijas al CRUD del hijo.
- Relaciones hasMany con acciones inline (fuera de scope actual).

**Prioridad sugerida:** baja-media (el 80% del examen se cubre con JSON + tablas `dom_*`).

---

## 7. ¿Se pueden incluir acciones personalizadas para los botones de acción?

### Veredicto: **Sí**

### Lo que existe hoy

Bloque **`actions.row`** / **`actions.bulk`** en JSON CRUD ([`modulo-crud-engine.md`](../modules/crud/modulo-crud-engine.md)):

| Tipo | Comportamiento |
|------|----------------|
| `builtin` | show / edit / delete |
| `handler` | POST → clase `CrudActionHandlerInterface` en [`config/crud_handlers.php`](../../config/crud_handlers.php) |
| `transition` | Cambio de estado validado por `CrudStateMachine` + guard opcional |
| `link` | Navegación GET a ruta custom |

**Metadatos por acción:** `label`, `icon`, `permission`, `confirm`, `visible_when`, `enabled_when`, `guard` (transitions).

**Orquestación:** [`CrudActionService`](../../app/Application/Services/CrudActionService.php) + [`CrudActionResolver`](../../app/Application/Services/CrudActionResolver.php).  
**UI:** [`partials/actions_row.php`](../../app/Presentation/Views/admin/crud/partials/actions_row.php) + confirmación unificada `#confirmModal`.

### Ejemplos reales en repo

- `demo_productos`: handler `toggle`, transitions `activar` / `desactivar`, bulk toggle.
- `demo_pedidos`: transitions `pagar` / `cancelar` con guard `demo_pedido_pagar_guard`.

### Limitaciones

- Handlers deben registrarse en whitelist PHP (no FQCN en JSON).
- Bulk solo tipo `handler` (no transitions bulk nativas).
- Lógica de negocio **no** va en el JSON; va en clases Application.

**Prioridad de mejora:** baja (capacidad madura).

---

## Áreas de oportunidad consolidadas

Priorizadas por impacto en sistemas ficticios tipo “operaciones + eventos + clientes”:

### Alta prioridad

1. **Row-level scope CRUD (`CrudListScopeInterface` operativo)**  
   - *Gap:* interfaz sin integración en list/show/edit.  
   - *Implementar:* cableado en `CrudDataService`, policy de ownership, declaración en JSON o handler registry.

2. **Calendario como capacidad de plataforma o módulo reutilizable**  
   - *Gap:* cero soporte.  
   - *Implementar:* vista + API de eventos + documentación en checklist de módulos.

### Media prioridad

3. **Pie de totales (`summaries`) en listado no agrupado**  
   - *Gap:* datos calculados, UI no los muestra.  
   - *Implementar:* ajuste en `CrudTableBuilder` + `index.php`.

4. **Agrupación temporal (semana/mes)**  
   - *Gap:* solo `GROUP BY` columna cruda.  
   - *Implementar:* `group_by_period` en CRUD Engine + SQL parametrizado.

5. **Dashboard: filtrado sistemático por permiso**  
   - *Gap:* depende de disciplina en cada proveedor.  
   - *Implementar:* config declarativa de widgets + filtro central; opcional layout por rol.

### Baja prioridad

6. **Detalle CRUD: relaciones más ricas** (subtotales, CRUD hijo embebido, belongsTo como card).  
7. **Personalización de dashboard por usuario** (preferencias en BD).

---

## Matriz “¿Puedo construir el dominio ficticio solo con lo actual?”

| Capacidad del dominio ficticio | ¿Listo hoy? | Esfuerzo extra típico |
|--------------------------------|-------------|------------------------|
| CRUD de clientes/eventos/catálogos | Sí | JSON + tablas `dom_*` + permisos + menú |
| Workflow con estados (pendiente/pagado/cancelado) | Sí | JSON `states` + transitions |
| Botones “Registrar abono”, “Cerrar evento” | Sí | handlers/transitions + permisos |
| Detalle con hijos (abonos, gastos) | Sí | relaciones hasMany + tabs |
| Usuario solo ve sus clientes | No (solo RBAC global) | Implementar list scope + policy |
| Calendario mensual de eventos | No | Módulo/vista nueva |
| Dashboard distinto por vendedor vs gerente | Parcial | Proveedores PHP filtrando por `$context` |
| Reporte suma ventas al pie del listado | Parcial | Activar `group_by` o completar UI de `summaryRow` |
| Ventas agrupadas por mes | No nativo | Columna mes, vista SQL, o extensión `group_by_period` |

---

## Referencias de código revisadas

| Tema | Archivos clave |
|------|----------------|
| CRUD listado/agregación | `CrudDataService.php`, `GenericCrudRepository.php`, `CrudTableBuilder.php` |
| CRUD detalle | `CrudDetailBuilder.php`, `show.php`, `demo_pedidos.json` |
| CRUD acciones | `CrudActionResolver.php`, `CrudActionService.php`, `actions_row.php` |
| RBAC | `RbacPolicy.php`, `RbacService.php`, `CrudResourceService.php` |
| Dashboard | `BuildDashboardViewModelUseCase.php`, `DashboardBuildContext.php`, `modulo-dashboard.md` |
| Scope listado (sin cablear) | `CrudListScopeInterface.php`, `CrudListContext.php` |
| Confirmaciones UI | `partials/confirm_modal.php`, `ConfirmForms` en `app.js` |

---

## Conclusión

El framework **cubre bien** un dominio ficticio basado en **CRUD declarativo**, **RBAC por acción**, **máquina de estados**, **detalle con relaciones hasMany** y **acciones custom**. Los huecos más relevantes para un examen de “sistema real multi-usuario” son:

1. **Aislamiento de datos por usuario** (scope de filas).  
2. **Calendario**.  
3. **Agregaciones UX** (totales al pie sin forzar `group_by`; agrupación por semana/mes).  
4. **Dashboard por perfil** (patrón existe; falta estandarizar y automatizar el filtrado).

Este informe no implica cambios en el repositorio más allá de la documentación aquí contenida.
