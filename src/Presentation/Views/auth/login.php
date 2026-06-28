<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Csrf;
use Lebytek\Framework\Kernel\Security\Session;

$__theme = get_defined_vars();

$flashAll           = $flashAll ?? Session::flashAll();
$registroHabilitado = !empty($registroHabilitado);

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Bienvenido</h3>
    <p class="text-muted small mb-0">Ingresa tus credenciales para continuar</p>
</div>

<form method="POST" action="/login" novalidate id="loginForm" data-no-loading>
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
        <a href="/recuperar" class="small text-decoration-none">¿Olvidaste tu contraseña?</a>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold" id="loginBtn">
        <span class="btn-text">Iniciar sesión</span>
        <span class="btn-spinner spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
    </button>

    <?php if ($registroHabilitado): ?>
        <p class="text-center small mt-3 mb-0">
            ¿No tienes cuenta? <a href="/registro" class="text-decoration-none">Crear cuenta</a>
        </p>
    <?php endif; ?>
</form>
<?php
$contentHtml = ob_get_clean();

ob_start();
?>
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
<?php
$extraScripts = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'    => 'Iniciar sesión',
    'flashAll'     => $flashAll,
    'contentHtml'  => $contentHtml,
    'extraScripts' => $extraScripts,
]);
