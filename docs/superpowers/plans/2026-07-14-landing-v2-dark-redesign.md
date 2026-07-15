# Landing v2 (Dark Redesign) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dark-themed landing variant (imported from Claude Design) that coexists with the current landing and is selected at runtime by a flag, so it can be trialed with instant rollback.

**Architecture:** New additive view/layout/partials + assets under `v2` names; `LandingController` picks the `v1` (current, untouched) or `v2` view+layout pair from an `EnvLoader` flag plus a `?landing=` query override. The same view-model (`$bloques`, `$paquetes`, `comprasHabilitadas`, UI vars) feeds both. v2 partials read the exact same data keys as their v1 counterparts; only the markup/theme differ.

**Tech Stack:** PHP 8 (strict types), `Lebytek\Framework` `ViewHelper`, vanilla JS (IntersectionObserver), self-contained CSS (no Bootstrap in v2). Custom test runner (`php tests/run.php Marketing`, `test()` + `assert_true()`).

## Global Constraints

- **Do not modify or delete** any existing v1 file: `publico/landing.php`, `publico/layout.php`, `publico/partials/_*.php`, `public/assets/publico/landing.css`, `public/assets/publico/landing.js`.
- All PHP files start with `<?php` then `declare(strict_types=1);`.
- Escape all dynamic output with `ViewHelper::e(...)`.
- Fonts: **Syne** (600/700/800) for headings, **Space Grotesk** (400/500/600) for body. Palette: bg `#05070F`, text `#F5F7FA`, accents `#25D366`→`#00E6A0`, teal `#5EEAD4`, dark-on-light `#0B1220`, muted-green `#128A50`. Light-section gradient `linear-gradient(135deg,#E1E5E7,#D2D7DA)`.
- Lead form must POST to `/lead` with `ViewHelper::csrfField()` and backend fields `nombre`, `email`, `telefono`, `mensaje` (email required). "Empresa" is optional and merged into `mensaje` client-side; **no backend changes**.
- Purchasable slugs: `starter`, `business`. Buy links: `/comprar/{slug}?ciclo=monthly|annual`, only when `comprasHabilitadas` and `precio_mensual` is numeric > 0.
- Unknown flag/override value → treat as `v1` (fail-safe).
- Tests run with: `php tests/run.php Marketing`. Partials render standalone via `ViewHelper::render('publico/partials/v2/_x', [...], '')` (empty layout).
- Reveal-on-scroll contract: each section root has `data-reveal-id="<id>"` and class `lb-reveal`; JS adds `lb-reveal--on`. Respect `prefers-reduced-motion`.
- Billing-toggle contract (shared with v1 semantics): price element carries `data-monthly` / `data-annual`; buy link carries `data-compra-monthly` / `data-compra-annual`; toggle buttons carry `data-period="monthly|annual"`.

---

### Task 1: v2 content partials — hero, trust, features, testimonios

**Files:**
- Create: `app/Presentation/Views/publico/partials/v2/_hero.php`
- Create: `app/Presentation/Views/publico/partials/v2/_trust.php`
- Create: `app/Presentation/Views/publico/partials/v2/_features.php`
- Create: `app/Presentation/Views/publico/partials/v2/_testimonios.php`
- Test: `tests/Marketing/LandingV2ViewTest.php`

**Interfaces:**
- Consumes (data keys, same as v1):
  - hero: `$hero = ['titulo','subtitulo','badge','cta_texto','cta_url','cta2_texto','cta2_url','media'=>['img','alt']]`
  - trust: `$trust = ['items'=>[['valor','etiqueta'], ...]]`
  - features: `$features = ['titulo','lead','items'=>[['icon','titulo','texto'], ...]]`
  - testimonios: `$testimonios = ['items'=>[['texto','autor'], ...]]`
- Produces: section roots with `data-reveal-id` in {`hero`,`trust`,`features`,`testimonials`} and class `lb-reveal`, consumed by `landing_v2.js` (Task 4) and orchestrated by `landing_v2.php` (Task 5). Empty data → empty string (early `return`).

- [ ] **Step 1: Write the failing test** — create `tests/Marketing/LandingV2ViewTest.php`

```php
<?php
// tests/Marketing/LandingV2ViewTest.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

test('v2 hero renderiza titulo, badge, dos CTAs y visual con reveal', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_hero', ['hero' => [
        'titulo' => 'Titulo Hero V2', 'subtitulo' => 'Sub Hero', 'badge' => 'WhatsApp Business API',
        'cta_texto' => 'Solicitar demo', 'cta_url' => '#demo',
        'cta2_texto' => 'Ver paquetes', 'cta2_url' => '#paquetes',
    ]], '');
    assert_true(str_contains($html, 'Titulo Hero V2'), 'titulo');
    assert_true(str_contains($html, 'WhatsApp Business API'), 'badge');
    assert_true(str_contains($html, 'Solicitar demo'), 'cta 1');
    assert_true(str_contains($html, 'Ver paquetes'), 'cta 2');
    assert_true(str_contains($html, 'data-reveal-id="hero"'), 'hook de reveal');
});

test('v2 hero vacío no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_hero', ['hero' => []], '');
    assert_true(trim($html) === '', 'degradación sin datos');
});

test('v2 trust renderiza métricas y escapa valores', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_trust', ['trust' => ['items' => [
        ['valor' => '10k+', 'etiqueta' => 'Mensajes al mes'],
        ['valor' => '< 5 min', 'etiqueta' => 'Demo activa'],
    ]]], '');
    assert_true(str_contains($html, '10k+'), 'valor 1');
    assert_true(str_contains($html, 'Mensajes al mes'), 'etiqueta 1');
    assert_true(str_contains($html, '&lt; 5 min'), 'valor 2 escapado');
    assert_true(str_contains($html, 'data-reveal-id="trust"'), 'hook de reveal');
});

test('v2 trust sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_trust', ['trust' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 features renderiza titulo, lead e items', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_features', ['features' => [
        'titulo' => 'Integra sin complicaciones', 'lead' => 'Conecta en minutos',
        'items' => [
            ['titulo' => 'API lista', 'texto' => 'URL y token'],
            ['titulo' => 'Automatiza', 'texto' => 'Recordatorios'],
        ],
    ]], '');
    assert_true(str_contains($html, 'Integra sin complicaciones'), 'titulo');
    assert_true(str_contains($html, 'Conecta en minutos'), 'lead');
    assert_true(str_contains($html, 'API lista'), 'item 1');
    assert_true(str_contains($html, 'Recordatorios'), 'texto item 2');
    assert_true(str_contains($html, 'data-reveal-id="features"'), 'hook de reveal');
});

test('v2 features sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_features', ['features' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 testimonios renderiza texto y autor', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => ['items' => [
        ['texto' => 'Integramos en una tarde', 'autor' => 'María G. — Retail'],
    ]]], '');
    assert_true(str_contains($html, 'Integramos en una tarde'), 'texto');
    assert_true(str_contains($html, 'María G. — Retail'), 'autor');
    assert_true(str_contains($html, 'data-reveal-id="testimonials"'), 'hook de reveal');
});

test('v2 testimonios sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => []], '');
    assert_true(trim($html) === '', 'degradación');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Marketing`
Expected: FAIL — the `publico/partials/v2/_hero` view file cannot be resolved (missing files).

