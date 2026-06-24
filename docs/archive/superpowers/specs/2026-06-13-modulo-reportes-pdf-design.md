# Diseño: Módulo Reportes + Kit de PDF

> Fecha: 2026-06-13
> Estado: spec aprobado (brainstorming) — pendiente de plan de implementación.
> Patrón de referencia: módulo Calendario (`docs/modules/modulo-calendario.md`) —
> capa opcional y desacoplada que consume el CRUD Engine read-only.

---

## 1. Resumen

Dos módulos opcionales y desacoplados, declarados como manifiestos en
`config/modules/*.php` y toggleables en `config/vertical.php`:

- **`pdf-kit` ("Kit de PDF")** — envoltorio endurecido de `dompdf/dompdf ^3.1`
  (ya presente en `composer.json`, aún sin cablear) + biblioteca de **componentes
  atómicos** de documento + `PdfRenderingService` + registro de plantillas. No
  depende del CRUD Engine. Cualquier módulo puede emitir un PDF usándolo.
- **`reportes` ("Reportes")** — sobre el kit y el CRUD Engine. El **programador**
  declara *fuentes reportables* (`config/reportes/{key}.json`) que exponen columnas,
  indicadores, filtros y plantillas sobre un recurso CRUD. El **usuario final** arma
  y **guarda** reportes (tabla `rep_reportes`), propios con opción de compartir, y
  los regenera como PDF con un botón.

El módulo cubre **dos modos** desde el inicio (por fases):

- **`coleccion`** — muchas filas de un recurso, filtradas, con indicadores/agregados
  (ej. "ventas del mes", listado con totales).
- **`registro`** — una sola fila renderizada como documento (ticket de compra,
  presupuesto, contrato) disparado desde la ficha del recurso CRUD.

### Decisiones de diseño fijadas (brainstorming)

1. **Ambos modos** (`coleccion` + `registro`) desde el inicio, entregados por fases.
2. **El usuario solo configura datos y elige plantilla**; el HTML/diseño del PDF es
   100% del programador. Logo/empresa/título salen de la configuración del sistema
   (`cfg_configuraciones`). Sin HTML de usuario (sin riesgo de inyección a dompdf).
3. **Alcance de datos:** un recurso CRUD primario + sus relaciones declaradas
   (`belongsTo` para etiquetas, `hasMany` para hijos). Sin joins libres. Reutiliza
   scope, permisos y filtros del recurso. Sin SQL nuevo.
4. **Propiedad:** reporte guardado privado por usuario (`created_by`), con opción de
   compartir. Reutiliza el patrón `list.scope` owner del CRUD Engine.
5. **Arquitectura:** dos módulos separados (kit reutilizable + builder encima).

---

## 2. Arquitectura general y regla de dependencia

```
reportes ─► pdf-kit ─► core
   └──────► crud-engine   (lee recursos: columnas, scope, permisos, filtros, relaciones)
```

- **`pdf-kit`**: `requiere: [core]`. No conoce el CRUD Engine ni reportes.
- **`reportes`**: `requiere: [core, crud-engine, pdf-kit]`. Es el pegamento entre el
  CRUD Engine (origen de datos) y el kit (render de PDF).
- Onion: las capas externas dependen hacia adentro; Domain sin dependencias externas;
  Infrastructure implementa interfaces de Domain.
- **Toggles:** `modules.pdf_kit` y `modules.reportes` en `config/vertical.php`
  (ambos opcionales).

---

## 3. Módulo `pdf-kit`

Modelo central: un **documento** es una lista ordenada de **componentes atómicos**
(datos puros, sin HTML); un *renderer* los convierte a HTML pensado para dompdf;
dompdf devuelve bytes PDF. Una **plantilla** es una clase del programador que compone
esos componentes a partir de un payload de datos.

### 3.1 Domain (`app/Domain/Pdf/`)

- `PdfDocument` — VO/builder: `PdfPageSetup` (tamaño, orientación, márgenes) +
  `PdfBlock[]` ordenados.
