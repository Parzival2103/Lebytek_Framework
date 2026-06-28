<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Security\Csrf;
use App\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="mb-4 text-center text-md-start">
    <h3 class="fw-bold mb-1">Recuperar contraseña</h3>
    <p class="text-muted small mb-0">Te enviaremos un enlace para restablecerla</p>
</div>

<form method="POST" action="/recuperar" novalidate>
    <?= Csrf::field() ?>

    <div class="mb-4">
        <label for="email" class="form-label fw-medium small">Correo electrónico</label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="correo@empresa.com"
                   value="<?= ViewHelper::old('email') ?>" autocomplete="email" autofocus required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 fw-semibold">Enviar instrucciones</button>

    <p class="text-center small mt-3 mb-0">
        <a href="/login" class="text-decoration-none">Volver a iniciar sesión</a>
    </p>
</form>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Recuperar contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
