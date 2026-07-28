# Marketing Front Público — Rediseño Visual + Demo WhatsApp — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rediseñar el front público del módulo Marketing (layout + landing) con un sistema visual moderno orientado a conversión y poblar una demo idempotente con sabor WhatsApp SaaS, manteniendo el desacople total del núcleo.

**Architecture:** El front público pasa a ser data-driven por bloques (`dom_mkt_bloques`) y theme-aware (lee el color primario del tema vía `LebytekUiConfig`). La landing se descompone en partials enfocados (`_hero`, `_trust`, `_pricing`, `_testimonios`, `_lead_form`, `_footer`), cada uno renderizable y testeable de forma aislada. El contenido WhatsApp vive **solo como datos** en un seed demo separado e idempotente; ningún archivo del núcleo referencia clases de Marketing ni código de dominio WhatsApp.

**Tech Stack:** PHP 8.1+, MVC+Onion propio, Bootstrap 5 + CSS propio (tokens), Google Fonts (Plus Jakarta Sans + Inter), Bootstrap Icons, JS vanilla, MySQL (`dom_mkt_*`), harness de tests propio (`php tests/run.php`).

## Global Constraints

- PHP 8.1+ con `declare(strict_types=1)` en todo archivo PHP nuevo.
- Desacople: ningún archivo del núcleo referencia clases del namespace `App\…\Marketing`; nada del dominio WhatsApp entra como código (solo como datos demo).
- Datos demo idempotentes: `WHERE NOT EXISTS` / `UPDATE` guardado; sin `FOREIGN KEY`; prefijo de tablas `dom_mkt_*`.
- Escapar toda salida en vistas con `ViewHelper::e(...)`.
- Bootstrap 5; mobile-first; las clases CSS propias usan prefijo `ct-`.
- Acentos de color SIEMPRE vía `var(--app-primary)` (lo emite el partial `partials/styles/lebytek_theme_vars.php`); nunca hardcodear el verde WhatsApp en el front.
- Tests se ejecutan con `php tests/run.php <filtro>` (el filtro hace `str_contains` contra la ruta del archivo, p. ej. `Marketing/PublicViewTest`).
- Commits frecuentes (uno por tarea). No usar `--no-verify`.

---

### Task 1: Sistema visual + layout público theme-aware + footer partial

**Files:**
- Create: `public/assets/publico/landing.css`
- Create: `public/assets/publico/landing.js`
- Create: `public/assets/publico/hero-mock.svg`
- Create: `app/Presentation/Views/publico/partials/_footer.php`
- Modify: `app/Presentation/Views/publico/layout.php` (reemplazo completo)
- Modify: `app/Presentation/Controllers/Publico/LandingController.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: `ViewHelper::render($view, $data, $layout)`, `ViewHelper::partial($name, $data)`, `ViewHelper::e(...)`; `LebytekUiConfig::resolve(array): array` → claves `primaryColor, primaryHover, primaryActive, primarySubtle, primaryRgb, lebytekCssVariables, bodyBg, darkMode`; `ConfiguracionService::all(): array`, `empresaNombre(): string`, `empresaLogo(): string`.
- Produces: layout público que (1) inyecta variables de tema vía el partial existente `styles/lebytek_theme_vars` con `includeNavChrome=false`, (2) enlaza `/assets/publico/landing.css` y `/assets/publico/landing.js`, (3) renderiza el footer mediante `publico/partials/_footer` leyendo `$bloques['footer']`. El partial `_footer` espera `['footer' => array, 'empresaNombre' => string]`.

- [ ] **Step 1: Write the failing tests**

Añadir al final de `tests/Marketing/PublicViewTest.php`:

```php
test('layout público inyecta las variables de tema (color primario)', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
        'primaryColor' => '#ff2200', 'primaryRgb' => '255, 34, 0',
    ], 'publico/layout');
    assert_true(str_contains($html, '--app-primary'), 'emite variable --app-primary');
    assert_true(str_contains($html, '#ff2200'), 'usa el color primario recibido');
});

test('layout público enlaza el sistema visual y las fuentes', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($html, '/assets/publico/landing.css'), 'enlaza el stylesheet público');
    assert_true(str_contains($html, '/assets/publico/landing.js'), 'enlaza el js público');
    assert_true(str_contains($html, 'fonts.googleapis.com'), 'carga Google Fonts');
});

test('footer usa columnas del bloque footer y cae a fallback sin él', function (): void {
    $conFooter = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => ['footer' => ['columnas' => [
            ['titulo' => 'Producto', 'links' => [['texto' => 'Paquetes', 'url' => '#paquetes']]],
        ], 'legal' => 'Texto legal demo']],
        'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($conFooter, 'Texto legal demo'), 'muestra legal del bloque');
    assert_true(str_contains($conFooter, 'Paquetes'), 'muestra link de columna');

    $sinFooter = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME Fallback', 'empresaLogo' => '',
        'bloques' => [], 'paquetes' => [],
    ], 'publico/layout');
    assert_true(str_contains($sinFooter, 'ACME Fallback'), 'footer fallback con nombre de empresa');
});

