<?php
use App\Kernel\Helpers\ViewHelper;

$colspan = (int) ($colspan ?? 1);
?>
<tr>
    <td colspan="<?= $colspan ?>" class="p-0">
        <div class="ct-empty-state crud-empty-state py-4 px-2">
            <i class="bi bi-inbox ct-empty-state__icon" aria-hidden="true"></i>
            <p class="ct-empty-state__title"><?= ViewHelper::e((string) ($emptyTitle ?? 'Sin registros')) ?></p>
            <?php if (!empty($emptyHint)): ?>
                <p class="ct-empty-state__hint"><?= ViewHelper::e((string) $emptyHint) ?></p>
            <?php endif; ?>
        </div>
    </td>
</tr>
