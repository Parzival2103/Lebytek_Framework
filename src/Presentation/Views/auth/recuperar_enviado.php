<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Security\Session;

$__theme  = get_defined_vars();
$flashAll = $flashAll ?? Session::flashAll();

ob_start();
?>
<div class="text-center">
    <div class="display-5 text-primary mb-3" aria-hidden="true"><i class="bi bi-envelope-check"></i></div>
    <h3 class="fw-bold mb-2">Revisa tu correo</h3>
    <p class="text-muted small mb-4">
        Si el correo existe en el sistema, enviamos las instrucciones para restablecer la contraseña.
        El enlace vence en 60 minutos.
    </p>
    <p class="small mb-0"><a href="/login" class="text-decoration-none">Volver a iniciar sesión</a></p>
</div>
<?php
$contentHtml = ob_get_clean();

echo ViewHelper::partial('auth_card', $__theme + [
    'pageTitle'   => 'Recuperar contraseña',
    'flashAll'    => $flashAll,
    'contentHtml' => $contentHtml,
]);