test('LandingController resuelve el tema con LebytekUiConfig', function (): void {
    $src = file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true($src !== false, 'archivo existe');
    assert_true(str_contains($src, 'LebytekUiConfig::resolve'), 'el controlador resuelve el tema');
    assert_true(str_contains($src, "'primaryColor'"), 'pasa primaryColor a la vista');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL en los 4 tests nuevos (el layout aún no inyecta tema ni enlaza assets; `LandingController` no usa `LebytekUiConfig`).

- [ ] **Step 3: Create the design-system stylesheet**

Create `public/assets/publico/landing.css`:

```css
/* public/assets/publico/landing.css — sistema visual del front público de Marketing.
   Acentos vía var(--app-primary) (lo emite partials/styles/lebytek_theme_vars). */
:root {
  --ct-pub-ink: #0f172a;
  --ct-pub-text: #1e293b;
  --ct-pub-muted: #64748b;
  --ct-pub-surface: #f8fafc;
  --ct-pub-border: #e2e8f0;
  --ct-pub-hero-bg: #0f172a;
  --ct-pub-radius: 1rem;
  --ct-pub-shadow: 0 4px 24px rgba(15, 23, 42, .08);
  --ct-pub-shadow-hover: 0 10px 34px rgba(15, 23, 42, .14);
  --ct-pub-font-head: 'Plus Jakarta Sans', system-ui, sans-serif;
  --ct-pub-font-body: 'Inter', system-ui, sans-serif;
}

body.ct-public {
  font-family: var(--ct-pub-font-body);
  color: var(--ct-pub-text);
  background: #fff;
}
.ct-public h1, .ct-public h2, .ct-public h3, .ct-public h4 { font-family: var(--ct-pub-font-head); }
.ct-public img { max-width: 100%; height: auto; }

/* Navbar */
.ct-nav {
  position: sticky; top: 0; z-index: 1030;
  background: rgba(255, 255, 255, .85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid transparent;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.ct-nav.is-scrolled { border-bottom-color: var(--ct-pub-border); box-shadow: var(--ct-pub-shadow); }
.ct-nav .navbar-brand { font-family: var(--ct-pub-font-head); font-weight: 800; color: var(--ct-pub-ink); }
.ct-nav .nav-link { color: var(--ct-pub-muted); font-weight: 500; }
.ct-nav .nav-link:hover { color: var(--ct-pub-ink); }

/* Section helpers */
.ct-section__title { font-weight: 800; font-size: clamp(1.6rem, 3vw, 2.2rem); }
.ct-section__lead { color: var(--ct-pub-muted); max-width: 38rem; margin: .5rem auto 0; }

/* Hero */
.ct-hero {
  position: relative; overflow: hidden;
  background: radial-gradient(120% 120% at 80% -10%, #1e293b 0%, var(--ct-pub-hero-bg) 55%);
  color: #fff; padding: clamp(3.5rem, 8vw, 6.5rem) 0;
}
.ct-hero__glow {
  position: absolute; inset: auto auto -30% -10%;
  width: 38rem; height: 38rem; border-radius: 50%;
  background: var(--app-primary, #0d6efd); filter: blur(120px); opacity: .25;
}
.ct-hero .container { position: relative; z-index: 1; }
.ct-hero__badge {
  display: inline-block; padding: .35rem .9rem; border-radius: 999px;
  background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .18);
  font-size: .8rem; font-weight: 600; letter-spacing: .02em; margin-bottom: 1.1rem;
}
.ct-hero__title { font-weight: 800; font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.08; }
.ct-hero__subtitle { color: rgba(255, 255, 255, .72); font-size: 1.12rem; margin-top: 1rem; max-width: 34rem; }
.ct-hero__media { border-radius: var(--ct-pub-radius); box-shadow: 0 24px 60px rgba(0, 0, 0, .35); }

/* Trust */
.ct-trust { background: var(--ct-pub-surface); border-bottom: 1px solid var(--ct-pub-border); padding: 2.2rem 0; }
.ct-trust__item { display: flex; flex-direction: column; }
.ct-trust__valor { font-family: var(--ct-pub-font-head); font-weight: 800; font-size: 1.6rem; color: var(--ct-pub-ink); }
.ct-trust__etiqueta { color: var(--ct-pub-muted); font-size: .92rem; }

/* Pricing */
.ct-pricing { padding: clamp(3rem, 7vw, 5rem) 0; }
.ct-billing {
  display: inline-flex; gap: .25rem; margin: 1.25rem auto 0; padding: .3rem;
  background: var(--ct-pub-surface); border: 1px solid var(--ct-pub-border); border-radius: 999px;
}
.ct-billing__btn {
  border: 0; background: transparent; border-radius: 999px;
  padding: .45rem 1.2rem; font-weight: 600; color: var(--ct-pub-muted); cursor: pointer;
}
.ct-billing__btn.is-active { background: #fff; color: var(--ct-pub-ink); box-shadow: var(--ct-pub-shadow); }
.ct-plan {
  display: flex; flex-direction: column; position: relative;
  background: #fff; border: 1px solid var(--ct-pub-border); border-radius: var(--ct-pub-radius);
  padding: 2rem 1.6rem; box-shadow: var(--ct-pub-shadow); transition: transform .2s ease, box-shadow .2s ease;
}
.ct-plan:hover { transform: translateY(-4px); box-shadow: var(--ct-pub-shadow-hover); }
.ct-plan--featured { border-color: var(--app-primary, #0d6efd); box-shadow: 0 0 0 1px var(--app-primary, #0d6efd), var(--ct-pub-shadow-hover); }
.ct-plan__badge {
  position: absolute; top: -.8rem; left: 50%; transform: translateX(-50%);
  background: var(--app-primary, #0d6efd); color: #fff; font-size: .72rem; font-weight: 700;
  padding: .25rem .8rem; border-radius: 999px;
}
.ct-plan__name { font-weight: 800; font-size: 1.25rem; }
.ct-plan__price { font-family: var(--ct-pub-font-head); font-weight: 800; font-size: 2.1rem; margin: .4rem 0 1rem; }
.ct-plan__period { font-size: .95rem; font-weight: 500; color: var(--ct-pub-muted); }
.ct-plan__features { list-style: none; padding: 0; margin: 0 0 1.5rem; text-align: left; }
.ct-plan__features li { display: flex; gap: .55rem; align-items: flex-start; padding: .35rem 0; color: var(--ct-pub-text); }
.ct-plan__features i { color: var(--app-primary, #0d6efd); margin-top: .15rem; }

/* Testimonios */
.ct-testimonios { padding: clamp(3rem, 7vw, 5rem) 0; background: var(--ct-pub-surface); }
.ct-testimonio { background: #fff; border: 1px solid var(--ct-pub-border); border-radius: var(--ct-pub-radius); padding: 1.6rem; box-shadow: var(--ct-pub-shadow); }
.ct-testimonio__stars { color: #f59e0b; margin-bottom: .6rem; }
.ct-testimonio__text { color: var(--ct-pub-text); }
.ct-testimonio__autor { color: var(--ct-pub-muted); font-weight: 600; font-size: .9rem; }

/* Demo form */
.ct-demo { padding: clamp(3rem, 7vw, 5rem) 0; }
.ct-demo__card { max-width: 34rem; background: #fff; border: 1px solid var(--ct-pub-border); border-radius: var(--ct-pub-radius); padding: 2rem; box-shadow: var(--ct-pub-shadow); }

/* Footer */
.ct-footer { background: var(--ct-pub-ink); color: rgba(255, 255, 255, .72); padding: 3rem 0 2rem; }
.ct-footer__brand { font-family: var(--ct-pub-font-head); font-weight: 800; color: #fff; font-size: 1.2rem; }
.ct-footer__legal { color: rgba(255, 255, 255, .55); font-size: .9rem; margin-top: .5rem; }
.ct-footer__col-title { color: #fff; font-size: .95rem; font-weight: 700; margin-bottom: .6rem; }
.ct-footer__links { list-style: none; padding: 0; margin: 0; }
.ct-footer__links a { color: rgba(255, 255, 255, .72); text-decoration: none; display: inline-block; padding: .2rem 0; }
.ct-footer__links a:hover { color: #fff; }
.ct-footer__sep { border-color: rgba(255, 255, 255, .12); margin: 2rem 0 1rem; }
.ct-footer__bottom { color: rgba(255, 255, 255, .5); font-size: .85rem; }

/* Reveal animation */
[data-reveal] { opacity: 0; transform: translateY(14px); transition: opacity .5s ease, transform .5s ease; }
[data-reveal].is-visible { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) { [data-reveal] { opacity: 1; transform: none; transition: none; } }
```

- [ ] **Step 4: Create the JS**

Create `public/assets/publico/landing.js`:

```js
/* public/assets/publico/landing.js — interacciones del front público (sin dominio). */
(function () {
  'use strict';

  // Navbar: solidificar al hacer scroll
  var nav = document.querySelector('.ct-nav');
  if (nav) {
    var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 8); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Toggle de facturación mensual/anual
  var billing = document.querySelector('.ct-billing');
  if (billing) {
    var prices = document.querySelectorAll('.ct-plan__price');
    billing.addEventListener('click', function (e) {
      var btn = e.target.closest('.ct-billing__btn');
      if (!btn) return;
      var period = btn.getAttribute('data-period');
      billing.querySelectorAll('.ct-billing__btn').forEach(function (b) {
        var active = b === btn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      prices.forEach(function (p) {
        var amount = p.querySelector('.ct-plan__amount');
        var periodEl = p.querySelector('.ct-plan__period');
        var value = period === 'annual' ? p.getAttribute('data-annual') : p.getAttribute('data-monthly');
        if (amount) amount.textContent = value || '';
        if (periodEl) {
          var numeric = /\d/.test(value || '');
          periodEl.textContent = numeric ? (period === 'annual' ? '/año' : '/mes') : '';
        }
      });
    });
  }

  // Reveal on scroll
  var revealables = document.querySelectorAll('[data-reveal]');
  if (revealables.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
    }, { threshold: 0.12 });
    revealables.forEach(function (el) { io.observe(el); });
  } else {
    revealables.forEach(function (el) { el.classList.add('is-visible'); });
  }
})();
```

- [ ] **Step 5: Create the generic hero SVG**

Create `public/assets/publico/hero-mock.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 420" role="img" aria-label="Vista previa de mensajería automatizada">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#1e293b"/><stop offset="1" stop-color="#0f172a"/>
    </linearGradient>
  </defs>
  <rect width="520" height="420" rx="20" fill="url(#g)"/>
  <rect x="60" y="48" width="240" height="324" rx="22" fill="#0b1220" stroke="#334155" stroke-width="2"/>
  <rect x="78" y="70" width="204" height="26" rx="8" fill="#1e293b"/>
  <circle cx="92" cy="83" r="5" fill="#22c55e"/>
  <rect x="92" y="120" width="150" height="36" rx="12" fill="#1e293b"/>
  <rect x="150" y="172" width="114" height="36" rx="12" fill="#2563eb"/>
  <rect x="92" y="224" width="120" height="36" rx="12" fill="#1e293b"/>
  <rect x="150" y="276" width="114" height="30" rx="12" fill="#2563eb"/>
  <g transform="translate(330 150)">
    <rect width="150" height="150" rx="16" fill="#0b1220" stroke="#334155" stroke-width="2"/>
    <rect x="18" y="22" width="80" height="10" rx="5" fill="#334155"/>
    <rect x="18" y="44" width="114" height="8" rx="4" fill="#1e293b"/>
    <rect x="18" y="62" width="100" height="8" rx="4" fill="#1e293b"/>
    <rect x="18" y="98" width="114" height="30" rx="10" fill="#22c55e"/>
  </g>
</svg>
```

- [ ] **Step 6: Create the footer partial**

Create `app/Presentation/Views/publico/partials/_footer.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_footer.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$footer        = is_array($footer ?? null) ? $footer : [];
$columnas      = is_array($footer['columnas'] ?? null) ? $footer['columnas'] : [];
$legal         = (string) ($footer['legal'] ?? '');
$empresaNombre = (string) ($empresaNombre ?? '');
?>
<footer class="ct-footer">
  <div class="container">
    <?php if ($columnas !== []): ?>
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="ct-footer__brand"><?= ViewHelper::e($empresaNombre) ?></div>
          <?php if ($legal !== ''): ?><p class="ct-footer__legal"><?= ViewHelper::e($legal) ?></p><?php endif; ?>
        </div>
        <?php foreach ($columnas as $col): ?>
          <div class="col-6 col-lg-2">
            <h4 class="ct-footer__col-title"><?= ViewHelper::e((string) ($col['titulo'] ?? '')) ?></h4>
            <ul class="ct-footer__links">
              <?php foreach ((is_array($col['links'] ?? null) ? $col['links'] : []) as $ln): ?>
                <li><a href="<?= ViewHelper::e((string) ($ln['url'] ?? '#')) ?>"><?= ViewHelper::e((string) ($ln['texto'] ?? '')) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
      <hr class="ct-footer__sep">
    <?php endif; ?>
    <div class="ct-footer__bottom text-center">
      &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
    </div>
  </div>
</footer>
```

- [ ] **Step 7: Replace the public layout**

Replace the full contents of `app/Presentation/Views/publico/layout.php`:

```php
<?php
// app/Presentation/Views/publico/layout.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
$bloques       = is_array($bloques ?? null) ? $bloques : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($empresaNombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?= ViewHelper::partial('styles/lebytek_theme_vars', [
        'includeNavChrome'    => false,
        'primaryColor'        => $primaryColor        ?? null,
        'primaryHover'        => $primaryHover        ?? null,
        'primaryActive'       => $primaryActive       ?? null,
        'primarySubtle'       => $primarySubtle       ?? null,
        'primaryRgb'          => $primaryRgb          ?? null,
        'lebytekCssVariables' => $lebytekCssVariables ?? [],
        'bodyBg'              => $bodyBg              ?? null,
        'darkMode'           => $darkMode            ?? false,
    ]) ?>
    <link href="/assets/publico/landing.css" rel="stylesheet">
</head>
<body class="ct-public">
    <nav class="navbar navbar-expand-lg ct-nav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($empresaLogo !== ''): ?>
                    <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="30">
                <?php endif; ?>
                <span><?= ViewHelper::e($empresaNombre) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ctNav" aria-controls="ctNav" aria-expanded="false" aria-label="Menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="ctNav">
                <ul class="navbar-nav align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="#paquetes">Paquetes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#demo">Demo</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm px-3 ms-lg-2" href="/login">Acceder</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <?= $content ?? '' ?>
    </main>

    <?= ViewHelper::render('publico/partials/_footer', [
        'footer'        => $bloques['footer'] ?? [],
        'empresaNombre' => $empresaNombre,
    ], '') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/publico/landing.js" defer></script>
</body>
</html>
```

- [ ] **Step 8: Wire the theme into LandingController**

Replace the full contents of `app/Presentation/Controllers/Publico/LandingController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Kernel\BaseClasses\BaseController;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Helpers\LebytekUiConfig;
use App\Application\Services\ConfiguracionService;
use App\Application\Marketing\RenderLandingUseCase;

final class LandingController extends BaseController
{
    public function __construct(
        private readonly ConfiguracionService $configuracionService,
        private readonly RenderLandingUseCase $renderLanding
    ) {}

    public function index(Request $request): Response
    {
        $vm = $this->renderLanding->ejecutar('home');
        $ui = LebytekUiConfig::resolve($this->configuracionService->all());

        return $this->view('publico/landing', [
            'empresaNombre'       => $this->configuracionService->empresaNombre(),
            'empresaLogo'         => $this->configuracionService->empresaLogo(),
            'bloques'             => $vm['bloques'],
            'paquetes'            => $vm['paquetes'],
            'primaryColor'        => $ui['primaryColor'],
            'primaryHover'        => $ui['primaryHover'],
            'primaryActive'       => $ui['primaryActive'],
            'primarySubtle'       => $ui['primarySubtle'],
            'primaryRgb'          => $ui['primaryRgb'],
            'lebytekCssVariables' => $ui['lebytekCssVariables'],
            'bodyBg'              => $ui['bodyBg'],
            'darkMode'            => $ui['darkMode'],
        ], 'publico/layout');
    }
}
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en todos (los 3 originales + los 4 nuevos). Nota: `landing.php` aún tiene su contenido original (hero/paquetes/form inline), por lo que los tests originales siguen verdes.

- [ ] **Step 10: Commit**

```bash
git add public/assets/publico/landing.css public/assets/publico/landing.js public/assets/publico/hero-mock.svg \
        app/Presentation/Views/publico/partials/_footer.php \
        app/Presentation/Views/publico/layout.php \
        app/Presentation/Controllers/Publico/LandingController.php \
        tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): layout publico theme-aware + sistema visual y footer data-driven"
```

---

### Task 2: Partial Hero (con slot de media)

**Files:**
- Create: `app/Presentation/Views/publico/partials/_hero.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: variable `$hero` (array) con claves opcionales `titulo, subtitulo, badge, cta_texto, cta_url, cta2_texto, cta2_url, media:{img,alt}`.
- Produces: partial renderizable vía `ViewHelper::render('publico/partials/_hero', ['hero' => array], '')`. Si `titulo` y `subtitulo` están vacíos, no emite nada (degradación).

- [ ] **Step 1: Write the failing tests**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('hero renderiza badge, dos CTAs y media cuando están presentes', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => [
        'titulo' => 'Titulo Hero', 'subtitulo' => 'Sub Hero', 'badge' => 'API de WhatsApp',
        'cta_texto' => 'Solicitar demo', 'cta_url' => '#demo',
        'cta2_texto' => 'Ver paquetes', 'cta2_url' => '#paquetes',
        'media' => ['img' => '/assets/publico/hero-mock.svg', 'alt' => 'Mock demo'],
    ]], '');
    assert_true(str_contains($html, 'Titulo Hero'), 'titulo');
    assert_true(str_contains($html, 'API de WhatsApp'), 'badge');
    assert_true(str_contains($html, 'Solicitar demo'), 'cta 1');
    assert_true(str_contains($html, 'Ver paquetes'), 'cta 2');
    assert_true(str_contains($html, '/assets/publico/hero-mock.svg'), 'media img');
    assert_true(str_contains($html, 'Mock demo'), 'media alt');
});

