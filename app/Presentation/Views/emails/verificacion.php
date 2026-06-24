<?php
use App\Kernel\Helpers\ViewHelper;
use App\Kernel\Constants\AppConstants;

/** @var string $nombre */
/** @var string $url */
/** @var string|null $empresaNombre */
$empresaNombre = AppConstants::resolveEmpresaNombre($empresaNombre ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:24px; background:#f0f2f5; font-family: Arial, Helvetica, sans-serif; color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px;">
                <tr><td>
                    <h2 style="margin:0 0 16px;"><?= ViewHelper::e($empresaNombre) ?></h2>
                    <p style="margin:0 0 8px;">Hola <?= ViewHelper::e($nombre) ?>,</p>
                    <p style="margin:0 0 24px;">Gracias por registrarte. Confirma tu correo para activar tu cuenta:</p>
                    <p style="margin:0 0 24px;">
                        <a href="<?= ViewHelper::e($url) ?>"
                           style="background:#0d6efd; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block;">
                            Verificar mi correo
                        </a>
                    </p>
                    <p style="margin:0 0 8px; font-size:13px; color:#6c757d;">
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href="<?= ViewHelper::e($url) ?>"><?= ViewHelper::e($url) ?></a>
                    </p>
                    <p style="margin:16px 0 0; font-size:13px; color:#6c757d;">
                        Si no creaste esta cuenta, ignora este mensaje.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
