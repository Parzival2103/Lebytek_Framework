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
