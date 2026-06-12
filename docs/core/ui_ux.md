# UI/UX DEL FRAMEWORK — LEBYTEK UI v0.1

---

## 1. Propósito

Este documento define las reglas visuales y de experiencia de usuario del **sistema visual LEBYTEK UI**, usado por el framework **Lebytek**.

**Contraste** es el shell / proyecto de referencia actual construido sobre **LEBYTEK UI** (misma capa CSS `.ct-*`, Bootstrap 5.3 y configuración en `cfg_configuraciones`). El nombre del producto no sustituye al nombre del sistema visual en documentación técnica.

Su objetivo es establecer un sistema UI consistente para:

- CRUD Engine
- Dashboard
- Administración
- Ajustes
- Menús
- Módulos futuros `dom_*`

La intención no es diseñar pantalla por pantalla, sino definir reglas visuales reutilizables que permitan que todos los módulos se vean como parte del mismo sistema.

---

## 2. Principio general

El framework debe usar un estilo:

**SaaS administrativo limpio, moderno, sobrio y configurable.**

La UI debe transmitir:

- claridad
- confianza
- orden
- velocidad de uso
- consistencia visual
- profesionalismo técnico

---

## 3. Base tecnológica visual

El sistema usa:

- Bootstrap 5.3
- Bootstrap Icons
- CSS propio del framework
- configuración visual desde base de datos

No se deben agregar frameworks UI externos en esta etapa.

No usar:

- Tailwind
- AdminLTE
- Material UI
- React/Vue
- librerías de animación pesadas

---

## 4. Nombre del sistema visual

El sistema visual del framework se denomina:

```text
LEBYTEK UI
```

**LEBYTEK UI** es la capa visual reutilizable (componentes, tokens y convenciones), construida sobre Bootstrap 5.3 y CSS propio (clases `.ct-*`). **Contraste** designa el producto/shell que la emplea en este repositorio.

---

## 5. Configuración desde base de datos

La personalización visual debe continuar usando `cfg_configuraciones`.

El sistema debe permitir modificar aspectos visuales sin tocar código cuando sea razonable.

### Configuraciones recomendadas

```text
ui.menu_position = side | top | bottom
ui.layout_width = fluid | boxed
ui.content_density = comfortable | compact
ui.card_style = soft | bordered | flat
ui.table_density = normal | compact
ui.enable_animations = 1 | 0

theme.mode = light | dark | auto
theme.primary_color
theme.sidebar_bg
theme.sidebar_text
theme.topbar_bg
theme.border_radius
theme.shadow_level
theme.logo_path
theme.app_name
```

---

## 6. Niveles de personalización

### Nivel 1 — Editable por cliente/admin

Estos ajustes pueden exponerse en panel visual:

- nombre del sistema
- logo
- color principal
- modo claro/oscuro/auto
- posición del menú
- densidad de contenido
- estilo de cards

### Nivel 2 — Editable por admin técnico

Estos ajustes pueden ser configurables, pero no necesariamente para cliente final:

- animaciones
- tabla compacta
- sidebar colapsable
- bottombar móvil
- layout boxed/fluid

### Nivel 3 — Interno del framework

No debe editarse desde panel en esta etapa:

- breakpoints
- estructura HTML base
- reglas CSS internas
- componentes base críticos
- convenciones de layout

---

## 7. Layouts oficiales

El framework soporta tres layouts oficiales.

### 7.1 Side layout

Valor:

```text
ui.menu_position = side
```

Uso recomendado:

- ERP
- CRM
- panel administrativo completo
- sistemas con muchos módulos

Comportamiento:

- desktop: sidebar fijo
- tablet: sidebar colapsable
- mobile: sidebar como offcanvas
- navegación jerárquica
- grupos visibles según permisos

### 7.2 Top layout

Valor:

```text
ui.menu_position = top
```

Uso recomendado:

- dashboards simples
- sistemas pequeños
- portales administrativos ligeros

Comportamiento:

- navegación horizontal
- módulos principales visibles
- opciones secundarias en dropdown
- mayor espacio vertical para contenido

### 7.3 Bottom layout

Valor:

```text
ui.menu_position = bottom
```

Uso recomendado:

- PWA
- uso móvil
- captura rápida
- operación en campo

Comportamiento:

- barra inferior fija
- máximo 4 o 5 acciones principales
- icono + texto corto
- acciones secundarias en menú adicional

No debe usarse como layout principal para sistemas administrativos grandes en escritorio.

---