- Componentes (VOs puros): `PdfHeader`, `PdfLogo`, `PdfText`, `PdfDataTable`
  (columnas + filas + formato `money`/`date`/`datetime`), `PdfIndicatorCard`
  (KPI: label, valor, formato), `PdfTotalsBlock`, `PdfSignatureBlock`, `PdfFooter`,
  `PdfSpacer`, `PdfPageBreak`.
- `PdfTemplateInterface` — `compose(array $payload): PdfDocument`.
- `PdfEngineInterface` — `render(string $html, PdfPageSetup $setup): string` (bytes).

### 3.2 Application (`app/Application/Pdf/`)

- `PdfComponentRenderer` — mapea cada componente VO a su partial HTML. **Todo** texto
  de datos pasa por `htmlspecialchars`; CSS inline; sin recursos remotos.
- `PdfRenderingService` — orquesta: `renderDocument(PdfDocument)` o
  `renderTemplate(string $key, array $payload)` → arma el HTML del esqueleto +
  componentes → `PdfEngineInterface` → bytes. Helpers `download()`, `stream()`,
  `save()`.
- `PdfTemplateRegistry` — resuelve clave → clase `PdfTemplateInterface` desde
  `config/pdf_templates.php`. **Nunca** FQCN en datos de usuario (misma política que
  los handlers del CRUD Engine).

### 3.3 Infrastructure (`app/Infrastructure/Pdf/`)

- `DompdfRenderer implements PdfEngineInterface` — endurecido: `isRemoteEnabled=false`,
  `chroot` a la app, fuentes locales, sin PHP embebido.
- `PdfStorage` — guardar PDFs (`storage/pdf/…` o `uploads/pdf`), opcional, para
  reportes archivables + auditoría.

### 3.4 Presentation (`app/Presentation/Views/`)

- `pdf/document.php` — esqueleto HTML (`<html><head><style>…print CSS…</style></head><body>`).
- `partials/pdf/components/*.php` — un partial por componente (tabla, kpi, firma, etc.).
  Estos son los **componentes atómicos** reutilizables por otros módulos.

### 3.5 Config / manifiesto

- `config/modules/pdf-kit.php` — manifiesto (`requiere: [core]`, providers vacíos).
- `config/pdf.php` — defaults de papel/orientación/márgenes/fuente + **marca** (logo,
  empresa, colores) leída de `cfg_configuraciones`.
- `config/pdf_templates.php` — whitelist clave → clase de plantilla.
- Sin `bootstrap_sql` propio (el kit no necesita tablas). Plantillas demo = clases PHP.

### 3.6 Seguridad del kit

- Todo texto de datos escapado; sin recursos remotos en dompdf; `chroot`.
- Plantillas solo del programador (whitelist); el kit nunca recibe HTML de usuario.

### 3.7 API para otros módulos (ejemplo)

```php
$pdf = $pdfRenderingService->renderTemplate('ticket_compra', [
    'pedido' => $record, 'items' => $items, 'marca' => $brand,
]);
return $response->download($pdf, "ticket-{$record['id']}.pdf");
```

---

## 4. Módulo `reportes`

### 4.1 Config del programador — `config/reportes/{key}.json`

Una **fuente reportable** referencia un recurso CRUD y declara qué queda expuesto al
usuario (mismo patrón que `config/calendars/{key}.json`).

```json
{
  "fuente": { "key": "pedidos", "title": "Pedidos", "resource": "demo_pedidos", "icon": "bi-receipt" },
  "modos": ["coleccion", "registro"],
  "expose": {
    "columns":    [ { "name": "id", "label": "N°" }, { "name": "total", "label": "Total", "format": "money" } ],
    "relations":  [ { "name": "cliente", "type": "belongsTo", "label": "Cliente" },
                    { "name": "items",   "type": "hasMany",   "label": "Ítems" } ],
    "indicators": [ { "name": "total_sum", "column": "total", "agg": "sum", "label": "Total vendido" },
                    { "name": "conteo",    "agg": "count",     "label": "N° pedidos" } ],
    "filters":    [ { "field": "status", "label": "Estado" }, { "field": "fecha", "label": "Fecha", "type": "range" } ]
  },
  "templates": {
    "coleccion": ["reporte_listado"],
    "registro":  ["ticket_compra", "presupuesto"]
  }
}
```