test('hero vacío no emite sección (degradación)', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => []], '');
    assert_true(trim($html) === '', 'sin contenido cuando no hay datos');
});

test('hero sin media no genera <img>', function (): void {
    $html = ViewHelper::render('publico/partials/_hero', ['hero' => [
        'titulo' => 'Solo texto', 'subtitulo' => 'Sin media',
    ]], '');
    assert_true(str_contains($html, 'Solo texto'), 'titulo presente');
    assert_true(!str_contains($html, 'ct-hero__media'), 'sin elemento de media');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL con "Vista no encontrada: …/publico/partials/_hero.php".

- [ ] **Step 3: Create the hero partial**

Create `app/Presentation/Views/publico/partials/_hero.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_hero.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$hero      = is_array($hero ?? null) ? $hero : [];
$titulo    = (string) ($hero['titulo'] ?? '');
$subtitulo = (string) ($hero['subtitulo'] ?? '');
$badge     = (string) ($hero['badge'] ?? '');
$ctaTexto  = (string) ($hero['cta_texto'] ?? '');
$ctaUrl    = (string) ($hero['cta_url'] ?? '#demo');
$cta2Texto = (string) ($hero['cta2_texto'] ?? '');
$cta2Url   = (string) ($hero['cta2_url'] ?? '#paquetes');
$media     = is_array($hero['media'] ?? null) ? $hero['media'] : [];
$mediaImg  = (string) ($media['img'] ?? '');
$mediaAlt  = (string) ($media['alt'] ?? '');

if ($titulo === '' && $subtitulo === '') {
    return;
}
?>
<section class="ct-hero" id="inicio">
  <span class="ct-hero__glow" aria-hidden="true"></span>
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 text-center text-lg-start">
        <?php if ($badge !== ''): ?><span class="ct-hero__badge"><?= ViewHelper::e($badge) ?></span><?php endif; ?>
        <h1 class="ct-hero__title"><?= ViewHelper::e($titulo) ?></h1>
        <?php if ($subtitulo !== ''): ?><p class="ct-hero__subtitle"><?= ViewHelper::e($subtitulo) ?></p><?php endif; ?>
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start mt-4">
          <?php if ($ctaTexto !== ''): ?>
            <a href="<?= ViewHelper::e($ctaUrl) ?>" class="btn btn-primary btn-lg px-4"><?= ViewHelper::e($ctaTexto) ?></a>
          <?php endif; ?>
          <?php if ($cta2Texto !== ''): ?>
            <a href="<?= ViewHelper::e($cta2Url) ?>" class="btn btn-outline-light btn-lg px-4"><?= ViewHelper::e($cta2Texto) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <?php if ($mediaImg !== ''): ?>
          <img src="<?= ViewHelper::e($mediaImg) ?>" alt="<?= ViewHelper::e($mediaAlt) ?>" class="ct-hero__media" loading="lazy">
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en los 3 tests del hero.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/_hero.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): partial hero con slot de media configurable"
```

---

### Task 3: Partial Trust bar

**Files:**
- Create: `app/Presentation/Views/publico/partials/_trust.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: variable `$trust` (array) con forma `{items: [{valor, etiqueta}, ...]}`.
- Produces: partial renderizable vía `ViewHelper::render('publico/partials/_trust', ['trust' => array], '')`. Sin `items` no emite nada.

- [ ] **Step 1: Write the failing tests**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('trust bar renderiza métricas cuando hay items', function (): void {
    $html = ViewHelper::render('publico/partials/_trust', ['trust' => ['items' => [
        ['valor' => 'REST API', 'etiqueta' => 'Integración simple'],
        ['valor' => '< 5 min', 'etiqueta' => 'Tiempo de setup'],
    ]]], '');
    assert_true(str_contains($html, 'REST API'), 'valor 1');
    assert_true(str_contains($html, 'Integración simple'), 'etiqueta 1');
    assert_true(str_contains($html, '< 5 min'), 'valor 2 escapado');
});

test('trust bar sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_trust', ['trust' => []], '');
    assert_true(trim($html) === '', 'degradación sin items');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL con "Vista no encontrada: …/publico/partials/_trust.php".

- [ ] **Step 3: Create the trust partial**

Create `app/Presentation/Views/publico/partials/_trust.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_trust.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$items = is_array($trust['items'] ?? null) ? $trust['items'] : [];
if ($items === []) {
    return;
}
?>
<section class="ct-trust" aria-label="Indicadores de confianza">
  <div class="container">
    <div class="row g-4 text-center">
      <?php foreach ($items as $it): ?>
        <div class="col-6 col-md-<?= count($items) >= 4 ? '3' : '4' ?>">
          <div class="ct-trust__item">
            <span class="ct-trust__valor"><?= ViewHelper::e((string) ($it['valor'] ?? '')) ?></span>
            <span class="ct-trust__etiqueta"><?= ViewHelper::e((string) ($it['etiqueta'] ?? '')) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en los 2 tests del trust.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/_trust.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): partial trust bar de métricas"
```

---

### Task 4: Partial Pricing (con toggle mensual/anual)

**Files:**
- Create: `app/Presentation/Views/publico/partials/_pricing.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: variable `$paquetes` (list) de arrays con claves `nombre, precio_mensual, precio_anual, features (array|JSON string), destacado (bool), badge`.
- Produces: partial renderizable vía `ViewHelper::render('publico/partials/_pricing', ['paquetes' => list], '')`. Emite el toggle `.ct-billing` y, por plan, `.ct-plan__price` con `data-monthly`/`data-annual` (formateados con la función local `$fmt`: `''` → `'A medida'`, `69.00` → `'$69'`). Conserva el manejo de `features` como array y como JSON string. Sin paquetes no emite nada.

- [ ] **Step 1: Write the failing tests**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('pricing renderiza toggle, precios formateados, destacado y features (array y JSON)', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => [
        ['nombre' => 'Básico', 'precio_mensual' => '69.00', 'precio_anual' => '599.00',
         'features' => ['5,000 mensajes/mes', '1 número de WhatsApp']],
        ['nombre' => 'Pro', 'precio_mensual' => '99.00', 'precio_anual' => '899.00',
         'destacado' => 1, 'badge' => 'Más popular', 'features' => '["30,000 mensajes/mes"]'],
        ['nombre' => 'Empresa', 'precio_mensual' => '', 'precio_anual' => '',
         'features' => ['Mensajes ilimitados']],
    ]], '');
    assert_true(str_contains($html, 'ct-billing'), 'toggle de facturación');
    assert_true(str_contains($html, 'data-period="annual"'), 'opción anual');
    assert_true(str_contains($html, 'data-annual="$599"'), 'precio anual formateado');
    assert_true(str_contains($html, 'data-monthly="$69"'), 'precio mensual formateado');
    assert_true(str_contains($html, 'ct-plan--featured'), 'plan destacado');
    assert_true(str_contains($html, 'Más popular'), 'badge');
    assert_true(str_contains($html, '5,000 mensajes/mes'), 'feature de array');
    assert_true(str_contains($html, '30,000 mensajes/mes'), 'feature desde JSON string');
    assert_true(str_contains($html, 'A medida'), 'precio vacío muestra A medida');
});

