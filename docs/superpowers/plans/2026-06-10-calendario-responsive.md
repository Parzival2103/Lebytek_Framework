# Calendario Responsive (vista grande) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer que el calendario grande (vista "month", y también "week"/"day") quepa y se acomode a cualquier ancho de ventana —incluyendo móviles de ~360px— sin recortar columnas, sin scroll horizontal y conservando estilo y funcionalidad (clicks, popover, crear/editar/eliminar).

**Architecture:** El bug es puramente de CSS de layout. `.lebytek-calendar-grid` y `.lebytek-calendar-week` usan `grid-template-columns: repeat(7, 1fr)`. Un `1fr` desnudo equivale a `minmax(auto, 1fr)`, así que cada columna se niega a encogerse por debajo del ancho intrínseco de su contenido (los pills usan `white-space: nowrap`). Con 7 columnas no-encogibles, la rejilla desborda su contenedor; como la rejilla tiene `overflow: hidden`, las columnas sobrantes se **recortan** y solo se ven los primeros ~3 días. El arreglo es `minmax(0, 1fr)` + `min-width: 0` en los hijos, más afinado tipográfico en móvil (pills como barra de color, estilo Google Calendar móvil). No se toca JS ni PHP de lógica; solo CSS. Una tarea previa restaura el archivo de vista borrado para no romper `main`.

**Tech Stack:** PHP 8.1 (Lebytek MVC+Onion), Bootstrap 5, CSS plano en `public/assets/css/lebytek-ui.css`, JS vanilla en `public/assets/js/calendar.js`. Sin framework de test CSS/JS; verificación visual con redimensionado de viewport (Playwright MCP o navegador manual).

**Decisión de diseño confirmada:** En móvil angosto la vista MES **comprime la rejilla de 7 columnas** para caber en la ventana; los pills de mes se muestran como barra/punto de color (sin título), con el número de día visible. Los clicks siguen abriendo el popover con la info completa (no se pierde funcionalidad).

---

## File Structure

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `app/Presentation/Views/admin/calendario/index.php` | Restaurar (está borrado en working tree) | Shell + toolbar del calendario que el controlador renderiza |
| `public/assets/css/lebytek-ui.css` | Modificar (bloque "Módulo Calendario", líneas ~315–448 y media query ~393–396) | Reglas de rejilla mes/semana, `min-width:0` en hijos, afinado móvil |

No se crean archivos nuevos. No se modifica `calendar.js` ni controladores. El resto del bloque calendario (popover, mini-widget, leyenda) queda intacto.

---

## Pre-flight: confirmación con el usuario

Antes de tocar nada, confirma con el usuario que el borrado de `app/Presentation/Views/admin/calendario/index.php` en el working tree fue **accidental** (no hay reemplazo en ningún lado y el controlador `CalendarioController::index()` aún hace `return $this->view('admin/calendario/index', $data);`). Si fue intencional para reescribirlo, este plan necesita ajustarse. Asumimos accidental.

---

### Task 1: Restaurar la vista del calendario borrada

**Files:**
- Restore: `app/Presentation/Views/admin/calendario/index.php` (existe en `HEAD`, borrado en working tree)

- [ ] **Step 1: Verificar que está borrada y que existe en HEAD**

Run:
```bash
git status --short -- app/Presentation/Views/admin/calendario/index.php
git cat-file -e HEAD:app/Presentation/Views/admin/calendario/index.php && echo "EXISTE EN HEAD"
```
Expected: la primera muestra ` D app/Presentation/Views/admin/calendario/index.php`; la segunda imprime `EXISTE EN HEAD`.

- [ ] **Step 2: Restaurar el archivo desde HEAD**

Run:
```bash
git checkout HEAD -- app/Presentation/Views/admin/calendario/index.php
```
Expected: sin salida (éxito).

- [ ] **Step 3: Confirmar que el archivo volvió y compila**

Run:
```bash
git status --short -- app/Presentation/Views/admin/calendario/index.php
php -l app/Presentation/Views/admin/calendario/index.php
```
Expected: `git status` ya **no** lista el archivo como borrado; `php -l` imprime `No syntax errors detected`.

