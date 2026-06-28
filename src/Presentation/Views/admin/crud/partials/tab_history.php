<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
/** @var array<int, array<string, mixed>> $historyEntries */
$historyEntries = $historyEntries ?? [];
?>
<?php if ($historyEntries === []): ?>
    <p class="text-muted small mb-0">Sin movimientos registrados.</p>
<?php else: ?>
<ul class="list-group list-group-flush">
    <?php foreach ($historyEntries as $entry): ?>
        <?php
            $accion = (string) ($entry['accion'] ?? '');
            $detalle = (string) ($entry['detalle'] ?? '');
            $fecha = (string) ($entry['created_at'] ?? '');
            $autor = trim((string) ($entry['nombre'] ?? '') . ' ' . (string) ($entry['apellido'] ?? ''));
            $ts = $fecha !== '' ? strtotime($fecha) : false;
        ?>
        <li class="list-group-item px-0">
            <div class="d-flex justify-content-between gap-2">
                <span class="fw-medium small"><?= ViewHelper::e($accion) ?></span>
                <span class="text-muted small"><?= $ts ? date('d/m/Y H:i', $ts) : ViewHelper::e($fecha) ?></span>
            </div>
            <?php if ($detalle !== ''): ?>
                <div class="text-muted small text-truncate"><?= ViewHelper::e($detalle) ?></div>
            <?php endif; ?>
            <?php if ($autor !== ''): ?>
                <div class="text-muted small">Por: <?= ViewHelper::e($autor) ?></div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
