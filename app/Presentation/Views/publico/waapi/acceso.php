<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

$error = $error ?? null;
?>
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <h1 class="h3 mb-2 text-center">Iniciar sesión</h1>
                <p class="text-muted text-center mb-4">
                    Pega el token completo del correo de credenciales (ej. <code class="font-monospace">15|abc…</code>).
                </p>

                <?php if (is_string($error) && $error !== ''): ?>
                    <div class="alert alert-danger"><?= ViewHelper::e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/portal/acceso" class="card border-0 shadow-sm waapi-metric-card">
                    <div class="card-body p-4">
                        <?= ViewHelper::csrfField() ?>
                        <div class="mb-3">
                            <label for="token" class="form-label">Token de acceso</label>
                            <textarea
                                id="token"
                                name="token"
                                class="form-control waapi-token-field"
                                rows="4"
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="15|pega_aqui_tu_token_completo"
                            ></textarea>
                            <p class="waapi-token-hint mt-2 mb-0">
                                Copia todo el valor del correo, incluido el número y el símbolo <strong>|</strong>.
                                El token se guarda solo en tu sesión en este servidor.
                            </p>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar al panel</button>
                    </div>
                </form>

                <p class="small text-muted text-center mt-3 mb-0">
                    ¿No tienes token? Solicita una demo en <a href="https://lebytek.com">lebytek.com</a>.
                </p>
            </div>
        </div>
    </div>
</section>
