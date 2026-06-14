# Diseño: Interfaz visual del módulo Reportes (builder + modelo de datos)

> Fecha: 2026-06-14
> Estado: spec aprobado (brainstorming) — pendiente de plan de implementación.
> Extiende y detalla las Fases 1–2 del spec previo
> `docs/superpowers/specs/2026-06-13-modulo-reportes-pdf-design.md`.
> El módulo `pdf-kit` (Fase 0) ya está implementado; aquí se construye la capa
> `reportes` encima, con foco en la **interfaz de construcción** y su modelo de
> datos/tratamientos/periodo.
> Patrón de referencia: módulo Calendario (`docs/modules/modulo-calendario.md`) —
> capa opcional y desacoplada que consume el CRUD Engine read-only.

---

## 1. Resumen

El `pdf-kit` rinde documentos pero, por sí solo, "se ve limitado": no existe UI para
que el usuario final componga reportes. Este spec define esa capa: el módulo
**`reportes`**, una vista **estilo CRUD Engine** que lista reportes guardados y un
**asistente por pasos (wizard)** que arma un reporte a partir de una **plantilla** y
una **fuente reportable** que el programador habilitó.

Mecánica central: **el usuario solo configura datos y elige plantilla**; el HTML/diseño
del PDF es 100% del programador. La plantilla elegida define **qué pasos pide el
wizard** ("según la plantilla, te pide distintos datos").

### Decisiones fijadas (brainstorming 2026-06-14)

1. **Modelo de datos: catálogo de fuentes sobre CRUD.** El programador registra
   fuentes en `config/reportes/{key}.json`; cada una apunta a un recurso CRUD y declara
   columnas, relaciones, tratamientos, filtros y periodo permitidos. Los datos se leen
   vía `CrudDataService` (hereda scope, permisos, filtros). **Sin SQL nuevo.**
2. **Builder = asistente por pasos (wizard).** Los pasos aparecen/desaparecen según la
   plantilla. (Descartado el enfoque de página única con preview en vivo.)
3. **Tratamientos por columna v1:** Agrupar por · Sumar/Contar/Promedio · Mín/Máx · Ordenar.
4. **Periodo v1:** Relativos (Hoy/Semana/Mes/Año) · Anteriores (Ayer/Mes pasado/Año pasado)
   · Sin periodo/Todo **con `max_rows` obligatorio**. Sin rango personalizado en v1.
5. **4 plantillas demo:** `tabla_estadistica` (colección), `ticket_compra`, `presupuesto`,
   `contrato` (registro).
6. **Índice estilo CRUD Engine** mediante vista a medida (no un recurso `config/cruds/`
   real), reutilizando partials y estilos responsive del CRUD Engine.

---

## 2. Vista índice — `/admin/reportes`

Listado tipo CRUD de los reportes **guardados** del usuario (propios + compartidos +
predeterminados demo). Vista a medida (`ReportesController::index`) que reutiliza los
partials y estilos del CRUD Engine, no un recurso `config/cruds/`.

- Tabla responsive con filas clickeables (igual que el trabajo responsive reciente del
  CRUD: fila entera clickeable en móvil).
- Columnas: **Nombre · Fuente · Plantilla · Modo · Compartido · Actualizado**.
- Acciones por fila: **Generar PDF** (primario), **Editar**, **Eliminar**
  (vía `#confirmModal`, CSRF).
- Botón **"Nuevo reporte"** → abre el wizard (`/admin/reportes/crear`).
- **Reportes predeterminados** = reportes demo sembrados (compartidos) que aparecen
  listados desde el inicio y se regeneran con un clic.
- Scope **owner**: cada quien ve los suyos; `compartido=1` los publica a quien tenga
  `reportes.ver`; el acceso directo a un reporte ajeno no compartido devuelve **404**
  (no revela su existencia).

---

## 3. El wizard de creación/edición

`/admin/reportes/crear` y `/admin/reportes/{id}/editar` renderizan
`admin/reportes/builder.php`. Pasos **condicionados por la plantilla**:

1. **Plantilla** — galería de plantillas disponibles (las 4 demo + las del programador),
   cada una con su modo (`coleccion`/`registro`) e ícono. La elección define los pasos
   siguientes.
2. **Fuente** — solo fuentes compatibles con el modo de la plantilla. (En modo
   `registro`, fuente y registro llegan desde la ficha CRUD; ver §6.2, este paso se omite.)
3. **Columnas** — checklist de las columnas expuestas por la fuente, reordenables.
4. **Tratamientos** — por columna: agrupar / sumar / contar / promedio / mín / máx /
   ordenar; **solo se ofrecen los válidos por tipo de dato** y por lo expuesto en la
   fuente. Paso visible solo si la plantilla declara `permite_tratamientos`.
