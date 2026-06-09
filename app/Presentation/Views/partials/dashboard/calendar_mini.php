<?php

use App\Kernel\Helpers\ViewHelper;

/** @var array<string,mixed> $data — pasado por ViewHelper::partial */
$key     = (string) ($key ?? ($data['key'] ?? ''));
$title   = (string) ($title ?? ($data['title'] ?? 'Calendario'));
$icon    = (string) ($icon ?? ($data['icon'] ?? 'bi-calendar-event'));
$url     = (string) ($url ?? ($data['url'] ?? '#'));
$feedUrl = (string) ($feedUrl ?? ($data['feedUrl'] ?? ''));

$today = new DateTimeImmutable('today');
$first = $today->modify('first day of this month');
$last  = $today->modify('last day of this month');
$monthLabel = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][(int) $today->format('n')];

// Lunes anterior (o igual) al día 1, y domingo posterior (o igual) al último día.
$gridStart = $first->modify('-' . (((int) $first->format('N')) - 1) . ' days');
$gridEnd   = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');

$widgetId = 'cal-mini-' . preg_replace('/[^a-z0-9_-]/i', '', $key);
$from = $gridStart->format('Y-m-d');
$to   = $gridEnd->format('Y-m-d');
?>
<div class="card border-0 shadow-sm ct-card h-100 lebytek-calendar-mini" id="<?= ViewHelper::e($widgetId) ?>">
    <div class="card-body p-3">
        <a href="<?= ViewHelper::e($url) ?>" class="text-decoration-none text-reset d-block">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold d-inline-flex align-items-center gap-2">
                    <i class="bi <?= ViewHelper::e($icon) ?>" aria-hidden="true"></i>
                    <?= ViewHelper::e($title) ?>
                </span>
                <span class="small text-muted"><?= ViewHelper::e($monthLabel . ' ' . $today->format('Y')) ?></span>
            </div>
            <div class="lebytek-calendar-mini-grid">
                <?php foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $w): ?>
                    <span class="lebytek-calendar-mini-weekday"><?= $w ?></span>
                <?php endforeach; ?>
                <?php
                $cursor = $gridStart;
                $guard = 0;
                while ($cursor <= $gridEnd && $guard < 50):
                    $inMonth = $cursor->format('n') === $today->format('n');
                    $isToday = $cursor->format('Y-m-d') === $today->format('Y-m-d');
                    $cls = 'lebytek-calendar-mini-day';
                    if (!$inMonth) { $cls .= ' is-muted'; }
                    if ($isToday) { $cls .= ' is-today'; }
                    ?>
                    <span class="<?= $cls ?>" data-day="<?= $cursor->format('Y-m-d') ?>">
                        <?= (int) $cursor->format('j') ?>
                    </span>
                    <?php
                    $cursor = $cursor->modify('+1 day');
                    $guard++;
                endwhile;
                ?>
            </div>
            <div class="small text-muted mt-2 text-end">Ver agenda <i class="bi bi-arrow-right"></i></div>
        </a>
    </div>
</div>
<?php if ($feedUrl !== ''): ?>
<script>
(function () {
    var root = document.getElementById('<?= ViewHelper::e($widgetId) ?>');
    if (!root) { return; }
    var feed = <?= json_encode($feedUrl . (str_contains($feedUrl, '?') ? '&' : '?') . 'desde=' . $from . '&hasta=' . $to, JSON_UNESCAPED_SLASHES) ?>;
    fetch(feed, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { eventos: [] }; })
        .then(function (d) {
            (d.eventos || []).forEach(function (ev) {
                var day = String(ev.start || '').slice(0, 10);
                var cell = root.querySelector('.lebytek-calendar-mini-day[data-day="' + day + '"]');
                if (cell) { cell.classList.add('has-events'); }
            });
        })
        .catch(function () {});
})();
</script>
<?php endif; ?>
