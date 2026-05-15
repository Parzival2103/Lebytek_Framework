# Auditoría técnica — CRUD Engine v0.1 y sistema base

**Estado documental:** informe de corte **2026-04-28**. Describe el estado del repositorio en esa fecha. Parte del contenido quedó **superada** por cambios posteriores (handlers con whitelist, `security.mode`, menú granular, validaciones backend, agregaciones, etc.). Para el contrato vigente del motor usar [`../modules/crud/modulo-crud-engine.md`](../modules/crud/modulo-crud-engine.md) y [`../modules/crud/history/`](../modules/crud/history/).

**Alcance:** proyecto completo con foco en CRUD Engine, comparado contra [`docs/core/arquitectura.md`](../core/arquitectura.md), [`docs/modules/crud/modulo-crud-engine.md`](../modules/crud/modulo-crud-engine.md) (ubicación histórica en el informe: `docs/modulo-crud-engine.md`), `database/schema/schema.sql` y estado real (auth, RBAC, menú, layout).

**Metodología:** revisión estática del código y configuración; sin ejecución de tests automatizados en esta auditoría.

**Fecha de referencia:** 2026-04-28.

---

## 1. Resumen general

### Estado del sistema

La base plataforma (`auth_*`, `cfg_*`, `core_*`, `log_*`) está alineada con `schema.sql`. El CRUD Engine está **implementado de extremo a extremo**: rutas bajo `/admin/crud/*`, controlador genérico, servicios de aplicación, entidad de definición en dominio, repositorio genérico en infraestructura, JSON en `config/cruds/`, vistas bajo `app/Presentation/Views/admin/crud/`, integración con sesión/RBAC y bitácora.

### Nivel de cumplimiento global (estimado)

| Referencia                         | Cumplimiento aproximado |
|-----------------------------------|-------------------------|
| `arquitectura.md` (capas, flujo)  | ~78 %                   |
| `modulo-crud-engine.md` (funcional) | ~68 %                |
| `schema.sql` + tablas `dom_*` demo | ~85 % (demo); riesgo en `log_bitacora.registro_id` vs PK `BIGINT` |
| Seguridad SQL / prefijos tablas   | ~82 %                   |
| RBAC por recurso                  | ~88 %                   |

**Riesgos principales**

1. **Handlers (`CrudHookRunner`):** instanciación dinámica de clase leída del JSON; fallos silenciosos si la clase no existe; sin contrato de interfaz ni lista blanca — riesgo de ejecución de código si la configuración en disco queda comprometida o editable por terceros.
2. **`security.mode` / excepciones documentadas:** el documento del módulo describe `mode: "restricted"`; el validador solo interpreta `allow_core_table` (booleano). Un solo flag desbloquea prefijos bloqueados y tablas no `dom_*` sin granularidad documentada.
3. **Bitácora vs PK de dominio:** `log_bitacora.registro_id` es `INT UNSIGNED` en `schema.sql`; tablas demo `dom_*` usan `BIGINT UNSIGNED` — posible pérdida de precisión o truncamiento en escenarios de IDs grandes (coherencia esquema ↔ motor).
4. **Menú jerárquico:** el padre “CRUD Demo” exige `administracion.ver`; sin ese permiso el ítem padre se omite por completo y **no se reevalúan los hijos** como entradas de primer nivel — usuarios solo con `demo_clientes.ver` pueden no ver el menú aunque tengan permiso de recurso (acceso directo por URL sigue posible si conocen la ruta).

### Clasificación por sección (leyenda: ✔ Correcto · ⚠ Mejorable · ✖ Incorrecto)

---

## 2. Hallazgos por sección

### 2.1 Arquitectura general

**Estado: ⚠ Mejorable**

**Qué está bien**

