<?php
use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $token */
/** @var string $apiBaseUrl */
?>
<!DOCTYPE html>
<html lang="es">
<body style="margin:0; padding:24px; background:#f0f2f5; font-family: Arial, Helvetica, sans-serif; color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:32px;">
                <tr><td>
                    <h2 style="margin:0 0 16px;">Lebytek</h2>
                    <p style="margin:0 0 8px;">Hola <?= ViewHelper::e($nombre) ?>,</p>
                    <p style="margin:0 0 16px;">Tu demo está lista. Usa estas credenciales para conectar con nuestra API:</p>
                    <p style="margin:0 0 8px;"><strong>Base URL</strong></p>
                    <p style="margin:0 0 16px; font-family: monospace; background:#f8f9fa; padding:12px; border-radius:4px;"><?= ViewHelper::e($apiBaseUrl) ?></p>
                    <p style="margin:0 0 8px;"><strong>Token de acceso</strong></p>
                    <p style="margin:0 0 16px; font-family: monospace; background:#f8f9fa; padding:12px; border-radius:4px; word-break:break-all;"><?= ViewHelper::e($token) ?></p>
                    <p style="margin:0 0 16px; font-size:14px; color:#6c757d;">
                        Conserva este correo; el token <strong>no se vuelve a mostrar</strong>.
                    </p>
                    <p style="margin:16px 0 0; font-size:13px; color:#6c757d;">
                        Saludos,<br>Equipo Lebytek
                    </p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