test('pricing sin paquetes no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_pricing', ['paquetes' => []], '');
    assert_true(trim($html) === '', 'degradación sin paquetes');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL con "Vista no encontrada: …/publico/partials/_pricing.php".

- [ ] **Step 3: Create the pricing partial**

Create `app/Presentation/Views/publico/partials/_pricing.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_pricing.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$paquetes = is_array($paquetes ?? null) ? $paquetes : [];
if ($paquetes === []) {
    return;
}

$fmt = static function (string $v): string {
    if ($v === '') {
        return 'A medida';
    }
    $n = (float) $v;
    return '$' . rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
};
?>
<section class="ct-pricing" id="paquetes">
  <div class="container">
    <div class="text-center">
      <h2 class="ct-section__title">Paquetes</h2>
      <p class="ct-section__lead">Elige el plan que se adapta al volumen de tu negocio.</p>
      <div class="ct-billing" role="group" aria-label="Periodo de facturación">
        <button type="button" class="ct-billing__btn is-active" data-period="monthly" aria-pressed="true">Mensual</button>
        <button type="button" class="ct-billing__btn" data-period="annual" aria-pressed="false">Anual</button>
      </div>
    </div>
    <div class="row g-4 justify-content-center align-items-stretch mt-1">
      <?php foreach ($paquetes as $p): ?>
        <?php
          $features = $p['features'] ?? [];
          if (is_string($features)) {
              $decoded = json_decode($features, true);
              $features = is_array($decoded) ? $decoded : [];
          }
          $featured   = !empty($p['destacado']);
          $mensualTxt = $fmt((string) ($p['precio_mensual'] ?? ''));
          $anualTxt   = $fmt((string) ($p['precio_anual'] ?? ''));
          $numeric    = preg_match('/\d/', $mensualTxt) === 1;
        ?>
        <div class="col-md-4">
          <div class="ct-plan <?= $featured ? 'ct-plan--featured' : '' ?>" data-reveal>
            <?php if (!empty($p['badge'])): ?>
              <span class="ct-plan__badge"><?= ViewHelper::e((string) $p['badge']) ?></span>
            <?php endif; ?>
            <h3 class="ct-plan__name"><?= ViewHelper::e((string) ($p['nombre'] ?? '')) ?></h3>
            <p class="ct-plan__price" data-monthly="<?= ViewHelper::e($mensualTxt) ?>" data-annual="<?= ViewHelper::e($anualTxt) ?>">
              <span class="ct-plan__amount"><?= ViewHelper::e($mensualTxt) ?></span><?php if ($numeric): ?><span class="ct-plan__period">/mes</span><?php endif; ?>
            </p>
            <?php if ($features !== []): ?>
              <ul class="ct-plan__features">
                <?php foreach ($features as $f): ?>
                  <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= ViewHelper::e((string) $f) ?></span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <a href="#demo" class="btn <?= $featured ? 'btn-primary' : 'btn-outline-primary' ?> w-100 mt-auto">Solicitar demo</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en los 2 tests de pricing.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/_pricing.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): partial pricing con toggle mensual/anual y precios formateados"
```

