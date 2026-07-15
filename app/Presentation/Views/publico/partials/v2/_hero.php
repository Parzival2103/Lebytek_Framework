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