- [ ] **Step 3: Create `app/Presentation/Views/publico/partials/v2/_hero.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_hero.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$hero      = is_array($hero ?? null) ? $hero : [];
$titulo    = (string) ($hero['titulo'] ?? '');
$subtitulo = (string) ($hero['subtitulo'] ?? '');
$badge     = (string) ($hero['badge'] ?? '');
$ctaTexto  = (string) ($hero['cta_texto'] ?? '');
$ctaUrl    = (string) ($hero['cta_url'] ?? '#demo');
$cta2Texto = (string) ($hero['cta2_texto'] ?? '');
$cta2Url   = (string) ($hero['cta2_url'] ?? '#paquetes');

if ($titulo === '' && $subtitulo === '') {
    return;
}
?>
<section id="inicio" data-reveal-id="hero" class="lb-reveal" style="position:relative; overflow:hidden;">
  <div style="position:absolute; top:-140px; left:-120px; width:520px; height:520px; border-radius:50%; background:radial-gradient(circle, rgba(37,211,102,0.20), transparent 70%); filter:blur(10px); pointer-events:none;"></div>
  <div style="position:absolute; bottom:-160px; right:-100px; width:480px; height:480px; border-radius:50%; background:radial-gradient(circle, rgba(94,234,212,0.14), transparent 70%); filter:blur(10px); pointer-events:none;"></div>

  <div class="lb-hero-grid" style="position:relative; max-width:1240px; margin:0 auto; padding:72px 28px 96px; display:grid; grid-template-columns:minmax(320px,1fr) minmax(360px,1fr); gap:48px; align-items:center;">
    <div>
      <?php if ($badge !== ''): ?>
        <span style="display:inline-block; font-size:12px; letter-spacing:0.14em; text-transform:uppercase; color:#5EEAD4; border:1px solid rgba(94,234,212,0.35); padding:6px 12px; border-radius:20px; margin-bottom:20px;"><?= ViewHelper::e($badge) ?></span>
      <?php endif; ?>
      <h1 style="font-family:'Space Grotesk',sans-serif; font-weight:600; font-size:clamp(34px,4.4vw,54px); line-height:1.2; margin:0 0 18px; max-width:15ch;"><?= ViewHelper::e($titulo) ?></h1>
      <?php if ($subtitulo !== ''): ?>
        <p style="font-size:17px; line-height:1.6; color:rgba(245,247,250,0.72); max-width:46ch; margin:0 0 28px;"><?= ViewHelper::e($subtitulo) ?></p>
      <?php endif; ?>
      <div style="display:flex; gap:14px; flex-wrap:wrap;">
        <?php if ($ctaTexto !== ''): ?>
          <a href="<?= ViewHelper::e($ctaUrl) ?>" style="background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:16px; padding:14px 26px; border-radius:10px;"><?= ViewHelper::e($ctaTexto) ?></a>
        <?php endif; ?>
        <?php if ($cta2Texto !== ''): ?>
          <a href="<?= ViewHelper::e($cta2Url) ?>" style="border:1px solid rgba(255,255,255,0.25); color:#F5F7FA; font-weight:500; font-size:16px; padding:14px 26px; border-radius:10px;"><?= ViewHelper::e($cta2Texto) ?></a>
        <?php endif; ?>
      </div>
    </div>

    <div class="lb-anim lb-hero-visual" style="position:relative; height:clamp(320px,38vw,460px); animation:floatY 7s ease-in-out infinite;" aria-hidden="true">
      <svg viewBox="0 0 460 400" style="position:absolute; inset:0; width:100%; height:100%; overflow:visible;">
        <path d="M230,200 Q120,80 60,60" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="1.5" stroke-dasharray="2 6"></path>
        <path d="M230,200 Q380,90 410,50" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="1.5" stroke-dasharray="2 6"></path>
        <path d="M230,200 Q100,300 60,350" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="1.5" stroke-dasharray="2 6"></path>
        <path d="M230,200 Q380,300 410,350" fill="none" stroke="rgba(255,255,255,0.14)" stroke-width="1.5" stroke-dasharray="2 6"></path>
      </svg>
      <div class="lb-anim" style="position:absolute; left:50%; top:50%; width:112px; height:112px; margin:-56px 0 0 -56px; border-radius:50%; background:radial-gradient(circle at 35% 30%, rgba(255,255,255,0.08), rgba(11,18,32,0.9)); border:1px solid rgba(37,211,102,0.5); display:flex; align-items:center; justify-content:center; animation:hubPulse 3s ease-in-out infinite;">
        <div style="width:14px; height:14px; border-radius:50%; background:#00E6A0;"></div>
      </div>
      <div class="lb-anim" style="position:absolute; width:7px; height:7px; border-radius:50%; background:#00E6A0; box-shadow:0 0 8px 2px rgba(0,230,160,0.6); offset-path:path('M230,200 Q120,80 60,60'); animation:dotMove 3.4s linear infinite 0s;"></div>
      <div class="lb-anim" style="position:absolute; width:7px; height:7px; border-radius:50%; background:#00E6A0; box-shadow:0 0 8px 2px rgba(0,230,160,0.6); offset-path:path('M230,200 Q380,90 410,50'); animation:dotMove 3.4s linear infinite 0.9s;"></div>
      <div class="lb-anim" style="position:absolute; width:7px; height:7px; border-radius:50%; background:#00E6A0; box-shadow:0 0 8px 2px rgba(0,230,160,0.6); offset-path:path('M230,200 Q100,300 60,350'); animation:dotMove 3.4s linear infinite 1.8s;"></div>
      <div class="lb-anim" style="position:absolute; width:7px; height:7px; border-radius:50%; background:#00E6A0; box-shadow:0 0 8px 2px rgba(0,230,160,0.6); offset-path:path('M230,200 Q380,300 410,350'); animation:dotMove 3.4s linear infinite 2.6s;"></div>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Create `app/Presentation/Views/publico/partials/v2/_trust.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_trust.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$items = is_array($trust['items'] ?? null) ? $trust['items'] : [];
if ($items === []) {
    return;
}
?>
<section data-reveal-id="trust" class="lb-reveal" aria-label="Indicadores de confianza" style="background:linear-gradient(135deg,#E1E5E7,#D2D7DA); color:#0B1220; border-top:1px solid #25D366; border-bottom:1px solid #25D366;">
  <div class="lb-trust" style="max-width:1240px; margin:0 auto; padding:36px 28px; display:flex; flex-wrap:wrap; gap:40px; justify-content:space-between; align-items:center;">
    <?php foreach ($items as $it): ?>
      <div>
        <div style="font-family:'Syne',sans-serif; font-size:28px; font-weight:700; color:#128A50;"><?= ViewHelper::e((string) ($it['valor'] ?? '')) ?></div>
        <div style="font-size:12px; color:rgba(11,18,32,0.55); text-transform:uppercase; letter-spacing:0.08em;"><?= ViewHelper::e((string) ($it['etiqueta'] ?? '')) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
```

- [ ] **Step 5: Create `app/Presentation/Views/publico/partials/v2/_features.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_features.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$features = is_array($features ?? null) ? $features : [];
$items    = is_array($features['items'] ?? null) ? $features['items'] : [];
$titulo   = (string) ($features['titulo'] ?? 'Funcionalidades');
$lead     = (string) ($features['lead'] ?? '');

