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