5. **Filtros** — filtros declarados por la fuente (estado, etc.).
6. **Periodo** — presets de periodo; visible solo si la fuente declara `period.field` y
   la plantilla declara `requiere_periodo`.
7. **Detalles y guardar** — nombre del reporte, título del documento, orientación,
   bandera `compartido`.

Cada paso valida contra el `expose` de la fuente (server-side al guardar; client-side
para UX). La plantilla expone un **schema de pasos** que decide la visibilidad:
`modo`, `requiere_periodo`, `permite_tratamientos`, `min_columnas`, `max_columnas`.

### 3.1 Schema de pasos de la plantilla

`PdfTemplateInterface` ya tiene `supports(string $mode)`. Para el wizard se añade un
contrato ligero (sin romper el kit) que la capa `reportes` consulta:

```php
interface ReporteTemplateInterface extends PdfTemplateInterface
{
    /** @return array{modo:string,requiere_periodo:bool,permite_tratamientos:bool,min_columnas:int,max_columnas:int} */
    public function schemaPasos(): array;
}
```

Las plantillas demo de colección implementan esta interfaz extendida; las de registro
solo necesitan `PdfTemplateInterface` (su builder no usa los pasos de columnas).

---

## 4. Config del programador — `config/reportes/{key}.json`

Una **fuente reportable** referencia un recurso CRUD y declara qué queda expuesto,
ampliando el `expose` del spec previo con tratamientos y periodo:

```json
{
  "fuente": { "key": "pedidos", "title": "Pedidos", "resource": "demo_pedidos", "icon": "bi-receipt" },
  "modos": ["coleccion", "registro"],
  "expose": {
    "columns": [
      { "name": "id",      "label": "N°",      "type": "text" },
      { "name": "cliente", "label": "Cliente", "type": "text" },
      { "name": "estado",  "label": "Estado",  "type": "text" },
      { "name": "fecha",   "label": "Fecha",   "type": "date" },
      { "name": "total",   "label": "Total",   "type": "money", "treatments": ["sum","avg","min","max"] }
    ],
    "relations": [
      { "name": "cliente_rel", "type": "belongsTo", "label": "Cliente" },
      { "name": "items",       "type": "hasMany",   "label": "Ítems" }
    ],
    "group_by": ["cliente", "estado"],
    "order_by": ["total", "fecha"],
    "filters":  [ { "field": "estado", "label": "Estado" } ],
    "period":   { "field": "fecha", "label": "Fecha",
                  "presets": ["hoy","semana","mes","anio","ayer","mes_pasado","anio_pasado","todo"] },
    "max_rows": 5000
  },
  "templates": {
    "coleccion": ["tabla_estadistica"],
    "registro":  ["ticket_compra", "presupuesto", "contrato"]
  }
}
```

### 4.1 Reglas de validación (`ReporteConfigValidator`, espejo de `CalendarConfigValidator`)

- `resource` debe existir en `config/cruds/`.
- Columnas / filtros / `group_by` / `order_by` / `period.field` / relaciones expuestas
  deben existir en el recurso y **no** ser columnas protegidas.
- `treatments` por columna deben ser válidos para el `type` de la columna
  (p.ej. `sum/avg` solo sobre numéricas/dinero; `min/max` sobre numéricas o fechas).
- `period.presets` ⊆ vocabulario soportado (§5.3).
- `templates.*` deben estar en la whitelist `config/pdf_templates.php` y soportar el modo.
- `max_rows` obligatorio (guarda de coste; aplica especialmente al preset `todo`).

---

## 5. Modelo de tratamientos y periodo

### 5.1 Tratamientos por columna (v1)

| Tratamiento | Aplica a | Semántica |
|---|---|---|
| `group_by`        | cualquier columna de `expose.group_by` | Define las filas del reporte agregado. |
| `sum` / `count` / `avg` | numérica / dinero (count: cualquiera) | Agregación; reutiliza `list.summaries`/`list.aggregation` del CRUD Engine con sus guardas de coste. |
| `min` / `max`     | numérica o fecha | Valor extremo. |
| `order_by`        | columna de `expose.order_by` | Orden asc/desc, independiente de la agrupación. |

Cuando hay `group_by`, las columnas sin agregación deben formar parte del agrupamiento;
el validador del builder lo fuerza (no se permiten columnas "sueltas" en un reporte
agregado). Si no hay `group_by`, el reporte es un listado plano (cada fila = un registro).

### 5.2 Resolución en datos

`BuildReporteDataUseCase` traduce la selección guardada a una lectura de
`CrudDataService` con scope del usuario, y **agrega en PHP** (no SQL nuevo) cuando hay
`group_by`/agregaciones, reutilizando la semántica de `list.summaries` del CRUD Engine.
El resultado alimenta `PdfDataTable` (filas), `PdfTotalsBlock` (totales) y
`PdfIndicatorCard` (KPIs).

