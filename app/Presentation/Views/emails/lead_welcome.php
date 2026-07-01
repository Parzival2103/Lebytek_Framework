<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $landingUrl */
/** @var string|null $empresaNombre */

$renderPartial = static function (string $name, array $data = []): string {
    return ViewHelper::renderFile(ViewHelper::resolve('emails/partials/'.$name), $data);
};

echo $renderPartial('_shell_open', [
    'preheader'      => 'Recibimos tu solicitud. Un especialista de Lebytek te contactará pronto con los siguientes pasos.',
    'headerBg'       => '#0f172a',
    'headerTitle'    => 'Recibimos tu solicitud',
    'headerSubtitle' => 'Estamos preparando tu demo de WhatsApp API',
    'empresaNombre'  => $empresaNombre ?? null,
]);
?>

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong><?= ViewHelper::e($nombre) ?></strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Gracias por contactarnos. Hemos recibido tu solicitud de demo y un especialista de
    <strong>Lebytek</strong> la revisará en breve para ayudarte a integrar
    <strong>WhatsApp en tu negocio</strong> con nuestra API.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td style="padding:12px 0; border-bottom:1px solid #e9ecef;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>API WhatsApp</strong> — envía y recibe mensajes desde tus sistemas
        </td>
    </tr>
    <tr>
        <td style="padding:12px 0; border-bottom:1px solid #e9ecef;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>Campañas masivas</strong> — promociones y avisos con seguimiento
        </td>
    </tr>
    <tr>
        <td style="padding:12px 0;">
            <span style="color:#25D366; font-weight:bold; margin-right:8px;">&#10003;</span>
            <strong>Integración rápida</strong> — conecta tu app en minutos, no semanas
        </td>
    </tr>
</table>

<?= $renderPartial('_info_box', [
    'title'       => '¿Qué sigue?',
    'borderColor' => '#25D366',
    'bgColor'     => '#f0fdf4',
    'items'       => [
        'Revisamos tu solicitud y validamos tu caso de uso.',
        'Te contactamos para confirmar detalles y el plan adecuado.',
        'Recibirás por correo tus credenciales de acceso a la API.',
    ],
]) ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <?= $renderPartial('_cta_button', [
                'url'     => rtrim($landingUrl, '/').'#paquetes',
                'label'   => 'Ver paquetes y precios',
                'bgColor' => '#25D366',
            ]) ?>
        </td>
    </tr>
</table>

<p style="margin:0; font-size:14px; color:#6c757d; line-height:1.7;">
    ¿Tienes dudas? Escríbenos a
    <a href="mailto:soporte@lebytek.com" style="color:#0d6efd;">soporte@lebytek.com</a>
    y con gusto te ayudamos.
</p>

<?= $renderPartial('_shell_close', ['empresaNombre' => $empresaNombre ?? null]) ?>
