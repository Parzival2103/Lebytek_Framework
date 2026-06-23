<?php
// app/Presentation/Views/publico/layout.php

use App\Kernel\Helpers\ViewHelper;

$empresaNombre = $empresaNombre ?? '';
$empresaLogo   = $empresaLogo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ViewHelper::e($empresaNombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($empresaLogo !== ''): ?>
                    <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="" height="32">
                <?php endif; ?>
                <span class="fw-semibold"><?= ViewHelper::e($empresaNombre) ?></span>
            </a>
            <a href="/login" class="btn btn-outline-secondary btn-sm">Acceder</a>
        </div>
    </nav>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer class="border-top py-4 mt-5">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
