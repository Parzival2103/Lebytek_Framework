<?php
// app/Presentation/Views/publico/layout.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
$bloques       = is_array($bloques ?? null) ? $bloques : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($pageTitle ?? ($empresaNombre . ' — WhatsApp Business')) ?></title>
    <meta name="description" content="<?= ViewHelper::e($metaDescription ?? 'Automatiza WhatsApp para tu negocio: campañas, demo instantánea y panel multi-usuario.') ?>">
    <meta name="theme-color" content="<?= ViewHelper::e($primaryColor ?? '#25D366') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?= ViewHelper::partial('styles/lebytek_theme_vars', [
        'includeNavChrome'    => false,
        'primaryColor'        => $primaryColor        ?? null,
        'primaryHover'        => $primaryHover        ?? null,
        'primaryActive'       => $primaryActive       ?? null,
        'primarySubtle'       => $primarySubtle       ?? null,
        'primaryRgb'          => $primaryRgb          ?? null,
        'lebytekCssVariables' => $lebytekCssVariables ?? [],
        'bodyBg'              => $bodyBg              ?? null,
        'darkMode'           => $darkMode            ?? false,
    ]) ?>
    <link href="/assets/publico/landing.css" rel="stylesheet">
</head>
<body class="ct-public">
    <nav class="navbar navbar-expand-lg ct-nav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($empresaLogo !== ''): ?>
                    <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="30">
                <?php endif; ?>
                <span><?= ViewHelper::e($empresaNombre) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ctNav" aria-controls="ctNav" aria-expanded="false" aria-label="Menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="ctNav">
                <ul class="navbar-nav align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="#funciones">Funciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="#paquetes">Paquetes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#demo">Demo</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm px-3 ms-lg-2" href="/login">Acceder</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <?= $content ?? '' ?>
    </main>

    <?= ViewHelper::render('publico/partials/_footer', [
        'footer'        => $bloques['footer'] ?? [],
        'empresaNombre' => $empresaNombre,
    ], '') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/publico/landing.js" defer></script>
</body>
</html>