## 8. Responsive oficial

El sistema debe ser adaptable por dispositivo.

| Dispositivo | Comportamiento esperado |
|---|---|
| Desktop `lg+` | Sidebar fijo o topbar completa |
| Tablet `md` | Sidebar colapsable u offcanvas |
| Mobile `sm` | Offcanvas o bottombar |
| CRUD tables | `table-responsive` obligatorio |
| Formularios | Campos apilados o grid reducido |

Reglas:

- no diseñar solo para escritorio
- no esconder acciones críticas en móvil
- no usar tablas sin contenedor responsive
- no depender de hover como única interacción

---

## 9. Espaciado

El sistema debe usar una escala fija de Bootstrap.

### Escala estándar

```text
p-2 = compacto
p-3 = estándar
p-4 = secciones principales

gap-2 = botones pequeños
gap-3 = grupos de acciones/cards

mb-3 = separación normal
mb-4 = separación fuerte
```

### Densidades

#### Compact

Uso:

- tablas grandes
- sistemas de captura rápida
- pantallas con mucha información

Reglas:

- `table-sm`
- cards con `p-3`
- botones `btn-sm`
- menos espacio vertical

#### Comfortable

Uso:

- sistemas generales
- usuarios no técnicos
- pantallas táctiles

Reglas:

- cards con `p-4`
- más separación entre secciones
- inputs cómodos
- botones normales

Configuración:

```text
ui.content_density = compact | comfortable
```

---

## 10. Cards

Todo contenido administrativo debe vivir dentro de una card o sección visual clara.

### Tipos oficiales

```text
crud-card
metric-card
settings-card
form-card
table-card
detail-card
empty-state-card
```

### Reglas

- cada card debe tener propósito claro
- título visible cuando aplique
- subtítulo opcional
- acciones arriba a la derecha en desktop
- acciones apiladas en móvil
- bordes y sombras consistentes
- no dejar contenido flotando sin contenedor

### Configuración

```text
ui.card_style = soft | bordered | flat
theme.border_radius
theme.shadow_level
```

---

## 11. Tablas

Las tablas deben ser limpias, compactas y fáciles de escanear.

### Reglas obligatorias

- usar `table-responsive`
- usar `table-hover`
- columnas de acciones compactas
- badges para estados
- empty state cuando no hay registros
- filtros arriba de la tabla
- paginación clara
- no saturar con demasiadas columnas en móvil

### Configuración

```text
ui.table_density = normal | compact
ui.table_striped = 1 | 0
ui.table_hover = 1 | 0
```

### CRUD Engine

El CRUD Engine puede usar:

```text
list.table_compact
list.table_sm
```

---

## 12. Formularios

Los formularios deben priorizar claridad, prevención de errores y rapidez de captura.

### Reglas

- label siempre visible
- placeholder no reemplaza label
- help text cuando sea útil
- errores debajo del campo
- required visual
- acciones claras al final
- grid Bootstrap responsive
- en móvil, campos apilados
- no saturar una sola sección con demasiados campos

### Tipos visuales base

```text
text
textarea
select
checkbox
file
readonly
hidden
```

### Tipos especiales futuros

```text
money
date
datetime
phone
email
select_relation
upload_image
```

---

## 13. Botones

Los botones deben tener jerarquía clara.

### Jerarquía oficial

| Tipo | Uso |
|---|---|
| Primary | Acción principal |
| Secondary | Cancelar / volver |
| Outline | Acción secundaria |
| Danger | Eliminar |
| Link | Acción discreta |

### Reglas

- una sola acción primaria por pantalla
- eliminar siempre requiere confirmación
- botones de tabla deben ser compactos
- en móvil, botones pueden apilarse
- no usar colores arbitrarios fuera del sistema

### Confirmaciones (modal global)

Todas las confirmaciones del panel admin usan el modal Bootstrap `#confirmModal`, montado una vez en [`layouts/base.php`](../../app/Presentation/Views/layouts/base.php) vía [`partials/confirm_modal.php`](../../app/Presentation/Views/partials/confirm_modal.php). El comportamiento lo orquesta `ConfirmForms` en [`public/assets/js/app.js`](../../public/assets/js/app.js).

#### Opciones configurables

Cualquier módulo puede usar el confirm global por dos vías:

**1. Declarativa (atributos `data-confirm-*`)** — sobre un `<form>` o elemento clickeable. Desde PHP usar `ViewHelper::confirmAttrs()`:

```php
<form method="POST" action="/logout" <?= ViewHelper::confirmAttrs([
    'body'    => '¿Deseas cerrar la sesión actual?',
    'title'   => 'Cerrar sesión',
    'ok'      => 'Cerrar sesión',
    'variant' => 'danger',
    'icon'    => 'warning',
]) ?>>
```

**2. Programática (JS)** — `window.Lebytek.confirm(opts)` devuelve `Promise<boolean>`:

```js
const ok = await window.Lebytek.confirm({
  title: 'Publicar cambios',
  body: 'Los cambios serán visibles de inmediato',
  emphasis: 'de inmediato',   // fragmento del body que se subraya
  icon: 'info',               // warning | danger | success | info | question
  variant: 'success',         // color del botón OK (paleta Bootstrap, whitelist)
  cancelVariant: 'dark',      // opcional: color sólido del botón cancelar
  ok: 'Publicar',
  cancel: 'Todavía no',
});
```

| Opción | Data-attribute | Default | Notas |
|---|---|---|---|
| `body` | `data-confirm` | `¿Confirmar esta acción?` | Activa la intercepción; texto plano |
| `title` | `data-confirm-title` | `Confirmar acción` | |
| `ok` | `data-confirm-ok` | `Confirmar` | Texto del botón confirmar |
| `cancel` | `data-confirm-cancel` | `Cancelar` | Texto del botón cancelar |
| `variant` | `data-confirm-variant` | `primary` | `primary\|secondary\|success\|danger\|warning\|info\|dark` |
| `cancelVariant` | `data-confirm-cancel-variant` | outline-secondary | Misma whitelist |
| `icon` | `data-confirm-icon` | sin icono | `warning\|danger\|success\|info\|question` |
| `emphasis` | `data-confirm-emphasis` | sin énfasis | Primera ocurrencia dentro de `body`; se subraya |

Las variantes e iconos fuera de la whitelist caen al default (previene inyección de clases). El body y el énfasis se renderizan como texto (sin HTML). Los `confirm()` nativos del navegador están prohibidos en vistas (test de contrato `tests/Presentation/ConfirmModalContractTest.php`).

**Reglas:**

- No incrustar modales de confirmación en vistas de módulo.
- No usar `window.confirm` ni `#crudDeleteModal` (legado eliminado).
- Acciones destructivas: `data-confirm-variant="danger"`.
- Defaults de delete CRUD y logout: [`UiConfirmConstants`](../../app/Kernel/Constants/UiConfirmConstants.php).
- JS custom: `window.Lebytek.confirm({ title, body, variant, ok, cancel, icon, emphasis, cancelVariant })`.

### Configuración

```text
theme.primary_color
ui.button_radius
ui.button_size = normal | small
```

---

## 14. Badges y estados

Los estados deben ser reconocibles visualmente.

### Mapa estándar

| Estado | Badge |
|---|---|
| activo | success |
| inactivo | secondary |
| pendiente | warning |
| cancelado | danger |
| borrado | dark |
| bloqueado | danger |
| procesando | info |

### Reglas

- status siempre como badge en tablas
- deleted debe tener indicador claro
- no depender solo del color
- usar texto junto con color

---

## 15. Animaciones

Las animaciones deben ser ligeras y funcionales.

### Permitido

```css
transition: 0.2s ease;
```

Aplicar a:

- hover en cards
- hover en botones
- hover en filas
- colapsado de sidebar
- focus en inputs
- modals/offcanvas Bootstrap

### No permitido

- animaciones largas
- efectos 3D
- animaciones que retrasen operación

## Navegación responsive (breakpoint único: 992px / `lg`)

Las tres navegaciones cambian de modo móvil↔escritorio en **992px** (`lg` de Bootstrap). "Responsive" significa lo mismo en todo el panel.

| Layout | < 992px (móvil) | ≥ 992px (escritorio) |
|---|---|---|
| `side` | Sidebar como drawer (botón en `topbar`), overlay + Escape | Sidebar fijo, colapsable a iconos |
| `top` | `nav_top` se vuelve drawer lateral (hamburguesa `#topNavToggle`); las acciones (tema/estilos/usuario) quedan siempre en la barra | Barra horizontal con dropdowns (sin cambios) |
| `bottom` | Barra inferior fija (`nav_bottom`) con panel "Más" y submenús | Fallback a barra superior `nav_top`; la bottombar se oculta |