---

### Task 5: Partial Testimonios

**Files:**
- Create: `app/Presentation/Views/publico/partials/_testimonios.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: variable `$testimonios` (array) con forma `{items: [{texto, autor, avatar?}, ...]}`.
- Produces: partial renderizable vía `ViewHelper::render('publico/partials/_testimonios', ['testimonios' => array], '')`. Sin `items` no emite nada.

- [ ] **Step 1: Write the failing tests**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('testimonios renderiza texto, autor y estrellas', function (): void {
    $html = ViewHelper::render('publico/partials/_testimonios', ['testimonios' => ['items' => [
        ['texto' => 'Integramos la API en una tarde', 'autor' => 'María G., E-commerce'],
    ]]], '');
    assert_true(str_contains($html, 'Integramos la API en una tarde'), 'texto');
    assert_true(str_contains($html, 'María G., E-commerce'), 'autor');
    assert_true(str_contains($html, 'bi-star-fill'), 'estrellas');
});

test('testimonios sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/_testimonios', ['testimonios' => []], '');
    assert_true(trim($html) === '', 'degradación sin items');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL con "Vista no encontrada: …/publico/partials/_testimonios.php".

- [ ] **Step 3: Create the testimonios partial**

Create `app/Presentation/Views/publico/partials/_testimonios.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_testimonios.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$items = is_array($testimonios['items'] ?? null) ? $testimonios['items'] : [];
if ($items === []) {
    return;
}
?>
<section class="ct-testimonios" id="resenas">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="ct-section__title">Lo que dicen nuestros clientes</h2>
    </div>
    <div class="row g-4">
      <?php foreach ($items as $t): ?>
        <div class="col-md-4">
          <article class="ct-testimonio h-100" data-reveal>
            <div class="ct-testimonio__stars" aria-label="5 estrellas">
              <?php for ($i = 0; $i < 5; $i++): ?><i class="bi bi-star-fill" aria-hidden="true"></i><?php endfor; ?>
            </div>
            <p class="ct-testimonio__text">&ldquo;<?= ViewHelper::e((string) ($t['texto'] ?? '')) ?>&rdquo;</p>
            <footer class="ct-testimonio__autor"><?= ViewHelper::e((string) ($t['autor'] ?? '')) ?></footer>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en los 2 tests de testimonios.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/_testimonios.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): partial de testimonios"
