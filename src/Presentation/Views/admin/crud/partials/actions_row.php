<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $rowActions */
/** @var string $resource */
$rowActions = $rowActions ?? [];

// Defaults visuales para acciones builtin (paridad con la vista clásica).
$builtinIcon = ['show' => 'bi-eye', 'edit' => 'bi-pencil', 'delete' => 'bi-trash'];
$builtinBtn  = ['show' => 'btn-outline-info', 'edit' => 'btn-outline-primary', 'delete' => 'btn-outline-danger'];

$renderConfirmAttrs = static function (array $action): string {
    $attrs = [];
    if (!empty($action['confirm'])) {
        $attrs[] = 'data-confirm="' . ViewHelper::e((string) $action['confirm']) . '"';
    }
    if (!empty($action['confirm_title'])) {
        $attrs[] = 'data-confirm-title="' . ViewHelper::e((string) $action['confirm_title']) . '"';
    }
    if (!empty($action['confirm_variant'])) {
        $attrs[] = 'data-confirm-variant="' . ViewHelper::e((string) $action['confirm_variant']) . '"';
    }
    if (!empty($action['confirm_ok'])) {
        $attrs[] = 'data-confirm-ok="' . ViewHelper::e((string) $action['confirm_ok']) . '"';
    }
    return implode(' ', $attrs);
};
?>
<div class="d-flex justify-content-end gap-1 flex-wrap">
    <?php foreach ($rowActions as $a):
        $name = (string) ($a['name'] ?? '');
        $label = (string) ($a['label'] ?? $name);
        $type = (string) ($a['type'] ?? '');
        $enabled = (bool) ($a['enabled'] ?? true);
        $disabledAttr = $enabled ? '' : ' disabled aria-disabled="true"';
        $icon = (string) ($a['icon'] ?? '');
        if ($icon === '' && $type === 'builtin') {
            $icon = $builtinIcon[$name] ?? '';
        }
        $btnClass = $type === 'builtin' ? ($builtinBtn[$name] ?? 'btn-outline-secondary') : 'btn-outline-secondary';
        $confirmAttrs = $renderConfirmAttrs($a);
    ?>
        <?php if ($type === 'builtin' && $name === 'delete'): ?>
            <form method="POST" action="<?= ViewHelper::e((string) ($a['href'] ?? '#')) ?>" class="d-inline"
                  <?= $confirmAttrs ?>>
                <?= ViewHelper::csrfField() ?>
                <button type="submit" class="btn btn-sm <?= $btnClass ?>"<?= $disabledAttr ?>
                        title="<?= ViewHelper::e($label) ?>" aria-label="<?= ViewHelper::e($label) ?>">
                    <?php if ($icon !== ''): ?><i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i><?php else: ?><?= ViewHelper::e($label) ?><?php endif; ?>
                </button>
            </form>
        <?php elseif ($type === 'builtin' || $type === 'link'): ?>
            <a href="<?= ViewHelper::e((string) ($a['href'] ?? '#')) ?>"
               class="btn btn-sm <?= $btnClass ?><?= $enabled ? '' : ' disabled' ?>"
               title="<?= ViewHelper::e($label) ?>" aria-label="<?= ViewHelper::e($label) ?>">
                <?php if ($icon !== ''): ?><i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i><?php else: ?><?= ViewHelper::e($label) ?><?php endif; ?>
            </a>
        <?php else: /* handler / transition (POST) */ ?>
            <form method="POST" action="<?= ViewHelper::e((string) ($a['endpoint'] ?? '#')) ?>" class="d-inline js-crud-action"
                  <?= $confirmAttrs ?>>
                <?= ViewHelper::csrfField() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary"<?= $disabledAttr ?>
                        title="<?= ViewHelper::e($label) ?>" aria-label="<?= ViewHelper::e($label) ?>">
                    <?php if ($icon !== ''): ?><i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i><?php else: ?><?= ViewHelper::e($label) ?><?php endif; ?>
                </button>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
