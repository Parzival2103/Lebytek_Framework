# Auth / RBAC — seguridad y operación v0.1

**Ámbito:** autenticación, roles, permisos, política de acceso, menú y rutas web admin. No sustituye a [modulo-crud-engine.md](../modules/crud/modulo-crud-engine.md) para recursos `dom_*`.

**Relacionado:** [modulos_especializados_plataforma.md](./modulos_especializados_plataforma.md), [modulo-menu.md](../modules/modulo-menu.md), [correccion_alineacion_modulos_v0.1.md](../audits/correccion_alineacion_modulos_v0.1.md), [correccion_auth_rbac_v0.1.md](../audits/correccion_auth_rbac_v0.1.md).

---

## 1. Propósito

Centralizar cómo el sistema:

- autentica usuarios (`auth_usuarios`),
- resuelve permisos efectivos por rol (`auth_roles`, `auth_permisos`, `auth_roles_permisos`, `auth_usuarios_roles`),
- expone o niega rutas y entradas de menú,
- valida slugs de permisos y coherencia con el CRUD Engine.

El objetivo de seguridad es **mínimo privilegio**, **sin SQL dinámico inseguro** en repositorios RBAC y **sin asignar permisos inexistentes** aunque el cliente manipule el POST.

---

## 2. Por qué no usa el CRUD Engine

Las tablas `auth_*` están **excluidas** del motor (ver [modulo-crud-engine.md](../modules/crud/modulo-crud-engine.md) §6). Los flujos involucran credenciales, catálogo técnico de autorización y efectos globales; requieren **módulos especializados** y validación explícita.

---

## 3. Estructura de tablas

| Tabla | Uso |
|-------|-----|
| `auth_usuarios` | Cuentas; hash de contraseña; estado activo |
| `auth_roles` | Roles; slug único |
| `auth_permisos` | Permisos; `slug` único; `modulo` para agrupación UI |
| `auth_roles_permisos` | N:M rol ↔ permiso |
| `auth_usuarios_roles` | N:M usuario ↔ rol |
| `core_menu_items` | Menú; `permiso_slug` opcional |

No existe columna `status` en `auth_permisos` en el esquema base: el catálogo “vigente” es el conjunto de filas presentes; obsoletos se gestionan por procedimiento (ver §8).

---

## 4. Regla de slugs de permiso

Formato canónico validado en dominio:

```text
^[a-z0-9_]+\.[a-z0-9_]+$
```

Ejemplos válidos: `dashboard.ver`, `usuarios.gestionar`, `demo_clientes.crear`, `clientes.ver`.

Implementación: [`PermisoSlugFormatRule`](../../app/Domain/Rules/PermisoSlugFormatRule.php) y [`PermisoModuloFormatRule`](../../app/Domain/Rules/PermisoModuloFormatRule.php) para el campo `modulo`.

Los **slugs de rol** siguen usando el [`Slug`](../../app/Domain/ValueObjects/Slug.php) genérico (no exigen punto).

---

## 5. Permisos por ruta (web)

Definidos en [`routes/web.php`](../../routes/web.php) con `RbacMiddleware`. Lista de referencia sincronizada con código en [`config/rbac_route_permissions.php`](../../config/rbac_route_permissions.php) (usada por el informe de integridad).

| Prefijo / ruta | Permiso |
|----------------|---------|
| `/admin/dashboard` | `dashboard.ver` |
| `/admin/ajustes` | `administracion.ver` |
| `/admin/administracion/usuarios` | `usuarios.gestionar` |
| `/admin/administracion/roles` | `roles.gestionar` |
| `/admin/administracion/permisos` | `administracion.ver` |
| `/admin/crud/{resource}` | `{permission_prefix}.ver` \| `crear` \| `editar` \| `eliminar` (vía `CrudResourceService`) |

El rol `administrador` sigue la política especial en [`RbacPolicy`](../../app/Domain/Policies/RbacPolicy.php) (`esAdministrador()` → acceso total a comprobaciones de permiso).

---

## 6. Permisos por menú

