<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $collapseId */
/** @var string $headingId */
/** @var string $title */
/** @var string $iconClass */
/** @var string|null $bodyHtml */
$subtitle = isset($subtitle) ? (string) $subtitle : '';
$subtitleHtml = isset($subtitleHtml) ? (string) $subtitleHtml : '';
$titleExtraHtml = isset($titleExtraHtml) ? (string) $titleExtraHtml : '';
$parentAccordionId = isset($parentAccordionId) ? (string) $parentAccordionId : 'ajustesAccordion';
$bodyHtml = isset($bodyHtml) ? (string) $bodyHtml : '';

?>
<div class="accordion-item ct-ajustes-accordion-item border-0 shadow-sm mb-3 overflow-hidden rounded">
    <h2 class="accordion-header mb-0" id="<?= ViewHelper::e($headingId) ?>">
        <button class="accordion-button collapsed px-4 py-3 bg-transparent shadow-none ct-ajustes-accordion-btn"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= ViewHelper::e($collapseId) ?>"
                aria-expanded="false"
                aria-controls="<?= ViewHelper::e($collapseId) ?>">
            <span class="d-flex align-items-start gap-2 text-start w-100 pe-2">
                <i class="bi <?= ViewHelper::e($iconClass) ?> text-primary flex-shrink-0 mt-1" aria-hidden="true"></i>
                <span class="flex-grow-1 min-w-0">
                    <span class=" d-inline"><?= ViewHelper::e($title) ?></span><?= $titleExtraHtml ?>
                    <?php if ($subtitleHtml !== '') : ?>
                        <span class="small text-muted d-block mt-1"><?= $subtitleHtml ?></span>
                    <?php elseif ($subtitle !== '') : ?>
                        <span class="small text-muted d-block mt-1"><?= ViewHelper::e($subtitle) ?></span>
                    <?php endif; ?>
                </span>
            </span>
        </button>
    </h2>
    <div id="<?= ViewHelper::e($collapseId) ?>"
         class="accordion-collapse collapse"
         data-bs-parent="#<?= ViewHelper::e($parentAccordionId) ?>"
         aria-labelledby="<?= ViewHelper::e($headingId) ?>">
        <div class="accordion-body p-4 border-top">
            <?= $bodyHtml ?>
        </div>
    </div>
</div>