```

---

### Task 6: Partial Lead Form (restyle)

**Files:**
- Create: `app/Presentation/Views/publico/partials/_lead_form.php`
- Test: `tests/Marketing/PublicViewTest.php` (añadir tests)

**Interfaces:**
- Consumes: `ViewHelper::csrfField()`, `App\Kernel\Security\Session::flashAll()`.
- Produces: partial renderizable vía `ViewHelper::render('publico/partials/_lead_form', [], '')`. Emite un `<form method="POST" action="/lead">` con campo CSRF y los inputs `nombre, email, telefono, mensaje`. Muestra flashes `success`/`error` si existen.

- [ ] **Step 1: Write the failing tests**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('lead form postea a /lead con CSRF y campos requeridos', function (): void {
    $html = ViewHelper::render('publico/partials/_lead_form', [], '');
    assert_true(str_contains($html, 'action="/lead"'), 'postea a /lead');
    assert_true(str_contains($html, 'method="POST"'), 'método POST');
    assert_true(str_contains($html, 'name="nombre"'), 'campo nombre');
    assert_true(str_contains($html, 'name="email"'), 'campo email');
    assert_true(str_contains($html, 'name="telefono"'), 'campo teléfono');
    assert_true(str_contains($html, 'name="mensaje"'), 'campo mensaje');
    assert_true(str_contains($html, 'csrf'), 'incluye token CSRF');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL con "Vista no encontrada: …/publico/partials/_lead_form.php".

- [ ] **Step 3: Create the lead form partial**

Create `app/Presentation/Views/publico/partials/_lead_form.php`:

```php
<?php
// app/Presentation/Views/publico/partials/_lead_form.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Session;

$flash = $flashAll ?? Session::flashAll();
$flash = is_array($flash) ? $flash : [];
?>
<section class="ct-demo" id="demo">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="ct-section__title">Solicita una demo</h2>
      <p class="ct-section__lead">Cuéntanos sobre tu proyecto y te contactamos pronto.</p>
    </div>
    <div class="ct-demo__card mx-auto">
      <?php foreach ($flash as $tipo => $msg): ?>
        <?php if (in_array($tipo, ['success', 'error'], true)): ?>
          <div class="alert alert-<?= $tipo === 'success' ? 'success' : 'danger' ?>">
            <?= ViewHelper::e(is_array($msg) ? implode(' ', $msg) : (string) $msg) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <form method="POST" action="/lead">
        <?= ViewHelper::csrfField() ?>
        <div class="mb-3"><input type="text" name="nombre" class="form-control form-control-lg" placeholder="Nombre" required></div>
        <div class="mb-3"><input type="email" name="email" class="form-control form-control-lg" placeholder="Correo" required></div>
        <div class="mb-3"><input type="text" name="telefono" class="form-control form-control-lg" placeholder="Teléfono (opcional)"></div>
        <div class="mb-3"><textarea name="mensaje" class="form-control" rows="3" placeholder="¿En qué te ayudamos?"></textarea></div>
        <button type="submit" class="btn btn-primary btn-lg w-100">Enviar</button>
      </form>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: PASS en el test del lead form.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/_lead_form.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): partial del formulario de captación restyled"
