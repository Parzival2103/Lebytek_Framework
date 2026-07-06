<?php
// app/Presentation/Views/publico/waapi/layout.php — shell mínimo del portal cliente waapi
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? 'Lebytek';
$empresaLogo   = $empresaLogo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($pageTitle ?? 'Portal cliente — WhatsApp API') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?= ViewHelper::partial('styles/lebytek_theme_vars', [
        'includeNavChrome' => false,
        'primaryColor'     => '#25D366',
    ]) ?>
    <link href="/assets/publico/landing.css" rel="stylesheet">
    <link href="/assets/publico/waapi-portal.css" rel="stylesheet">
</head>
<body class="ct-public waapi-portal">
    <header class="waapi-portal__header border-bottom bg-white">
        <div class="container py-3 d-flex align-items-center justify-content-between gap-3">
            <a href="/" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
                <?php if ($empresaLogo !== ''): ?>
                    <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="28">
                <?php endif; ?>
                <span class="fw-bold"><?= ViewHelper::e($empresaNombre) ?></span>
                <span class="text-muted small d-none d-sm-inline">· Portal cliente</span>
            </a>
            <?php if (! empty($showLogout)): ?>
                <form method="post" action="/portal/logout" class="mb-0">
                    <?= ViewHelper::csrfField() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="waapi-portal__main">
        <?= $content ?? '' ?>
    </main>

    <footer class="waapi-portal__footer border-top bg-light">
        <div class="container py-3 text-center text-muted small">
            WhatsApp API gestionada por <?= ViewHelper::e($empresaNombre) ?> ·
            <a href="https://docs.lebytek.com" target="_blank" rel="noopener">Documentación</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
