# Spec — Vistas responsive: navegación (`nav_*`) y tablas del CRUD Engine

**Fecha:** 2026-06-10
**Estado:** Aprobado (diseño) — pendiente de plan de implementación
**Autor:** Brainstorming asistido (Claude Code)

---

## 1. Problema

El panel funciona bien en escritorio, pero en teléfono la navegación y las tablas se rompen:

1. **`nav_top` no se despliega en móvil.** Con el layout superior activo y pantalla angosta, el menú colapsado de Bootstrap no se ve (queda recortado/sin fondo). No hay forma de navegar.
2. **`nav_bottom` deja sin menú al escritorio.** La barra inferior es exclusiva de móvil (`d-md-none`) y el layout "bottom" no renderiza ninguna navegación alternativa para pantallas anchas → sin menú en escritorio.
3. **Tablas del CRUD Engine incómodas en móvil.** Usan scroll horizontal (`.table-responsive`); en teléfono obliga a desplazar lateralmente para ver columnas y acciones.

## 2. Objetivo

- `nav_top` debe comportarse como `nav_side` en móvil (drawer con botón hamburguesa) y conservar su estilo horizontal en escritorio.
- `nav_bottom` debe seguir siendo la barra inferior en móvil **y** ofrecer navegación válida en escritorio (fallback a `nav_top`).
- Las tablas del CRUD Engine deben poder colapsar columnas en móvil: click en la fila despliega las columnas ocultas como detalle hacia abajo, con columnas prioritarias siempre visibles, usando la extensión **Responsive de DataTables**.

## 3. Alcance

**Incluye:**
- `app/Presentation/Views/partials/nav_top.php`, `nav_bottom.php` (y ajustes menores en `topbar.php` si aplica).
- `app/Presentation/Views/layouts/base.php` (ramas de layout `top` y `bottom`).
- `public/assets/js/app.js` (módulos de drawer/navegación).
- `public/assets/css/app.css` (reglas responsive de las navegaciones).
- `app/Presentation/Views/admin/crud/index.php` (tabla del CRUD Engine).
- `app/Application/Services/CrudTableBuilder.php` (propagar `priority`).
- `config/cruds/*.json` (nuevo campo opcional `list.columns[].priority`; documentación + ejemplo).
- `public/assets/css/crud-engine.css` (estilos DataTables Responsive + dark mode).
- Documentación: `docs/core/ui_ux.md` y doc del CRUD Engine.

**No incluye (fuera de alcance):**
- Migrar las tablas hechas a mano (usuarios, roles, permisos) a DataTables. Se dejan con su comportamiento actual (`TableSearch`/`TableSort`). Se podrá migrar en un trabajo posterior.
- Rediseño visual de la navegación en escritorio (se conserva idéntico).
- Cambiar el flujo server-side del CRUD Engine (búsqueda, orden, filtros, paginación, totales) — se mantiene intacto.

## 4. Decisiones de diseño

### 4.1 Breakpoint único de cambio: `lg` (992px)
Hoy conviven dos breakpoints: `lg`/992px para el sidebar móvil y `md`/768px para la bottombar. Eso genera estados intermedios inconsistentes. **Se estandariza el cambio móvil↔escritorio en `lg` (992px)** para las tres navegaciones. "Responsive" significará lo mismo en todo el panel.

### 4.2 Carga de DataTables: scopeada a vistas CRUD
jQuery + DataTables (core + tema Bootstrap 5 + extensión Responsive) se cargan **solo en `crud/index.php`** (donde ya se inyectan `crud-engine.css/js`), no en `base.php`. El resto del panel permanece sin jQuery. Coste: jQuery se carga en las vistas de listado CRUD; beneficio: el resto de la app mantiene su filosofía sin dependencias.

### 4.3 DataTables en modo "solo-responsive"
El motor CRUD ya resuelve búsqueda, orden, filtros, paginación y la fila de totales (`tfoot`) en el servidor. DataTables se inicializa con `paging:false`, `searching:false`, `info:false`, `ordering:false`, `lengthChange:false`. **Solo** aporta el colapso responsive (child-row) y la priorización de columnas. Así no duplica ni rompe el flujo existente.

### 4.4 Columnas prioritarias dirigidas por config
Nuevo campo opcional `list.columns[].priority` (entero; **menor = más prioritaria**, convención DataTables) en `config/cruds/{recurso}.json`. Si no se declara, se aplica un default razonable (ver 5.3.3).

