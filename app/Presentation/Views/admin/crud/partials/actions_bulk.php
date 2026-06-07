<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $bulkActions */
/** @var string $resource */
$bulkActions = $bulkActions ?? [];
if ($bulkActions === []) { return; }
?>
<div class="ct-bulk-bar d-none align-items-center gap-2 p-2 border-bottom bg-light" data-crud-bulk-bar>
    <span class="small text-muted"><span data-crud-bulk-count>0</span> seleccionados</span>
    <?php foreach ($bulkActions as $b):
        $name = (string) ($b['name'] ?? '');
    ?>
        <form method="POST" action="<?= ViewHelper::e((string) ($b['endpoint'] ?? '#')) ?>" class="d-inline js-crud-bulk-form"
              <?php if (!empty($b['confirm'])): ?>data-confirm="<?= ViewHelper::e((string) $b['confirm']) ?>"<?php endif; ?>>
            <?= ViewHelper::csrfField() ?>
            <span data-crud-bulk-ids></span>
            <button type="submit" class="btn btn-sm btn-outline-primary"><?= ViewHelper::e((string) ($b['label'] ?? $name)) ?></button>
        </form>
    <?php endforeach; ?>
</div>
