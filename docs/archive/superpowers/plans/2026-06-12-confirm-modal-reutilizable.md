# Confirm Modal Reutilizable y Configurable — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extender el modal de confirmación global (`#confirmModal`) que hoy usa el CRUD Engine para que sea un componente reutilizable por cualquier módulo, configurable por uso (texto de botones, color de botones, icono semántico warning/danger/success/info/question, y subrayado/énfasis de un fragmento del texto), y reemplazar los 4 `confirm()` nativos del logout por este componente.

**Architecture:** El componente ya existe en 3 piezas: partial PHP (`app/Presentation/Views/partials/confirm_modal.php`, montado una vez en `layouts/base.php`), módulo JS `ConfirmModal` + `ConfirmForms` (`public/assets/js/app.js:452-589`, API declarativa vía atributos `data-confirm-*` y promesas), y estilos en `public/assets/css/lebytek-ui.css`. Se extiende sin romper la API actual: nuevas opciones (`icon`, `emphasis`, `cancelVariant`, whitelist de `variant`), un helper PHP `ViewHelper::confirmAttrs()` para generar los atributos desde vistas, exposición programática `window.Lebytek.confirm()` para JS de otros módulos, y constantes de logout en `UiConfirmConstants`.

**Tech Stack:** PHP 8.1 (sin framework, MVC+Onion propio), Bootstrap 5 + Bootstrap Icons, JS vanilla en módulos IIFE, test runner propio (`php tests/run.php [filtro]`, microtest con `test()`, `assert_true()`, `assert_same()`).

---

## Contexto para el implementador (leer primero)

**Cómo funciona hoy el confirm:**

1. `layouts/base.php:105` monta el partial una vez: `<?= ViewHelper::partial('confirm_modal') ?>`.
2. Cualquier `<form>` o elemento clickeable con `data-confirm="texto"` (y opcionales `data-confirm-title`, `data-confirm-variant`, `data-confirm-ok`, `data-confirm-cancel`) es interceptado por el módulo `ConfirmForms` en `app.js`, que llama `ConfirmModal.show(opts)` → Promise<boolean>. Si se confirma, el form se reenvía con `data-confirm-bypass`.
3. `ConfirmModal.applyOptions()` hoy solo soporta `variant: 'danger' | 'primary'` para el botón OK, y el body es solo `textContent` plano. No hay icono ni énfasis.
4. Los 4 logout (`partials/topbar.php:64`, `partials/nav_top.php:89`, `partials/nav_side.php:97`, `partials/nav_bottom.php:86`) usan `onsubmit="return confirm('¿Cerrar sesión?');"` — el confirm nativo que hay que eliminar.

**Convenciones del repo:**

- Escapado de salida SIEMPRE con `ViewHelper::e()` (`app/Kernel/Helpers/ViewHelper.php`).
- Tests: archivos `*Test.php` bajo `tests/`, funciones globales `test(string $name, Closure $fn)`, `assert_true($cond, $msg = '')`, `assert_same($expected, $actual)`. Se ejecutan con `php tests/run.php` (acepta un filtro substring de ruta como primer argumento, p. ej. `php tests/run.php ConfirmModal`).
- Textos de UI en español. Commits estilo conventional commits en español (ver `git log`).
- NO usar `innerHTML` con datos de usuario en JS (XSS). El énfasis del body se construye con nodos DOM.

---

### Task 1: Constantes de logout en `UiConfirmConstants`

**Files:**
- Modify: `app/Kernel/Constants/UiConfirmConstants.php`
- Test (create): `tests/Kernel/UiConfirmConstantsTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Kernel/UiConfirmConstantsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel\Constants\UiConfirmConstants;

test('UiConfirmConstants define textos de logout', function (): void {
    assert_same('Cerrar sesión', UiConfirmConstants::LOGOUT_TITLE);
    assert_same('¿Deseas cerrar la sesión actual?', UiConfirmConstants::LOGOUT_BODY);
    assert_same('Cerrar sesión', UiConfirmConstants::LOGOUT_OK);
    assert_same('warning', UiConfirmConstants::LOGOUT_ICON);
});

test('UiConfirmConstants mantiene defaults existentes', function (): void {
    assert_same('Confirmar acción', UiConfirmConstants::DEFAULT_TITLE);
    assert_same('Confirmar', UiConfirmConstants::DEFAULT_OK);
    assert_same('Cancelar', UiConfirmConstants::DEFAULT_CANCEL);
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php UiConfirmConstantsTest`
Expected: FAIL (Error por constante `LOGOUT_TITLE` no definida).

