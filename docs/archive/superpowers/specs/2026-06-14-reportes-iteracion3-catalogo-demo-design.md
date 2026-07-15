# Diseño: Reportes — Iteración 3 (catálogo demo + documentos de registro)

> Fecha: 2026-06-14
> Estado: spec aprobado (brainstorming) — pendiente de plan de implementación.
> Continúa `docs/superpowers/specs/2026-06-14-reportes-interfaz-visual-design.md`
> (Fases 1–2) y `docs/superpowers/specs/2026-06-13-modulo-reportes-pdf-design.md`
> (Fase 0, pdf-kit). El módulo `reportes` ya está construido para **colección**;
> esta iteración añade el **modo registro** y, sobre todo, **ejercita los módulos
> demo** para servir de catálogo de ejemplos.

---

## 1. Objetivo

Las iteraciones 1–2 dejaron el módulo `reportes` funcional pero ejercitado sobre una
sola fuente (`config/reportes/citas.json`, recurso `demo_citas`, solo colección) con una
sola plantilla real (`tabla_estadistica`) y un solo reporte demo sembrado.

El objetivo de la iteración 3 es **ampliar la cobertura sobre los módulos demo** para que
funcione como **biblioteca de referencia**: cuando eventualmente se construya el primer
vertical demo completo, la IA que lea `config/reportes/`, `config/cruds/` y los seeds verá
**todas las opciones que el módulo soporta, funcionando, sobre datos demo reales**.

Para lograrlo se hacen dos cosas a la vez (decisión de brainstorming):

1. **Implementar el modo registro** (Fase 2 del spec previo): plantillas
   `ticket_compra`, `presupuesto`, `contrato` disparadas desde el CRUD.
2. **Agregar fuentes reportables y reportes demo sobre todos los módulos demo**, con
   variedad de tratamientos por módulo.

### Hallazgos del estado actual (verificados en código)

- Solo existe `config/reportes/citas.json` (modo colección).
- Solo existe la clase `TablaEstadisticaTemplate` (+ `DemoReporteTemplate`) en
  `config/pdf_templates.php`. Las plantillas de registro **no** están construidas.
- Solo se siembra un reporte demo: `demo_citas_por_estado` (compartido).
- La ruta de generación actual es `POST /admin/reportes/{id}/generar` (solo colección
  guardada). **No** existe ruta de registro.
- El CRUD Engine **ya** soporta acciones de fila `type:"link"` que sustituyen `{id}` en la
  ruta (`CrudActionResolver.php:78`). El disparo de documentos de registro **no requiere
  código nuevo en el CRUD Engine**.

---

## 2. El catálogo demo (centerpiece)

### 2.1 Fuentes reportables (`config/reportes/*.json`): de 1 → 4

| Fuente | Recurso | modos | templates | Capacidades demostradas |
|---|---|---|---|---|
| `citas` *(existe)* | demo_citas | coleccion | tabla_estadistica | count, group_by, period (`fecha_inicio`) |
| `pedidos` *(nuevo)* | demo_pedidos | coleccion + registro | tabla_estadistica · ticket_compra · presupuesto | sum/avg/min/max sobre dinero, belongsTo cliente, hasMany items, period (`created_at`) |
| `productos` *(nuevo)* | demo_productos | coleccion | tabla_estadistica | sum stock + precio, group_by por categoría (relación) y por estado |
| `clientes` *(nuevo)* | demo_clientes | coleccion + registro | tabla_estadistica · contrato | count por estado, **owner-scope** propagado al reporte, datos embebidos en texto |

`demo_categorias` **no** se vuelve fuente propia; queda solo como destino de relación
(agrupar productos por categoría).

Cada fuente sigue el esquema del spec previo (§4): `fuente`, `modos`, `expose`
(`columns` con `type` y `treatments`, `relations`, `group_by`, `order_by`, `filters`,
`period`, `max_rows`), `templates.{coleccion,registro}`.

### 2.2 Reportes demo sembrados (`rep_reportes`, `compartido=1`): variedad por módulo

Forma elegida: **amplio + variedad por módulo** (varios reportes por fuente cuando
muestran combinaciones distintas de tratamientos).

