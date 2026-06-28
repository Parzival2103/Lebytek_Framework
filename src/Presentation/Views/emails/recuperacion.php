<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;
use Lebytek\Framework\Kernel\Constants\AppConstants;

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
                    <p style="margin:0 0 24px;">Recibimos una solicitud para restablecer tu contraseña:</p>
                    <p style="margin:0 0 24px;">
                        <a href="<?= ViewHelper::e($url) ?>"
                           style="background:#0d6efd; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; display:inline-block;">
                            Restablecer contraseña
                        </a>
                    </p>
                    <p style="margin:0 0 8px; font-size:13px; color:#6c757d;">
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <a href="<?= ViewHelper::e($url) ?>"><?= ViewHelper::e($url) ?></a>
                    </p>
                    <p style="margin:16px 0 0; font-size:13px; color:#6c757d;">
                        Si no solicitaste este cambio, ignora este mensaje; tu contraseña actual sigue siendo válida.
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
