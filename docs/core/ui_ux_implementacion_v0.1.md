# Implementación LEBYTEK UI v0.1

**Fecha de referencia:** 2026-04-30.  
**Documento normativo de diseño:** [ui_ux.md](./ui_ux.md) (LEBYTEK UI / reglas visuales del framework).

Este documento describe la primera capa implementada bajo el nombre de producto **LEBYTEK UI**, alineada al contrato de `ui_ux.md`, sin modificar el documento canónico salvo decisiones ya reflejadas allí.

---

## 1. Resumen

- Se añadió una hoja **LEBYTEK UI** (`public/assets/css/lebytek-ui.css`) con utilidades y componentes `.ct-*`.
- La configuración visual se centraliza en **`App\Kernel\Helpers\LebytekUiConfig`**, leyendo `cfg_configuraciones` con **claves planas** ya existentes (`primary_color`, `navbar_color`, `menu_layout`, etc.) y **claves opcionales** con prefijo o notación punto (`theme.primary_color`, `ui.layout_width`, …) con **fallback seguro**.
- El bloque de variables CSS inyectadas (Bootstrap + tema) se extrajo a **`app/Presentation/Views/partials/styles/lebytek_theme_vars.php`** para reutilizarlo en **`layouts/base.php`** y **`auth/login.php`** (login sin reglas de navbar).
- **CRUD Engine**, **dashboard**, **administración** (usuarios, roles, permisos), **ajustes** y **login** usan las mismas convenciones de página/cards/acciones donde aplica.

---

## 2. Archivos nuevos

| Ruta | Descripción |
|------|-------------|
| `public/assets/css/lebytek-ui.css` | Clases `.ct-page`, `.ct-card`, `.ct-table-card`, `.ct-empty-state`, `.ct-actions`, densidad, layout boxed/fluid, login helpers. |
| `app/Kernel/Helpers/LebytekUiConfig.php` | Resolución de tokens desde arreglo de configuración; `defaultStatusBadges()`; `globalTableCompact()`. |
| `app/Presentation/Views/partials/styles/lebytek_theme_vars.php` | `:root` con `--ct-*`, `--app-*`, overrides Bootstrap; opción `includeNavChrome`. |
| `app/Presentation/Views/partials/empty_state.php` | Empty state genérico reutilizable (icono, título, hint). |
| `docs/core/ui_ux_implementacion_v0.1.md` | Este documento. |

---

## 3. Archivos modificados (principales)

| Área | Archivos |
|------|----------|
| Config tema | `app/Presentation/Controllers/AdminBaseController.php`, `app/Presentation/Controllers/AuthController.php` |
| Layout | `app/Presentation/Views/layouts/base.php` |
| Login | `app/Presentation/Views/auth/login.php` |
| CRUD | `app/Presentation/Views/admin/crud/index.php`, `form.php`, `show.php`; `app/Application/Services/CrudTableBuilder.php`; `app/Presentation/Controllers/Admin/CrudController.php`; `public/assets/css/crud-engine.css` |
| Partials | `partials/crud/list_empty.php`, `partials/nav_side.php`, `partials/topbar.php`, `partials/nav_top.php`, `partials/nav_bottom.php`, `partials/dashboard/kpi_grid.php` |
| Admin | `admin/dashboard/index.php`, `admin/usuarios/index.php`, `admin/roles/index.php`, `admin/permisos/index.php`, `admin/ajustes/index.php` |

---

## 4. Clases CSS LEBYTEK (`.ct-*`)

Definidas principalmente en `lebytek-ui.css`:

- **Página:** `.ct-page`, `.ct-page-header`, `.ct-page-title`, `.ct-page-subtitle`
- **Contenedores:** `.ct-card`, `.ct-table-card`, `.ct-form-card`, `.ct-detail-card`, `.ct-metric-card`
- **Acciones:** `.ct-actions` (+ utilidades responsive documentadas en comentarios)
- **Vacío:** `.ct-empty-state`, `__icon`, `__title`, `__hint`
- **Utilidades:** `.ct-col-actions`, `.ct-table-toolbar-search`, `.ct-login-page`, `.ct-login-card`, `.ct-login-brand-logo`
- **Modificadores de body (desde config):** `.ct-layout-fluid` | `.ct-layout-boxed`, `.ct-density-comfortable` | `.ct-density-compact`, `.ct-card-soft` | `.ct-card-bordered` | `.ct-card-flat`, `.ct-animations-on` | `.ct-animations-off`

