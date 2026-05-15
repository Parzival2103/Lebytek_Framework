# Corrección de alineación de módulos v0.1

**Fecha:** 2026-05-02.  
**Ámbito:** cierre de acciones derivadas de [auditoria_alineacion_modulos_v0.1.md](./auditoria_alineacion_modulos_v0.1.md). **No** se migró ningún módulo al CRUD Engine.

---

## 1. Archivos modificados

| Archivo | Cambio |
|---------|--------|
| [`routes/web.php`](../../routes/web.php) | RBAC por ruta: `dashboard.ver`, `administracion.ver` (ajustes), `usuarios.gestionar`, `roles.gestionar`, `administracion.ver` (permisos). Eliminado el único `RbacMiddleware('administracion.ver')` del grupo `/administracion` en favor de permisos por recurso. |
| [`app/Presentation/Controllers/Admin/UsuariosController.php`](../../app/Presentation/Controllers/Admin/UsuariosController.php) | Constante `USUARIOS_BASE` y redirects corregidos a `/admin/administracion/usuarios`. |
| [`database/seeds/015_core_menu_items.sql`](../../database/seeds/015_core_menu_items.sql) | Subítems Usuarios y Roles: `permiso_slug` alineado con rutas (`usuarios.gestionar`, `roles.gestionar`). Ajustes sigue en `administracion.ver`. |
| [`database/migrations/20260502120000_menu_rbac_granular_admin_subitems.sql`](../../database/migrations/20260502120000_menu_rbac_granular_admin_subitems.sql) | `UPDATE` idempotentes en `core_menu_items` para instalaciones ya existentes. |

## Archivos creados

| Archivo | Contenido |
|---------|-----------|
| [`docs/core/modulos_especializados_plataforma.md`](../core/modulos_especializados_plataforma.md) | Normativa de módulos especializados del core vs CRUD Engine. |
| `docs/audits/correccion_alineacion_modulos_v0.1.md` | Este informe. |

---

## 2. Permisos aplicados (HTTP)

| Ruta (prefijo `/admin`) | Middleware RBAC |
|-------------------------|-----------------|
| `GET /dashboard` | `dashboard.ver` |
| `GET|POST /ajustes`, `POST /ajustes/toggle-tema` | `administracion.ver` |
| Rutas `/administracion/usuarios/*` | `usuarios.gestionar` |
| Rutas `/administracion/roles/*` | `roles.gestionar` |
| Rutas `/administracion/permisos/*` | `administracion.ver` (ver §4) |
| `/crud/*` | Sin cambio: permisos por recurso en `CrudResourceService`. |

Orden efectivo de middlewares: `AuthMiddleware` del grupo admin + permisos anteriores + `CsrfMiddleware` donde corresponda.

---

## 3. Redirects corregidos (usuarios)

Sustituido el prefijo erróneo `/admin/usuarios` por **`/admin/administracion/usuarios`** (vía `UsuariosController::USUARIOS_BASE`) en:

- éxito al crear y actualizar usuario,
- error de validación al crear (vuelta a `/crear`),
- error de validación al editar (vuelta a `/{id}/editar`),
- éxito y error al eliminar/desactivar.

Las vistas ya usaban URLs correctas en formularios y enlaces; no fue necesario cambiar plantillas.

---

## 4. Decisiones tomadas

1. **`permisos.gestionar`:** no existe en [`010_auth_permisos.sql`](../../database/seeds/010_auth_permisos.sql). No se añadió slug nuevo para no alterar el catálogo sin revisión explícita de producto. Las rutas de permisos usan **`administracion.ver`** como permiso de ruta (coherente con la criticidad del catálogo RBAC).
2. **`ajustes.ver`:** no existe en seeds. Se usa **`administracion.ver`**, alineado con el ítem de menú “Ajustes”.
3. **Menú:** subítems Usuarios y Roles pasan a **`usuarios.gestionar`** y **`roles.gestionar`** para coincidir con rutas y evitar ver en menú lo que la ruta denegaría (y viceversa). El padre “Administración” sigue con `administracion.ver`; el servicio [`AdminNavigationMenuService`](../../app/Application/Services/AdminNavigationMenuService.php) puede mostrar el padre como agrupador si hay hijos visibles sin el permiso del padre.
4. **Rol `administrador`:** [`RbacPolicy`](../../app/Domain/Policies/RbacPolicy.php) concede `puede()` a cualquier permiso si el usuario tiene rol `administrador`; el comportamiento del superusuario no cambia.
5. **Instalaciones existentes:** ejecutar la migración `20260502120000_menu_rbac_granular_admin_subitems.sql` para actualizar filas ya insertadas (los `INSERT IGNORE` de seeds no sobrescriben).

---

## 5. Pendientes

| Ítem | Nota |
|------|------|
| **Slug `permisos.gestionar`** | Evaluar creación en `auth_permisos`, asignación a roles y cambio de `RbacMiddleware` + menú si se desea separar “ver administración” de “editar catálogo de permisos”. |
| **Slug `ajustes.ver`** | Opcional, si se quiere restringir ajustes sin otorgar todo el alcance implícito de `administracion.ver` en otros flujos futuros. |
| **Roles personalizados** | Quien tuviera **solo** `administracion.ver` y dependiera de acceder a usuarios/roles sin slugs granulares debe recibir **`usuarios.gestionar`** y **`roles.gestionar`** (la semilla `administrador` ya tiene todos los permisos vía `CROSS JOIN`). |
| **Dashboard / KPIs** | Los enlaces del proveedor por defecto pueden mostrarse a usuarios con `dashboard.ver` aunque no tengan otros permisos; la ruta destino seguirá respondiendo 403. Refinar en un ticket de UX si se desea ocultar enlaces según `RbacPolicy` en Application/Infrastructure. |

---

## 6. Criterios de aceptación (checklist)

1. Dashboard exige **`dashboard.ver`** además de sesión.  
2. Ajustes exige **`administracion.ver`** además de sesión.  
3. Usuarios / roles exigen **`usuarios.gestionar`** / **`roles.gestionar`**; permisos exigen **`administracion.ver`**.  
4. Redirects de usuarios apuntan a rutas reales bajo `/admin/administracion/usuarios`.  
5. Documento [`modulos_especializados_plataforma.md`](../core/modulos_especializados_plataforma.md) creado.  
6. No se migró nada al CRUD Engine; **`schema.sql`** no se modificó.

---

*Fin del informe v0.1.*