- [ ] **Step 3: Implementar las constantes**

En `app/Kernel/Constants/UiConfirmConstants.php`, agregar después de `DELETE_OK`:

```php
    public const LOGOUT_TITLE = 'Cerrar sesión';
    public const LOGOUT_BODY = '¿Deseas cerrar la sesión actual?';
    public const LOGOUT_OK = 'Cerrar sesión';
    public const LOGOUT_ICON = 'warning';
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php tests/run.php UiConfirmConstantsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Kernel/Constants/UiConfirmConstants.php tests/Kernel/UiConfirmConstantsTest.php
git commit -m "feat(ui): constantes de confirmacion de logout en UiConfirmConstants"
```

---

### Task 2: Helper `ViewHelper::confirmAttrs()`

Genera la cadena de atributos `data-confirm-*` escapada para usar en cualquier vista. Es la API PHP del componente para "demás módulos".

**Files:**
- Modify: `app/Kernel/Helpers/ViewHelper.php` (agregar un método, junto a `csrfField()` ~línea 85)
- Test (create): `tests/Kernel/ViewHelperConfirmAttrsTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Kernel/ViewHelperConfirmAttrsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

test('confirmAttrs genera todos los data-attributes soportados', function (): void {
    $attrs = ViewHelper::confirmAttrs([
        'body'          => '¿Eliminar registro?',
        'title'         => 'Confirmar eliminación',
        'ok'            => 'Eliminar',
        'cancel'        => 'Volver',
        'variant'       => 'danger',
        'cancelVariant' => 'secondary',
        'icon'          => 'danger',
        'emphasis'      => 'Eliminar registro',
    ]);

    assert_true(str_contains($attrs, 'data-confirm="¿Eliminar registro?"'));
    assert_true(str_contains($attrs, 'data-confirm-title="Confirmar eliminación"'));
    assert_true(str_contains($attrs, 'data-confirm-ok="Eliminar"'));
    assert_true(str_contains($attrs, 'data-confirm-cancel="Volver"'));
    assert_true(str_contains($attrs, 'data-confirm-variant="danger"'));
    assert_true(str_contains($attrs, 'data-confirm-cancel-variant="secondary"'));
    assert_true(str_contains($attrs, 'data-confirm-icon="danger"'));
    assert_true(str_contains($attrs, 'data-confirm-emphasis="Eliminar registro"'));
});

test('confirmAttrs omite claves vacías y escapa HTML', function (): void {
    $attrs = ViewHelper::confirmAttrs([
        'body'  => '¿Borrar "<b>x</b>"?',
        'title' => '',
    ]);

    assert_true(!str_contains($attrs, 'data-confirm-title'));
    assert_true(!str_contains($attrs, '<b>'));
    assert_true(str_contains($attrs, '&lt;b&gt;'));
});

test('confirmAttrs con body vacío devuelve cadena vacía', function (): void {
    assert_same('', ViewHelper::confirmAttrs([]));
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php ViewHelperConfirmAttrsTest`
Expected: FAIL (Error: método `confirmAttrs` no existe).

- [ ] **Step 3: Implementar el método**

En `app/Kernel/Helpers/ViewHelper.php`, agregar después del método `csrfToken()`:

```php
    /**
     * Genera atributos data-confirm-* para el modal de confirmación global (#confirmModal).
     * Claves: body (requerida), title, ok, cancel, variant, cancelVariant, icon, emphasis.
     * Variantes válidas: primary|secondary|success|danger|warning|info|dark.
     * Iconos válidos: warning|danger|success|info|question.
     */
    public static function confirmAttrs(array $opts): string
    {
        $map = [
            'body'          => 'data-confirm',
            'title'         => 'data-confirm-title',
            'ok'            => 'data-confirm-ok',
            'cancel'        => 'data-confirm-cancel',
            'variant'       => 'data-confirm-variant',
            'cancelVariant' => 'data-confirm-cancel-variant',
            'icon'          => 'data-confirm-icon',
            'emphasis'      => 'data-confirm-emphasis',
        ];

        $attrs = [];
        foreach ($map as $key => $attr) {
            $value = (string) ($opts[$key] ?? '');
            if ($value !== '') {
                $attrs[] = $attr . '="' . self::e($value) . '"';
            }
        }

        return implode(' ', $attrs);
    }
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php tests/run.php ViewHelperConfirmAttrsTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Kernel/Helpers/ViewHelper.php tests/Kernel/ViewHelperConfirmAttrsTest.php
git commit -m "feat(ui): helper ViewHelper::confirmAttrs para el modal de confirmacion"
```

---

### Task 3: Icono en el partial `confirm_modal.php` + estilos

**Files:**
- Modify: `app/Presentation/Views/partials/confirm_modal.php`
- Modify: `public/assets/css/lebytek-ui.css` (sección `.ct-confirm-modal`, ~línea 308)
- Test (modify): `tests/Presentation/ConfirmModalContractTest.php`

- [ ] **Step 1: Escribir el test que falla**

Agregar al final de `tests/Presentation/ConfirmModalContractTest.php`:

```php
test('Partial confirm_modal incluye slot de icono y elementos requeridos', function (): void {
    $path = APP_PATH . '/Presentation/Views/partials/confirm_modal.php';
    $content = (string) file_get_contents($path);

    assert_true(str_contains($content, 'id="confirmModalIcon"'), 'Falta slot de icono');
    assert_true(str_contains($content, 'id="confirmModalTitle"'));
    assert_true(str_contains($content, 'id="confirmModalBody"'));
    assert_true(str_contains($content, 'id="confirmModalOk"'));
    assert_true(str_contains($content, 'id="confirmModalCancel"'));
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php ConfirmModalContractTest`
Expected: FAIL en "Falta slot de icono". Los demás asserts y tests existentes del archivo pasan.

- [ ] **Step 3: Agregar el slot de icono al partial**

En `app/Presentation/Views/partials/confirm_modal.php`, reemplazar el `modal-header`:

```php
            <div class="modal-header">
                <span id="confirmModalIcon" class="ct-confirm-icon d-none" aria-hidden="true"></span>
                <h5 class="modal-title" id="confirmModalTitle"><?= ViewHelper::e(UiConfirmConstants::DEFAULT_TITLE) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= ViewHelper::e(UiConfirmConstants::DEFAULT_CANCEL) ?>"></button>
            </div>
```

(El único cambio es la línea nueva del `<span id="confirmModalIcon">`.)

- [ ] **Step 4: Agregar estilos**

En `public/assets/css/lebytek-ui.css`, después del bloque `.ct-confirm-modal .modal-body p { ... }` (línea ~310), agregar:

```css
.ct-confirm-modal .ct-confirm-icon {
  font-size: 1.4rem;
  line-height: 1;
  margin-right: 0.5rem;
}

.ct-confirm-modal .ct-confirm-emphasis {
  text-decoration: underline;
  text-underline-offset: 0.2em;
  font-weight: 600;
}
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `php tests/run.php ConfirmModalContractTest`
Expected: PASS (todos los tests del archivo).

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/partials/confirm_modal.php public/assets/css/lebytek-ui.css tests/Presentation/ConfirmModalContractTest.php
git commit -m "feat(ui): slot de icono y estilos de enfasis en confirm_modal"
```

---

### Task 4: Extender JS `ConfirmModal` y `ConfirmForms` (icono, énfasis, variantes, API global)

**Files:**
- Modify: `public/assets/js/app.js` (módulos `ConfirmModal` líneas ~452-521 y `ConfirmForms` líneas ~526-589)
- Test (modify): `tests/Presentation/ConfirmModalContractTest.php`

- [ ] **Step 1: Escribir el test de contrato que falla**

Agregar al final de `tests/Presentation/ConfirmModalContractTest.php` (es un test de contrato sobre el fuente JS — el proyecto no tiene runner de JS):