- Flujo HTTP: rutas → `CrudController` (Presentation) → `CrudResourceService` y colaboradores (Application) → entidades `CrudResourceDefinition` / `CrudFieldDefinition` (Domain) → `GenericCrudRepository` / `BitacoraRepository` (Infrastructure), coherente con `arquitectura.md` §5–§8 y §11.
- `CrudController` se limita a orquestación HTTP, CSRF, flash y delegación; no contiene SQL ni reglas de negocio pesadas.

**Desviaciones**

- `CrudConfigValidator` y `CrudDataService` dependen del **repositorio concreto** `GenericCrudRepository` en Application, no de una interfaz en Domain. `arquitectura.md` §6 indica que Application no debe acoplarse a detalles de infraestructura; aquí el acoplamiento es directo (mejorable introduciendo un puerto, p. ej. `CrudMetadataPort` / `CrudPersistencePort` en Domain).
- `modulo-crud-engine.md` §16 lista carpetas planas (`Application/CrudResourceService`); el proyecto usa `Application/Services/` — coherente con el resto del repo, pero **divergencia documental** menor.

**Impacto:** mantenimiento y testabilidad; no bloquea funcionalidad actual.

---

### 2.2 Estructura del CRUD Engine (clases previstas)

**Estado: ✔ Correcto (con matices)**

| Componente (doc §16)     | Ubicación real |
|--------------------------|----------------|
| `CrudController`         | `app/Presentation/Controllers/Admin/CrudController.php` |
| `CrudResourceService`    | `app/Application/Services/CrudResourceService.php` |
| `CrudConfigLoader`       | `app/Application/Services/CrudConfigLoader.php` |
| `CrudConfigValidator`    | `app/Application/Services/CrudConfigValidator.php` |
| `CrudFormBuilder`        | `app/Application/Services/CrudFormBuilder.php` |
| `CrudTableBuilder`       | `app/Application/Services/CrudTableBuilder.php` |
| `CrudDataService`        | `app/Application/Services/CrudDataService.php` |
| `CrudHookRunner`         | `app/Application/Services/CrudHookRunner.php` |
| `GenericCrudRepository` | `app/Infrastructure/Repositories/GenericCrudRepository.php` |
| `CrudResourceDefinition` / `CrudFieldDefinition` | `app/Domain/Entities/` (doc dice “Domain”; el repo usa `Entities` — aceptable bajo convenciones del proyecto) |

**Matices**

- `CrudDataService` concentra persistencia, validación de payload, uploads, hooks y bitácora — **clase grande** pero aún acotada a un caso de uso; división futura posible (p. ej. `CrudPayloadFactory`, `CrudUploadService`).
- No hay duplicación grave de constantes de columnas protegidas: repetidas en `CrudDataService` y `CrudConfigValidator` — **duplicación menor**.

---

### 2.3 Carga y validación de JSON

**Estado: ✔ Correcto (⚠ en listados y errores)**

**Fortalezas**

- Lectura desde `ROOT_PATH . '/config/cruds'` con archivo `{resource}.json`.
- `json_decode` con comprobación de array; validación estructural vía `CrudConfigValidator` (resource, tabla, PK, columnas de formulario/listado/filtros, permisos en `auth_permisos`).
- Coherencia `resource.key` ↔ nombre de archivo tras validación.

**Debilidades**

- `CrudConfigLoader::listResources()` **traga cualquier excepción** (`catch (\Throwable)`) y omite el recurso — adecuado para no romper el menú, pero **fallos silenciosos** en administración de configuración (sin log).
- `security.mode` del JSON **no tiene efecto** en código (solo documentación / datos muertos).

---

### 2.4 Seguridad del CRUD Engine

**Estado: ⚠ Mejorable (globalmente sólido en SQL)**

**Fortalezas**