if ($items === []) {
    return;
}
?>
<section id="funciones" data-reveal-id="features" class="lb-reveal">
  <div style="max-width:1240px; margin:0 auto; padding:80px 28px;">
    <h2 style="font-family:'Syne',sans-serif; font-size:clamp(28px,3vw,38px); font-weight:700; max-width:20ch; margin:0 0 12px;"><?= ViewHelper::e($titulo) ?></h2>
    <?php if ($lead !== ''): ?>
      <p style="color:rgba(245,247,250,0.65); max-width:56ch; font-size:16px; margin:0 0 40px;"><?= ViewHelper::e($lead) ?></p>
    <?php endif; ?>
    <div class="lb-features-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px;">
      <?php foreach ($items as $item): ?>
        <div style="border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.02); border-radius:12px; padding:28px;">
          <div style="width:24px; height:24px; border-radius:6px; background:linear-gradient(135deg,#25D366,#00E6A0);" aria-hidden="true"></div>
          <div style="font-family:'Syne',sans-serif; font-weight:700; font-size:18px; margin-top:14px;"><?= ViewHelper::e((string) ($item['titulo'] ?? '')) ?></div>
          <p style="font-size:14px; color:rgba(245,247,250,0.65); margin:8px 0 0;"><?= ViewHelper::e((string) ($item['texto'] ?? '')) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 6: Create `app/Presentation/Views/publico/partials/v2/_testimonios.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_testimonios.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$items = is_array($testimonios['items'] ?? null) ? $testimonios['items'] : [];
if ($items === []) {
    return;
}
?>
<section id="resenas" data-reveal-id="testimonials" class="lb-reveal">
  <div style="max-width:1240px; margin:0 auto; padding:80px 28px;">
    <h2 style="font-family:'Syne',sans-serif; font-size:clamp(28px,3vw,38px); font-weight:700; margin:0 0 40px;">Lo que dicen nuestros clientes</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px,1fr)); gap:32px;">
      <?php foreach ($items as $t): ?>
        <div style="border-left:2px solid #00E6A0; padding-left:20px;">
          <p style="font-size:16px; line-height:1.6; color:rgba(245,247,250,0.85); margin:0 0 12px;">&ldquo;<?= ViewHelper::e((string) ($t['texto'] ?? '')) ?>&rdquo;</p>
          <div style="font-size:13px; color:rgba(245,247,250,0.5);"><?= ViewHelper::e((string) ($t['autor'] ?? '')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — the 8 new `v2` hero/trust/features/testimonios tests pass; existing Marketing tests still pass.

- [ ] **Step 8: Commit**

```bash
git add app/Presentation/Views/publico/partials/v2/_hero.php app/Presentation/Views/publico/partials/v2/_trust.php app/Presentation/Views/publico/partials/v2/_features.php app/Presentation/Views/publico/partials/v2/_testimonios.php tests/Marketing/LandingV2ViewTest.php
git commit -m "feat(marketing): v2 landing content partials (hero/trust/features/testimonios)"
```

---

### Task 2: v2 pricing + faq partials (JS-contract sections)

**Files:**
- Create: `app/Presentation/Views/publico/partials/v2/_pricing.php`
- Create: `app/Presentation/Views/publico/partials/v2/_faq.php`
- Test: `tests/Marketing/LandingV2ViewTest.php` (append)

**Interfaces:**
- Consumes:
  - pricing: `$paquetes = [['nombre','precio_mensual','precio_anual','features'(array|json),'destacado','badge','slug'], ...]`, `$comprasHabilitadas` (bool)
  - faq: `$faq = ['titulo','lead','items'=>[['pregunta','respuesta'], ...]]`
- Produces: pricing price elements with `data-monthly`/`data-annual`, buy links with `data-compra-monthly`/`data-compra-annual`, billing buttons with `data-period`; faq toggles with `data-faq-toggle` and panels `.lb-faq-panel`. All consumed by `landing_v2.js` (Task 4).

- [ ] **Step 1: Write the failing tests** — append to `tests/Marketing/LandingV2ViewTest.php`

```php
test('v2 pricing renderiza toggle, precios data-*, destacado y features (array y JSON)', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['comprasHabilitadas' => true, 'paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199.00', 'precio_anual' => '1759.00',
         'features' => ['1 instancia', 'Hasta 5000 mensajes']],
        ['nombre' => 'Business', 'slug' => 'business', 'precio_mensual' => '4499.00', 'precio_anual' => '3599.00',
         'destacado' => 1, 'badge' => 'Más popular', 'features' => '["Hasta 3 instancias"]'],
        ['nombre' => 'Enterprise', 'slug' => 'empresa', 'precio_mensual' => '', 'precio_anual' => '',
         'features' => ['A medida']],
    ]], '');
    assert_true(str_contains($html, 'data-period="annual"'), 'toggle anual');
    assert_true(str_contains($html, 'data-monthly="$2,199"'), 'precio mensual formateado');
    assert_true(str_contains($html, 'data-annual="$1,759"'), 'precio anual formateado');
    assert_true(str_contains($html, 'Más popular'), 'badge destacado');
    assert_true(str_contains($html, 'Hasta 5000 mensajes'), 'feature de array');
    assert_true(str_contains($html, 'Hasta 3 instancias'), 'feature desde JSON');
    assert_true(str_contains($html, 'A medida'), 'precio vacío');
    assert_true(str_contains($html, '/comprar/starter?ciclo=monthly'), 'link compra starter');
    assert_true(str_contains($html, 'data-compra-annual="/comprar/business?ciclo=annual"'), 'link compra anual business');
    assert_true(!str_contains($html, '/comprar/empresa'), 'enterprise no comprable');
});

test('v2 pricing sin compras habilitadas no muestra botón comprar', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['comprasHabilitadas' => false, 'paquetes' => [
        ['nombre' => 'Starter', 'slug' => 'starter', 'precio_mensual' => '2199.00', 'precio_anual' => '1759.00', 'features' => ['x']],
    ]], '');
    assert_true(!str_contains($html, '/comprar/starter'), 'sin link de compra');
    assert_true(str_contains($html, 'Solicitar demo'), 'mantiene CTA demo');
});

test('v2 pricing sin paquetes no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_pricing', ['paquetes' => []], '');
    assert_true(trim($html) === '', 'degradación');
});

test('v2 faq renderiza preguntas, respuestas y toggles', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_faq', ['faq' => [
        'titulo' => 'Preguntas frecuentes', 'lead' => 'Respuestas rápidas',
        'items' => [
            ['pregunta' => '¿Qué es la API?', 'respuesta' => 'Envía mensajes desde tu sistema.'],
            ['pregunta' => '¿Cuánto tarda?', 'respuesta' => 'Minutos.'],
        ],
    ]], '');
    assert_true(str_contains($html, 'Preguntas frecuentes'), 'titulo');
    assert_true(str_contains($html, 'Respuestas rápidas'), 'lead');
    assert_true(str_contains($html, '¿Qué es la API?'), 'pregunta');
    assert_true(str_contains($html, 'Envía mensajes desde tu sistema.'), 'respuesta');
    assert_true(str_contains($html, 'data-faq-toggle'), 'hook de toggle');
    assert_true(str_contains($html, 'lb-faq-panel'), 'panel');
    assert_true(str_contains($html, 'data-reveal-id="faq"'), 'hook de reveal');
});