Detalles de implementación:
- El drawer de `nav_top` reutiliza el overlay `.sidebar-overlay` y el módulo `NavDrawer` (`public/assets/js/app.js`).
- `#topNavMenu` contiene **solo** los links de menú (sin la clase `collapse`, para animar el drawer con `translateX`); las acciones viven en `.topnav-actions`, fuera del drawer.
- En el layout `bottom`, `base.php` renderiza `nav_top` envuelto en `d-none d-lg-block` y `nav_bottom` con `d-lg-none`.
- El `padding-bottom` del contenido en layout `bottom` se retira en `@media (min-width: 992px)`.
- loaders exagerados
- librerías externas

### Configuración

```text
ui.enable_animations = 1 | 0
```

---

## 16. Color y tema

El sistema debe preparar tres modos:

```text
light
dark
auto
```

### Configuraciones recomendadas

```text
theme.mode = light | dark | auto
theme.primary_color
theme.success_color
theme.warning_color
theme.danger_color
theme.info_color
theme.body_bg
theme.surface_bg
theme.text_color
theme.muted_color
```

### v0.1 recomendada

Para esta etapa basta con implementar correctamente:

```text
theme.mode
theme.primary_color
theme.sidebar_bg
theme.sidebar_text
theme.border_radius
theme.shadow_level
```

---

## 17. Tipografía

Usar fuente de sistema.

```css
font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
```

### Jerarquía

| Elemento | Uso |
|---|---|
| h4 | título de página |
| h5 | título de card/sección |
| small | ayuda o metadatos |
| text-muted | información secundaria |

Reglas:

- evitar `h1` en pantallas internas
- no usar demasiados tamaños
- mantener jerarquía consistente
- títulos deben describir la acción o pantalla

---

## 18. Iconografía

Usar una sola librería principal de íconos.

Recomendado:

```text
Bootstrap Icons
```

### Reglas

- icono + texto en navegación
- icono solo permitido en acciones obvias
- acciones destructivas deben tener texto, tooltip o confirmación
- no mezclar varias librerías de iconos sin razón

---

## 19. Accesibilidad mínima

El sistema debe cumplir una base mínima de accesibilidad.

### Reglas

- labels conectados a inputs
- contraste suficiente
- focus visible
- botones con texto o `aria-label`
- modals/offcanvas bien estructurados
- no depender solo del color
- mensajes de error claros
- navegación usable con teclado en formularios principales

---

## 20. Aplicación en CRUD Engine

El CRUD Engine debe respetar LEBYTEK UI.

### Index

- header claro
- acción principal arriba
- filtros en card
- tabla responsive
- badges para estados
- empty state
- paginación visible

### Form

- campos en grid responsive
- errores por campo
- help text
- required visual
- acciones claras
- cards por sección si aplica

### Show

- datos en card o definition list
- labels claros
- acciones consistentes
- formatos fecha/moneda aplicados

---

## 21. Aplicación en futuros módulos

Todo módulo nuevo debe usar:

- layout oficial
- cards oficiales
- tablas responsive
- formularios con labels visibles
- botones con jerarquía
- badges de estado
- spacing estándar

No debe crear estilo propio aislado salvo que sea un componente especializado.

---

## 22. Qué NO hacer

No hacer:

- estilos inline innecesarios
- colores hardcodeados si existen en configuración
- HTML duplicado por cada módulo
- tablas sin responsive
- formularios sin labels
- botones sin jerarquía
- animaciones pesadas
- dependencias UI externas innecesarias
- rediseñar cada módulo con estética distinta

---

## 23. Regla de evolución

Todo cambio visual importante debe:

1. agregarse a este documento
2. implementarse en CSS o componente reutilizable
3. respetar configuración existente
4. no romper layout side/top/bottom
5. probarse en desktop y móvil

---

## 24. Decisiones oficiales v0.1

- El sistema visual se llama **LEBYTEK UI**; **Contraste** es el shell/proyecto actual sobre esa capa
- Bootstrap 5.3 es la base
- La personalización vive en `cfg_configuraciones`
- Layouts oficiales: `side`, `top`, `bottom`
- En móvil, `side` debe convertirse en offcanvas
- CRUD Engine y módulos futuros comparten las mismas reglas visuales
- No se agregan librerías UI externas por ahora
- El documento canónico de UI/UX es este archivo

---

## 25. Estado actual

Este documento define el contrato visual inicial del framework.

Las pantallas existentes deben ir alineándose gradualmente a estas reglas:

- CRUD Engine
- Dashboard
- Administración
- Ajustes
- Menús
- Módulos futuros