## 5. Diseño detallado

### 5.1 Área A — `nav_top`: drawer en móvil, barra horizontal en escritorio

**Causa raíz:** `.topnav` define `height` fijo (`--topbar-height`) y el `#topNavMenu` colapsado de Bootstrap se despliega *dentro* de ese alto → recortado/sin fondo.

**Cambios:**

1. **`nav_top.php` — reestructura:**
   - Separar los *links de menú* (que irán al drawer en móvil) de las *acciones de la derecha* (tema, panel de estilos, menú de usuario). Hoy ambas viven dentro de `#topNavMenu`; por eso desaparecen juntas en móvil.
   - Las acciones de la derecha quedan **siempre** en la barra superior (fuera del colapso/drawer).
   - El botón hamburguesa (`navbar-toggler`) controla el drawer en móvil.

2. **CSS (`app.css`):**
   - **≥992px:** sin cambios — navbar horizontal con dropdowns idéntica a hoy.
   - **<992px:** `#topNavMenu` (o un contenedor `topnav-drawer`) pasa a `position:fixed; top:0; left:0; bottom:0; width:var(--sidebar-width); transform:translateX(-100%)`, con transición; clase `.open` lo desliza a `translateX(0)`. Reutiliza el overlay oscuro existente.

3. **JS (`app.js`):**
   - Generalizar el patrón de drawer que ya usa el módulo `Sidebar` (overlay + open/close + Escape + click-fuera) para que `nav_top` lo reutilice sin duplicar lógica. Opción: un pequeño módulo `NavDrawer` parametrizado por selectores, o extender `Sidebar` para enlazar también el toggler del topnav.

**Criterio de aceptación A:**
- En <992px con layout `top`, el hamburguesa abre un drawer lateral con los links del menú; overlay oscuro; cierra con Escape/click-fuera.
- En ≥992px, la barra superior se ve y comporta exactamente como hoy.
- Las acciones (tema/estilos/usuario) son accesibles en ambos tamaños.

### 5.2 Área B — `nav_bottom`: barra inferior en móvil + fallback a `nav_top` en escritorio

**Causa raíz:** la bottombar es `d-md-none` y el `layout-bottom-wrapper` no renderiza navegación para escritorio.

**Cambios:**

1. **`base.php` — rama `MENU_LAYOUT_BOTTOM`:** renderizar **ambas** navegaciones:
   - `nav_top` visible solo en escritorio (`d-none d-lg-flex` o equivalente).
   - `nav_bottom` visible solo en móvil (<992px).
   - Estructura: `nav_top` (desktop) → `main` + contenido → `footer` → `nav_bottom` (móvil).

2. **`nav_bottom.php` + CSS:** cambiar `d-md-none` → `d-lg-none` en la barra y sus paneles (`bottomnav-more-panel`, `bottomnav-sub-panel`, overlay) para alinear con el breakpoint 992px (consistencia con Área A).

3. **CSS:** añadir `padding-bottom` al contenido del layout bottom en móvil = alto de la bottombar (`60px`) para que la barra fija no tape el último contenido.

**Criterio de aceptación B:**
- Con layout `bottom` en <992px: se ve la barra inferior y navega correctamente (incl. panel "Más" y submenús).
- Con layout `bottom` en ≥992px: se ve la barra superior (`nav_top`) y navega correctamente; la bottombar está oculta.
- El contenido nunca queda tapado por la barra inferior fija.

### 5.3 Área C — Tablas CRUD: DataTables Responsive (solo CRUD Engine)

**Causa raíz:** `.table-responsive` (scroll horizontal) es incómodo en móvil.

#### 5.3.1 Carga de assets (scopeada)
En `crud/index.php`, junto a los assets actuales, cargar vía CDN:
- jQuery 3.x
- DataTables core + integración Bootstrap 5 (CSS + JS)
- Extensión **Responsive** de DataTables (CSS + JS)

#### 5.3.2 Inicialización (modo solo-responsive)
Inicializar DataTables sobre la tabla del CRUD con:
- `responsive: { details: { type: 'inline' } }` → click en la fila despliega columnas ocultas como detalle hacia abajo.
- `paging: false`, `searching: false`, `info: false`, `ordering: false`, `lengthChange: false`.
- Compatibilidad con `<tfoot>` de totales: asegurar que la fila de totales **no** se incluya en el cálculo/colapso responsive (ver riesgos). Si es necesario, excluirla explícitamente o renderizarla fuera del control de DataTables.
- Columnas de checkbox (`data-crud-select-all`/`data-crud-row-check`) y de Acciones: `orderable:false` y prioridad alta (siempre visibles).

