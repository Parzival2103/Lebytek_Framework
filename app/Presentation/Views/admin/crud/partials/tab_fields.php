<?php
use App\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $tabColumns */
/** @var array<string, mixed> $row */
$tabColumns = $tabColumns ?? [];
$row = $row ?? [];
?>
<dl class="row crud-dl mb-0">
    <?php foreach ($tabColumns as $column): ?>
        <?php
            $name = (string) ($column['name'] ?? '');
            $raw = $row[$name] ?? '';
            $format = (string) ($column['format'] ?? '');
            $display = (string) $raw;
            if ($format === 'date' && $raw !== '' && $raw !== null) {
                $ts = strtotime((string) $raw);
                $display = $ts ? date('d/m/Y', $ts) : $display;
            } elseif ($format === 'datetime' && $raw !== '' && $raw !== null) {
                $ts = strtotime((string) $raw);
                $display = $ts ? date('d/m/Y H:i', $ts) : $display;
            } elseif ($format === 'money' && $raw !== '' && $raw !== null) {
                $display = '$' . number_format((float) $raw, 2, '.', ',');
            }
            $badge = null;
            if (!empty($column['badge']) && is_array($column['badge'])) {
                $badge = (string) ($column['badge'][(string) $raw] ?? '');
            }
        ?>
        <dt class="col-12 col-sm-4 col-lg-3"><?= ViewHelper::e((string) ($column['label'] ?? $name)) ?></dt>
        <dd class="col-12 col-sm-8 col-lg-9 mb-3">
            <?php if ($badge !== null && $badge !== ''): ?>
                <span class="badge rounded-pill bg-<?= ViewHelper::e($badge) ?>-subtle text-<?= ViewHelper::e($badge) ?> border border-<?= ViewHelper::e($badge) ?>-subtle">
                    <?= ViewHelper::e($display) ?>
                </span>
            <?php else: ?>
                <span class="d-block py-1 border-bottom border-light-subtle"><?= ViewHelper::e($display) ?></span>
            <?php endif; ?>
        </dd>
    <?php endforeach; ?>
</dl>
