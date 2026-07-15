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