- `GenericCrudRepository`: placeholders `?` en valores; identificadores validados con regex `^[a-zA-Z_][a-zA-Z0-9_]*$` y backticks — mitiga inyección vía nombres de tabla/columna siempre que solo pasen por ese validador.
- `CrudConfigValidator`: prefijos bloqueados `auth_`, `cfg_`, `core_`, `log_`; exige prefijo `dom_` salvo `allow_core_table`.
- Columnas de formulario/listado/filtro contrastadas con `information_schema` antes de aceptar la config.
- Campos protegidos del formulario alineados con el módulo (id, auditoría, soft delete).

**Lagunas / riesgos**

1. **`allow_core_table`:** un único booleano anula **tanto** el bloqueo por prefijo **como** la obligación de `dom_`, sin el matiz de `mode: "restricted"` del documento del módulo — superficie mayor que la especificación textual sugiere.
2. **Handlers:** ver sección 2.11; no es SQL injection pero es **superficie de ejecución arbitraria** ligada a disco.
3. **Filtros y búsqueda:** nombres de columna provienen del JSON ya validado contra la tabla; riesgo residual bajo salvo bypass de validación o futuras rutas que acepten columnas sin validar.

---

### 2.5 Autenticación y autorización

**Estado: ✔ Correcto**

- Rutas `/admin/crud/*` bajo grupo `AuthMiddleware` en `routes/web.php` — exige sesión.
- **No** están bajo `RbacMiddleware('administracion.ver')` a diferencia de `/admin/administracion/*`; el control de acceso fino se hace en `CrudResourceService` con `RbacService::verificar()` y permisos `{prefix}.ver|crear|editar|eliminar`, alineado con `modulo-crud-engine.md` §7.
- `CrudConfigValidator` exige que los cuatro slugs existan en `auth_permisos` — evita CRUDs “huérfanos” de RBAC a nivel de configuración.

**⚠ Menor:** orden de operaciones: se carga y valida el JSON antes de `verificar()` en cada acción. Un usuario autenticado sin permiso podría distinguir “recurso inexistente / JSON inválido” vs “403” según manejo de excepciones; hoy muchos errores de config devuelven redirección a dashboard con mensaje — **enumeración leve** de recursos.

---

### 2.6 CrudDataService (SELECT / INSERT / UPDATE / delete lógico)

**Estado: ✔ Correcto**

- Listado: `WHERE deleted = 0`, búsqueda LIKE parametrizada, filtros `= ?`, orden restringido a columnas del SELECT, paginación con `LIMIT`/`OFFSET` parametrizados.
- Alta: rellena columnas de auditoría y soft delete en código; no expone columnas protegidas vía formulario.
- Actualización: solo payload construido desde campos del formulario + auditoría.
- Borrado: actualización a `deleted = 1` con `deleted_at` / `deleted_by` y `updated_*` — coherente con §11 del módulo.

**⚠** `findById` devuelve cualquier fila (incl. `deleted = 1`); la capa de servicio corrige en `show`/`edit`/`update`/`delete`. Correcto siempre que no se reutilice `find` en otro flujo sin esa comprobación.

---

### 2.7 Form Builder y vista de formulario

**Estado: ⚠ Mejorable**

**Cumple**

- Tipos en UI: `text`, `textarea`, `select`, `checkbox`, `hidden`, `file`; grid Bootstrap vía `col`; `required` y mensajes de error por campo.
- `CrudFormBuilder` desacoplado de HTML; la vista itera campos.

**Gaps frente al módulo**

- `CrudFieldDefinition` incluye `validation` (array) pero **`CrudDataService::buildPayload` no aplica** reglas declaradas en JSON más allá de `required` — “validaciones simples” del doc §9 parcialmente incumplidas.
- **Bug funcional:** en `form.php`, campos `select` y `checkbox` con `readonly` usan `disabled`. Los controles deshabilitados **no se envían en POST**; en edición se puede **pierden valores** al guardar. Estado: **✖ Incorrecto** para el caso `readonly` + edición (corrección sería código; fuera del alcance de este informe).

---

### 2.8 Table Builder

**Estado: ⚠ Mejorable**

**Cumple**

