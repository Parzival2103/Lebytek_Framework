# Refinamiento CRUD Engine v0.1

**Objetivo:** mayor seguridad y consistencia en validación/sanitización, protección ante agregaciones pesadas y UI más profesional y responsive, **sin** reconstruir el motor ni alterar el contrato base (*tabla física + JSON = CRUD*).

**Fecha de referencia:** 2026-04-28.

**Fuera de alcance (explícito):** builder visual, versionado de JSON, multi-tenant, caché de agregaciones, jobs, vistas materializadas.

---

## Archivos modificados o agregados

| Área | Ruta |
|------|------|
| Validación | `app/Application/Services/CrudFieldValidationService.php` |
| Payload / listado | `app/Application/Services/CrudDataService.php` |
| Definición recurso | `app/Domain/Entities/CrudResourceDefinition.php` |
| Definición campo | `app/Domain/Entities/CrudFieldDefinition.php` |
| Validador JSON | `app/Application/Services/CrudConfigValidator.php` |
| Repositorio | `app/Infrastructure/Repositories/GenericCrudRepository.php` |
| Tabla / índice | `app/Application/Services/CrudTableBuilder.php`, `app/Application/Services/CrudResourceService.php` |
| Formulario | `app/Application/Services/CrudFormBuilder.php` |
| Vistas | `app/Presentation/Views/admin/crud/index.php`, `form.php`, `show.php` |
| Partials | `app/Presentation/Views/partials/crud/aggregation_skipped_alert.php`, `list_empty.php` |
| Estilos | `public/assets/css/crud-engine.css` |
| Demo JSON | `config/cruds/demo_productos.json` |
| Documentación | `docs/modules/crud/history/refinamiento_crud_engine_v0.1.md` (este archivo) |

---

## Mejoras de validación

- Flujo explícito por campo: **sanitizar → normalizar → validar** (`sanitizeRawInput`, `normalizeValue`, `validateValue`); la persistencia usa **`toStorageValue()`** solo tras validación sin errores.
- **`validatePayload()`** recorre campos del formulario (excluye `file` y `readonly`) para centralizar errores por nombre de campo.
- Tipos y reglas soportados (vía `validation.type` y/o tipo de control): `required`, `string`/`text`, `integer`, `decimal`, `numeric`, `money`, `boolean`, `date` (AAAA-MM-DD estricto), `datetime` (varios formatos fijos), `email`, `min`/`max`, `minlength`/`maxlength`, `in`, **`regex` seguro** (longitud máxima del patrón, sin `\0`, sin `(?R)` recursivo, comprobación de compilación).
- **Checkbox obligatorio:** si `required`, debe enviarse marcado (`1`); ausencia en POST se normaliza a `0` y falla validación.
- **Enteros y decimales:** no se aceptan conversiones silenciosas inválidas; entero estricto vía `filter_var`; decimal rechaza notación científica y valida con `filter_var` + cadena normalizada.
- Métodos **`validateFieldValue` / `coerceValue`** se mantienen como compatibilidad interna (delegan al nuevo flujo).

---

## Reglas de sanitización aplicadas

- Cadenas (`text`, `textarea`, `hidden`, `select`): **trim**; **sin HTML** por defecto (`strip_tags`), salvo `validation.allow_html: true`.
- **Email:** trim + minúsculas.
- **Checkbox / booleano en reglas:** normalización controlada a `0`/`1` antes de validar.
- **Archivos:** nombre de archivo saneado; extensión validada si existe `validation.allowed_extensions` (lista de extensiones permitidas, sin punto).

La **validación de reglas** no se mezcla con la limpieza: `validateValue` opera sobre el valor ya sanitizado y normalizado.

---

## Protección de performance (agrupaciones / sumas)