- [ ] **Step 4: Commit de la restauración (aislado del cambio CSS)**

Run:
```bash
git add app/Presentation/Views/admin/calendario/index.php
git commit -m "fix(calendario): restaura vista index borrada del working tree

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```
Expected: commit creado con 1 archivo.

---

### Task 2: Rejilla del MES que cabe en la ventana (fix de overflow)

**Files:**
- Modify: `public/assets/css/lebytek-ui.css` (regla `.lebytek-calendar-grid`, ~315–323; `.lebytek-calendar-day`, ~336–344; `.lebytek-calendar-events`, ~357–362)

- [ ] **Step 1: Cambiar las columnas del mes a `minmax(0, 1fr)`**

Reemplaza la regla `.lebytek-calendar-grid` (actual):
```css
.lebytek-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background-color: var(--bs-border-color, #dee2e6);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--ct-radius, 0.625rem);
  overflow: hidden;
}
```
por:
```css
.lebytek-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 1px;
  background-color: var(--bs-border-color, #dee2e6);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--ct-radius, 0.625rem);
  overflow: hidden;
}
```
(Único cambio: `repeat(7, 1fr)` → `repeat(7, minmax(0, 1fr))`.)

- [ ] **Step 2: Permitir que las celdas y su contenido se encojan (`min-width: 0`)**

Reemplaza la regla `.lebytek-calendar-day` (actual):
```css
.lebytek-calendar-day {
  background-color: var(--bs-body-bg, #fff);
  min-height: 6.5rem;
  padding: 0.35rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  transition: background-color var(--ct-transition, 0.2s ease);
}
```
por (añade `min-width: 0;`):
```css
.lebytek-calendar-day {
  background-color: var(--bs-body-bg, #fff);
  min-height: 6.5rem;
  min-width: 0;
  padding: 0.35rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  transition: background-color var(--ct-transition, 0.2s ease);
}
```

Y reemplaza `.lebytek-calendar-events` (actual):
```css
.lebytek-calendar-events {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  overflow: hidden;
}
```
por (añade `min-width: 0;`):
```css
.lebytek-calendar-events {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
  overflow: hidden;
}
```

- [ ] **Step 3: Verificar visualmente que aparecen las 7 columnas (escritorio y tablet)**

Levanta el server local si no está corriendo:
```bash
php -S localhost:8000 -t public
```
Abre `http://localhost:8000/admin/calendario/demo_citas` (login `admin@sistema.local`). Con el navegador a 1200px y luego a 768px de ancho:
Expected: se ven **las 7 columnas** (Lun…Dom) completas, sin recorte y sin scroll horizontal. (Antes solo se veían Lun/Mar/Mié.)

Si no puedes loguear manualmente, usa Playwright MCP: `browser_navigate` al login, `browser_fill_form` con las credenciales, navega al calendario y `browser_resize` a 1200 y 768; toma `browser_snapshot`/screenshot y cuenta 7 `lebytek-calendar-weekday`.

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/lebytek-ui.css
git commit -m "fix(calendario): rejilla del mes cabe en la ventana (minmax(0,1fr) + min-width)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Misma corrección para vistas Semana y Día

**Files:**
- Modify: `public/assets/css/lebytek-ui.css` (regla `.lebytek-calendar-week`, ~399–407; `.lebytek-calendar-col`, ~411–416; `.lebytek-calendar-col-body`, ~430–435)

- [ ] **Step 1: `minmax(0, 1fr)` en la rejilla de semana**

Reemplaza `.lebytek-calendar-week` (actual):
```css
.lebytek-calendar-week {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background-color: var(--bs-border-color, #dee2e6);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--ct-radius, 0.625rem);
  overflow: hidden;
}
```
por:
```css
.lebytek-calendar-week {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 1px;
  background-color: var(--bs-border-color, #dee2e6);
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--ct-radius, 0.625rem);
  overflow: hidden;
}
```

- [ ] **Step 2: `min-width: 0` en columnas y su cuerpo**

Reemplaza `.lebytek-calendar-col` (actual):
```css
.lebytek-calendar-col {
  background-color: var(--bs-body-bg, #fff);
  min-height: 8rem;
  display: flex;
  flex-direction: column;
}
```
por:
```css
.lebytek-calendar-col {
  background-color: var(--bs-body-bg, #fff);
  min-height: 8rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
```

