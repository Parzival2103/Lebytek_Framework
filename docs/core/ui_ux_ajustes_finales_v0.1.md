# Ajustes finales de coherencia — LEBYTEK UI v0.1

**Fecha de referencia:** 2026-04-30.  
**Contexto:** pasada fina posterior a [ui_ux_implementacion_v0.1.md](./ui_ux_implementacion_v0.1.md).

---

## 1. Archivos modificados

| Área | Archivo |
|------|---------|
| Documentación canónica | `docs/core/ui_ux.md` |
| Documentación implementación | `docs/core/ui_ux_implementacion_v0.1.md` |
| Controlador ajustes | `app/Presentation/Controllers/Admin/AjustesController.php` |
| Base admin (caché tema) | `app/Presentation/Controllers/AdminBaseController.php` |
| Vista ajustes | `app/Presentation/Views/admin/ajustes/index.php` |
| Vista usuarios | `app/Presentation/Views/admin/usuarios/index.php` |
| CSS LEBYTEK | `public/assets/css/lebytek-ui.css` |
| Helper tema | `app/Kernel/Helpers/LebytekUiConfig.php` (comentario) |

**Documento nuevo:** `docs/core/ui_ux_ajustes_finales_v0.1.md` (este archivo).

---

## 2. Decisiones tomadas

### 2.1 Marca y documentación

- El **sistema visual** se documenta de forma unificada como **LEBYTEK UI**.
- **Contraste** se define explícitamente como el **shell / proyecto** actual que usa LEBYTEK UI, sin renombrar clases `.ct-*`.
- Se actualizaron referencias cruzadas en `ui_ux_implementacion_v0.1.md` y comentarios en CSS/helper para evitar la ambigüedad «Contraste UI = capa visual».

### 2.2 Panel de ajustes

- Se expusieron controles alineados con `LebytekUiConfig` y con claves **estables** en `cfg_configuraciones` (snake_case sin puntos en nombre de columna, p. ej. `ui_layout_width`), ya contempladas como alias en el helper.
- Los valores se **validan en servidor** (`AjustesController::validatedLebytekUiSettings`) para no persistir cadenas arbitrarias.
- Tras guardar o alternar tema AJAX, se llama a `AdminBaseController::resetSystemConfigCache()` para evitar configuración obsoleta en la caché estática del mismo worker PHP-FPM.

### 2.3 Estilos inline (ajustes)

- La **vista previa del navbar** dejó de usar estilos inline; las reglas viven en `lebytek-ui.css` (prefijo `.ct-navbar-preview-*`).
- Los **swatches de color** (`color-preview-swatch`) siguen usando fondo dinámico vía atributo `style` o sincronización JS existente: no se movieron para no romper la previsualización en tiempo real sin refactor del `app.js`.

### 2.4 Empty states

- **Usuarios:** listado vacío reutiliza `partials/empty_state.php`, alineado con roles/permisos.

---

## 3. Configuraciones expuestas en `/admin/ajustes`

| Clave persistida | Control | Valores |
|------------------|---------|---------|
| `ui_layout_width` | Select | `fluid`, `boxed` |
| `ui_content_density` | Select | `comfortable`, `compact` |
| `ui_card_style` | Select | `soft`, `bordered`, `flat` |
| `ui_table_density` | Select | `normal`, `compact` |
| `ui_enable_animations` | Switch | `1` / ausente → `0` |
| `theme_border_radius` | Select | `sm`, `md`, `lg`, `xl` |
| `theme_shadow_level` | Select | `0` … `3` |

Las claves anteriores no requieren cambio de esquema: son filas nuevas en `cfg_configuraciones` al guardar. Las existentes (`primary_color`, `menu_layout`, etc.) no se renombran.

---

## 4. Vistas alineadas (coherencia visual)

| Vista | Notas |
|-------|--------|
| Login | Sin cambios en esta pasada; sigue tema desde `LebytekUiConfig`. |
| Dashboard | Sin cambios estructurales; ya usa `.ct-page` / KPIs `.ct-card`. |
| CRUD index / form / show | Sin cambios funcionales; ya integran `.ct-page` y cards LEBYTEK. |
| Usuarios | Empty state unificado con `empty_state.php`. |
| Roles / permisos | Ya usaban `empty_state`; sin cambio. |
| Ajustes | Nueva tarjeta «Interfaz (LEBYTEK UI)» + preview navbar sin inline. |

---

## 5. Pendientes restantes

- Opcional: mover la lógica de **swatches de color** a variables CSS + JS mínimo para eliminar los últimos `style=""` en previews de color.
- Opcional: semilla SQL `INSERT IGNORE` para valores por defecto de las nuevas claves en instalaciones limpias (no obligatorio: el formulario muestra defaults y el primer guardado persiste).
- Revisar si otros listados admin (p. ej. futuros módulos) deben adoptar `empty_state.php` de forma sistemática.

---

## 6. Criterios respetados

- Sin cambios a `schema.sql`, rutas, contrato CRUD JSON ni dependencias nuevas.
- PHP 8.1 compatible.
- Layouts side / top / bottom intactos.