```

---

### Task 7: Orquestar la landing con los partials

**Files:**
- Modify: `app/Presentation/Views/publico/landing.php` (reemplazo completo)
- Test: `tests/Marketing/PublicViewTest.php` (añadir test de integración)

**Interfaces:**
- Consumes: variables `$bloques` (array indexado por clave: `hero`, `trust`, `testimonios`, `footer`) y `$paquetes` (list). Renderiza los partials `_hero`, `_trust`, `_pricing`, `_testimonios`, `_lead_form` (el footer lo renderiza el layout en Task 1).
- Produces: la página completa data-driven. Los tests originales de `PublicViewTest` (hero title, features array/JSON, degradación) siguen pasando.

- [ ] **Step 1: Write the failing test**

Añadir a `tests/Marketing/PublicViewTest.php`:

```php
test('landing integra todas las secciones desde bloques y paquetes', function (): void {
    $html = ViewHelper::render('publico/landing', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'bloques' => [
            'hero'        => ['titulo' => 'Hero Integrado', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo'],
            'trust'       => ['items' => [['valor' => 'REST API', 'etiqueta' => 'Integración simple']]],
            'testimonios' => ['items' => [['texto' => 'Excelente servicio', 'autor' => 'Cliente X']]],
        ],
        'paquetes' => [
            ['nombre' => 'Pro', 'precio_mensual' => '99.00', 'precio_anual' => '899.00', 'destacado' => 1, 'features' => ['30,000 mensajes/mes']],
        ],
    ], 'publico/layout');
    assert_true(str_contains($html, 'Hero Integrado'), 'sección hero');
    assert_true(str_contains($html, 'REST API'), 'sección trust');
    assert_true(str_contains($html, 'ct-pricing'), 'sección pricing');
    assert_true(str_contains($html, 'Excelente servicio'), 'sección testimonios');
    assert_true(str_contains($html, 'action="/lead"'), 'sección formulario');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing/PublicViewTest`
Expected: FAIL en el test de integración (la landing actual no renderiza trust ni testimonios).

- [ ] **Step 3: Replace landing.php**

Replace the full contents of `app/Presentation/Views/publico/landing.php`:

```php
<?php
// app/Presentation/Views/publico/landing.php
declare(strict_types=1);

use App\Kernel\Helpers\ViewHelper;

$bloques  = is_array($bloques ?? null) ? $bloques : [];
$paquetes = is_array($paquetes ?? null) ? $paquetes : [];

echo ViewHelper::render('publico/partials/_hero',        ['hero'        => $bloques['hero']        ?? []], '');
echo ViewHelper::render('publico/partials/_trust',       ['trust'       => $bloques['trust']       ?? []], '');
echo ViewHelper::render('publico/partials/_pricing',     ['paquetes'    => $paquetes], '');
echo ViewHelper::render('publico/partials/_testimonios', ['testimonios' => $bloques['testimonios'] ?? []], '');
echo ViewHelper::render('publico/partials/_lead_form',   [], '');
```

- [ ] **Step 4: Run the full marketing suite to verify everything passes**

Run: `php tests/run.php Marketing`
Expected: PASS en toda la suite Marketing, incluidos los 3 tests originales de `PublicViewTest` (hero title, features array/JSON string, degradación sin bloques) y el nuevo test de integración.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/landing.php tests/Marketing/PublicViewTest.php
git commit -m "feat(marketing): landing pública data-driven compuesta por partials"
```

---

### Task 8: Seed demo WhatsApp idempotente + flag en seed.php

**Files:**
- Create: `database/schema/modules/marketing_demo.sql`
- Modify: `scripts/seed.php`
- Test: `tests/Marketing/DemoSeedTest.php`

**Interfaces:**
- Consumes: tablas `dom_mkt_paquetes`, `dom_mkt_bloques`, `dom_mkt_plantillas` (ya creadas por `marketing.sql`).
- Produces: `database/schema/modules/marketing_demo.sql` idempotente y `scripts/seed.php` que lo aplica con el flag `--marketing-demo` (mismo patrón que `--crud-engine`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Marketing/DemoSeedTest.php`:

```php
<?php
// tests/Marketing/DemoSeedTest.php
declare(strict_types=1);

test('marketing_demo.sql existe y es idempotente sin FKs ni dominio acoplado', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true($sql !== false, 'archivo existe');
    assert_true(str_contains($sql, 'WHERE NOT EXISTS'), 'inserts guardados por NOT EXISTS');
    assert_true(!str_contains($sql, 'FOREIGN KEY'), 'sin FOREIGN KEY');
    assert_true(!str_contains($sql, 'CREATE TABLE'), 'no recrea tablas (solo datos)');
    assert_true(str_contains($sql, 'dom_mkt_paquetes'), 'opera sobre dom_mkt_paquetes');
});

test('marketing_demo.sql siembra los 3 paquetes demo y desactiva el placeholder', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true(str_contains($sql, "'Básico'"), 'plan Básico');
    assert_true(str_contains($sql, "'Pro'"), 'plan Pro');
    assert_true(str_contains($sql, "'Empresa'"), 'plan Empresa');
    assert_true(str_contains($sql, "'Más popular'"), 'badge del destacado');
    assert_true(str_contains($sql, "SET `activo` = 0 WHERE `nombre` = 'Plan Demo'"), 'desactiva placeholder genérico');
});

test('marketing_demo.sql siembra bloques hero/trust/testimonios/footer', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing_demo.sql');
    assert_true(str_contains($sql, "'hero'"), 'bloque hero');
    assert_true(str_contains($sql, "'trust'"), 'bloque trust');
    assert_true(str_contains($sql, "'testimonios'"), 'bloque testimonios');
    assert_true(str_contains($sql, "'footer'"), 'bloque footer');
    assert_true(str_contains($sql, '/assets/publico/hero-mock.svg'), 'media del hero (SVG genérico)');
});

test('seed.php aplica el demo de marketing tras el flag --marketing-demo', function (): void {
    $src = file_get_contents(ROOT_PATH . '/scripts/seed.php');
    assert_true($src !== false, 'seed.php existe');
    assert_true(str_contains($src, '--marketing-demo'), 'declara el flag');
    assert_true(str_contains($src, 'marketing_demo.sql'), 'referencia el archivo demo');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php Marketing/DemoSeedTest`
Expected: FAIL (el archivo demo no existe; `seed.php` no tiene el flag).

- [ ] **Step 3: Create the demo seed**

Create `database/schema/modules/marketing_demo.sql`:

```sql
-- database/schema/modules/marketing_demo.sql
-- Datos demo (sabor WhatsApp SaaS) para ejercitar el front público de Marketing.
-- Solo datos: las tablas dom_mkt_* ya existen tras marketing.sql.
-- Idempotente (UPDATE guardado / INSERT ... WHERE NOT EXISTS).
-- Cargar con: php scripts/seed.php --marketing-demo
SET NAMES utf8mb4;

-- ── Paquetes ──────────────────────────────────────────────────────────────────
UPDATE `dom_mkt_paquetes` SET `activo` = 0 WHERE `nombre` = 'Plan Demo';

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Básico' AS nombre, 69.00 AS precio_mensual, 599.00 AS precio_anual,
  JSON_ARRAY('5,000 mensajes/mes','1 número de WhatsApp','Soporte por correo') AS features,
  0 AS destacado, NULL AS badge, 1 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Básico');

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Pro' AS nombre, 99.00 AS precio_mensual, 899.00 AS precio_anual,
  JSON_ARRAY('30,000 mensajes/mes','1 número de WhatsApp','Soporte prioritario') AS features,
  1 AS destacado, 'Más popular' AS badge, 2 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Pro');

INSERT INTO `dom_mkt_paquetes` (`nombre`,`precio_mensual`,`precio_anual`,`features`,`destacado`,`badge`,`orden`,`activo`)
SELECT * FROM (SELECT
  'Empresa' AS nombre, NULL AS precio_mensual, NULL AS precio_anual,
  JSON_ARRAY('Mensajes ilimitados','Múltiples números','Soporte dedicado') AS features,
  0 AS destacado, NULL AS badge, 3 AS orden, 1 AS activo
) AS t WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `nombre` = 'Empresa');

-- ── Bloque hero (demo-autoritativo: UPDATE si existe, INSERT si falta) ─────────
UPDATE `dom_mkt_bloques` SET `contenido` = JSON_OBJECT(
  'titulo','Envía mensajes de WhatsApp desde tus programas',
  'subtitulo','API simple y confiable para automatizar notificaciones, alertas y mensajes a tus clientes.',
  'badge','API de WhatsApp',
  'cta_texto','Solicitar demo','cta_url','#demo',
  'cta2_texto','Ver paquetes','cta2_url','#paquetes',
  'media', JSON_OBJECT('img','/assets/publico/hero-mock.svg','alt','Vista previa de mensajería automatizada')
), `activo` = 1 WHERE `pagina` = 'home' AND `clave` = 'hero';

INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'hero' AS clave, JSON_OBJECT(
  'titulo','Envía mensajes de WhatsApp desde tus programas',
  'subtitulo','API simple y confiable para automatizar notificaciones, alertas y mensajes a tus clientes.',
  'badge','API de WhatsApp',
  'cta_texto','Solicitar demo','cta_url','#demo',
  'cta2_texto','Ver paquetes','cta2_url','#paquetes',
  'media', JSON_OBJECT('img','/assets/publico/hero-mock.svg','alt','Vista previa de mensajería automatizada')
) AS contenido, 1 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'hero');