#### 5.3.3 Columnas prioritarias (config-driven)
- **Config:** nuevo campo opcional `list.columns[].priority` (entero) en `config/cruds/{recurso}.json`.
- **Propagación:** `CrudTableBuilder::build()` añade la clave `priority` al arreglo `$columns[]` (leyéndola de la definición de columna; ausente → no se setea).
- **Render:** `index.php` emite `data-priority="N"` en cada `<th>` que tenga `priority`.
- **Default cuando no se declara `priority` en ninguna columna:**
  - 1ª columna de datos → siempre visible (prioridad alta).
  - Columna de **Acciones** → siempre visible (`responsivePriority` alta).
  - Columna de checkbox de selección → siempre visible.
  - El resto colapsa por ancho disponible, en orden de aparición.

#### 5.3.4 Acciones dentro del detalle expandido
Las acciones por fila (editar/eliminar, formularios con CSRF) deben seguir funcionando al moverse al child-row. Los módulos `ConfirmForms` y `ConfirmModal` usan *event delegation* a nivel `document`, por lo que los handlers siguen disparándose. **Punto de verificación manual** (ver §6).

#### 5.3.5 Dark mode y tema
Overrides en `crud-engine.css` para que el child-row de detalle y los controles de DataTables respeten las variables de tema y `data-bs-theme` (claro/oscuro).

**Criterio de aceptación C:**
- En móvil, la tabla CRUD no genera scroll horizontal por defecto: las columnas no prioritarias se ocultan y se despliegan al hacer click en la fila.
- Las columnas marcadas con `priority` (o las del default) permanecen visibles.
- Búsqueda, orden, filtros, paginación y fila de totales siguen funcionando vía servidor, sin regresión.
- Las acciones por fila funcionan también desde el detalle expandido.
- El colapso respeta el tema claro/oscuro.

### 5.4 Área D — Documentación y verificación
- Actualizar `docs/core/ui_ux.md`: comportamiento responsive de las tres navegaciones y el breakpoint único 992px.
- Documentar el campo `list.columns[].priority` en la doc del CRUD Engine, con ejemplo.

## 6. Pruebas y verificación

**Automatizadas (PHPUnit):**
- `CrudTableBuilder` propaga `priority` al arreglo de columnas cuando está declarado; lo omite cuando no.
- (Si existe cobertura de render) la vista emite `data-priority` correctamente.

**Verificación manual / E2E (checklist):**
- Layout `side`: móvil (drawer) + escritorio (sidebar) — sin regresión.
- Layout `top`: móvil (drawer hamburguesa) + escritorio (barra horizontal).
- Layout `bottom`: móvil (barra inferior + "Más" + submenús) + escritorio (barra superior).
- Tabla CRUD en móvil: colapso por click, columnas prioritarias visibles, acciones operativas desde el detalle, totales correctos.
- Tema claro/oscuro en todos los anteriores.

## 7. Riesgos y mitigaciones

1. **DataTables Responsive + `tfoot` de totales:** el cálculo responsive podría incluir la fila de totales. *Mitigación:* excluir explícitamente la fila de totales del control responsive o renderizarla fuera de DataTables.
2. **jQuery global (aunque scopeado a vistas CRUD):** añade peso en las vistas de listado. *Mitigación:* carga solo en `crud/index.php`; el resto del panel no lo carga.
3. **Child-row con formularios CSRF:** el clonado/movimiento de nodos por DataTables podría afectar a los formularios de acción. *Mitigación:* `ConfirmForms`/`ConfirmModal` usan delegación a nivel `document`; verificación manual obligatoria.
4. **Reestructura de `nav_top.php`:** mover las acciones fuera del colapso podría afectar estilos existentes en escritorio. *Mitigación:* conservar el marcado de la barra ≥992px idéntico; los cambios son aditivos para <992px.

## 8. Criterios de finalización (Definition of Done)
- Las tres navegaciones funcionan en móvil y escritorio según §5.1–5.2.
- La tabla CRUD colapsa en móvil con columnas prioritarias y acciones operativas según §5.3.
- Sin regresión en el flujo server-side del CRUD Engine ni en los totales.
- Tests PHPUnit verdes; checklist de verificación manual completado.
- Documentación actualizada (`ui_ux.md` + doc CRUD Engine).
