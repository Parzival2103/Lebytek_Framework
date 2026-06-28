<?php
// app/Presentation/Views/publico/partials/_pricing.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

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
