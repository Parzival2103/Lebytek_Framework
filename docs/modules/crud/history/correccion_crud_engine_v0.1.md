# Corrección CRUD Engine v0.1

Informe breve de endurecimiento y mejoras aplicadas al **CRUD Engine existente**, alineado con [`docs/audits/auditoria_crud_engine_v0.1.md`](../../../audits/auditoria_crud_engine_v0.1.md) (sin reconstruir el motor).

## Archivos modificados / agregados

### Seguridad y configuración

- `config/crud_handlers.php` (whitelist de handlers; claves simples desde JSON)
- `app/Domain/Interfaces/CrudHookHandlerInterface.php`
- `app/Application/Crud/Handlers/AbstractCrudHookHandler.php`
- `app/Application/Services/CrudHandlerRegistry.php`
- `app/Application/Services/CrudHookRunner.php`
- `app/Application/Services/CrudConfigValidator.php`
- `app/Application/Services/CrudConfigLoader.php`
- `config/container.php` (DI: registry + validación + `CrudFieldValidationService`)

### Datos, validación y agregaciones

- `app/Application/Services/CrudFieldValidationService.php`
- `app/Application/Services/CrudDataService.php`
- `app/Infrastructure/Repositories/GenericCrudRepository.php`
- `app/Domain/Entities/CrudResourceDefinition.php`
- `app/Application/Services/CrudTableBuilder.php`
- `app/Application/Services/CrudResourceService.php`

### UI CRUD + navegación

- `app/Application/Services/CrudFormBuilder.php`
- `app/Presentation/Views/admin/crud/form.php`
- `app/Presentation/Views/admin/crud/index.php`
- `app/Presentation/Views/admin/crud/show.php`
- `app/Application/Services/AdminNavigationMenuService.php`
- `app/Presentation/Views/partials/nav_side.php`
- `app/Presentation/Views/partials/nav_top.php`
- `app/Presentation/Views/partials/nav_bottom.php`

### Demo / DB

- `config/cruds/demo_productos.json` (ejemplo `group_by` + `summaries`)
- `database/migrations/20260428133000_crud_demo_menu_parent_perm_null.sql` (padre CRUD Demo sin `administracion.ver`)

## Problemas corregidos (mapeo a fases)

### Fase 1 — urgente

1. **Handlers dinámicos:** JSON ya no puede declarar FQCN en `hooks.handler`. Se usa clave simple + whitelist (`config/crud_handlers.php`) + validación de interfaz en carga.
2. **Readonly sin pérdida en POST:** `select`/`checkbox` readonly se renderizan deshabilitados visualmente con **input hidden** que conserva `name`/valor; además el backend preserva readonly en update.
3. **Menú vs permisos:** el menú permite conservar padres como agrupador si hay hijos visibles aunque el permiso del padre falle; además se ajustó el demo para que el padre no exija `administracion.ver` (migración) y se evitó `href=""` en ítems sin URL.

### Fase 2 — corto plazo

4. **Validaciones JSON en backend:** reglas mínimas en `CrudFieldValidationService` (required, longitudes, tipos, min/max, email, fecha, `in`, `regex` con validación de patrón).
5. **Logs en loader:** `CrudConfigLoader` registra fallas de JSON/validación y omisiones en `listResources()` vía `AppLogger` (archivo diario en `storage/logs`).
6. **Control de tipos:** coercion conservadora + validación previa; checkbox ausente en POST se trata como `0` en creates/updates no-readonly.

### Fase 3 — mejora

7. **Agrupaciones y sumas:** soporte básico `list.group_by` + `list.summaries` con agregación vía SQL parametrizado + presentación agrupada en UI (sin SQL crudo desde JSON).
8. **UI CRUD:** encabezados, tabla responsive/striped, badges, modal de confirmación para borrado, footer de totales en modo agrupado (cuando aplica).

## Decisiones tomadas

- **Whitelist de handlers** centralizada en `config/crud_handlers.php` para mantener compatibilidad con PHP 8.1 sin acoplarse a reflexión “mágica”.
- **Readonly:** doble defensa (UI + servidor). El servidor ignora POST en readonly y reusa valores persistidos.
- **Menú:** la lógica de filtrado se corrigió en `AdminNavigationMenuService`; el ajuste SQL del padre evita depender de un permiso “amplio” para agrupar demos.
- **Agregaciones:** alias fijos `crud_sum_*` / `crud_cnt_*` generados en repositorio (identificadores validados), y la UI de listado en modo agrupado construye columnas a partir de `group_by` + `summaries` para no exigir columnas “fantasma” en `list.columns`.
- **Logging:** se usa `AppLogger` como logger existente y estable; no se agregó escritura directa a bitácora para no inflar dependencias del loader.

## Pendientes recomendados

- Añadir handlers reales al mapa (`config/crud_handlers.php`) cuando existan clases con lógica (hoy el mapa puede estar vacío y fallará validación si se declara `handler`).
- Unificar formato numérico para enteros (ej. `stock`) vs `money` en summaries (hoy el demo usa `money` por simplicidad).
- Evaluar interfaces de repositorio **solo** si el equipo quiere test doubles; no es necesario para estabilidad v0.1.

## Pruebas manuales sugeridas

1. **Handlers:** crear temporalmente un JSON con `hooks.handler` FQCN y verificar que **falla validación**; usar clave registrada y verificar ejecución de hooks.
2. **Readonly:** en un campo `select`/`checkbox` readonly, editar otros campos y guardar: valores readonly permanecen.
3. **Menú:** usuario con `demo_clientes.ver` sin `administracion.ver` debe ver el grupo **CRUD Demo** y el link hijo.
4. **Validación backend:** enviar valores inválidos (email, min/max, `in`) y verificar errores por campo + flash en create/edit.
5. **Loader:** romper un JSON en `config/cruds/` y verificar warning en listado + error log en acceso directo.
6. **Agrupación:** abrir `/admin/crud/demo_productos` y verificar grupos por `status`, orden por columnas permitidas y footer de totales.
7. **Delete UX:** eliminar desde listado y detalle y verificar modal + POST CSRF.