```php
test('app.js soporta las opciones extendidas del confirm', function (): void {
    $path = dirname(APP_PATH) . '/public/assets/js/app.js';
    $content = (string) file_get_contents($path);

    assert_true(str_contains($content, 'confirmIcon'), 'Falta lectura de data-confirm-icon');
    assert_true(str_contains($content, 'confirmEmphasis'), 'Falta lectura de data-confirm-emphasis');
    assert_true(str_contains($content, 'confirmCancelVariant'), 'Falta lectura de data-confirm-cancel-variant');
    assert_true(str_contains($content, 'ct-confirm-emphasis'), 'Falta render de énfasis');
    assert_true(str_contains($content, 'confirmModalIcon'), 'Falta manejo del slot de icono');
    assert_true(str_contains($content, 'window.Lebytek'), 'Falta API global window.Lebytek.confirm');
});
```

Nota: `APP_PATH` apunta a `/app`, por eso `dirname(APP_PATH)` es la raíz del proyecto.

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php ConfirmModalContractTest`
Expected: FAIL en "Falta lectura de data-confirm-icon".

- [ ] **Step 3: Reemplazar el módulo `ConfirmModal` en `app.js`**

Reemplazar el bloque completo `const ConfirmModal = (() => { ... })();` (líneas ~452-521) por:

```js
const ConfirmModal = (() => {
  const DEFAULTS = {
    title: 'Confirmar acción',
    body: '¿Confirmar esta acción?',
    ok: 'Confirmar',
    cancel: 'Cancelar',
    variant: 'primary',
    cancelVariant: '',
    icon: '',
    emphasis: '',
  };

  // Whitelist: evita inyección de clases arbitrarias vía data-attributes.
  const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];

  const ICONS = {
    warning:  'bi-exclamation-triangle-fill text-warning',
    danger:   'bi-exclamation-octagon-fill text-danger',
    success:  'bi-check-circle-fill text-success',
    info:     'bi-info-circle-fill text-info',
    question: 'bi-question-circle-fill text-primary',
  };

  let pending = null;
  let modalInstance = null;

  function getModal() {
    const el = document.getElementById('confirmModal');
    if (!el || typeof bootstrap === 'undefined') return null;
    modalInstance = modalInstance || bootstrap.Modal.getOrCreateInstance(el);
    return { el, instance: modalInstance };
  }

  // Construye el body con nodos DOM (nunca innerHTML) y subraya
  // la primera ocurrencia de `emphasis` dentro de `body`.
  function renderBody(bodyEl, body, emphasis) {
    bodyEl.textContent = '';
    const idx = emphasis ? body.indexOf(emphasis) : -1;
    if (idx === -1) {
      bodyEl.textContent = body;
      return;
    }
    bodyEl.append(document.createTextNode(body.slice(0, idx)));
    const u = document.createElement('u');
    u.className = 'ct-confirm-emphasis';
    u.textContent = emphasis;
    bodyEl.append(u);
    bodyEl.append(document.createTextNode(body.slice(idx + emphasis.length)));
  }

  function applyOptions(opts) {
    const titleEl = document.getElementById('confirmModalTitle');
    const bodyEl = document.getElementById('confirmModalBody');
    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const iconEl = document.getElementById('confirmModalIcon');
    if (!titleEl || !bodyEl || !okBtn || !cancelBtn) return;

    titleEl.textContent = opts.title;
    renderBody(bodyEl, opts.body, opts.emphasis);
    okBtn.textContent = opts.ok;
    cancelBtn.textContent = opts.cancel;

    const variant = VARIANTS.includes(opts.variant) ? opts.variant : 'primary';
    okBtn.className = 'btn btn-' + variant;
    cancelBtn.className = VARIANTS.includes(opts.cancelVariant)
      ? 'btn btn-' + opts.cancelVariant
      : 'btn btn-outline-secondary';

    if (iconEl) {
      const iconClass = ICONS[opts.icon] || '';
      iconEl.className = iconClass
        ? 'ct-confirm-icon bi ' + iconClass
        : 'ct-confirm-icon d-none';
    }
  }

  function show(options = {}) {
    const opts = Object.assign({}, DEFAULTS, options);
    const modal = getModal();
    if (!modal) return Promise.resolve(window.confirm(opts.body));

    if (pending) pending.resolve(false);

    applyOptions(opts);
    return new Promise((resolve) => {
      pending = { resolve };
      modal.instance.show();
    });
  }

  function init() {
    const modal = getModal();
    if (!modal) return;

    const okBtn = document.getElementById('confirmModalOk');
    const el = modal.el;

    okBtn?.addEventListener('click', () => {
      pending?.resolve(true);
      pending = null;
      modal.instance.hide();
    });

    el.addEventListener('hidden.bs.modal', () => {
      if (pending) {
        pending.resolve(false);
        pending = null;
      }
    });
  }

  return { show, init };
})();