- Configuración opcional en JSON bajo **`list.aggregation`**:
  - **`enabled`** (booleano, por defecto `true` si el bloque no define `enabled: false`): si es `false`, no se aplica el límite (comportamiento previo ante grandes volúmenes; uso bajo responsabilidad del operador).
  - **`max_rows`** (entero, por defecto **5000**, acotado 1–500000 en carga y validación de esquema).
  - **`require_filter_above`** (opcional): si el conteo de filas candidatas (**mismos `WHERE` que el listado**, `deleted = 0`, búsqueda y filtros) es **mayor** que este umbral y **no** hay búsqueda ni filtros de listado activos, se omite la agregación.
  - Si el conteo supera **`max_rows`**, también se omite.
- Antes de agrupar / calcular sumas globales: **`COUNT(*)`** con los mismos filtros (`GenericCrudRepository::countFiltered`).
- Si se omite: no se ejecuta `GROUP BY` ni `selectGlobalAggregates`; el listado cae en **modo tabla estándar paginada**; **`summaryRow` vacío**; mensaje en UI y **`AppLogger::warning`** con recurso, tabla y métricas.
- SQL de agregaciones **sin cambios de contrato**: columnas ya validadas en JSON, alias fijos, prepared statements (como antes).

---

## Mejoras UI/UX

- Contenedor **`container-fluid`** con espaciado responsive (`px-3`, `py-3`, `p-md-4`, etc.).
- **Cards** con sombra suave, cabeceras y formularios de filtros con labels accesibles.
- Tablas: **`table-responsive`**, **`table-hover`**, **`table-striped`**, **`table-sm`** opcional vía **`list.table_compact`** o alias **`list.table_sm`** en JSON.
- **Empty state** reutilizable (`partials/crud/list_empty.php`).
- Alerta de agregación omitida (`partials/crud/aggregation_skipped_alert.php`).
- Formularios: rejilla Bootstrap, **`help_text`** desde JSON, acciones con barra **sticky** ligera (`.crud-form-actions`).
- Detalle (**show**): listado tipo **`<dl class="row">`** con jerarquía clara.
- **`public/assets/css/crud-engine.css`**: transiciones 0.2s, hover suave en cards, foco visible en controles (sin librerías JS nuevas). Hoja enlazada solo desde vistas CRUD para no afectar el resto del admin.

---

## Decisiones tomadas

1. **`list.aggregation` opcional:** si falta, se aplican `enabled=true` y `max_rows=5000` al construir `CrudResourceDefinition`, manteniendo JSON antiguos compatibles.
2. **`require_filter_above`:** solo aplica si está definido y es **> 0**; fuerza al menos búsqueda o un filtro de listado cuando el volumen supera el umbral.
3. **Orden del `buildPayload`:** validación y primer `assertNoErrors` **antes** de `toStorageValue` y de **`move_uploaded_file`**, para no escribir archivos si el payload textual es inválido.
4. **Regex en configuración:** si el patrón es inseguro o inválido, se devuelve error de campo genérico (“Regla de formato no disponible”) sin detalles internos.
5. **No se modifica `schema.sql`** ni el login/RBAC global más allá del alcance CRUD ya existente.

---

## Pruebas manuales sugeridas

1. Crear registro válido (demo productos / clientes).
2. Crear registro inválido y comprobar errores por campo (flash + formulario).
3. Editar con campos `readonly` (valores preservados).
4. Checkbox activo/inactivo en alta y edición.
5. Email inválido (si hay campo email con reglas).
6. Decimal inválido (`precio_venta` en demo).
7. Fecha inválida (campo con `validation.type` `date`/`datetime`).
8. Listado agrupado con pocos registros: totales visibles.
9. Simular volumen alto o `max_rows` bajo en JSON temporal: mensaje de omisión y listado sin agregación.
10. Índice en escritorio y en viewport móvil.
11. Formulario en móvil (acciones sticky, campos apilados).
12. Detalle responsive.
13. Borrado lógico con modal de confirmación.

---

## Pendientes que no se abordan en esta entrega

- Builder visual de CRUD.
- Versionado de definiciones JSON.
- Multi-tenant.
- Caché de agregaciones, colas o vistas materializadas.
- Lista blanca de handlers ya documentada en corrección previa; no duplicada aquí.

---

*Fin del documento de refinamiento v0.1.*