Los layouts **side / top / bottom** conservan sus clases existentes; se añadieron marcas **`.ct-sidebar`**, **`.ct-topbar`**, **`.ct-bottombar`** en los partials correspondientes para documentación y futuros hooks CSS.

---

## 5. Configuración (`cfg_configuraciones`)

`LebytekUiConfig::resolve()` interpreta (entre otras) estas claves, con prioridad de la primera existente en el arreglo cargado:

| Concepto (doc ui_ux) | Claves admitidas en BD |
|---------------------|-------------------------|
| Color primario | `theme.primary_color`, `theme_primary_color`, `primary_color` |
| Fondo barra lateral / nav | `theme.sidebar_bg`, `theme_sidebar_bg`, `theme.topbar_bg`, `theme_topbar_bg`, `navbar_color` |
| Texto sidebar (opcional) | `theme.sidebar_text`, `theme_sidebar_text` |
| Radio y sombra | `theme.border_radius`, `border_radius`, `theme.shadow_level`, `shadow_level` |
| Nombre / logo | `theme.app_name`, `empresa_nombre`; `theme.logo_path`, `empresa_logo` |
| Menú | `ui.menu_position`, `menu_layout` |
| Ancho layout | `ui.layout_width`, `ui_layout_width`, `layout_width` (`fluid` \| `boxed`) |
| Densidad | `ui.content_density`, `ui_content_density`, `content_density` |
| Estilo card | `ui.card_style`, `ui_card_style`, `card_style` (`soft` \| `bordered` \| `flat`) |
| Tabla global | `ui.table_density`, `ui_table_density`, `table_density` (`compact` fuerza `table-sm` en CRUD index si el JSON no fuerza compact explícito) |
| Animaciones | `ui.enable_animations`, `ui_enable_animations`, `enable_animations` (`0` / `false` / `off` desactiva transiciones en body) |

Variables CSS generadas (además de `--app-*` y `--bs-*` existentes): `--ct-primary`, `--ct-sidebar-bg`, `--ct-topbar-bg`, `--ct-sidebar-text`, `--ct-radius`, `--ct-shadow-card`, `--ct-transition`, etc.

---

## 6. CRUD Engine

- Vistas **index / form / show** envueltas en **`.ct-page`** y cards con **`.ct-table-card`**, **`.ct-form-card`**, **`.ct-detail-card`** según corresponda.
- **Badges:** para columnas `status` o `estado`, se fusiona el mapa del JSON con el mapa estándar (activo→success, inactivo→secondary, pendiente→warning, …) definido en `LebytekUiConfig::defaultStatusBadges()`.
- **Tabla compacta global:** si `ui.table_density = compact` y el recurso no define `list.table_compact` / `table_sm`, el controlador activa compactación en el listado.
- **crud-engine.css:** anillo de foco usa `rgba(var(--bs-primary-rgb, …), 0.25)` para coherencia con el tema.

---

## 7. Decisiones

- **Nombre:** el sistema visual se documenta como **LEBYTEK UI**; **Contraste** es el shell/proyecto actual en este repositorio.
- **Sin nuevas dependencias** npm/composer; solo Bootstrap 5.3 CDN existente + CSS propio.
- **Sin cambio de esquema SQL:** las nuevas claves son opcionales; el seed `035_cfg_configuraciones.sql` sigue siendo válido.
- **Partial de tema:** evita duplicar el bloque largo de overrides entre admin y login; el login usa `includeNavChrome => false`.

---

## 8. Pendientes recomendados

- Exponer en **Ajustes** (formulario) los nuevos parámetros opcionales (`ui.layout_width`, `ui.content_density`, `ui.card_style`, `theme.border_radius`, …) cuando el producto lo requiera.
- Unificar **empty states** de usuarios con `empty_state.php` (actualmente solo roles/permisos y CRUD list).
- Revisión **WCAG** más estricta (contraste en `theme.sidebar_text` personalizado).
- Opcional: reducir estilos **inline** restantes en `admin/ajustes` (preview del navbar).

---

## 9. Pruebas manuales sugeridas

Las listadas en la especificación del proyecto (login y admin en desktop/móvil, tres layouts, CRUD completo, ajustes, RBAC, modal de borrado, vacíos, validación, paginación).