- Columnas dinámicas, badges, formatos `date` / `datetime` / `money`, filtros, búsqueda, orden (vía query string), paginación, acciones por fila condicionadas a permisos y a `list.actions`.

**No cumple el alcance declarativo del módulo §10**

- **Sin agrupación ni sumatorias** en listado.
- Flag `sortable` en JSON **no restringe** el `<select name="orden">` en la vista (se listan todas las columnas de definición; el servidor sí acota a columnas del SELECT — coherente pero la UX no refleja `sortable`).

---

### 2.9 Borrado lógico

**Estado: ✔ Correcto**

- No hay `DELETE` físico en el flujo CRUD revisado; solo `UPDATE` con banderas y timestamps de borrado, acorde a `modulo-crud-engine.md` §11.

---

### 2.10 Bitácora

**Estado: ⚠ Mejorable**

- Se registran acciones `crud.create`, `crud.update`, `crud.delete` con tabla, usuario, IP y detalle (JSON o texto en delete).
- **Inconsistencia con `schema.sql`:** columna `registro_id` como `INT UNSIGNED` frente a PK `BIGINT` en tablas `dom_*` de la migración demo — riesgo de **truncamiento** o límites en IDs altos; el interfaz `BitacoraRepositoryInterface::registrar` tipa `?int` — coherente con el esquema actual pero **débil para dominios con BIGINT**.

---

### 2.11 Handlers (hooks)

**Estado: ✖ Incorrecto / riesgo alto operativo**

- Métodos documentados (`beforeStore`, `afterStore`, etc.) se invocan por nombre si existen.
- **Sin interfaz obligatoria**, sin inyección de dependencias, **sin try/catch** en el runner: una excepción en handler rompe la petición (puede ser deseable o no).
- Si la clase no existe o el método no existe → **salida silenciosa** (sin log) — dificulta operación y auditoría de configuración.
- **Riesgo de seguridad:** cualquier FQCN en JSON que el autoload pueda resolver se instancia con `new` — en práctica equivalente a “ejecutar código de clases disponibles” si alguien controla `config/cruds/*.json`. Para producción interna exige **control estricto de permisos de filesystem** y, idealmente, **lista blanca de handlers** o registro explícito en contenedor.

---

### 2.12 Vistas y UI

**Estado: ⚠ Mejorable**

- Uso de `AdminBaseController` → layout `layouts/base` y datos de sistema (tema, menú) — alineado con el resto del admin.
- Bootstrap, cards, tablas responsive, CSRF en formularios POST — consistente.
- Duplicación leve de rutas hardcodeadas `/admin/crud/...` en vistas y servicios (aceptable en v0.1; centralizar base URL sería mejora).

---

### 2.13 Integración general (menú, rutas, layout)

**Estado: ⚠ Mejorable**

- Rutas coinciden con el contrato del módulo §8 (mismos paths bajo prefijo `/admin` del router).
- Migración `20260428132500_crud_engine_demo_resources.sql`: ítems en `core_menu_items`, permisos y datos demo — **bien encaminado**.
- **Menú:** el padre exige `administracion.ver`; el servicio `AdminNavigationMenuService` omite el ítem completo si falla el permiso del padre, **eliminando submenús visibles** aunque el usuario tenga `demo_clientes.ver` / `demo_productos.ver`. Impacto: **navegación inconsistente con permisos granulares** salvo que todos los operadores CRUD tengan también `administracion.ver`.

---

## 3. Problemas críticos

Deben abordarse antes de considerar el motor “cerrado” para entornos sensibles:

1. **Handlers dinámicos sin restricción** (`CrudHookRunner`) + configuración en disco — riesgo de ejecución de código no auditado si el JSON se altera.
2. **Pérdida de datos en edición** con `readonly` en `select`/`checkbox` por uso de `disabled` en la vista (ver §2.7).
3. **Menú:** dependencia del permiso del padre impide que usuarios solo con permisos de recurso vean entradas de menú (diseño o bug de producto; hoy es **bloqueante** para RBAC granular sin `administracion.ver`).