### 5.3 Periodo (v1)

Presets soportados (vocabulario cerrado), resueltos a un rango `[desde, hasta]` concreto
**al generar** sobre `period.field`:

- Relativos móviles: `hoy`, `semana`, `mes`, `anio`.
- Anteriores cerrados: `ayer`, `mes_pasado`, `anio_pasado`.
- `todo`: sin filtro temporal, **siempre** capeado por `expose.max_rows`.

Los presets relativos se recalculan en cada generación: un reporte guardado "de este mes"
trae siempre el mes vigente. **Sin rango personalizado en v1.**

---

## 6. Tabla `rep_reportes` y flujo de generación

### 6.1 Reporte guardado — `rep_reportes`

```
id, key, nombre, fuente_key, modo ('coleccion'|'registro'),
columnas JSON, tratamientos JSON, filtros JSON, periodo JSON,
opciones JSON (titulo, orientacion), template_key, compartido TINYINT(1),
status, deleted, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by
```

- Columnas de sistema estándar del CRUD Engine.
- Scope **owner** (§2). Prefijo `rep_*` (reservado para reportes en CLAUDE.md).
- **Re-validación al generar:** lo guardado se interseca con el `expose` vigente; la
  config del programador es la fuente de verdad. Si quitó una columna, desaparece del PDF.

### 6.2 Flujo de datos

**Colección:**
```
POST /admin/reportes/{id}/generar
 → carga ReporteGuardado (chequeo owner/compartido; ajeno no compartido → 404)
 → ReporteConfigLoader: selección guardada ∩ expose actual
 → BuildReporteDataUseCase:
     CrudDataService.list(recurso, filtros, scope=usuario actual)
     + resuelve periodo a [desde,hasta] sobre period.field
     + aplica group_by/agregaciones/order_by  + respeta max_rows
 → payload (filas, columnas, totales, KPIs, marca desde cfg_configuraciones)
 → PdfTemplateRegistry resuelve template_key → compose() → PdfDocument
 → PdfRenderingService.render → bytes → descarga (+ log 'reporte.generar' en log_bitacora)
```

**Registro único (ticket / presupuesto / contrato):** disparado desde el CRUD como
acción de fila declarativa (`actions.row` tipo `link` en `config/cruds/{recurso}.json`):
```
GET /admin/reportes/generar?fuente=pedidos&id=123&template=ticket_compra
 → CrudDataService.find(123) CON scope del usuario (si no lo ve → 404)
 → carga registro + relaciones declaradas (items hasMany, cliente belongsTo)
 → plantilla bespoke (ticket_compra) → compose → render → PDF
```

---

## 7. Plantillas demo (4)

Registradas en `config/pdf_templates.php` (whitelist clave → clase). Demo data enganchada
a recursos demo existentes (`demo_pedidos`, `demo_productos`).

| Clave | Modo | Componentes del kit que ejercita |
|---|---|---|
| `tabla_estadistica` | colección | `PdfHeader` + marca, `PdfDataTable` agrupada, `PdfTotalsBlock`, `PdfIndicatorCard` |
| `ticket_compra`     | registro  | `PdfLogo`, header compacto, `PdfDataTable` (ítems), total, `PdfFooter` |
| `presupuesto`       | registro  | datos cliente, `PdfDataTable` (conceptos), subtotal/impuestos/total, `PdfSignatureBlock` |
| `contrato`          | registro  | `PdfText` largo con datos del registro incrustados, `PdfSignatureBlock` |

`tabla_estadistica` implementa `ReporteTemplateInterface` (schema de pasos:
`permite_tratamientos=true`, `requiere_periodo=true`). Las de registro implementan solo
`PdfTemplateInterface`.

---

## 8. Capas (espejo del Calendario)

| Capa | Piezas |
|---|---|
| Domain | `ReporteFuente` (config), `ReporteGuardado` (entidad), `ReporteRepositoryInterface`, `ReporteTemplateInterface` |
| Application | `ReporteConfigLoader` + `ReporteConfigValidator`; `BuildReporteDataUseCase`; `GenerarReporteUseCase`; `CrearReporteUseCase` / `ActualizarReporteUseCase`; `PeriodoResolver` (preset → rango) |
| Infrastructure | `PdoReporteRepository` (`rep_reportes`); lectura de datos vía **`CrudDataService`** existente |
| Presentation | `ReportesController` (Admin) + vistas `admin/reportes/{index,builder}.php` + partials de pasos del wizard |

---

## 9. Rutas

