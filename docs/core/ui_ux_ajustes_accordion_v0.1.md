# Ajustes del sistema — acordeón (LEBYTEK UI) v0.1

**Fecha de referencia:** 2026-05-06.

---

## 1. Archivos modificados o creados

| Tipo | Ruta |
|------|------|
| Nueva vista parcial | [`app/Presentation/Views/partials/admin/ajustes_accordion_item.php`](../../app/Presentation/Views/partials/admin/ajustes_accordion_item.php) |
| Vista ajustes | [`app/Presentation/Views/admin/ajustes/index.php`](../../app/Presentation/Views/admin/ajustes/index.php) |
| JS global | [`public/assets/js/app.js`](../../public/assets/js/app.js) (`AjustesAccordion`, inicialización, export en `window.App`) |
| CSS LEBYTEK | [`public/assets/css/lebytek-ui.css`](../../public/assets/css/lebytek-ui.css) (bloque `#ajustesAccordion.ct-ajustes-accordion`) |
| Documentación | `docs/core/ui_ux_ajustes_accordion_v0.1.md` (este archivo) |

**Sin cambios:** controlador [`AjustesController`](../../app/Presentation/Controllers/Admin/AjustesController.php), servicios, repositorios y esquema SQL.

---

## 2. Decisión UX aplicada

- Cada bloque de ajustes es un **ítem de acordeón** Bootstrap 5, con apariencia alineada a tarjetas LEBYTEK (`shadow-sm`, bordes redondeados, cabecera tipo panel).
- **Una sola sección abierta a la vez** mediante `data-bs-parent="#ajustesAccordion"`.
- **Estado inicial:** todas las secciones **cerradas** (ningún `.accordion-collapse` con clase `show` en el HTML).
- **Cabecera:** icono Bootstrap Icons, título en negrita, subtítulo opcional (texto escapado o HTML de confianza vía `subtitleHtml`), chevron estándar del componente como indicador expandido/colapsado.
- **Secciones con formulario activo:** Información de la empresa, Layout y tema, Interfaz (LEBYTEK UI), Colores del sistema. **Dashboard** y **Login** son **placeholders** (solo texto orientativo, sin campos con `name` nuevos).

---

## 3. Comportamiento del acordeón

- Al hacer clic en un encabezado, Bootstrap abre el panel correspondiente y cierra el resto por el `data-bs-parent`.
- Los campos permanecen en el DOM aunque el panel esté colapsado; el envío del formulario `#ajustesForm` **no cambia** respecto al diseño anterior.
- **localStorage:** clave `lebytek_admin_ajustes_accordion`, valor = `id` del elemento `.accordion-collapse` abierto. Tras `shown.bs.collapse` se guarda; si el usuario **cierra** la última sección abierta, se elimina la clave para que la próxima carga vuelva a **todo cerrado**. Si hay valor válido al cargar, se abre solo esa sección con `bootstrap.Collapse.getOrCreateInstance(..., { toggle: false }).show()`.

---

## 4. localStorage

**Sí, implementado** (módulo `AjustesAccordion` en `app.js`), con la clave y el comportamiento descritos arriba.

---

## 5. Cómo agregar una nueva sección

1. En [`admin/ajustes/index.php`](../../app/Presentation/Views/admin/ajustes/index.php), dentro de `<div class="accordion ct-ajustes-accordion" id="ajustesAccordion">`, añadir un bloque:
   - `ob_start();` … markup del cuerpo (campos, textos) … `$bodyNuevo = ob_get_clean();`
   - `ViewHelper::partial('admin/ajustes_accordion_item', [ ... ])`
2. Definir **`collapseId`** y **`headingId`** únicos (convención sugerida: `ajustesCollapse*` / `ajustesHeading*`).
3. Pasar **`title`**, **`iconClass`** (clase `bi-*`), y opcionalmente **`subtitle`**, **`subtitleHtml`** (solo HTML generado en vista y controlado), **`titleExtraHtml`** (p. ej. badges), **`bodyHtml`**.
4. Si la sección incluye inputs nuevos que deban persistir, ampliar **solo** la capa de aplicación/controlador y las claves en configuración; esta refactorización de vista no sustituye ese paso.

Contrato del partial: ver comentarios PHPDoc en [`ajustes_accordion_item.php`](../../app/Presentation/Views/partials/admin/ajustes_accordion_item.php).

---

## 6. Referencias

- Documentación previa de ajustes LEBYTEK: [`ui_ux_ajustes_finales_v0.1.md`](./ui_ux_ajustes_finales_v0.1.md).
