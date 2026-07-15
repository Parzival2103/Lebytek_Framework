<?php
// app/Presentation/Views/publico/partials/_faq.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$faq   = is_array($faq ?? null) ? $faq : [];
$items = is_array($faq['items'] ?? null) ? $faq['items'] : [];
$titulo = (string) ($faq['titulo'] ?? 'Preguntas frecuentes');

if ($items === []) {
    return;
}
?>
<section class="ct-faq" id="faq">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="ct-section__title"><?= ViewHelper::e($titulo) ?></h2>
      <?php if (!empty($faq['lead'])): ?>
        <p class="ct-section__lead"><?= ViewHelper::e((string) $faq['lead']) ?></p>
      <?php endif; ?>
    </div>
    <div class="accordion ct-faq__accordion" id="ctFaqAccordion">
      <?php foreach ($items as $i => $item): ?>
        <?php
          $pregunta  = trim((string) ($item['pregunta'] ?? ''));
          $respuesta = trim((string) ($item['respuesta'] ?? ''));
          if ($pregunta === '') {
              continue;
          }
          $collapseId = 'ctFaqItem' . $i;
          $headingId  = 'ctFaqHeading' . $i;
          $openFirst  = $i === 0;
        ?>
        <div class="accordion-item ct-faq__item" data-reveal>
          <h3 class="accordion-header" id="<?= ViewHelper::e($headingId) ?>">
            <button
              class="accordion-button<?= $openFirst ? '' : ' collapsed' ?>"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#<?= ViewHelper::e($collapseId) ?>"
              aria-expanded="<?= $openFirst ? 'true' : 'false' ?>"
              aria-controls="<?= ViewHelper::e($collapseId) ?>"
            >
              <?= ViewHelper::e($pregunta) ?>
            </button>
          </h3>
          <div
            id="<?= ViewHelper::e($collapseId) ?>"
            class="accordion-collapse collapse<?= $openFirst ? ' show' : '' ?>"
            aria-labelledby="<?= ViewHelper::e($headingId) ?>"
            data-bs-parent="#ctFaqAccordion"
          >
            <div class="accordion-body ct-faq__answer">
              <?php if ($respuesta !== ''): ?>
                <?= nl2br(ViewHelper::e($respuesta)) ?>
              <?php else: ?>
                <span class="ct-faq__placeholder text-muted">Respuesta pendiente.</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