```
GET    /admin/reportes                  (index: propios + compartidos + predeterminados)
GET    /admin/reportes/crear            (wizard)
POST   /admin/reportes                  (guardar)
GET    /admin/reportes/{id}/editar      (wizard precargado)
POST   /admin/reportes/{id}             (actualizar)
POST   /admin/reportes/{id}/eliminar    (borrado lógico, CSRF + #confirmModal)
POST   /admin/reportes/{id}/generar     (PDF de colección, CSRF)
GET    /admin/reportes/generar          (PDF de registro: ?fuente=&id=&template=)
```

---

## 10. RBAC

- **Permisos:** `reportes.{ver, crear, editar, eliminar, generar, compartir}`.
- **Datos siempre acotados por el recurso subyacente:** generar pasa por `CrudDataService`
  con permisos y row-level scope del usuario que ejecuta. Un PDF nunca contiene filas que
  el usuario no podría ver en el CRUD. Sin bypass privilegiado.
- **Re-validación server-side:** columnas/tratamientos/filtros/periodo guardados se
  intersectan con el `expose` vigente al generar.

---

## 11. Fases de entrega

- **Fase 1 — Reportes de colección + wizard.** Catálogo de fuentes (config + loader +
  validator), tabla `rep_reportes` + repo, **wizard** (plantilla → fuente → columnas →
  tratamientos → filtros → periodo → guardar), `BuildReporteDataUseCase` con
  tratamientos + `PeriodoResolver`, índice estilo CRUD, plantilla `tabla_estadistica`.
- **Fase 2 — Documentos de registro único.** Modo `registro`, acción CRUD
  "Generar documento", plantillas demo `ticket_compra`, `presupuesto`, `contrato`.
- **Fase 3 — Compartir + pulido.** Flag `compartido`, gestión de guardados
  (editar/eliminar), acceso rápido desde dashboard (slot widgets/quick access, opcional).

---

## 12. Instalación demo (idempotente)

`bootstrap_sql` del módulo `reportes` (espejo de `database/schema/modules/calendario.sql`):

- Tabla `rep_reportes`.
- Permisos `reportes.*` + asignación al rol `administrador`.
- Entrada de menú a `/admin/reportes` en `core_menu_items`.
- Reportes predeterminados de ejemplo enganchados a `demo_pedidos`/`demo_productos`
  (incluye al menos un reporte de colección `compartido=1` que aparece listado desde el
  arranque).

Plantillas demo = **clases PHP** registradas en `config/pdf_templates.php` (no filas).
`php scripts/install.php` sigue siendo seguro de re-ejecutar.

---

## 13. Pruebas (`php tests/run.php`, arnés plano)

- **Validator:** rechaza columnas/filtros/group_by/order_by/period/plantillas no
  existentes o no expuestos; rechaza tratamientos inválidos por tipo; exige `max_rows`.
- **PeriodoResolver:** cada preset resuelve al rango esperado (mes vigente, mes pasado,
  etc.); `todo` no filtra pero respeta `max_rows`.
- **BuildReporteDataUseCase:** respeta el row-level scope (un no-owner no obtiene filas
  ajenas); `group_by` + `sum` produce las filas/totales esperados; la intersección
  guardado ∩ expose elimina columnas retiradas.
- **Wizard / schema de pasos:** una plantilla sin `requiere_periodo` no expone el paso
  Periodo; una sin `permite_tratamientos` no expone Tratamientos.
- **RBAC:** generar sin permiso → denegado; registro ajeno → 404.

---

## 14. Manejo de errores

- Config inválida → falla al cargar con mensaje claro (no se registra ruta/menú), igual
  que el Calendario.
- Plantilla inexistente / incompatible con el modo → error controlado, no se genera.
- Agregación/“todo” supera `max_rows` → se acota a `max_rows` con aviso (mismo guard del
  CRUD); el PDF se genera con el subconjunto y una nota.
- dompdf falla → excepción capturada, flash de error, log operativo; nunca se descarga un
  PDF corrupto.

---

## 15. Fuera de alcance (YAGNI v1)

Editor WYSIWYG de plantillas, joins libres multi-tabla, reportes programados / por correo,
export a Excel/CSV (solo PDF), gráficos embebidos (charts), **rango de fechas
personalizado**, **vista previa en vivo** (enfoque B del builder, descartado). Se pueden
añadir como fases futuras sobre el mismo kit sin reescritura.

---

## 16. Principios

- El kit no sabe nada de CRUD; reportes nunca arma SQL ni reimplementa permisos (toda
  lectura pasa por `CrudDataService`, igual que el Calendario).
- Config (JSON del programador) es la fuente de verdad; lo guardado por el usuario se
  re-valida contra ella al generar.
- La **plantilla** gobierna qué pide el wizard (schema de pasos); el usuario solo elige
  datos, nunca HTML.
- Plantillas y handlers solo por whitelist (clave → clase), nunca FQCN en datos.
- Componentes atómicos del kit reutilizables por cualquier módulo.