// API pública para otros módulos: window.Lebytek.confirm(opts) => Promise<boolean>
window.Lebytek = Object.assign(window.Lebytek || {}, { confirm: ConfirmModal.show });
```

- [ ] **Step 4: Extender `readConfirmOptions` en `ConfirmForms`**

En el mismo `app.js`, dentro del módulo `ConfirmForms`, reemplazar la función `readConfirmOptions`:

```js
  function readConfirmOptions(el) {
    if (!el || !el.dataset.confirm) return null;
    return {
      body: el.dataset.confirm,
      title: el.dataset.confirmTitle || 'Confirmar acción',
      variant: el.dataset.confirmVariant || 'primary',
      ok: el.dataset.confirmOk || 'Confirmar',
      cancel: el.dataset.confirmCancel || 'Cancelar',
      cancelVariant: el.dataset.confirmCancelVariant || '',
      icon: el.dataset.confirmIcon || '',
      emphasis: el.dataset.confirmEmphasis || '',
    };
  }
```

- [ ] **Step 5: Correr los tests y verificar que pasan**

Run: `php tests/run.php ConfirmModalContractTest`
Expected: PASS (todos los tests del archivo).

- [ ] **Step 6: Verificación manual en navegador**

```bash
php -S localhost:8000 -t public
```

Iniciar sesión como `admin@sistema.local`, ir a un CRUD (p. ej. `/admin/administracion/usuarios`), pulsar eliminar: el modal debe verse y comportarse igual que antes (regresión). Luego en la consola del navegador:

```js
window.Lebytek.confirm({
  title: 'Prueba',
  body: 'Esto eliminará el registro de forma permanente',
  emphasis: 'de forma permanente',
  icon: 'danger',
  variant: 'danger',
  ok: 'Sí, eliminar',
  cancel: 'No',
}).then(console.log);
```

Expected: modal con icono rojo octagonal, fragmento subrayado en negrita, botón OK rojo "Sí, eliminar"; la promesa resuelve `true`/`false` según el botón.

- [ ] **Step 7: Commit**

```bash
git add public/assets/js/app.js tests/Presentation/ConfirmModalContractTest.php
git commit -m "feat(ui): ConfirmModal configurable (icono, enfasis, variantes) y API window.Lebytek.confirm"
```

---

### Task 5: Reemplazar los 4 confirm nativos del logout

**Files:**
- Modify: `app/Presentation/Views/partials/topbar.php:64`
- Modify: `app/Presentation/Views/partials/nav_top.php:89`
- Modify: `app/Presentation/Views/partials/nav_side.php:97`
- Modify: `app/Presentation/Views/partials/nav_bottom.php:86`
- Test (modify): `tests/Presentation/ConfirmModalContractTest.php`

- [ ] **Step 1: Endurecer el test de contrato (falla con el código actual)**

En `tests/Presentation/ConfirmModalContractTest.php`, en el primer test (`'Presentation views: no crudDeleteModal ni window.confirm en markup'`), agregar dentro del `foreach`, junto a los asserts existentes:

```php
        assert_true(
            !str_contains($content, 'return confirm('),
            'Native confirm() inline in: ' . $relative
        );
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php tests/run.php ConfirmModalContractTest`
Expected: FAIL con `Native confirm() inline in: partials\topbar.php` (o el primero que encuentre de los 4 partials).

- [ ] **Step 3: Reemplazar el logout en los 4 partials**

En cada uno de los 4 archivos, verificar que el bloque `use` al inicio del archivo incluya (agregarlo si falta):

```php
use App\Kernel\Constants\UiConfirmConstants;
```

Definir los atributos una vez por archivo, justo antes del `<form>` de logout:

```php
<?php $logoutConfirmAttrs = ViewHelper::confirmAttrs([
    'body'    => UiConfirmConstants::LOGOUT_BODY,
    'title'   => UiConfirmConstants::LOGOUT_TITLE,
    'ok'      => UiConfirmConstants::LOGOUT_OK,
    'variant' => 'danger',
    'icon'    => UiConfirmConstants::LOGOUT_ICON,
]); ?>
```

Y en cada form, quitar `onsubmit="return confirm('¿Cerrar sesión?');"` y agregar `<?= $logoutConfirmAttrs ?>`:

`topbar.php:64`:
```php
<form method="POST" action="/logout" class="m-0" <?= $logoutConfirmAttrs ?>>
```

`nav_top.php:89`:
```php
<form method="POST" action="/logout" class="m-0" <?= $logoutConfirmAttrs ?>>
```

`nav_side.php:97`:
```php
<form method="POST" action="/logout" class="ms-auto nav-label m-0" <?= $logoutConfirmAttrs ?>>
```

`nav_bottom.php:86`:
```php
<form method="POST" action="/logout" class="bottomnav-logout-form flex-grow-1 m-0 min-w-0 d-flex" <?= $logoutConfirmAttrs ?>>
```

El resto de cada form (csrfField, botón, icono) queda intacto.

- [ ] **Step 4: Correr la suite completa**

Run: `php tests/run.php`
Expected: PASS total, 0 fallos (la suite completa, no solo el filtro — los 4 partials se validan en el test de contrato).

- [ ] **Step 5: Verificación manual**

Con `php -S localhost:8000 -t public` y sesión iniciada, probar el logout desde: topbar de escritorio, nav superior, sidebar y nav inferior móvil (reducir viewport con devtools). En los 4 casos debe abrirse el modal con título "Cerrar sesión", icono warning amarillo, botón OK rojo "Cerrar sesión"; "Cancelar" no cierra la sesión; "Cerrar sesión" hace POST a `/logout` y redirige al login.

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/partials/topbar.php app/Presentation/Views/partials/nav_top.php app/Presentation/Views/partials/nav_side.php app/Presentation/Views/partials/nav_bottom.php tests/Presentation/ConfirmModalContractTest.php
git commit -m "feat(ui): logout usa el modal de confirmacion global en vez del confirm nativo"
```