Y reemplaza `.lebytek-calendar-col-body` (actual):
```css
.lebytek-calendar-col-body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.35rem;
}
```
por (añade `min-width: 0;`):
```css
.lebytek-calendar-col-body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
  padding: 0.35rem;
}
```

- [ ] **Step 3: Verificar Semana y Día en escritorio y móvil**

En `http://localhost:8000/admin/calendario/demo_citas`, pulsa el botón "Semana" y luego "Día".
- A 1200px y 768px: "Semana" muestra 7 columnas completas sin recorte.
- A 575px o menos: "Semana" colapsa a 1 columna apilada (ya existe la media query `@media (max-width: 767.98px) { .lebytek-calendar-week { grid-template-columns: repeat(1, 1fr); } }`), legible y sin scroll horizontal.
Expected: ninguna columna recortada en ningún ancho; los pills con hora+título se leen completos en la vista apilada.

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/lebytek-ui.css
git commit -m "fix(calendario): vistas semana/día caben en la ventana (minmax(0,1fr) + min-width)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Afinado móvil de la vista MES (pills como barra de color, estilo Google Calendar)

**Files:**
- Modify: `public/assets/css/lebytek-ui.css` (media query `@media (max-width: 575.98px)`, ~393–396; y `.lebytek-calendar-weekday`, ~325–334 — solo dentro de la media query)

- [ ] **Step 1: Ampliar la media query móvil del mes**

Reemplaza el bloque actual:
```css
@media (max-width: 575.98px) {
  .lebytek-calendar-day { min-height: 4.5rem; }
  .lebytek-calendar-pill { font-size: 0.65rem; }
}
```
por:
```css
@media (max-width: 575.98px) {
  /* Encabezados de día más compactos para 7 columnas en pantallas angostas */
  .lebytek-calendar-weekday {
    padding: 0.35rem 0.1rem;
    font-size: 0.6rem;
    letter-spacing: 0;
  }

  .lebytek-calendar-day {
    min-height: 4.5rem;
    padding: 0.2rem;
    gap: 0.15rem;
  }

  .lebytek-calendar-daynum { font-size: 0.7rem; }

  /* Vista MES: los pills se vuelven barras de color sin título.
     El click sigue abriendo el popover con la info completa. */
  .lebytek-calendar-events .lebytek-calendar-pill {
    height: 0.4rem;
    padding: 0;
    font-size: 0;
    line-height: 0;
    border-radius: 999px;
    overflow: hidden;
  }

  .lebytek-calendar-more {
    font-size: 0.6rem;
    padding-left: 0.1rem;
    white-space: nowrap;
  }
}
```

Notas:
- `font-size: 0` oculta el título visualmente pero conserva el texto en el DOM (nombre accesible) y el atributo `title`.
- El selector se limita a `.lebytek-calendar-events .lebytek-calendar-pill` (pills de la vista MES). Los pills de Semana/Día viven en `.lebytek-calendar-col-body` y **no** se ven afectados (en móvil Semana ya es 1 columna ancha, donde el título completo sí se lee).
- No se toca `calendar.js`: el `<button>` sigue presente y clickeable como barra.

- [ ] **Step 2: Verificar vista MES en móvil (360px y 414px)**

En `http://localhost:8000/admin/calendario/demo_citas`, vista "Mes", redimensiona a 360px y a 414px.
Expected:
- Se ven las **7 columnas** completas, ninguna recortada, sin scroll horizontal.
- Cada día muestra su número y, si hay eventos, **barras de color** (warning/success/secondary según `estado`), no texto.
- Tocar/clic en una barra abre el popover con título, fechas y acciones (Ver/Editar/Eliminar según permisos) — funcionalidad intacta.
- El indicador "+N más" (cuando hay >3 eventos) sigue visible y no desborda la celda.

- [ ] **Step 3: Verificar modo oscuro y que no rompimos escritorio**

