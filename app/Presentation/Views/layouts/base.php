<?php

use App\Kernel\Security\Csrf;
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Constants\AppConstants;

$dataTheme = ($darkMode ?? false) ? 'dark' : 'light';
$bodyClass = trim(
    'layout-' . ($menuLayout ?? AppConstants::MENU_LAYOUT_SIDE)
    . (($darkMode ?? false) ? ' dark-mode' : '')
    . ' min-vh-100 '
    . (string) ($lebytekBodyClasses ?? '')
);
$pwaBasePath = ViewHelper::basePath();
$pwaManifestHref = ($pwaBasePath === '' ? '' : $pwaBasePath) . '/manifest.webmanifest';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="<?= $dataTheme ?>" data-dark-mode="<?= ($darkMode ?? false) ? '1' : '0' ?>" data-base-path="<?= ViewHelper::e($pwaBasePath) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= ViewHelper::e($empresaNombre ?? 'Sistema') ?> — Panel Administrativo">
    <meta name="theme-color" content="<?= ViewHelper::e($primaryColor ?? '#0d6efd') ?>">
    <link rel="manifest" href="<?= ViewHelper::e($pwaManifestHref) ?>">
    <link rel="apple-touch-icon" href="<?= ViewHelper::e(!empty($empresaLogo) ? $empresaLogo : ViewHelper::asset('icons/app-icon.svg')) ?>">
    <?= Csrf::metaTag() ?>
    <title><?= ViewHelper::e($titulo ?? 'Panel') ?> — <?= ViewHelper::e($empresaNombre ?? 'Sistema') ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <!-- AOS — Animate On Scroll -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= ViewHelper::asset('css/app.css') ?>">
    <!-- LEBYTEK UI v0.1 — componentes y utilidades (.ct-*) -->
    <link rel="stylesheet" href="<?= ViewHelper::asset('css/lebytek-ui.css') ?>">

    <!-- Variables del sistema + LEBYTEK (cfg_configuraciones) -->
    <?= ViewHelper::partial('styles/lebytek_theme_vars', [
        'includeNavChrome' => true,
        'primaryColor' => $primaryColor ?? '#0d6efd',
        'primaryHover' => $primaryHover ?? '#0b5ed7',
        'primaryActive' => $primaryActive ?? '#0a58ca',
        'primarySubtle' => $primarySubtle ?? '#e8f0fe',
        'primaryRgb' => $primaryRgb ?? '13, 110, 253',
        'navbarColor' => $navbarColor ?? '#1a1d2e',
        'navbarText' => $navbarText ?? 'rgba(255,255,255,0.88)',
        'navbarTextMuted' => $navbarTextMuted ?? 'rgba(255,255,255,0.45)',
        'navbarSeparator' => $navbarSeparator ?? 'rgba(255,255,255,0.1)',
        'bodyColor' => $bodyColor ?? '#f0f2f5',
        'bodyBg' => $bodyBg ?? '',
        'darkMode' => $darkMode ?? false,
        'lebytekCssVariables' => is_array($lebytekCssVariables ?? null) ? $lebytekCssVariables : [],
    ]) ?>
</head>
<body class="<?= $bodyClass ?>">

<?php if (($menuLayout ?? '') === AppConstants::MENU_LAYOUT_TOP): ?>
    <?= ViewHelper::partial('nav_top', compact('usuario', 'empresaNombre', 'empresaLogo', 'menuFiltrado', 'currentUri')) ?>
    <div class="layout-top-wrapper">
        <main class="main-content container-fluid">
            <?= ViewHelper::partial('flash_alerts', compact('flashAll')) ?>
            <?= ViewHelper::partial('breadcrumb', ['titulo' => $titulo ?? '']) ?>
            <?= $content ?? '' ?>
        </main>
    </div>

<?php elseif (($menuLayout ?? '') === AppConstants::MENU_LAYOUT_BOTTOM): ?>
    <div class="layout-bottom-wrapper">
        <main class="main-content container-fluid">
            <?= ViewHelper::partial('flash_alerts', compact('flashAll')) ?>
            <?= ViewHelper::partial('breadcrumb', ['titulo' => $titulo ?? '']) ?>
            <?= $content ?? '' ?>
        </main>
        <?= ViewHelper::partial('footer') ?>
    </div>
    <?= ViewHelper::partial('nav_bottom', compact('usuario', 'empresaNombre', 'menuFiltrado', 'currentUri')) ?>

<?php else: /* side (default) */ ?>
    <div class="layout-side-wrapper d-flex">
        <?= ViewHelper::partial('nav_side', compact('usuario', 'empresaNombre', 'empresaLogo', 'menuFiltrado', 'currentUri')) ?>
        <div class="layout-side-content d-flex flex-column flex-grow-1 overflow-hidden">
            <?= ViewHelper::partial('topbar', compact('usuario', 'empresaNombre', 'empresaLogo', 'titulo')) ?>
            <main class="main-content flex-grow-1">
                <?= ViewHelper::partial('flash_alerts', compact('flashAll')) ?>
                <?= ViewHelper::partial('breadcrumb', ['titulo' => $titulo ?? '']) ?>
                <?= $content ?? '' ?>
            </main>
            <?= ViewHelper::partial('footer') ?>
        </div>
    </div>
<?php endif; ?>

<?= ViewHelper::partial('style_panel', compact('usuario')) ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS — Animate On Scroll -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<!-- Fallback: si AOS no carga (CDN bloqueada) los elementos [data-aos] quedan visibles -->
<script>
window.addEventListener('load', function () {
    if (typeof AOS === 'undefined') {
        document.querySelectorAll('[data-aos]').forEach(function (el) {
            el.classList.add('aos-animate');
        });
    }
});
</script>
<!-- App JS -->
<script src="<?= ViewHelper::asset('js/app.js') ?>"></script>
</body>
</html>
