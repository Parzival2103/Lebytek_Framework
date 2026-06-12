# Spec — Cierre de parciales de la auditoría de capacidades

**Fecha:** 2026-06-08
**Autor:** sesión de brainstorming (superpowers)
**Estado:** aprobado para planificación
**Fuente:** `docs/audits/informe-capacidades-framework-examen-dominio-ficticio.md`

---

## Objetivo

Cerrar **solo** los ítems marcados **"Parcial"** en la auditoría **donde ya existe código y solo falta completar piezas**. No se introduce funcionalidad nueva desde cero.

### En alcance

| # | Ítem | Por qué entra |
|---|------|---------------|
| **#4** | Pie de totales en listado no agrupado | `summaryRow` ya se calcula en backend; solo falta capa de presentación. |
| **#3** | Aislamiento por usuario (row-level scope) | `CrudListScopeInterface` + `CrudListContext` existen huérfanos desde Fase 0. |
| **#2** | Dashboard por perfil (solo corto plazo) | Arquitectura de proveedores + `tienePermiso()` existen; falta que el proveedor por defecto filtre. |

### Fuera de alcance (decisiones explícitas del usuario)

- **#5 Agrupación por semana/mes (`group_by_period`):** la pieza de periodo (DATE_FORMAT/YEARWEEK, config, validación) **no tiene código existente**; es funcionalidad nueva sobre `group_by`. Excluido.
- **Calendario (#1):** ausente por completo, no parcial. Excluido.
- **Dashboard — mediano/largo plazo:** `config/dashboard_widgets.php` declarativo, tabla `cfg_dashboard_layout` por rol, editor visual, preferencias por usuario. Es funcionalidad nueva. Excluido.
- **Detalle CRUD enriquecido (#6) y acciones (#7):** veredicto "Sí" en la auditoría; no son parciales.

### Convenciones

- Stack y capas: ver `CLAUDE.md` (MVC + Onion, 5 capas).
- Tests: harness propio `php tests/run.php` (estilo microtest).
- Trabajo directo sobre `main` (sin PR; el VPS auto-pull).
- Sin regresión: **un recurso sin la nueva config debe comportarse exactamente como hoy.**

---

## Ítem #4 — Pie de totales en listado no agrupado

### Estado actual (verificado)

- `CrudDataService::list()` calcula `summaryRow` también en modo plano (`app/Application/Services/CrudDataService.php:194-202`) vía `GenericCrudRepository::selectGlobalAggregates`.
- `CrudTableBuilder::build()` solo formatea el pie cuando hay agrupación: `if ($grouped && $summaryRow !== [])` (`app/Application/Services/CrudTableBuilder.php:84`).
- `admin/crud/index.php` solo renderiza `<tfoot>` en modo agrupado.
- Existe el flag `aggregationSkipped` (+ `aggregationSkipMessage`) cuando la agregación se omite por volumen de filas.

### Cambio

1. **`CrudTableBuilder::build()`** — formatear `summaryRow` también cuando `!$grouped && $summaryRow !== []`.
   - En modo plano las columnas visibles son las literales del listado (`listColumns()`), **no** las del modo agrupado.
   - Mapear cada summary a la **posición de su columna**: para un summary `{type: sum|count, column: X}`, el valor (`crud_sum_X` / `crud_cnt_X` que viene en `summaryRow`) se coloca en la celda de la columna `X` del pie. Las columnas sin summary quedan con celda vacía.
   - La primera celda del pie lleva una etiqueta (p. ej. "Totales"); si la primera columna ya es objeto de un summary, la etiqueta va en una celda de cabecera del `<tfoot>` sin pisar el número.
   - Devolver el pie formateado en una clave consumible por la vista (reutilizar `summaryRow` formateado del payload).

2. **`admin/crud/index.php`** — renderizar `<tfoot>` cuando `!empty($summaryRow)` (no solo `$grouped`). En modo plano, una celda por columna alineada con `<thead>`.

3. **Respeto de `aggregationSkipped`** — si la agregación se omitió por volumen, **no** se muestra el pie (el `summaryRow` ya viene vacío en ese caso; verificar que la vista no asuma presencia).

### Tests (microtest)

- `CrudTableBuilder` con summaries y `!grouped` → devuelve pie formateado, valores en la columna correcta, resto de celdas vacías.
- Sin summaries → pie vacío, sin cambios respecto a hoy.
- Modo agrupado → comportamiento actual intacto (no regresión).
- `aggregationSkipped = true` → pie vacío.

### Sin regresión

Recursos sin `list.summaries` no cambian. Modo agrupado no cambia.

---

## Ítem #3 — Aislamiento por usuario (row-level scope)

### Estado actual (verificado)

- `app/Domain/Interfaces/CrudListScopeInterface.php` — `apply(CrudListContext $ctx): void`. **Sin implementaciones, sin invocadores.**
- `app/Application/Crud/Context/CrudListContext.php` — acumula condiciones estructuradas `{column, op, value}` con whitelist de operadores (`= != < > <= >= LIKE IN`). Recibe `userId`, `query`, etc.
- `config/crud_handlers.php` ya documenta `CrudListScopeInterface` como tipo de handler registrable por clave string (whitelist; nunca FQCN en JSON).
- `CrudDataService::list(CrudResourceDefinition $definition, array $query)` **no recibe `userId`** (a diferencia de `store/update/delete`, que reciben `?int $userId, string $ip`).
- `CrudDataService` ya asigna `created_by` automáticamente al crear (columna protegida).
- Hueco de seguridad: show/edit/update/delete no validan propiedad → un usuario con `.ver` y URL directa puede ver/editar registros ajenos.

### Declaración en JSON

**Caso común (built-in, sin clase PHP):**
```json
"list": {
  "scope": {
    "type": "owner",
    "column": "created_by",
    "bypass_permission": "{prefix}.ver_todos"
  }
}
```

**Caso custom (escape hatch, patrón de registry existente):**
```json
"list": {
  "scope_handler": "clientes_owner"
}
```
donde `clientes_owner` se registra en `config/crud_handlers.php` apuntando a una clase que implementa `CrudListScopeInterface`.

Reglas:
- Sin `scope` ni `scope_handler` → no se aplica scope (comportamiento actual).
- `{prefix}` en `bypass_permission` se expande al prefijo de permisos del recurso (igual que `.ver/.crear/.editar/.eliminar`).
- `scope` y `scope_handler` son mutuamente excluyentes; si aparecen ambos, el validador rechaza la config.

### Componentes

1. **`OwnerListScope`** (nuevo — `app/Application/Crud/Scopes/OwnerListScope.php`), implementa `CrudListScopeInterface`.
   - Construido con la config `scope` (column, bypass_permission ya expandido) + el set de permisos del usuario + flag de bypass resuelto.
   - `apply(CrudListContext $ctx)`: si el usuario **no** tiene el bypass y `userId` no es null, `$ctx->addCondition(column, '=', userId)`. Si tiene bypass → no añade condición (ve todo).
   - Si `userId` es null (no debería pasar en panel autenticado) → política de no-fuga concreta: añade `addCondition(column, '=', -1)` (id imposible, operador en whitelist) para que el listado quede vacío en lugar de mostrar filas ajenas.

2. **`CrudScopeResolver`** (nuevo — `app/Application/Services/CrudScopeResolver.php`), punto único de resolución.
   - Entrada: `CrudResourceDefinition` + contexto de usuario (`userId`, `permisoSlugs`).
   - Si `list.scope.type === 'owner'` → instancia `OwnerListScope`.
   - Si `list.scope_handler` → resuelve vía `CrudHandlerRegistry` y valida que implemente `CrudListScopeInterface`.
   - Si nada → devuelve `null` (sin scope).
   - Expone también la metadata de ownership (column + bypass resuelto) para reutilizarla en el bloqueo server-side, de modo que listado y bloqueo compartan una sola fuente de verdad.

3. **Cableado en `CrudDataService::list()`**
   - Cambiar firma para recibir el contexto de usuario necesario (`?int $userId`, `array $permisoSlugs`). Actualizar el/los llamador(es) en el controlador CRUD.
   - Construir `CrudListContext` con `userId` + `query`.
   - Obtener el scope vía `CrudScopeResolver`; si existe, `scope->apply($ctx)`.
   - Traducir `$ctx->conditions()` a `$where`/`$params`:
     - `column` con `quoteIdentifier`/backtick-quoting consistente con el resto de `list()`.
     - operador desde la whitelist ya validada por `CrudListContext`.
     - valor como placeholder `?` + `$params[]`.
   - Inyectar **antes** de `countFiltered` (`CrudDataService::list()` línea ~103) para que conteo, paginación, agregados y pie de totales respeten el scope.

4. **Bloqueo server-side (show/edit/update/delete)** — en `CrudResourceService` (capa Application).
   - Tras cargar el registro, si el recurso declara `scope` owner y el usuario **no** tiene el `bypass_permission` y `registro[column] !== userId` → denegar.
   - Reutiliza la metadata de `CrudScopeResolver` (misma fuente de verdad que el listado).
   - **Respuesta:** 404 (tratar como "no encontrado") para no revelar la existencia de registros ajenos. Aplica a show, edit (form), update (POST) y delete.

### Decisiones de borde

- **Sin scope declarado = cero cambios** (recursos `demo_*` intactos).
- `created_by` ya está disponible (auditoría de autor existente).
- `scope` + `scope_handler` simultáneos → error de validación en `CrudConfigValidator`.
- `bypass_permission` con `{prefix}` expandido por el resolver/validador.
- `userId` null en contexto autenticado → política de no-fuga (no devuelve filas ajenas; bloqueo deniega).

### Validación de config (`CrudConfigValidator`)

- `list.scope.type` debe ser `"owner"` (único tipo built-in soportado).
- `list.scope.column` requerido (string no vacío).
- `list.scope.bypass_permission` opcional (string).
- `list.scope_handler` opcional (string); excluyente con `list.scope`.

### Tests (microtest)

- `OwnerListScope::apply` añade condición `created_by = userId` sin bypass.
- `OwnerListScope::apply` no añade condición con bypass (admin ve todo).
- `OwnerListScope::apply` con `userId` null → política de no-fuga.
- `CrudScopeResolver` elige built-in / handler / null según config.
- `CrudScopeResolver` con `scope` + `scope_handler` → maneja exclusión (vía validador).
- `CrudDataService::list()` con scope owner → `$where`/`$params` incluyen el filtro; conteo/paginación lo respetan.
- `CrudDataService::list()` usuario con bypass → sin filtro.
- Bloqueo: show/update/delete de registro ajeno (sin bypass) → 404; propio → permitido; con bypass → permitido.
- `CrudConfigValidator`: rechaza `type` inválido, falta `column`, y `scope` + `scope_handler` juntos.
- Recurso sin scope → sin cambios (no regresión).

### Punto de mayor superficie

Propagar `userId` (+ `permisoSlugs`) hasta `CrudDataService::list()` toca la firma del método y su llamador en el controlador CRUD. Es el cambio de mayor alcance del plan, pero contenido a esa cadena.

---

## Ítem #2 — Dashboard por perfil (solo corto plazo)

### Estado actual (verificado)

- `DefaultPlatformDashboardProvider::contribute(DashboardBuildContext $context)` (`app/Infrastructure/Dashboard/DefaultPlatformDashboardProvider.php`) aporta KPIs, accesos rápidos y actividad **sin filtrar por permiso**.
- `DashboardBuildContext` expone `usuarioId`, `permisoSlugs`, `rolSlugs`, `tienePermiso()`, `tieneRol()`.
- El patrón "filtrar por `tienePermiso()`" ya está documentado en `docs/modules/modulo-dashboard.md`, pero el proveedor por defecto no lo aplica.

### Cambio

1. **`DefaultPlatformDashboardProvider::contribute()`** — envolver cada KPI / acceso rápido / ítem de actividad en un chequeo `$context->tienePermiso($slug)`:
   - "Usuarios" (KPI + quick) → permiso de gestión de usuarios.
   - "Roles" (KPI + quick) → permiso de gestión de roles.
   - "Ajustes" (KPI + quick) → permiso de ajustes.
   - Solo se agregan al array las entradas permitidas.
   - Si el usuario no tiene ninguna, el proveedor devuelve una contribución mínima válida (no rompe el dashboard ni el view model).

2. **Slugs reales** — usar los slugs sembrados en `database/seeds/010_auth_permisos.sql` para no filtrar con permisos inexistentes (que ocultarían todo). Verificar los slugs exactos durante la implementación.

### Fuera (nuevo, no parcial)

`config/dashboard_widgets.php` declarativo, `cfg_dashboard_layout` por rol, editor visual, preferencias por usuario.

### Tests (microtest)

- `DashboardBuildContext` con permiso de usuarios → KPI/quick "Usuarios" presente.
- Sin ese permiso → ausente.
- Contexto sin ninguno de los permisos → contribución válida (sin keys faltantes ni warnings), dashboard no rompe.
- Con todos → comportamiento equivalente al actual.

### Sin regresión

Un admin con todos los permisos ve lo mismo que hoy.

---

## Resumen de archivos afectados

| Capa | Archivo | Acción | Ítem |
|------|---------|--------|------|
| Application | `Services/CrudTableBuilder.php` | editar | #4 |
| Presentation | `Views/admin/crud/index.php` | editar | #4 |
| Application | `Crud/Scopes/OwnerListScope.php` | nuevo | #3 |
| Application | `Services/CrudScopeResolver.php` | nuevo | #3 |
| Application | `Services/CrudDataService.php` | editar (firma `list` + cableado) | #3 |
| Application | `Services/CrudResourceService.php` | editar (bloqueo show/edit/update/delete) | #3 |
| Application | `Services/CrudConfigValidator.php` | editar (validar `scope`/`scope_handler`) | #3 |
| Presentation | Controlador CRUD (llamador de `list()`) | editar (propagar userId/permisos) | #3 |
| Infrastructure | `Dashboard/DefaultPlatformDashboardProvider.php` | editar (filtrar por permiso) | #2 |
| Tests | `tests/...` (microtest por componente) | nuevo | #2/#3/#4 |
| Config (demo, opcional) | `config/cruds/*.json` | ejemplo de `scope` para validar e2e | #3 |
| Docs | `docs/modules/crud/modulo-crud-engine.md` | documentar `list.scope` / pie plano | #3/#4 |

---

## Criterios de aceptación globales

1. `php tests/run.php` en verde (incluye los nuevos tests).
2. Un recurso CRUD existente sin nueva config se comporta **exactamente** como antes (sin regresión en `demo_*`).
3. Un recurso con `list.scope` owner: usuario regular solo ve/edita/borra lo suyo; admin con `ver_todos` ve todo; acceso por URL directa a registro ajeno → 404.
4. Un listado plano con `list.summaries` muestra el pie de totales correctamente alineado.
5. El dashboard por defecto oculta KPIs/enlaces para los que el usuario no tiene permiso, sin romper para usuarios sin permisos.