---

## 4. Problemas importantes

No bloquean un piloto controlado, pero deben planificarse:

- `log_bitacora.registro_id` / tipo en PHP vs PK `BIGINT` en tablas de negocio.
- `security.mode` documentado pero no implementado; `allow_core_table` demasiado amplio frente al texto del módulo.
- Validaciones declarativas en JSON (`validation`) ignoradas en runtime.
- Funcionalidades de tabla avanzadas del módulo (agrupación, sumatorias) ausentes.
- `CrudConfigLoader::listResources()` sin telemetría ante JSON inválido.
- Acoplamiento Application → `GenericCrudRepository` concreto (arquitectura limpia).
- Posible enumeración leve de recursos por diferencias de error antes/después de RBAC.

---

## 5. Recomendaciones

### Arquitectura

- Definir interfaces en Domain para operaciones de metadatos y persistencia CRUD; implementarlas en `GenericCrudRepository` (o dividir en “metadata” + “persistence”).
- Documentar en `docs/modules/crud/modulo-crud-engine.md` la ubicación real bajo `Application/Services/` y `Presentation/Views/`.

### Seguridad y operación

- Lista blanca de clases handler o registro explícito en `config/container.php` mapeando `resource.key` → clase.
- Log de advertencia cuando handler o método no exista.
- Endurecer permisos OS sobre `config/cruds/` (solo deploy, lectura para PHP-FPM).
- Implementar `mode` / reglas finas para `allow_core_table` como indica el documento, o ajustar el documento al comportamiento real.

### Producto / UX

- Menú: si se desea RBAC solo por recurso, **no asignar** al padre un permiso más estricto que los hijos, o cambiar el algoritmo de filtrado para permitir mostrar hijos cuando el padre se oculta.
- Sustituir `disabled` por estrategia que preserve valores en POST para campos de solo lectura, o persistir desde servidor ignorando ausencia en POST para esos campos.

### Datos

- Migración de esquema: ampliar `log_bitacora.registro_id` a `BIGINT UNSIGNED` (y tipos en interfaz) si los dominios usan BIGINT.

---

## 6. Evaluación final

| Pregunta | Veredicto |
|----------|-----------|
| ¿El CRUD Engine es **estable** para un piloto interno con JSON confiable y usuarios con `administracion.ver` o URLs directas? | **Sí**, con vigilancia en handlers y bitácora. |
| ¿Está **listo para producción interna** sin más trabajo? | **No del todo** — corregir menú vs RBAC granular, readonly/disabled, y política de handlers antes de ampliar usuarios. |
| ¿Puede **escalar a múltiples módulos** solo con JSON + tablas `dom_*`? | **Sí en mecánica**; la validación y el repositorio genérico lo permiten. El cuello de botella será gobernanza de JSON, permisos en `auth_permisos`, y límites de UX (tabla avanzada, validaciones). |

---

## 7. Tabla resumen de secciones

| # | Sección | Estado |
|---|---------|--------|
| 1 | Arquitectura general | ⚠ |
| 2 | Estructura clases CRUD | ✔ |
| 3 | JSON carga/validación | ✔ / ⚠ |
| 4 | Seguridad SQL / tablas | ⚠ |
| 5 | Auth / RBAC | ✔ |
| 6 | CrudDataService | ✔ |
| 7 | Form builder | ⚠ / ✖ (readonly) |
| 8 | Table builder | ⚠ |
| 9 | Borrado lógico | ✔ |
| 10 | Bitácora | ⚠ |
| 11 | Handlers | ✖ |
| 12 | Vistas / UI | ⚠ |
| 13 | Integración menú/rutas | ⚠ |

---

*Fin del informe — solo auditoría; sin cambios de código en el repositorio como parte de esta entrega.*