- Activa modo oscuro (toggle de la topbar) y revisa el calendario a 360px y a 1200px: las barras y números usan las variables `--bs-*`/`--ct-*`, deben verse correctos en ambos temas.
- A 1200px la vista MES debe seguir mostrando los **títulos completos** en los pills (la regla barra solo aplica ≤575.98px).
Expected: sin regresiones en escritorio; colores correctos en claro y oscuro.

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/lebytek-ui.css
git commit -m "feat(calendario): vista mes responsive en móvil con pills tipo barra de color

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Verificación final integral

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Matriz de anchos × vistas**

En `http://localhost:8000/admin/calendario/demo_citas`, recorre esta matriz y confirma "OK" en cada celda (sin recorte de columnas, sin scroll horizontal, clicks funcionando):

| Ancho | Mes | Semana | Día | Tabla |
|---|---|---|---|---|
| 360px | barras, 7 cols | 1 col apilada | 1 col | tabla responsive |
| 768px | 7 cols con título | 7 cols | 1 col | tabla |
| 1200px | 7 cols con título | 7 cols | 1 col | tabla |

Expected: todas OK. La vista "Tabla" ya usa `.table-responsive` y no requiere cambios.

- [ ] **Step 2: Sanidad de la suite PHP (no debe verse afectada, pero confirmamos verde)**

Run:
```bash
./vendor/bin/phpunit --testsuite default 2>&1 | tail -20
```
(Si no existe el testsuite `default`, usa `./vendor/bin/phpunit`.)
Expected: misma cantidad de tests pasando que antes del cambio (este plan no toca PHP de lógica; sirve de control de no-regresión).

- [ ] **Step 3: Revisar el diff completo antes de cerrar**

Run:
```bash
git log --oneline -5
git diff HEAD~4 -- public/assets/css/lebytek-ui.css | cat
```
Expected: el diff de CSS contiene solo los cambios descritos (minmax, min-width, media query móvil ampliada). El archivo de vista restaurado aparece en el historial de commits.

- [ ] **Step 4: Confirmar al usuario y decidir push**

Resume al usuario qué se cambió y pregunta si desea `git push` a `main` (recordatorio: la VPS auto-pull despliega `main`). No hacer push sin confirmación explícita.

---

## Self-Review

**Cobertura del problema reportado:**
- "Solo se renderizan 3 columnas (Lun/Mar/Mié)" → Tasks 2 y 3 (minmax(0,1fr) + min-width eliminan el recorte por overflow). ✅
- "Debe acomodarse al tamaño de la ventana en móvil sin perder estilo ni funcionalidad" → Task 4 (rejilla comprimida + pills barra; clicks/popover intactos). ✅
- "Calendario grande de demo_citas" = vista MES por defecto (`config/calendars/demo_citas.json` → `views.default = "month"`). Cubierto por Tasks 2 y 4. ✅

**Riesgos detectados y mitigados:**
- Vista borrada `index.php` rompería `main` al commitear CSS → Task 1 la restaura primero, en commit aislado. ✅
- Afectar pills de Semana/Día con la regla barra → selector acotado a `.lebytek-calendar-events .lebytek-calendar-pill`. ✅

**Escaneo de placeholders:** sin TODO/TBD; todo el CSS aparece completo (antes/después). ✅

**Consistencia de nombres:** clases usadas (`.lebytek-calendar-grid`, `-day`, `-events`, `-pill`, `-week`, `-col`, `-col-body`, `-weekday`, `-more`) coinciden con `lebytek-ui.css` y con las que genera `calendar.js`. ✅

**Nota de testing:** No existe arnés de test CSS/JS en el repo; la verificación es visual por viewport (Playwright MCP o navegador). La suite PHPUnit se corre solo como control de no-regresión, ya que el cambio no toca PHP de lógica.

---

## Execution Handoff

Plan completo y guardado en `docs/superpowers/plans/2026-06-10-calendario-responsive.md`. Dos opciones de ejecución:

**1. Subagent-Driven (recomendado)** — despacho un subagente fresco por tarea, reviso entre tareas, iteración rápida.

**2. Inline Execution** — ejecuto las tareas en esta sesión con executing-plans, por lotes con checkpoints de revisión.

¿Cuál prefieres?