Reglas de validación (`ReporteConfigValidator`, espejo de `CalendarConfigValidator`):

- `resource` debe existir en `config/cruds/`.
- Columnas / filtros / relaciones / indicadores expuestos deben existir en el recurso
  y no ser columnas protegidas.
- `templates.*` deben estar en la whitelist `config/pdf_templates.php` y soportar el modo.
- Indicadores = agregaciones reutilizando la semántica `list.summaries`/`list.aggregation`
  del CRUD Engine, **con sus mismos guardas de coste** (`max_rows`, `require_filter_above`).

### 4.2 Reporte guardado del usuario — tabla `rep_reportes`

```
id, key, nombre, fuente_key, modo ('coleccion'|'registro'),
columnas JSON, indicadores JSON, filtros JSON, opciones JSON (titulo, orientacion),
template_key, compartido TINYINT(1),
status, deleted, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by
```

- Columnas de sistema estándar del CRUD Engine (sección 5 de su spec).
- Scope **owner** (igual que `list.scope` del CRUD): cada quien ve los suyos;
  `compartido=1` lo publica a quien tenga `reportes.ver`. Acceso directo a un reporte
  ajeno no compartido → **404** (no se revela su existencia).
- Prefijo `rep_*` (reservado para reportes en CLAUDE.md).

### 4.3 Capas (espejo del calendario)

| Capa | Piezas |
|---|---|
| Domain | `ReporteFuente` (config), `ReporteGuardado` (entidad), `ReporteRepositoryInterface` |
| Application | `ReporteConfigLoader` + `ReporteConfigValidator`; `BuildReporteDataUseCase`; `GenerarReporteUseCase`; `CrearReporteUseCase` / `ActualizarReporteUseCase` |
| Infrastructure | `PdoReporteRepository` (`rep_reportes`); lectura de datos vía **`CrudDataService`** existente |
| Presentation | `ReportesController` (Admin) + vistas `admin/reportes/{index,builder}.php` |

### 4.4 Flujo de datos (colección)

```
"Generar" → ReportesController::generar(id)
 → carga ReporteGuardado (chequeo owner/compartido)
 → ReporteConfigLoader: intersecta selecciones guardadas ∩ expose actual  (config = fuente de verdad)
 → BuildReporteDataUseCase: CrudDataService.list(recurso, filtros, scope=usuario actual) + indicadores
 → payload (filas, columnas, indicadores, marca desde cfg)
 → PdfTemplateRegistry resuelve 'reporte_listado' → compose() → PdfDocument
 → PdfRenderingService.render → bytes → descarga (+ log 'reporte.generar' en log_bitacora)
```

### 4.5 Flujo de datos (registro único) — tickets / presupuestos / contratos

El PDF por registro se dispara desde el CRUD como **acción declarativa**
(`actions.row` tipo `link` en `config/cruds/{recurso}.json`):

```
"Generar documento" → /admin/reportes/generar?fuente=pedidos&id=123&template=ticket_compra
 → CrudDataService.find(123) CON scope del usuario (si no lo puede ver → 404)
 → carga registro + relaciones declaradas (items hasMany, etiqueta cliente belongsTo)
 → template bespoke (ticket_compra) → compose → render → PDF
```

### 4.6 Rutas

```
GET    /admin/reportes                      (index: reportes propios + compartidos)
GET    /admin/reportes/crear                (builder)
POST   /admin/reportes                      (guardar)
GET    /admin/reportes/{id}/editar          (builder)
POST   /admin/reportes/{id}                 (actualizar)
POST   /admin/reportes/{id}/eliminar        (borrado lógico, CSRF + #confirmModal)
POST   /admin/reportes/{id}/generar         (PDF de colección, CSRF)
GET    /admin/reportes/generar              (PDF de registro: ?fuente=&id=&template=)
```

### 4.7 RBAC

- **Permisos de módulo:** `reportes.ver`, `reportes.crear`, `reportes.editar`,
  `reportes.eliminar`, `reportes.generar`, `reportes.compartir`.
