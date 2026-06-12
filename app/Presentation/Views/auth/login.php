<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$empresaNombre = $empresaNombre ?? 'Sistema Administrativo';
$primaryColor  = $primaryColor ?? '#0d6efd';
$darkMode      = $darkMode ?? false;
$empresaLogo   = $empresaLogo ?? '';
$flashAll      = $flashAll ?? Session::flashAll();
$pwaBasePath   = ViewHelper::basePath();
$pwaManifestHref = ($pwaBasePath === '' ? '' : $pwaBasePath) . '/manifest.webmanifest';
$lebytekBody   = trim('ct-login-page ' . (string) ($lebytekBodyClasses ?? ''));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="<?= $darkMode ? 'dark' : 'light' ?>" data-base-path="<?= ViewHelper::e($pwaBasePath) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= ViewHelper::e($primaryColor) ?>">
    <link rel="manifest" href="<?= ViewHelper::e($pwaManifestHref) ?>">
    <link rel="apple-touch-icon" href="<?= ViewHelper::e($empresaLogo !== '' ? $empresaLogo : ViewHelper::asset('icons/app-icon.svg')) ?>">
    <title>Iniciar sesión — <?= ViewHelper::e($empresaNombre) ?></title>
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
        <div class="login-brand d-md-flex flex-column align-items-center justify-content-center p-4 p-md-5">
            <?php if ($empresaLogo !== ''): ?>
                <img src="<?= ViewHelper::e($empresaLogo) ?>" alt="<?= ViewHelper::e($empresaNombre) ?>" class="mb-4 ct-login-brand-logo">
            <?php else: ?>
                <div class="login-brand-icon mb-4" aria-hidden="true">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
            <?php endif; ?>
            <h2 class="fw-bold text-white mb-2"><?= ViewHelper::e($empresaNombre) ?></h2>
            <p class="text-white-50 text-center mb-0 small">
                Sistema administrativo modular.<br>
                Accede para gestionar tu operación.
            </p>
        </div>

        <div class="login-form-side p-4 p-md-5 d-flex flex-column justify-content-center">
            <div class="mb-4 text-center text-md-start">
                <h3 class="fw-bold mb-1">Bienvenido</h3>
                <p class="text-muted small mb-0">Ingresa tus credenciales para continuar</p>
            </div>

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

            <form method="POST" action="/login" novalidate id="loginForm">
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-medium small">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control <?= Session::hasFlash('errors') || isset($flashAll['errors']['email']) ? 'is-invalid' : '' ?>"
                               placeholder="correo@empresa.com"
                               value="<?= ViewHelper::old('email') ?>"
                               autocomplete="email"
                               autofocus
                               required>
                        <?php if (!empty($flashAll['errors']['email'])): ?>
                            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-medium small">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>
                        <input type="password"
                               id="password"
                               name="password"
                               class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
                               placeholder="Tu contraseña"
                               autocomplete="current-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                        <?php if (!empty($flashAll['errors']['password'])): ?>
                            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="recordar" id="recordar" value="1">
                        <label class="form-check-label small" for="recordar">Recordarme</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-semibold" id="loginBtn">
                    <span class="btn-text">Iniciar sesión</span>
                    <span class="btn-spinner spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                </button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                &copy; <?= date('Y') ?> <?= ViewHelper::e($empresaNombre) ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ViewHelper::asset('js/app.js') ?>"></script>
<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon  = this.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
        this.setAttribute('aria-label', 'Ocultar contraseña');
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
        this.setAttribute('aria-label', 'Mostrar contraseña');
    }
});

document.getElementById('loginForm')?.addEventListener('submit', function () {
    const btn     = document.getElementById('loginBtn');
    const spinner = btn.querySelector('.btn-spinner');
    const text    = btn.querySelector('.btn-text');
    btn.disabled  = true;
    spinner.classList.remove('d-none');
    text.textContent = 'Verificando...';
});
</script>
</body>
</html>
