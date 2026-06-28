<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();
$email    = (string) ($email ?? '');

ob_start();
?>
<div class="text-center">
    <div class="display-5 text-primary mb-3" aria-hidden="true"><i class="bi bi-envelope-check"></i></div>
    <h3 class="fw-bold mb-2">Revisa tu correo</h3>
    <p class="text-muted small mb-4">
        Enviamos un enlace de verificación<?= $email !== '' ? ' a <strong>' . ViewHelper::e($email) . '</strong>' : '' ?>.
        La cuenta se activa al confirmar el correo.
    </p>

    <?php if ($email !== ''): ?>
        <form method="POST" action="/registro/reenviar" class="mb-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="email" value="<?= ViewHelper::e($email) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Reenviar correo
            </button>
        </form>
    <?php endif; ?>

    <p class="small mb-0"><a href="/login" class="text-decoration-none">Volver a iniciar sesión</a></p>
</div>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Revisa tu correo',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
