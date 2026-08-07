# Corrección Auth / RBAC v0.1

**Fecha:** 2026-05-02.  
**Alcance:** endurecimiento del módulo especializado Auth/RBAC (sin migrar tablas `auth_*` al CRUD Engine).

---

## 1. Archivos modificados o creados

| Ruta | Cambio |
|------|--------|
| [`app/Domain/Rules/PermisoSlugFormatRule.php`](../../app/Domain/Rules/PermisoSlugFormatRule.php) | **Nuevo** — patrón `^[a-z0-9_]+\.[a-z0-9_]+$` |
| [`app/Domain/Rules/PermisoModuloFormatRule.php`](../../app/Domain/Rules/PermisoModuloFormatRule.php) | **Nuevo** — normalización/validación `modulo` |
| [`app/Domain/Interfaces/PermisoRepositoryInterface.php`](../../app/Domain/Interfaces/PermisoRepositoryInterface.php) | `filterExistingPermisoIds`, `listarTodosLosSlugs` |
| [`app/Domain/Interfaces/MenuCatalogRepositoryInterface.php`](../../app/Domain/Interfaces/MenuCatalogRepositoryInterface.php) | `listarSlugsPermisoReferenciadosEnMenu` |
| [`app/Infrastructure/Repositories/PermisoRepository.php`](../../app/Infrastructure/Repositories/PermisoRepository.php) | Filtro de IDs en `sincronizarPermisosDeRol`; consultas preparadas para `IN` |
| [`app/Infrastructure/Repositories/MenuCatalogRepository.php`](../../app/Infrastructure/Repositories/MenuCatalogRepository.php) | DISTINCT `permiso_slug` activos |
| [`app/Application/UseCases/Roles/ListarRolesUseCase.php`](../../app/Application/UseCases/Roles/ListarRolesUseCase.php) | `obtenerPermisosAgrupadosParaFormulario()` — agrupación dinámica desde BD |
| [`app/Presentation/Controllers/Admin/RolesController.php`](../../app/Presentation/Controllers/Admin/RolesController.php) | Pasa `permisosAgrupados` a vistas |
| [`app/Presentation/Views/admin/roles/crear.php`](../../app/Presentation/Views/admin/roles/crear.php) | LEBYTEK, cards por grupo, filtro búsqueda, seleccionar todo por grupo |
| [`app/Presentation/Views/admin/roles/editar.php`](../../app/Presentation/Views/admin/roles/editar.php) | Igual |
| [`app/Presentation/Controllers/Admin/PermisosController.php`](../../app/Presentation/Controllers/Admin/PermisosController.php) | Validación slug/módulo; anti-duplicado por slug |
| [`app/Presentation/Views/admin/permisos/crear.php`](../../app/Presentation/Views/admin/permisos/crear.php) | LEBYTEK; patrón HTML alineado |
| [`app/Presentation/Views/admin/permisos/editar.php`](../../app/Presentation/Views/admin/permisos/editar.php) | LEBYTEK; patrón HTML |
| [`app/Application/Services/RbacIntegrityReportService.php`](../../app/Application/Services/RbacIntegrityReportService.php) | **Nuevo** — informe coherencia menú/rutas/CRUD vs BD |
| [`config/rbac_route_permissions.php`](../../config/rbac_route_permissions.php) | **Nuevo** — slugs de `RbacMiddleware` |
| [`config/container.php`](../../config/container.php) | Registro `RbacIntegrityReportService` |
| [`routes/web.php`](../../routes/web.php) | Formato limpio (misma semántica RBAC) |
| [`database/seeds/010_auth_permisos.sql`](../../database/seeds/010_auth_permisos.sql) | Permisos `clientes.*` para CRUD `clientes` |
| [`database/migrations/20260502150000_auth_permisos_dom_clientes.sql`](../../database/migrations/20260502150000_auth_permisos_dom_clientes.sql) | **Nuevo** — `INSERT IGNORE` + rol administrador en instalaciones existentes |
| [`scripts/rbac_integrity_report.php`](../../scripts/rbac_integrity_report.php) | **Nuevo** — CLI informe JSON |
| [`docs/core/auth_rbac_seguridad_v0.1.md`](../core/auth_rbac_seguridad_v0.1.md) | **Nuevo** — guía normativa |
| `docs/audits/correccion_auth_rbac_v0.1.md` | Este informe |

---

## 2. Hallazgos (auditoría interna)

### 2.1 Estado de permisos en repositorio

| Clasificación | Observación |
|---------------|-------------|
| **vigente** | Seeds base: `administracion.ver`, `usuarios.gestionar`, `roles.gestionar`, `bitacora.ver`, `dashboard.ver`; CRUD demo en migración `20260428132500_*`: `demo_*.*`; dominio `clientes.*` añadido en seed + migración |
| **legacy_detectado** | No hay slugs `catalogo.*` ni `entregas.*` en seeds del proyecto; si aparecen en BD de instalaciones antiguas, el informe los marcará por prefijo |
| **sin_uso_confirmado** | Slugs en BD que no aparecen en menú, ni en `rbac_route_permissions.php`, ni en permisos esperados desde `config/cruds/*.json` — revisar antes de borrar |
| **requiere_revision** | Slugs que no cumplen `PermisoSlugFormatRule` (carga vía `Slug` VO puede haber permitido variantes históricas con guión medio en segmentos) |

### 2.2 Vistas de roles

Las plantillas **no** hardcodeaban permisos de un dominio anterior: ya leían `$permisosPorModulo` desde `auth_permisos`. El problema percibido suele ser **datos antiguos en BD** (p. ej. migraciones demo u obras manuales). La nueva agrupación usa `modulo` o prefijo del slug y muestra el slug en cada línea para transparencia.

### 2.3 Seguridad

- **Alta:** `sincronizarPermisosDeRol` aceptaba cualquier entero en POST; ahora solo IDs existentes en `auth_permisos`.
- **Media:** creación de permisos sin formato estricto `modulo.accion`; ahora validado.
- **Repositorios:** revisados; sin concatenación de entrada de usuario en SQL en RBAC revisado.

---

## 3. Permisos nuevos o faltantes

- Añadidos **`clientes.ver`**, **`clientes.crear`**, **`clientes.editar`**, **`clientes.eliminar`** alineados con [`config/cruds/clientes.json`](../../config/cruds/clientes.json) (seed + migración idempotente).

---

## 4. Validaciones aplicadas

- `PermisoSlugFormatRule` y `PermisoModuloFormatRule` en alta/edición de permisos.
- Duplicado de slug en crear/actualizar (misma tabla).
- Filtrado de IDs al sincronizar rol.

---

## 5. Pruebas manuales sugeridas

Ver checklist en [`docs/core/auth_rbac_seguridad_v0.1.md`](../core/auth_rbac_seguridad_v0.1.md) §11.

---

## 6. Pendientes

- Definir slug dedicado `permisos.gestionar` y sustituir `administracion.ver` en rutas de catálogo de permisos si se desea separar capacidades.
- Revisión periódica de `slugs_posiblemente_sin_uso` del informe CLI antes de eliminar filas.
- Opcional: columna `activo` / `deprecated_at` en `auth_permisos` en una migración futura (requiere cambio de esquema acordado).
- Filtrar enlaces del dashboard por permisos efectivos (mejora UX; la ruta destino ya protege con RBAC).

---

*v0.1 — fin del informe.*
