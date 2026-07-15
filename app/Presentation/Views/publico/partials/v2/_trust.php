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