| Clave | Fuente | Tratamientos demostrados |
|---|---|---|
| `demo_citas_por_estado` *(existe)* | citas | group_by estado, count |
| `demo_pedidos_ventas_cliente` | pedidos | group_by cliente, sum(total) + count, period `mes` |
| `demo_pedidos_por_estado` | pedidos | group_by estado, count, listado sin period |
| `demo_productos_inventario_categoria` | productos | group_by categoría (relación), sum(stock_actual) + sum(precio_venta) |
| `demo_productos_por_estado` | productos | group_by status, count |
| `demo_clientes_por_estado` | clientes | group_by status, count (respeta owner-scope al generar) |

Total: **6 reportes de colección** (1 existente + 5 nuevos) cubriendo count, sum, avg,
min/max, agrupar por columna plana y por relación, con y sin periodo.

### 2.3 Documentos de registro (acciones `link` en los `config/cruds/*.json` demo)

- `demo_pedidos` → "Ticket PDF" (`ticket_compra`) y "Presupuesto PDF" (`presupuesto`).
- `demo_clientes` → "Contrato PDF" (`contrato`).

---

## 3. Mecánica de modo registro

### 3.1 Disparo (sin tocar el CRUD Engine)

Cada documento es una acción de fila declarativa `type:"link"`; el engine sustituye `{id}`:

```json
{ "name": "ticket", "type": "link", "label": "Ticket PDF", "icon": "bi-receipt",
  "route": "/admin/reportes/documento?fuente=pedidos&id={id}&template=ticket_compra",
  "permission": "reportes.generar" }
```