---

### Task 6: Documentación del componente

**Files:**
- Modify: `docs/core/ui_ux.md` (sección "confirmaciones", ~línea 428)

- [ ] **Step 1: Actualizar la sección de confirmaciones**

En `docs/core/ui_ux.md`, después del párrafo existente que describe `#confirmModal` (línea ~428), agregar:

````markdown
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
````

- [ ] **Step 2: Correr la suite completa como verificación final**

Run: `php tests/run.php`
Expected: PASS total.

- [ ] **Step 3: Commit**

```bash
git add docs/core/ui_ux.md
git commit -m "docs(ui): documentar opciones configurables del confirm modal global"
```

---

## Notas de riesgo / regresión

- **Backward compat:** los usos existentes de `data-confirm` (CRUD `actions_row.php`, `actions_bulk.php`, usuarios/roles/permisos) no cambian: las nuevas opciones tienen defaults vacíos y `variant` ya aceptaba `danger`/`primary` (ambos en la whitelist). Si no hay icono, el span queda `d-none`, idéntico al estado actual.
- **Fallback sin modal:** `ConfirmModal.show()` conserva el fallback `window.confirm` en JS para páginas sin el partial (p. ej. layouts de auth) — el test de contrato solo prohíbe confirm nativo en **vistas PHP**, no en `app.js`.
- **Dropdowns:** los logout de topbar/nav_top viven en dropdowns Bootstrap; el `preventDefault` del submit ocurre antes de que el dropdown se cierre, y el modal es global, así que no hay conflicto de z-index (el modal Bootstrap monta su backdrop por encima).
- **VPS auto-pull:** los pushes a `main` se despliegan al entorno de testing automáticamente; hacer push solo con la suite completa en verde.