Ítems en `core_menu_items` pueden declarar `permiso_slug`. El servicio [`AdminNavigationMenuService`](../../app/Application/Services/AdminNavigationMenuService.php) filtra por [`RbacPolicy`](../../app/Domain/Policies/RbacPolicy.php). Detalle: [modulo-menu.md](../modules/modulo-menu.md).

Los slugs del menú deben existir en `auth_permisos` salvo decisión explícita de ítem público (vacío).

---

## 7. Cómo agregar un permiso nuevo

1. Insertar fila en `auth_permisos` (o usar pantalla **Permisos** con CSRF) con `nombre`, `slug` (`modulo.accion`), `modulo` coherente con agrupación.
2. Asignar el permiso a roles (`auth_roles_permisos`) desde **Roles** o SQL.
3. Si aplica menú: actualizar `core_menu_items.permiso_slug` (migración o SQL controlado).
4. Si aplica CRUD: añadir los cuatro slugs `{prefix}.ver|crear|editar|eliminar` y reflejarlos en `config/cruds/*.json` (ver [uso-crud-engine.md](../modules/crud/uso-crud-engine.md)).
5. Ejecutar `php scripts/rbac_integrity_report.php` y revisar discrepancias.

---

## 8. Cómo detectar permisos obsoletos

- Ejecutar [`scripts/rbac_integrity_report.php`](../../scripts/rbac_integrity_report.php): agrupa slugs en BD vs menú vs `config/rbac_route_permissions.php` vs permisos esperados por JSON en `config/cruds/`.
- Clasificación orientativa en el informe: `legacy_detectado` (prefijos `catalogo.` o `entregas.` en slug), `sin_uso_confirmado`, `requiere_revision` (slug que no cumple formato), etc.
- **No borrar** filas automáticamente: documentar y planificar migración o deprecación.

---

## 9. Reglas contra SQL injection (RBAC)

- Repositorios [`UsuarioRepository`](../../app/Infrastructure/Repositories/UsuarioRepository.php), [`RolRepository`](../../app/Infrastructure/Repositories/RolRepository.php), [`PermisoRepository`](../../app/Infrastructure/Repositories/PermisoRepository.php), [`MenuCatalogRepository`](../../app/Infrastructure/Repositories/MenuCatalogRepository.php) usan **prepared statements** vía [`BaseRepository`](../../app/Kernel/BaseClasses/BaseRepository.php).
- `IN (...)` dinámico en `filterExistingPermisoIds` solo expande placeholders **`?`** con enteros validados, no texto concatenado.
- Evitar pasar nombres de columnas u `ORDER BY` desde entrada de usuario sin lista blanca (no aplica hoy en estos repositorios).

---

## 10. Asignación segura de permisos a roles

[`PermisoRepository::sincronizarPermisosDeRol`](../../app/Infrastructure/Repositories/PermisoRepository.php) solo persiste IDs devueltos por `filterExistingPermisoIds`, de modo que IDs inventados en POST **no** se enlazan.

---

## 11. Checklist de pruebas manuales (seguridad)

### Login

- Credenciales correctas / incorrectas; usuario inexistente; usuario inactivo.
- Contraseña con comillas o caracteres especiales (debe fallar sin error SQL).
- Cadenas tipo `admin' OR '1'='1` en email (debe fallar login normal).

### Roles

- Editar rol sin `roles.gestionar` (403).
- POST manipulado con `permiso_ids[]` inexistentes o negativos (deben ignorarse al guardar).

### Permisos

- Slug sin punto, con espacios, con SQL injection en texto (rechazo validación).
- Slug duplicado al crear (rechazo).

### Rutas

- `/admin/dashboard` sin `dashboard.ver` (403).
- `/admin/ajustes` sin `administracion.ver` (403).
- CRUD recurso sin `{prefix}.ver` (403 u omitido según acción).

### Menú

- Ítem con `permiso_slug` inexistente en BD: revisar informe; corregir datos.

---

## 12. Herramientas

| Recurso | Descripción |
|---------|-------------|
| `php scripts/rbac_integrity_report.php` | JSON con discrepancias y clasificación |
| `config/rbac_route_permissions.php` | Slugs esperados por middleware admin (mantener alineado a `routes/web.php`) |

---

*v0.1 — alineado a PHP 8.1, LEBYTEK UI, sin dependencias nuevas.*
