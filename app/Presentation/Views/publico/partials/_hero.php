<?php
// app/Presentation/Views/publico/partials/_hero.php
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
        <?php else: ?>
          <img src="/assets/publico/hero-dashboard.svg" alt="Vista previa del panel" class="ct-hero__mockup" loading="lazy">
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
