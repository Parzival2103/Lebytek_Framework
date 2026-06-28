<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Csrf;
use Lebytek\Framework\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Crear cuenta</h3>
    <p class="text-muted small mb-0">Completa tus datos; te enviaremos un correo de verificación</p>
</div>

<form method="POST" action="/registro" novalidate>
    <?= Csrf::field() ?>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="nombre" class="form-label fw-medium small">Nombre</label>
            <input type="text" id="nombre" name="nombre"
                   class="form-control <?= !empty($flashAll['errors']['nombre']) ? 'is-invalid' : '' ?>"
                   value="<?= ViewHelper::old('nombre') ?>" autocomplete="given-name" required>
            <?php if (!empty($flashAll['errors']['nombre'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['nombre']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 mb-3">
            <label for="apellido" class="form-label fw-medium small">Apellido</label>
            <input type="text" id="apellido" name="apellido"
                   class="form-control <?= !empty($flashAll['errors']['apellido']) ? 'is-invalid' : '' ?>"
                   value="<?= ViewHelper::old('apellido') ?>" autocomplete="family-name" required>
            <?php if (!empty($flashAll['errors']['apellido'])): ?>
                <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['apellido']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label fw-medium small">Correo electrónico</label>
        <input type="email" id="email" name="email"
               class="form-control <?= !empty($flashAll['errors']['email']) ? 'is-invalid' : '' ?>"
               placeholder="correo@empresa.com"
               value="<?= ViewHelper::old('email') ?>" autocomplete="email" required>
        <?php if (!empty($flashAll['errors']['email'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['email']) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label fw-medium small">Contraseña</label>
        <input type="password" id="password" name="password"
               class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
               placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
        <?php if (!empty($flashAll['errors']['password'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password']) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="password_confirmacion" class="form-label fw-medium small">Confirmar contraseña</label>
        <input type="password" id="password_confirmacion" name="password_confirmacion"
               class="form-control <?= !empty($flashAll['errors']['password_confirmacion']) ? 'is-invalid' : '' ?>"
               placeholder="Repite la contraseña" autocomplete="new-password" required>
        <?php if (!empty($flashAll['errors']['password_confirmacion'])): ?>
            <div class="invalid-feedback"><?= ViewHelper::e($flashAll['errors']['password_confirmacion']) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Crear cuenta</button>

    <p class="text-center small mt-3 mb-0">
        ¿Ya tienes cuenta? <a href="/login" class="text-decoration-none">Iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Crear cuenta',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
