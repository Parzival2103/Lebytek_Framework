<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Csrf;
use Lebytek\Framework\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();
$token    = (string) ($token ?? '');

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Nueva contraseña</h3>
    <p class="text-muted small mb-0">Define la nueva contraseña de tu cuenta</p>
</div>

<form method="POST" action="/restablecer" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= ViewHelper::e($token) ?>">

    <div class="mb-3">
        <label for="password" class="form-label fw-medium small">Nueva contraseña</label>
        <input type="password" id="password" name="password"
               class="form-control <?= !empty($flashAll['errors']['password']) ? 'is-invalid' : '' ?>"
               placeholder="Mínimo 8 caracteres" autocomplete="new-password" autofocus required>
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

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Guardar contraseña</button>

    <p class="text-center small mt-3 mb-0">
        <a href="/login" class="text-decoration-none">Volver a iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Restablecer contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
