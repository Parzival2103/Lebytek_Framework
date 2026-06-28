<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $relColumns */
/** @var array<int, array<string, mixed>> $relRows */
$relColumns = $relColumns ?? [];
$relRows = $relRows ?? [];
?>
<?php if ($relRows === []): ?>
    <p class="text-muted small mb-0">Sin registros relacionados.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
            <tr>
                <?php foreach ($relColumns as $column): ?>
                    <th class="px-3 text-nowrap"><?= ViewHelper::e((string) ($column['label'] ?? ($column['name'] ?? ''))) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relRows as $rrow): ?>
                <tr>
                    <?php foreach ($relColumns as $column): ?>
                        <?php
                            $name = (string) ($column['name'] ?? '');
                            $raw = $rrow[$name] ?? '';
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
                        ?>
                        <td class="px-3"><?= ViewHelper::e($display) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
