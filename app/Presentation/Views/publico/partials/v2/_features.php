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