test('v2 faq sin items no emite sección', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_faq', ['faq' => []], '');
    assert_true(trim($html) === '', 'degradación');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php tests/run.php Marketing`
Expected: FAIL — `publico/partials/v2/_pricing` / `_faq` view files missing.

- [ ] **Step 3: Create `app/Presentation/Views/publico/partials/v2/_pricing.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_pricing.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$paquetes = is_array($paquetes ?? null) ? $paquetes : [];
$comprasHabilitadas = ! empty($comprasHabilitadas);
if ($paquetes === []) {
    return;
}

$purchasableSlugs = ['starter', 'business'];

$fmt = static function (string $v): string {
    if ($v === '') {
        return 'A medida';
    }
    $n = (float) $v;
    return '$' . rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
};
$isNumericPrice = static function (mixed $v): bool {
    return $v !== null && $v !== '' && is_numeric($v) && (float) $v > 0;
};
?>
<section id="paquetes" data-reveal-id="pricing" class="lb-reveal" style="background:linear-gradient(135deg,#E1E5E7,#D2D7DA); color:#0B1220; border-top:1px solid #25D366; border-bottom:1px solid #25D366;">
  <div style="max-width:1240px; margin:0 auto; padding:80px 28px;">
    <h2 style="font-family:'Syne',sans-serif; font-size:clamp(28px,3vw,38px); font-weight:700; margin:0 0 8px;">Paquetes</h2>
    <p style="color:rgba(11,18,32,0.65); margin:0 0 32px;">Elige el plan que se adapta al volumen de tu negocio.</p>

    <div class="lb-billing" role="group" aria-label="Periodo de facturación" style="display:inline-flex; border:1px solid rgba(11,18,32,0.15); border-radius:10px; overflow:hidden; margin-bottom:36px;">
      <button type="button" class="lb-billing-btn is-active" data-period="monthly" aria-pressed="true">Mensual</button>
      <button type="button" class="lb-billing-btn" data-period="annual" aria-pressed="false">Anual</button>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px;">
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
          $slug       = (string) ($p['slug'] ?? '');
          $canBuy     = $comprasHabilitadas
              && in_array($slug, $purchasableSlugs, true)
              && $isNumericPrice($p['precio_mensual'] ?? null);
          $isEnterprise = $slug === 'empresa' || ! $isNumericPrice($p['precio_mensual'] ?? null);
          $cardBg     = $featured ? 'rgba(37,211,102,0.1)' : 'rgba(11,18,32,0.03)';
          $cardBorder = $featured ? '1px solid #25D366' : '1px solid rgba(11,18,32,0.1)';
        ?>
        <div style="position:relative; padding:32px; border-radius:14px; display:flex; flex-direction:column; gap:14px; background:<?= $cardBg ?>; border:<?= $cardBorder ?>;">
          <?php if (!empty($p['badge'])): ?>
            <span style="position:absolute; top:20px; right:20px; font-size:11px; font-weight:600; color:#05070F; background:linear-gradient(135deg,#25D366,#00E6A0); padding:4px 10px; border-radius:20px;"><?= ViewHelper::e((string) $p['badge']) ?></span>
          <?php endif; ?>
          <div style="font-family:'Syne',sans-serif; font-weight:700; font-size:20px;"><?= ViewHelper::e((string) ($p['nombre'] ?? '')) ?></div>
          <div data-monthly="<?= ViewHelper::e($mensualTxt) ?>" data-annual="<?= ViewHelper::e($anualTxt) ?>">
            <span class="lb-price-amount" style="font-family:'Syne',sans-serif; font-size:34px; font-weight:700;"><?= ViewHelper::e($mensualTxt) ?></span><?php if ($numeric): ?><span style="font-size:13px; color:rgba(11,18,32,0.55);"> /mes</span><?php endif; ?>
          </div>
          <?php if ($features !== []): ?>
            <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:9px; flex:1;">
              <?php foreach ($features as $f): ?>
                <li style="font-size:13px; color:rgba(11,18,32,0.75); padding-left:18px; position:relative;"><span style="position:absolute; left:0; color:#128A50;">✓</span><?= ViewHelper::e((string) $f) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a href="#demo" style="text-align:center; background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:15px; padding:12px; border-radius:8px;">Solicitar demo</a>
          <?php if ($canBuy): ?>
            <a href="/comprar/<?= ViewHelper::e($slug) ?>?ciclo=monthly"
               class="lb-compra"
               data-compra-monthly="/comprar/<?= ViewHelper::e($slug) ?>?ciclo=monthly"
               data-compra-annual="/comprar/<?= ViewHelper::e($slug) ?>?ciclo=annual"
               style="text-align:center; border:1px solid #25D366; color:#0B1220; font-weight:600; font-size:15px; padding:12px; border-radius:8px;">Comprar ya</a>
          <?php elseif ($comprasHabilitadas && $isEnterprise): ?>
            <a href="#demo" style="text-align:center; border:1px solid rgba(11,18,32,0.25); color:#0B1220; font-weight:500; font-size:15px; padding:12px; border-radius:8px;">Contactar</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Create `app/Presentation/Views/publico/partials/v2/_faq.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_faq.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$faq    = is_array($faq ?? null) ? $faq : [];
$items  = is_array($faq['items'] ?? null) ? $faq['items'] : [];
$titulo = (string) ($faq['titulo'] ?? 'Preguntas frecuentes');
$lead   = (string) ($faq['lead'] ?? '');

if ($items === []) {
    return;
}
?>
<section id="faq" data-reveal-id="faq" class="lb-reveal" style="background:linear-gradient(135deg,#E1E5E7,#D2D7DA); color:#0B1220; border-top:1px solid #25D366;">
  <div style="max-width:800px; margin:0 auto; padding:80px 28px;">
    <h2 style="font-family:'Syne',sans-serif; font-size:clamp(28px,3vw,38px); font-weight:700; margin:0 0 8px;"><?= ViewHelper::e($titulo) ?></h2>
    <?php if ($lead !== ''): ?>
      <p style="color:rgba(11,18,32,0.65); margin:0 0 32px;"><?= ViewHelper::e($lead) ?></p>
    <?php endif; ?>
    <div style="display:flex; flex-direction:column;">
      <?php foreach ($items as $i => $item): ?>
        <?php
          $pregunta  = trim((string) ($item['pregunta'] ?? ''));
          $respuesta = trim((string) ($item['respuesta'] ?? ''));
          if ($pregunta === '') {
              continue;
          }
        ?>
        <div style="border-bottom:1px solid rgba(11,18,32,0.12);">
          <button type="button" data-faq-toggle style="width:100%; text-align:left; background:none; border:none; cursor:pointer; padding:18px 0; display:flex; justify-content:space-between; align-items:center; gap:16px; font-family:'Syne',sans-serif; font-weight:600; font-size:17px; color:#0B1220;">
            <?= ViewHelper::e($pregunta) ?>
            <span class="lb-faq-icon" style="display:inline-block; font-size:20px; color:#128A50; transition:transform .25s ease;">+</span>
          </button>
          <div class="lb-faq-panel" style="max-height:0; overflow:hidden; transition:max-height .3s ease;">
            <p style="font-size:14px; color:rgba(11,18,32,0.65); padding-bottom:18px; margin:0;">
              <?php if ($respuesta !== ''): ?><?= nl2br(ViewHelper::e($respuesta)) ?><?php else: ?><span style="color:rgba(11,18,32,0.45);">Respuesta pendiente.</span><?php endif; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — the 5 new pricing/faq tests pass; existing tests still green.

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/publico/partials/v2/_pricing.php app/Presentation/Views/publico/partials/v2/_faq.php tests/Marketing/LandingV2ViewTest.php
git commit -m "feat(marketing): v2 pricing and faq partials with JS contracts"
```

---

### Task 3: v2 demo/lead form partial (real POST /lead)

**Files:**
- Create: `app/Presentation/Views/publico/partials/v2/_lead_form.php`
- Test: `tests/Marketing/LandingV2ViewTest.php` (append)

**Interfaces:**
- Consumes: optional `$flashAll` (falls back to `Session::flashAll()`), like v1 `_lead_form`.
- Produces: a real `<form method="POST" action="/lead">` with CSRF and inputs `nombre`, `email`, `telefono`, `mensaje`, plus optional `empresa` input carrying `data-empresa-merge` (merged into `mensaje` by `landing_v2.js`, Task 4).

- [ ] **Step 1: Write the failing tests** — append to `tests/Marketing/LandingV2ViewTest.php`

```php
test('v2 lead form postea a /lead con CSRF y campos requeridos', function (): void {
    $html = ViewHelper::render('publico/partials/v2/_lead_form', [], '');
    assert_true(str_contains($html, 'action="/lead"'), 'postea a /lead');
    assert_true(str_contains($html, 'method="POST"'), 'método POST');
    assert_true(str_contains($html, 'name="nombre"'), 'campo nombre');
    assert_true(str_contains($html, 'name="email"'), 'campo email');
    assert_true(str_contains($html, 'name="telefono"'), 'campo teléfono');
    assert_true(str_contains($html, 'name="mensaje"'), 'campo mensaje');
    assert_true(str_contains($html, 'data-empresa-merge'), 'campo empresa opcional para merge');
    assert_true(str_contains($html, 'csrf'), 'incluye token CSRF');
    assert_true(str_contains($html, 'id="demo"'), 'ancla demo');
});

test('v2 lead form muestra flash de éxito y error', function (): void {
    $ok  = ViewHelper::render('publico/partials/v2/_lead_form', ['flashAll' => ['success' => '¡Gracias!']], '');
    assert_true(str_contains($ok, '¡Gracias!'), 'muestra éxito');
    $err = ViewHelper::render('publico/partials/v2/_lead_form', ['flashAll' => ['error' => 'Falló algo']], '');
    assert_true(str_contains($err, 'Falló algo'), 'muestra error');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php tests/run.php Marketing`
Expected: FAIL — `publico/partials/v2/_lead_form` view file missing.

- [ ] **Step 3: Create `app/Presentation/Views/publico/partials/v2/_lead_form.php`**

```php
<?php
// app/Presentation/Views/publico/partials/v2/_lead_form.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;

$flash = $flashAll ?? Session::flashAll();
$flash = is_array($flash) ? $flash : [];

$inputStyle = 'width:100%; box-sizing:border-box; padding:11px 14px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#F5F7FA; font:inherit; font-size:14px;';
$labelStyle = 'display:block; font-size:12px; color:rgba(245,247,250,0.6); margin-bottom:6px;';
?>
<section id="demo" data-reveal-id="cta" class="lb-reveal" style="background:rgba(255,255,255,0.02); border-top:1px solid #25D366;">
  <div style="max-width:640px; margin:0 auto; padding:80px 28px;">
    <div style="border:1px solid rgba(255,255,255,0.75); box-shadow:0 0 32px rgba(255,255,255,0.18), 0 0 60px rgba(255,255,255,0.08); border-radius:16px; padding:36px; background:rgba(255,255,255,0.04);">
      <h2 style="font-family:'Syne',sans-serif; font-size:clamp(24px,2.6vw,30px); font-weight:700; margin:0 0 8px;">Solicita una demo</h2>
      <p style="color:rgba(245,247,250,0.65); margin:0 0 24px;">Cuéntanos sobre tu proyecto y te contactamos pronto.</p>

      <?php foreach ($flash as $tipo => $msg): ?>
        <?php if (in_array($tipo, ['success', 'error'], true)): ?>
          <div style="padding:12px 16px; margin-bottom:16px; border-radius:8px; font-size:14px; border:1px solid <?= $tipo === 'success' ? '#00E6A0' : '#ff6b6b' ?>; color:<?= $tipo === 'success' ? '#00E6A0' : '#ff9b9b' ?>;">
            <?= ViewHelper::e(is_array($msg) ? implode(' ', $msg) : (string) $msg) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <form method="POST" action="/lead" data-lead-form style="display:flex; flex-direction:column; gap:14px;">
        <?= ViewHelper::csrfField() ?>
        <div><label style="<?= $labelStyle ?>">Nombre</label><input type="text" name="nombre" placeholder="Tu nombre" required style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Correo</label><input type="email" name="email" placeholder="tu@correo.com" required style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Empresa (opcional)</label><input type="text" data-empresa-merge placeholder="Nombre de tu negocio" style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">WhatsApp / Teléfono</label><input type="tel" name="telefono" placeholder="55 0000 0000" style="<?= $inputStyle ?>"></div>
        <div><label style="<?= $labelStyle ?>">Mensaje</label><textarea name="mensaje" placeholder="Cuéntanos qué necesitas automatizar" style="<?= $inputStyle ?> min-height:90px; resize:vertical;"></textarea></div>
        <button type="submit" style="background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:15px; padding:13px; border:none; border-radius:8px; cursor:pointer;">Enviar</button>
      </form>
    </div>
  </div>
</section>
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — both lead-form tests pass; existing tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Presentation/Views/publico/partials/v2/_lead_form.php tests/Marketing/LandingV2ViewTest.php
git commit -m "feat(marketing): v2 demo form wired to real POST /lead"
```

---

### Task 4: v2 assets (CSS keyframes/responsive + vanilla JS behaviors)

**Files:**
- Create: `public/assets/publico/landing_v2.css`
- Create: `public/assets/publico/landing_v2.js`
- Test: `tests/Marketing/LandingV2AssetsTest.php`

**Interfaces:**
- Consumes (DOM contracts from Tasks 1–3): `[data-reveal-id]`.`lb-reveal`, `.lb-billing-btn[data-period]`, `[data-monthly]`/`[data-annual]`, `.lb-compra[data-compra-monthly]`/`[data-compra-annual]`, `[data-faq-toggle]` + `.lb-faq-panel` + `.lb-faq-icon`, `form[data-lead-form]` + `[data-empresa-merge]` + `textarea[name="mensaje"]`.
- Produces: `/assets/publico/landing_v2.css` and `/assets/publico/landing_v2.js` referenced by `layout_v2.php` (Task 5).

- [ ] **Step 1: Write the failing test** — create `tests/Marketing/LandingV2AssetsTest.php`

```php
<?php
// tests/Marketing/LandingV2AssetsTest.php
declare(strict_types=1);

test('landing_v2.css existe con keyframes, breakpoints y clases de reveal/billing', function (): void {
    $css = file_get_contents(ROOT_PATH . '/public/assets/publico/landing_v2.css');
    assert_true($css !== false, 'archivo existe');
    foreach (['@keyframes dotMove', '@keyframes hubPulse', '@keyframes floatY', '@keyframes drift',
              'max-width: 860px', 'max-width: 560px', 'prefers-reduced-motion',
              '.lb-reveal', '.lb-reveal--on', '.lb-billing-btn'] as $needle) {
        assert_true(str_contains($css, $needle), "css contiene {$needle}");
    }
});

test('landing_v2.js existe con reveal, acordeón, billing y merge de empresa', function (): void {
    $js = file_get_contents(ROOT_PATH . '/public/assets/publico/landing_v2.js');
    assert_true($js !== false, 'archivo existe');
    foreach (['IntersectionObserver', 'data-reveal-id', 'lb-reveal--on',
              'data-faq-toggle', 'lb-faq-panel',
              'data-period', 'data-monthly', 'data-compra-', 'data-empresa-merge'] as $needle) {
        assert_true(str_contains($js, $needle), "js referencia {$needle}");
    }
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Marketing`
Expected: FAIL — `landing_v2.css` / `landing_v2.js` do not exist (`file_get_contents` returns false).

- [ ] **Step 3: Create `public/assets/publico/landing_v2.css`**

```css
/* public/assets/publico/landing_v2.css — Landing v2 (dark redesign) */
body { margin: 0; background: #05070F; color: #F5F7FA; font-family: 'Space Grotesk', system-ui, sans-serif; }
a { color: #00E6A0; text-decoration: none; }
a:hover { color: #5EEAD4; }
::selection { background: rgba(37,211,102,0.3); }

@keyframes dotMove { 0% { offset-distance: 0%; opacity: 0; } 8% { opacity: 1; } 90% { opacity: 1; } 100% { offset-distance: 100%; opacity: 0; } }
@keyframes hubPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(37,211,102,0.35), 0 0 40px 10px rgba(37,211,102,0.15); } 50% { box-shadow: 0 0 0 14px rgba(37,211,102,0), 0 0 60px 18px rgba(37,211,102,0.28); } }
@keyframes floatY { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
@keyframes drift { 0% { transform: translate(0,0); opacity: 0.25; } 50% { opacity: 0.6; } 100% { transform: translate(6px,-14px); opacity: 0.25; } }

.lb-reveal { opacity: 0; transform: translateY(28px); transition: opacity .7s ease, transform .7s ease; }
.lb-reveal--on { opacity: 1; transform: none; }

.lb-billing-btn { padding: 9px 18px; font-size: 14px; cursor: pointer; background: transparent; color: #0B1220; border: none; }
.lb-billing-btn + .lb-billing-btn { border-left: 1px solid rgba(11,18,32,0.15); }
.lb-billing-btn.is-active { background: linear-gradient(135deg,#25D366,#00E6A0); color: #05070F; }

@media (prefers-reduced-motion: reduce) {
  .lb-anim { animation: none !important; }
  .lb-reveal { opacity: 1; transform: none; transition: none; }
}

@media (max-width: 860px) {
  .lb-hero-grid { grid-template-columns: 1fr !important; padding-top: 48px !important; padding-bottom: 56px !important; }
  .lb-hero-visual { height: 300px !important; order: -1; }
  .lb-features-grid { grid-template-columns: repeat(2, minmax(150px,1fr)) !important; }
  .lb-nav { padding: 12px 18px !important; gap: 12px !important; }
  .lb-nav-link { display: none !important; }
}
@media (max-width: 560px) {
  .lb-features-grid { grid-template-columns: 1fr !important; }
  .lb-trust { gap: 22px !important; justify-content: space-between !important; }
  .lb-trust > div { flex: 1 1 40%; }
}
```

- [ ] **Step 4: Create `public/assets/publico/landing_v2.js`**

```javascript
/* public/assets/publico/landing_v2.js — Landing v2 behaviors (vanilla) */
(function () {
  'use strict';

  // 1) Scroll reveal
  var revealEls = document.querySelectorAll('[data-reveal-id].lb-reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('lb-reveal--on');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach(function (el) { obs.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('lb-reveal--on'); });
  }

  // 2) FAQ accordion (single open)
  var faqButtons = document.querySelectorAll('[data-faq-toggle]');
  faqButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.parentElement.querySelector('.lb-faq-panel');
      var icon = btn.querySelector('.lb-faq-icon');
      var isOpen = panel && panel.style.maxHeight && panel.style.maxHeight !== '0px';
      faqButtons.forEach(function (other) {
        var p = other.parentElement.querySelector('.lb-faq-panel');
        var ic = other.querySelector('.lb-faq-icon');
        if (p) { p.style.maxHeight = '0px'; }
        if (ic) { ic.style.transform = 'rotate(0deg)'; }
      });
      if (!isOpen && panel) {
        panel.style.maxHeight = panel.scrollHeight + 'px';
        if (icon) { icon.style.transform = 'rotate(45deg)'; }
      }
    });
  });

  // 3) Billing toggle (monthly / annual)
  var billingBtns = document.querySelectorAll('.lb-billing-btn[data-period]');
  function applyPeriod(period) {
    billingBtns.forEach(function (b) {
      var active = b.getAttribute('data-period') === period;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-monthly][data-annual]').forEach(function (priceEl) {
      var amount = priceEl.querySelector('.lb-price-amount');
      if (amount) { amount.textContent = priceEl.getAttribute('data-' + period); }
    });
    document.querySelectorAll('.lb-compra[data-compra-' + period + ']').forEach(function (link) {
      link.setAttribute('href', link.getAttribute('data-compra-' + period));
    });
  }
  billingBtns.forEach(function (b) {
    b.addEventListener('click', function () { applyPeriod(b.getAttribute('data-period')); });
  });

  // 4) Merge optional "Empresa" into mensaje on submit
  var leadForm = document.querySelector('form[data-lead-form]');
  if (leadForm) {
    leadForm.addEventListener('submit', function () {
      var empresa = leadForm.querySelector('[data-empresa-merge]');
      var mensaje = leadForm.querySelector('textarea[name="mensaje"]');
      if (empresa && empresa.value.trim() && mensaje) {
        var prefix = 'Empresa: ' + empresa.value.trim();
        mensaje.value = mensaje.value.trim() ? (prefix + '\n' + mensaje.value) : prefix;
      }
    });
  }
})();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — both asset tests pass; existing tests green.

- [ ] **Step 6: Commit**

```bash
git add public/assets/publico/landing_v2.css public/assets/publico/landing_v2.js tests/Marketing/LandingV2AssetsTest.php
git commit -m "feat(marketing): v2 landing assets (reveal, faq, billing toggle, empresa merge)"
```

---

### Task 5: v2 layout shell + landing orchestrator (full-page integration)

**Files:**
- Create: `app/Presentation/Views/publico/layout_v2.php`
- Create: `app/Presentation/Views/publico/landing_v2.php`
- Test: `tests/Marketing/LandingV2ViewTest.php` (append)

**Interfaces:**
- Consumes: `$content` (injected by `ViewHelper::render` layout mechanism), `$empresaNombre`, `$empresaLogo`, `$pageTitle`, `$metaDescription`, `$bloques`, `$paquetes`, `$comprasHabilitadas`.
- Produces: full dark HTML document linking `/assets/publico/landing_v2.css` and `/assets/publico/landing_v2.js`, Syne + Space Grotesk fonts, glass nav, green-bordered footer. `landing_v2.php` echoes the Task 1–3 partials in order.

- [ ] **Step 1: Write the failing tests** — append to `tests/Marketing/LandingV2ViewTest.php`

```php
test('layout_v2 renderiza documento completo, fuentes y assets v2', function (): void {
    $html = ViewHelper::render('publico/landing_v2', [
        'empresaNombre' => 'ACME Demo', 'empresaLogo' => '',
        'bloques' => ['hero' => ['titulo' => 'Hero V2 Full', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo']],
        'paquetes' => [],
    ], 'publico/layout_v2');
    assert_true(str_contains($html, '<!DOCTYPE html>'), 'documento completo');
    assert_true(str_contains($html, 'ACME Demo'), 'nombre de empresa en nav');
    assert_true(str_contains($html, 'Hero V2 Full'), 'inyecta contenido hero');
    assert_true(str_contains($html, '/assets/publico/landing_v2.css'), 'enlaza css v2');
    assert_true(str_contains($html, '/assets/publico/landing_v2.js'), 'enlaza js v2');
    assert_true(str_contains($html, 'family=Syne'), 'carga fuente Syne');
    assert_true(str_contains($html, 'family=Space+Grotesk'), 'carga fuente Space Grotesk');
    assert_true(!str_contains($html, 'landing.css"'), 'no enlaza el css v1');
});

test('landing_v2 integra todas las secciones desde bloques y paquetes', function (): void {
    $html = ViewHelper::render('publico/landing_v2', [
        'empresaNombre' => 'ACME', 'empresaLogo' => '',
        'comprasHabilitadas' => true,
        'bloques' => [
            'hero'        => ['titulo' => 'Hero Integrado V2', 'subtitulo' => 'Sub', 'cta_texto' => 'Demo', 'cta_url' => '#demo'],
            'trust'       => ['items' => [['valor' => '10k+', 'etiqueta' => 'Mensajes al mes']]],
            'features'    => ['titulo' => 'Funciones', 'items' => [['titulo' => 'API lista', 'texto' => 'URL y token']]],
            'testimonios' => ['items' => [['texto' => 'Excelente', 'autor' => 'Cliente X']]],
            'faq'         => ['titulo' => 'Preguntas frecuentes', 'items' => [['pregunta' => '¿Cómo empiezo?', 'respuesta' => 'Solicita una demo.']]],
        ],
        'paquetes' => [
            ['nombre' => 'Business', 'slug' => 'business', 'precio_mensual' => '4499.00', 'precio_anual' => '3599.00', 'destacado' => 1, 'features' => ['Hasta 3 instancias']],
        ],
    ], 'publico/layout_v2');
    assert_true(str_contains($html, 'Hero Integrado V2'), 'sección hero');
    assert_true(str_contains($html, '10k+'), 'sección trust');
    assert_true(str_contains($html, 'API lista'), 'sección features');
    assert_true(str_contains($html, 'Excelente'), 'sección testimonios');
    assert_true(str_contains($html, 'id="paquetes"'), 'sección pricing');
    assert_true(str_contains($html, 'id="faq"'), 'sección faq');
    assert_true(str_contains($html, '¿Cómo empiezo?'), 'pregunta faq');
    assert_true(str_contains($html, 'action="/lead"'), 'sección formulario');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php tests/run.php Marketing`
Expected: FAIL — `publico/landing_v2` / `publico/layout_v2` view files missing.

- [ ] **Step 3: Create `app/Presentation/Views/publico/landing_v2.php`**

```php
<?php
// app/Presentation/Views/publico/landing_v2.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$bloques  = is_array($bloques ?? null) ? $bloques : [];
$paquetes = is_array($paquetes ?? null) ? $paquetes : [];

echo ViewHelper::render('publico/partials/v2/_hero',        ['hero'        => $bloques['hero']        ?? []], '');
echo ViewHelper::render('publico/partials/v2/_trust',       ['trust'       => $bloques['trust']       ?? []], '');
echo ViewHelper::render('publico/partials/v2/_features',    ['features'    => $bloques['features']    ?? []], '');
echo ViewHelper::render('publico/partials/v2/_pricing',     ['paquetes' => $paquetes, 'comprasHabilitadas' => ! empty($comprasHabilitadas)], '');
echo ViewHelper::render('publico/partials/v2/_testimonios', ['testimonios' => $bloques['testimonios'] ?? []], '');
echo ViewHelper::render('publico/partials/v2/_faq',         ['faq'         => $bloques['faq']         ?? []], '');
echo ViewHelper::render('publico/partials/v2/_lead_form',   [], '');
```

- [ ] **Step 4: Create `app/Presentation/Views/publico/layout_v2.php`**

```php
<?php
// app/Presentation/Views/publico/layout_v2.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($pageTitle ?? 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas') ?></title>
    <meta name="description" content="<?= ViewHelper::e($metaDescription ?? 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Demo inmediata.') ?>">
    <meta name="theme-color" content="#05070F">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="/assets/publico/landing_v2.css" rel="stylesheet">
</head>
<body>
    <header class="lb-nav" style="position:sticky; top:0; z-index:30; display:flex; align-items:center; gap:20px; padding:16px 28px; background:rgba(11,18,32,0.65); backdrop-filter:blur(10px); border-bottom:1px solid rgba(255,255,255,0.08); flex-wrap:wrap;">
      <a href="/" style="display:flex; align-items:center; gap:10px; margin-right:auto;">
        <?php if ($empresaLogo !== ''): ?>
          <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="22">
        <?php else: ?>
          <span style="width:22px; height:22px; border-radius:6px; background:linear-gradient(135deg,#25D366,#00E6A0);"></span>
        <?php endif; ?>
        <span style="font-family:'Syne',sans-serif; font-weight:700; font-size:18px; color:#F5F7FA;"><?= ViewHelper::e($empresaNombre) ?></span>
      </a>
      <a href="#funciones" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Funciones</a>
      <a href="#paquetes" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Paquetes</a>
      <a href="#faq" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">FAQ</a>
      <a href="#demo" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75);">Demo</a>
      <a href="/login" class="lb-nav-link" style="font-size:14px; color:rgba(245,247,250,0.75); margin-right:6px;">Acceder</a>
      <a href="#demo" style="background:linear-gradient(135deg,#25D366,#00E6A0); color:#05070F; font-weight:600; font-size:14px; padding:10px 18px; border-radius:8px;">Solicitar demo</a>
    </header>

    <main><?= $content ?? '' ?></main>

    <footer style="border-top:1px solid #25D366; padding:56px 28px;">
      <div style="max-width:1240px; margin:0 auto; display:flex; flex-wrap:wrap; gap:48px; justify-content:space-between;">
        <div style="max-width:32ch;">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
            <span style="width:16px; height:16px; border-radius:4px; background:linear-gradient(135deg,#25D366,#00E6A0);"></span>
            <span style="font-family:'Syne',sans-serif; font-weight:700; font-size:16px; color:#F5F7FA;"><?= ViewHelper::e($empresaNombre) ?></span>
          </div>
          <p style="font-size:13px; color:rgba(245,247,250,0.55);">Plataforma de mensajería WhatsApp Business para equipos en México.</p>
        </div>
        <div>
          <div style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:rgba(245,247,250,0.45); margin-bottom:10px;">Producto</div>
          <div style="display:flex; flex-direction:column; gap:8px; font-size:14px;">
            <a href="#paquetes">Paquetes</a><a href="#faq">FAQ</a><a href="#demo">Demo</a><a href="/login">Acceder</a>
          </div>
        </div>
        <div>
          <div style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:rgba(245,247,250,0.45); margin-bottom:10px;">Empresa</div>
          <div style="display:flex; flex-direction:column; gap:8px; font-size:14px;">
            <a href="#demo">Contacto</a><a href="mailto:soporte@lebytek.com">Soporte</a>
          </div>
        </div>
      </div>
      <div style="max-width:1240px; margin:32px auto 0; font-size:12px; color:rgba(245,247,250,0.4);">© <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?></div>
    </footer>

    <script src="/assets/publico/landing_v2.js" defer></script>
</body>
</html>
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — both full-page integration tests pass; existing tests green.

- [ ] **Step 6: Commit**

```bash
git add app/Presentation/Views/publico/layout_v2.php app/Presentation/Views/publico/landing_v2.php tests/Marketing/LandingV2ViewTest.php
git commit -m "feat(marketing): v2 dark layout shell and landing orchestrator"
```

---

### Task 6: Controller variant selection + flag config

**Files:**
- Modify: `app/Presentation/Controllers/Publico/LandingController.php`
- Modify: `config/app.php:14` (add `landing_variant` entry after `asset_version`)
- Modify: `.env.example` (add `LANDING_VARIANT=v1`)
- Test: `tests/Marketing/LandingVariantSelectionTest.php`

**Interfaces:**
- Consumes: `EnvLoader::get('LANDING_VARIANT', 'v1')`, `$request->query('landing', '')`.
- Produces: renders `publico/landing_v2` + `publico/layout_v2` when variant is `v2`, else `publico/landing` + `publico/layout`. Same `$data` array for both.

- [ ] **Step 1: Write the failing test** — create `tests/Marketing/LandingVariantSelectionTest.php`

```php
<?php
// tests/Marketing/LandingVariantSelectionTest.php
declare(strict_types=1);

test('LandingController implementa la selección de variante v1/v2 por flag y override', function (): void {
    $src = file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true($src !== false, 'archivo existe');
    assert_true(str_contains($src, "EnvLoader::get('LANDING_VARIANT'"), 'lee el flag de entorno');
    assert_true(str_contains($src, "query('landing'"), 'soporta override por query ?landing=');
    assert_true(str_contains($src, "'publico/landing_v2'"), 'referencia la vista v2');
    assert_true(str_contains($src, "'publico/layout_v2'"), 'referencia el layout v2');
    assert_true(str_contains($src, "'publico/landing'"), 'conserva la vista v1');
    assert_true(str_contains($src, "'publico/layout'"), 'conserva el layout v1');
});

test('config/app.php expone landing_variant con default v1', function (): void {
    $config = require ROOT_PATH . '/config/app.php';
    assert_true(array_key_exists('landing_variant', $config), 'clave landing_variant presente');
    assert_true($config['landing_variant'] === 'v1' || is_string($config['landing_variant']), 'valor string (default v1 sin env)');
});

test('.env.example documenta LANDING_VARIANT', function (): void {
    $env = file_get_contents(ROOT_PATH . '/.env.example');
    assert_true($env !== false, 'archivo existe');
    assert_true(str_contains($env, 'LANDING_VARIANT'), 'documenta el flag');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/run.php Marketing`
Expected: FAIL — controller lacks variant logic; `config/app.php` has no `landing_variant`; `.env.example` lacks `LANDING_VARIANT`.

- [ ] **Step 3: Modify `config/app.php`** — add the entry after `'asset_version'` (line 14)

Change the array so it reads:

```php
    'asset_version' => EnvLoader::get('APP_ASSET_VERSION', '1'),
    'landing_variant' => EnvLoader::get('LANDING_VARIANT', 'v1'),
];
```

- [ ] **Step 4: Modify `.env.example`** — append the flag with an explanatory comment

```bash
# Variante de landing pública: v1 (actual) | v2 (rediseño oscuro en prueba). Rollback = v1.
LANDING_VARIANT=v1
```

- [ ] **Step 5: Modify `app/Presentation/Controllers/Publico/LandingController.php`**

Add the import near the other `use` statements (after line 11 `use App\Application\Marketing\RenderLandingUseCase;`):

```php
use Lebytek\Framework\Kernel\EnvLoader;
```

Replace the `return $this->view(...)` block (lines 29-45) with variant resolution + a shared data array:

```php
        $variant  = strtolower((string) EnvLoader::get('LANDING_VARIANT', 'v1'));
        $override = strtolower((string) $request->query('landing', ''));
        if (in_array($override, ['v1', 'v2'], true)) {
            $variant = $override;
        }
        $useV2  = $variant === 'v2';
        $view   = $useV2 ? 'publico/landing_v2' : 'publico/landing';
        $layout = $useV2 ? 'publico/layout_v2'  : 'publico/layout';

        return $this->view($view, [
            'pageTitle'           => 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas',
            'metaDescription'     => 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Planes desde $2,199/mes. Demo inmediata.',
            'empresaNombre'       => $nombre,
            'empresaLogo'         => $this->configuracionService->empresaLogo(),
            'bloques'             => $vm['bloques'],
            'paquetes'            => $vm['paquetes'],
            'comprasHabilitadas'  => $comprasHabilitadas,
            'primaryColor'        => $ui['primaryColor'],
            'primaryHover'        => $ui['primaryHover'],
            'primaryActive'       => $ui['primaryActive'],
            'primarySubtle'       => $ui['primarySubtle'],
            'primaryRgb'          => $ui['primaryRgb'],
            'lebytekCssVariables' => $ui['lebytekCssVariables'],
            'bodyBg'              => $ui['bodyBg'],
            'darkMode'            => $ui['darkMode'],
        ], $layout);
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php tests/run.php Marketing`
Expected: PASS — the 3 variant/config/env tests pass; all existing Marketing tests still green.

- [ ] **Step 7: Full suite sanity check**

Run: `php tests/run.php`
Expected: PASS — no regressions across the suite.

- [ ] **Step 8: Manual verification (optional but recommended)**

Run: `php -S localhost:8000 -t public` then open `http://localhost:8000/?landing=v2` — the dark landing renders; `http://localhost:8000/` (or `?landing=v1`) still shows the current landing. Toggle the pricing Mensual/Anual and open a FAQ item to confirm JS behaviors.

- [ ] **Step 9: Commit**

```bash
git add app/Presentation/Controllers/Publico/LandingController.php config/app.php .env.example tests/Marketing/LandingVariantSelectionTest.php
git commit -m "feat(marketing): flag-based landing variant selection (v1/v2 + ?landing override)"
```

---

## Self-Review

**Spec coverage:**
- Rollback flag (config + `?landing=` override, fail-safe to v1) → Task 6. ✓
- Additive v2 views/layout/partials, same data keys → Tasks 1–3, 5. ✓
- v2 assets (keyframes, breakpoints, reveal/accordion/billing, empresa merge) → Task 4. ✓
- Real-data pricing (prices, features array/JSON, destacado/badge, buy gating, monthly/annual) → Task 2. ✓
- Hero/trust/features/testimonios/faq from `$bloques` with degradation → Tasks 1–2. ✓
- Demo form → real POST `/lead` + CSRF + email + optional empresa merge → Task 3. ✓
- Same view-model passed to both variants → Task 6. ✓
- Testing (`php tests/run.php Marketing`, degradation, smoke, variant selection) → every task + Task 6 Step 7. ✓
- v1 files untouched → asserted in Task 5 test (`no enlaza el css v1`) and enforced by Global Constraints. ✓

**Placeholder scan:** No TBD/TODO/"handle edge cases"; every code step contains full file/edit content. ✓

**Type/contract consistency:** DOM contracts are consistent across producer (partials) and consumer (JS): `data-reveal-id`+`lb-reveal`/`lb-reveal--on`, `.lb-billing-btn[data-period]`, `[data-monthly]`/`[data-annual]` with inner `.lb-price-amount`, `.lb-compra[data-compra-monthly|annual]`, `[data-faq-toggle]`+`.lb-faq-panel`+`.lb-faq-icon`, `form[data-lead-form]`+`[data-empresa-merge]`+`textarea[name="mensaje"]`. Price-format/gating logic in Task 2 matches v1's `_pricing.php` verbatim. Controller view/layout names in Task 6 match files created in Task 5. ✓
