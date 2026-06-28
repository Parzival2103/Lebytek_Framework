<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;
use Lebytek\Framework\Kernel\Constants\AppConstants;

/*
 * Shell visual común de las vistas de auth (login, registro, recuperación).
 * Recibe: pageTitle, contentHtml (lado del formulario), extraScripts (opcional)
 * y las variables de tema de LebytekUiConfig.
 */
$empresaNombre = AppConstants::resolveEmpresaNombre($empresaNombre ?? null);
$mostrarEmpresaNombre = AppConstants::empresaMostrarNombre($mostrarEmpresaNombre ?? null);
$primaryColor  = $primaryColor ?? '#0d6efd';
$darkMode      = $darkMode ?? false;
$empresaLogo   = $empresaLogo ?? '';
$flashAll      = $flashAll ?? Session::flashAll();
$pageTitle     = $pageTitle ?? 'Acceso';
$contentHtml   = $contentHtml ?? '';
$extraScripts  = $extraScripts ?? '';
$pwaBasePath   = ViewHelper::basePath();
$pwaManifestHref = ($pwaBasePath === '' ? '' : $pwaBasePath) . '/manifest.webmanifest';
$lebytekBody   = trim('ct-login-page ' . (string) ($lebytekBodyClasses ?? ''));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="<?= $darkMode ? 'dark' : 'light' ?>" data-base-path="<?= ViewHelper::e($pwaBasePath) ?>" data-asset-version="<?= ViewHelper::e(ViewHelper::assetVersion()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= ViewHelper::e($primaryColor) ?>">
    <link rel="manifest" href="<?= ViewHelper::e($pwaManifestHref) ?>">
    <link rel="apple-touch-icon" href="<?= ViewHelper::e($empresaLogo !== '' ? $empresaLogo : ViewHelper::asset('icons/app-icon.svg')) ?>">
    <title><?= ViewHelper::e($pageTitle) ?> — <?= ViewHelper::e($empresaNombre) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= ViewHelper::asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= ViewHelper::asset('css/lebytek-ui.css') ?>">
    <?= ViewHelper::partial('styles/lebytek_theme_vars', [
        'includeNavChrome' => false,
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
<body class="login-page <?= ViewHelper::e($lebytekBody) ?>">

<div class="login-wrapper d-flex align-items-center justify-content-center min-vh-100 px-3">
    <div class="login-card ct-login-card card shadow-lg border-0 w-100">
        <div class="login-brand d-flex flex-column align-items-center justify-content-center text-center p-4 p-md-5">
            <?php if ($empresaLogo !== ''): ?>
                <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="<?= ViewHelper::e($empresaNombre) ?>" class="<?= $mostrarEmpresaNombre ? 'mb-4' : 'mb-3' ?> ct-login-brand-logo mx-auto">
            <?php else: ?>
                <div class="login-brand-icon mb-4 mx-auto" aria-hidden="true">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
            <?php endif; ?>
            <?php if ($mostrarEmpresaNombre): ?>
            <h2 class="fw-bold text-white mb-2"><?= ViewHelper::e($empresaNombre) ?></h2>
            <?php endif; ?>
            <p class="text-white-50 text-center mb-0 small">
                Sistema administrativo modular.<br>
                Accede para gestionar tu operación.
            </p>
        </div>

        <div class="login-form-side p-4 p-md-5 d-flex flex-column justify-content-center">
            <?php foreach ($flashAll as $type => $msg): ?>
                <?php if ($type === 'errors' || !is_string($msg)) {
                    continue;
                } ?>
                <?php $bsType = $type === 'error' ? 'danger' : $type; ?>
                <div class="alert alert-<?= $bsType ?> alert-dismissible d-flex gap-2 py-2 fade show mb-3" role="alert">
                    <i class="bi bi-exclamation-circle-fill flex-shrink-0" aria-hidden="true"></i>
                    <span class="small"><?= ViewHelper::e($msg) ?></span>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endforeach; ?>

            <?= $contentHtml ?>

            <p class="text-center text-muted small mt-4 mb-0">
                &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ViewHelper::asset('js/app.js') ?>"></script>
<?= $extraScripts ?>
</body>
</html>