El `permission` con punto se usa tal cual (slug completo) según
`CrudActionDefinition` ("Slug completo si contiene punto; si no, se expande contra el
prefijo").

### 3.2 Ruta y controlador nuevos

La ruta actual `POST /admin/reportes/{id}/generar` es solo para colección guardada. Se
añade una ruta GET para registro:

```
GET /admin/reportes/documento?fuente=&id=&template=   → ReportesController::documento()
```

Flujo de `documento()`:

1. módulo habilitado + RBAC `reportes.generar`.
2. carga `fuente` (loader); valida que `template` ∈ `fuente.templates.registro`, esté en la
   whitelist `config/pdf_templates.php` y `supports('registro')`. Si no → 404 / error
   controlado (no se genera).
3. lee el registro vía la capa de datos **con scope del usuario**; si no lo ve → **404**
   (el PDF nunca contiene lo que el usuario no vería en el CRUD; sin bypass privilegiado).
4. carga las relaciones declaradas en `expose.relations` (belongsTo cliente, hasMany
   items).
5. arma payload → `PdfTemplateRegistry` resuelve `template` → `compose()` →
   `PdfRenderingService.render` → descarga (+ log `reporte.generar` en `log_bitacora`).

### 3.3 Capa de datos para registro

El `CrudReporteDataSource` actual sirve listas (colección). Se añade un método de un solo
registro con relaciones, `findRecord(recurso, id, scope, relaciones)`, que delega en el
`CrudDataService` existente — **sin SQL nuevo**, igual que el resto del módulo. Un
`GenerarDocumentoUseCase` (contraparte de un solo propósito de `GenerarReporteUseCase`)
orquesta los pasos 2–5.

### 3.4 Las 3 plantillas de registro

Clases PHP en `app/Application/Pdf/Templates/`, registradas en `config/pdf_templates.php`.
Implementan solo `PdfTemplateInterface` con `supports('registro') === true`; **no**
necesitan `schemaPasos()` porque el modo registro no pasa por el wizard.

| Clave | Fuente | Componentes del kit que ejercita |
|---|---|---|
| `ticket_compra` | pedidos | `PdfLogo` + marca, header compacto, `PdfDataTable` (ítems hasMany), total, `PdfFooter` |
| `presupuesto` | pedidos | bloque datos cliente (belongsTo), `PdfDataTable` (conceptos), subtotal/impuestos/total, `PdfSignatureBlock` |
| `contrato` | clientes | `PdfText` largo con datos del cliente incrustados, `PdfSignatureBlock` |

Esto ejercita los componentes del kit que la plantilla de colección no usa (logo, footer,
firma, texto largo). La plantilla decide qué campos/relaciones del payload usa; la fuente
solo declara qué queda expuesto.

---

## 4. Instalación demo (idempotente)

En `database/schema/modules/reportes.sql` (espejo del patrón actual):

- 5 nuevos `INSERT IGNORE` en `rep_reportes`, claves estables:
  `demo_pedidos_ventas_cliente`, `demo_pedidos_por_estado`,
  `demo_productos_inventario_categoria`, `demo_productos_por_estado`,
  `demo_clientes_por_estado`; todos `compartido=1`, `deleted=0`.
- Las acciones `link` de registro se declaran en `config/cruds/demo_pedidos.json` y
  `config/cruds/demo_clientes.json` (no en SQL).
- `php scripts/install.php` y `php scripts/seed.php` siguen siendo seguros de re-ejecutar.

---

## 5. Archivos por capa

| Capa | Cambios |
|---|---|
| Config | + `config/reportes/{pedidos,productos,clientes}.json`; editar `config/cruds/{demo_pedidos,demo_clientes}.json` (acciones `link`); editar `config/pdf_templates.php` (+3 claves) |
| Domain | sin cambios de contrato (`PdfTemplateInterface` / `ReporteTemplateInterface` ya existen) |
| Application | + `TicketCompraTemplate`, `PresupuestoTemplate`, `ContratoTemplate`; + `GenerarDocumentoUseCase`; + método `findRecord(...)` en la capa de datos de reportes |
| Infrastructure | reutiliza `CrudDataService` existente (lectura con scope); sin SQL nuevo |
| Presentation | + `ReportesController::documento()` |
| Rutas | + `GET /admin/reportes/documento` (RBAC `reportes.generar`) |
| Schema | editar `database/schema/modules/reportes.sql` (+5 seeds) |
| Container | registrar `GenerarDocumentoUseCase` en `config/container.php` |

---

## 6. Pruebas (`php tests/run.php`, arnés plano)

- **Config nuevo**: `ReporteConfigValidator` acepta `pedidos`, `productos`, `clientes`;
  rechaza un `templates.registro` no whitelisted o un modo no soportado por la plantilla.
- **GenerarDocumentoUseCase**: registro visible → payload con relaciones declaradas;
  registro fuera de scope → 404; `template` no declarado en `fuente.templates.registro` →
  rechazado; `template` que no soporta `registro` → rechazado.
- **Plantillas registro**: cada `compose()` produce un `PdfDocument` con los bloques
  esperados (ítems, totales, firma según corresponda).
- **Seeds**: las 5 filas demo cargan y son re-validables contra su `expose` vigente
  (la intersección guardado ∩ expose no las rompe).
- **Colección existente**: no hay regresión (las pruebas previas siguen verdes).

---

## 7. Manejo de errores

- Fuente o plantilla inválida → error controlado, no se genera (igual que colección y
  Calendario).
- `template` incompatible con el modo registro o no declarado por la fuente → 404 / error
  controlado.
- Registro fuera del scope del usuario → 404 (no revela existencia).
- dompdf falla → excepción capturada, flash de error, log operativo; nunca se descarga un
  PDF corrupto.

---

## 8. Fuera de alcance (YAGNI)

- Rango de fechas personalizado, charts embebidos, export a Excel/CSV, reportes
  programados / por correo, editor WYSIWYG (siguen fuera, igual que el spec previo).
- No se modifica el wizard de colección ni el CRUD Engine; el registro reutiliza el
  `link` existente.
- `demo_categorias` no se vuelve fuente propia (solo destino de relación).

---

## 9. Principios (heredados)

- El kit no sabe nada de CRUD; reportes nunca arma SQL ni reimplementa permisos (toda
  lectura pasa por `CrudDataService`, igual que el Calendario).
- Config (JSON del programador) es la fuente de verdad; lo guardado/disparado por el
  usuario se re-valida contra ella al generar.
- La plantilla gobierna el documento; el usuario solo elige datos/registro, nunca HTML.
- Plantillas y handlers solo por whitelist (clave → clase), nunca FQCN en datos.
- Componentes atómicos del kit reutilizables por cualquier módulo.
