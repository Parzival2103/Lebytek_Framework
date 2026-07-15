<?php
// app/Presentation/Views/publico/verificar_demo.php
declare(strict_types=1);

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$status = (string) ($status ?? 'invalid');
$token  = (string) ($token ?? '');
$lead   = is_array($lead ?? null) ? $lead : null;

$formStatuses = ['form', 'wrong_code'];
?>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <h1 class="h3 mb-4 text-center">Verificar tu correo</h1>

                <?php if (in_array($status, $formStatuses, true)): ?>
                    <?php if ($status === 'wrong_code'): ?>
                        <div class="alert alert-danger">El código ingresado no es correcto. Intenta de nuevo.</div>
                    <?php endif; ?>

                    <p class="text-muted text-center mb-4">
                        Ingresa el código de 6 caracteres que enviamos a tu correo para confirmar tu solicitud de demo.
                    </p>

                    <form method="post" action="/verificar-demo/<?= rawurlencode($token) ?>" class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <?= ViewHelper::csrfField() ?>
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código de verificación</label>
                                <input
                                    type="text"
                                    id="codigo"
                                    name="codigo"
                                    class="form-control text-center text-uppercase"
                                    maxlength="6"
                                    required
                                    autocomplete="one-time-code"
                                    autofocus
                                    placeholder="AB12CD"
                                >
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Verificar</button>
                        </div>
                    </form>
                <?php elseif ($status === 'ok'): ?>
                    <div class="alert alert-success text-center">
                        <p class="fw-semibold mb-2"><i class="bi bi-check-circle-fill me-1"></i> Correo verificado</p>
                        <p class="mb-0">Nuestro equipo revisará tu solicitud y te contactaremos en breve con tus credenciales de acceso.</p>
                    </div>
                <?php elseif ($status === 'already_verified'): ?>
                    <div class="alert alert-info text-center">
                        Este correo ya fue verificado previamente. Nuestro equipo te contactará en breve.
                    </div>
                <?php elseif ($status === 'expired'): ?>
                    <div class="alert alert-warning text-center">
                        Este enlace de verificación ya caducó. Solicita una demo nuevamente para recibir un nuevo código.
                    </div>
                <?php elseif ($status === 'locked'): ?>
                    <div class="alert alert-danger text-center">
                        Se alcanzó el número máximo de intentos para este código. Solicita una demo nuevamente para recibir un nuevo código.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        El enlace de verificación no es válido. Revisa el correo o solicita una demo nuevamente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