- **Acceso a datos siempre acotado por el recurso subyacente:** generar pasa por
  `CrudDataService` con los permisos y el **row-level scope** del usuario que ejecuta.
  Un PDF nunca contiene filas que el usuario no podría ver en el CRUD. Sin bypass
  privilegiado.
- **Re-validación server-side:** las columnas/indicadores/filtros guardados se
  intersectan con el `expose` vigente al generar; si el programador quitó una columna,
  desaparece del PDF (no se confía en lo guardado).

---

## 5. Fases de entrega

- **Fase 0 — Kit PDF** (`pdf-kit`): `DompdfRenderer` endurecido, componentes atómicos +
  partials, `PdfRenderingService`, `PdfTemplateRegistry`, esqueleto `document.php`,
  1 plantilla demo. Entregable independiente y testeable (render de un `PdfDocument`
  fijo → bytes con cabecera `%PDF`).
- **Fase 1 — Reportes de colección**: `config/reportes/*.json` + loader/validator,
  tabla `rep_reportes` + repo, builder UI (elegir fuente → columnas/indicadores/filtros
  → plantilla → guardar), generar con scope owner, plantilla genérica `reporte_listado`.
- **Fase 2 — Documentos de registro único**: modo `registro`, acción CRUD
  "Generar documento", plantillas bespoke demo `ticket_compra`, `presupuesto` y `contrato`.
- **Fase 3 — Compartir + pulido**: flag `compartido`, gestión de reportes guardados
  (editar/eliminar), acceso rápido en dashboard (slot `widgets`/quick access, opcional),
  más plantillas.

---

## 6. Demo de instalación (idempotente)

`bootstrap_sql` del módulo `reportes` (espejo de `database/schema/modules/calendario.sql`):

- Tabla `rep_reportes`.
- Permisos `reportes.*` + asignación al rol `administrador`.
- Entrada de menú a `/admin/reportes` en `core_menu_items`.
- Datos de ejemplo enganchados a recursos demo existentes (`demo_pedidos`,
  `demo_productos`).

Plantillas demo = **clases PHP** registradas en `config/pdf_templates.php` (no filas).
`php scripts/install.php` sigue siendo seguro de re-ejecutar.

---

## 7. Pruebas (`php tests/run.php`, arnés plano — aún sin PHPUnit)

- **Kit:** `PdfComponentRenderer` produce el HTML esperado por componente;
  `DompdfRenderer` devuelve bytes que empiezan con `%PDF`.
- **Validator:** rechaza columnas/indicadores/filtros/plantillas no existentes o no
  expuestos.
- **Data builder:** respeta el row-level scope (un usuario no-owner no obtiene filas
  ajenas); la intersección guardado ∩ expose elimina columnas retiradas.
- **RBAC:** generar sin permiso → denegado; registro ajeno → 404.

---

## 8. Manejo de errores

- Config inválida → falla al cargar con mensaje claro (no se registra ruta/menú), igual
  que el calendario.
- Plantilla inexistente / incompatible con el modo → error controlado, no se genera.
- Agregación supera `max_rows` sin filtros → se omite el indicador con aviso (mismo guard
  del CRUD), el PDF se genera sin ese KPI.
- dompdf falla → excepción capturada, flash de error, log operativo; nunca se descarga un
  PDF corrupto.

---

## 9. Fuera de alcance (YAGNI v1)

Editor WYSIWYG de plantillas, joins libres multi-tabla, reportes programados / por correo,
export a Excel/CSV (solo PDF), gráficos embebidos (charts). Se pueden añadir como fases
futuras sobre el mismo kit sin reescritura.

---

## 10. Principios

- El kit no sabe nada de CRUD; reportes nunca arma SQL ni reimplementa permisos
  (toda lectura pasa por `CrudDataService`, igual que el calendario).
- Config (JSON del programador) es la fuente de verdad; lo guardado por el usuario se
  re-valida contra ella al generar.
- Plantillas y handlers solo por whitelist (clave → clase), nunca FQCN en datos.
- Componentes atómicos del kit reutilizables por cualquier módulo.
