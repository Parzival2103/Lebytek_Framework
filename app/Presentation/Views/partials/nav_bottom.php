<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Constants\UiConfirmConstants;

$uri       = $currentUri ?? '/';
$menuItems = $menuFiltrado ?? [];

$primaryItems = array_slice($menuItems, 0, 4);
$moreItems    = array_slice($menuItems, 4);

$submenuJson = static function (array $item): string {
    $submenu = $item['submenu'] ?? [];
    return htmlspecialchars(json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
};
?>

<!-- Overlay compartido (más + submenú) -->
<div id="bottomnavMoreOverlay" class="bottomnav-more-overlay"></div>

<!-- Panel de submenú dinámico (se rellena con JS) -->
<div id="bottomnavSubPanel" class="bottomnav-sub-panel d-lg-none" role="dialog" aria-label="Submenú" hidden>
    <div class="bottomnav-sub-header">
        <span id="bottomnavSubTitle" class="fw-semibold"></span>
        <a id="bottomnavSubViewAll" href="#" class="bottomnav-sub-viewall">Ver todos</a>
    </div>
    <div id="bottomnavSubGrid" class="bottomnav-sub-grid"></div>
</div>

<!-- Panel expandible "más" con el resto de ítems -->
<?php if (!empty($moreItems)): ?>
<div id="bottomnavMorePanel" class="bottomnav-more-panel d-lg-none" role="dialog" aria-label="Más navegación">
    <?php foreach ($moreItems as $item): ?>
        <?php $isActive = str_starts_with($uri, $item['match'] ?? $item['url'] ?? ''); ?>
        <?php if (!empty($item['submenu'])): ?>
            <button type="button"
                    class="bottomnav-more-item bottomnav-sub-btn <?= $isActive ? 'active' : '' ?>"
                    data-label="<?= ViewHelper::e($item['label']) ?>"
                    data-main-url="<?= ViewHelper::e(($item['url'] ?? '') !== '' ? (string) $item['url'] : (($item['match'] ?? '') !== '' ? (string) $item['match'] : '#')) ?>"
                    data-submenu="<?= $submenuJson($item) ?>">
                <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                <span><?= ViewHelper::e($item['label']) ?></span>
            </button>
        <?php else: ?>
            <?php $moreLeafUrl = (string) ($item['url'] ?? ''); ?>
            <a href="<?= ViewHelper::e($moreLeafUrl !== '' ? $moreLeafUrl : '#') ?>"
               class="bottomnav-more-item <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                <span><?= ViewHelper::e($item['label']) ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Barra de navegación inferior -->
<nav class="bottomnav ct-bottombar d-flex d-lg-none fixed-bottom">
    <?php foreach ($primaryItems as $item): ?>
        <?php $isActive = str_starts_with($uri, $item['match'] ?? $item['url'] ?? ''); ?>
        <?php if (!empty($item['submenu'])): ?>
            <button type="button"
                    class="bottomnav-item bottomnav-sub-btn flex-grow-1 <?= $isActive ? 'active' : '' ?>"
                    data-label="<?= ViewHelper::e($item['label']) ?>"
                    data-main-url="<?= ViewHelper::e(($item['url'] ?? '') !== '' ? (string) $item['url'] : (($item['match'] ?? '') !== '' ? (string) $item['match'] : '#')) ?>"
                    data-submenu="<?= $submenuJson($item) ?>">
                <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                <span><?= ViewHelper::e($item['label']) ?></span>
            </button>
        <?php else: ?>
            <?php $primaryLeafUrl = (string) ($item['url'] ?? ''); ?>
            <a href="<?= ViewHelper::e($primaryLeafUrl !== '' ? $primaryLeafUrl : '#') ?>"
               class="bottomnav-item flex-grow-1 text-center <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= ViewHelper::e($item['icon'] ?? 'bi-circle') ?>"></i>
                <span><?= ViewHelper::e($item['label']) ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($moreItems)): ?>
    <button id="bottomnavMoreBtn" type="button"
            class="bottomnav-item bottomnav-more-btn flex-grow-1 border-0 bg-transparent"
            aria-expanded="false" aria-controls="bottomnavMorePanel">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>Más</span>
    </button>
    <?php endif; ?>

    <?php $logoutConfirmAttrs = ViewHelper::confirmAttrs([
        'body'    => UiConfirmConstants::LOGOUT_BODY,
        'title'   => UiConfirmConstants::LOGOUT_TITLE,
        'ok'      => UiConfirmConstants::LOGOUT_OK,
        'variant' => 'danger',
        'icon'    => UiConfirmConstants::LOGOUT_ICON,
    ]); ?>
    <form method="POST" action="/logout" class="bottomnav-logout-form flex-grow-1 m-0 min-w-0 d-flex" <?= $logoutConfirmAttrs ?>>
        <?= ViewHelper::csrfField() ?>
        <button type="submit" class="bottomnav-item bottomnav-sub-btn flex-grow-1 w-100 text-danger"
                title="Cerrar sesión">
            <i class="bi bi-box-arrow-right"></i>
            <span>Salir</span>
        </button>
    </form>
</nav>