-- ── Bloque trust ──────────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'trust' AS clave, JSON_OBJECT('items', JSON_ARRAY(
  JSON_OBJECT('valor','REST API','etiqueta','Integración simple'),
  JSON_OBJECT('valor','< 5 min','etiqueta','Tiempo de setup'),
  JSON_OBJECT('valor','24/7','etiqueta','Entrega confiable')
)) AS contenido, 2 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'trust');

-- ── Bloque testimonios ────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'testimonios' AS clave, JSON_OBJECT('items', JSON_ARRAY(
  JSON_OBJECT('texto','Integramos la API en una tarde y ahora enviamos confirmaciones automáticas.','autor','María G., E-commerce'),
  JSON_OBJECT('texto','El servicio es estable y el soporte responde rápido.','autor','Carlos R., SaaS de citas'),
  JSON_OBJECT('texto','Pasamos de SMS a WhatsApp y mejoró la tasa de respuesta.','autor','Lucía M., Logística')
)) AS contenido, 4 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'testimonios');

-- ── Bloque footer ─────────────────────────────────────────────────────────────
INSERT INTO `dom_mkt_bloques` (`pagina`,`clave`,`contenido`,`orden`,`activo`)
SELECT * FROM (SELECT 'home' AS pagina, 'footer' AS clave, JSON_OBJECT(
  'columnas', JSON_ARRAY(
    JSON_OBJECT('titulo','Producto','links', JSON_ARRAY(
      JSON_OBJECT('texto','Paquetes','url','#paquetes'),
      JSON_OBJECT('texto','Solicitar demo','url','#demo')
    )),
    JSON_OBJECT('titulo','Empresa','links', JSON_ARRAY(
      JSON_OBJECT('texto','Acceder','url','/login')
    ))
  ),
  'legal','Mensajería automatizada de WhatsApp para tu negocio.'
) AS contenido, 9 AS orden, 1 AS activo) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_bloques` WHERE `pagina` = 'home' AND `clave` = 'footer');

-- ── Plantilla autoresponder (copy demo) ───────────────────────────────────────
UPDATE `dom_mkt_plantillas`
SET `asunto` = 'Gracias por tu interés en nuestra API de WhatsApp',
    `cuerpo` = 'Hola {{nombre}}, recibimos tu solicitud de demo y te contactaremos en menos de 24 horas.'
WHERE `clave` = 'lead_autoresponder';
```

- [ ] **Step 4: Add the `--marketing-demo` flag to seed.php**

In `scripts/seed.php`, after the line:

```php
$incluirCrudEngine = in_array('--crud-engine', $argv ?? [], true);
```

add:

```php
$incluirMktDemo = in_array('--marketing-demo', $argv ?? [], true);
```

Then, after this block:

```php
if ($incluirCrudEngine) {
    $archivos[] = ROOT_PATH . '/database/schema/modules/crud-engine.sql';
}
```

add:

```php
if ($incluirMktDemo) {
    $archivos[] = ROOT_PATH . '/database/schema/modules/marketing_demo.sql';
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php tests/run.php Marketing/DemoSeedTest`
Expected: PASS en los 4 tests del seed demo.

- [ ] **Step 6: Run the full marketing suite (no regressions)**

Run: `php tests/run.php Marketing`
Expected: PASS en toda la suite Marketing.

- [ ] **Step 7: Commit**

```bash
git add database/schema/modules/marketing_demo.sql scripts/seed.php tests/Marketing/DemoSeedTest.php
git commit -m "feat(marketing): seed demo WhatsApp idempotente + flag --marketing-demo"
```

---

## Verificación final (manual, opcional)

Tras todas las tareas, para ver la demo en un entorno con el módulo instalado y `modules.marketing=true`:

```bash
php scripts/seed.php --marketing-demo
php -S localhost:8000 -t public
# Abrir http://localhost:8000/ → hero WhatsApp, trust bar, 3 paquetes con toggle, testimonios, formulario, footer.
```

El color de acento (CTAs, plan destacado, checks) seguirá el color primario configurado en el tema de la app.

---

## Self-Review (autor del plan)

**1. Cobertura del spec:**
- §1 Desacople → Tasks 1–8 confinan cambios a `publico/`, assets, `LandingController`, `seed.php` y seed demo; sin código de dominio WhatsApp. ✅ (Constraint global + test de desacople preexistente `ContainerWiringTest`/decoupling se mantiene.)
- §2 Integración con tema → Task 1 (partial `lebytek_theme_vars`, `LebytekUiConfig::resolve`). ✅
- §3 Sistema visual → Task 1 (`landing.css` tokens + fuentes; `landing.js`). ✅
- §4 Secciones data-driven (hero/trust/pricing/testimonios/form/footer) → Tasks 1–7. ✅
- §5 Flujo de datos → Task 1 (controller) + Task 7 (landing orquesta). ✅
- §6 Demo idempotente WhatsApp + media SVG genérico → Task 8 + Task 1 (SVG). ✅
- §7 JS sin dominio → Task 1 (`landing.js`). ✅
- §8 Testing → tests en cada Task; suite `php tests/run.php Marketing`. ✅

**2. Placeholder scan:** Sin TBD/TODO; todo paso con código/SQL/comando concreto y salida esperada. ✅

**3. Type/nombre consistency:** Claves de bloque (`hero/trust/testimonios/footer`), formas (`{items:[...]}`, `media:{img,alt}`, `footer:{columnas,legal}`), clases CSS `ct-*`, atributos `data-monthly`/`data-annual`/`data-period`, y vars de tema (`primaryColor`, `lebytekCssVariables`) coinciden entre layout, partials, JS, controller, CSS y seed. La función `$fmt` produce `$69`/`A medida` consistente con los asserts de Task 4. ✅

Nota de orden: el footer lo crea/renderiza Task 1 (partial + layout); Tasks 2–6 crean partials independientes testeados de forma aislada; Task 7 reescribe `landing.php` para orquestarlos (los partials ya existen). Los tests originales de `PublicViewTest` permanecen verdes en cada paso.
