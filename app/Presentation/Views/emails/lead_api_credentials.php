<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $token */
/** @var string $apiBaseUrl */
/** @var string $docsUrl */
/** @var bool $showDocsCta */
/** @var string|null $empresaNombre */

$renderPartial = static function (string $name, array $data = []): string {
    return ViewHelper::renderFile(ViewHelper::resolve('emails/partials/'.$name), $data);
};

echo $renderPartial('_shell_open', [
    'preheader'      => 'Tu demo está lista. Aquí tienes tu token y la base URL para conectar con la API de Lebytek.',
    'headerBg'       => '#0d6efd',
    'headerTitle'    => 'Tu acceso a la API está listo',
    'headerSubtitle' => 'Bienvenido a la plataforma de mensajería de Lebytek',
    'empresaNombre'  => $empresaNombre ?? null,
]);
?>

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong><?= ViewHelper::e($nombre) ?></strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Tu solicitud ha sido aprobada y ya puedes comenzar a utilizar nuestra
    <strong>API de WhatsApp</strong>. A continuación encontrarás tus credenciales de acceso.
</p>

<?= $renderPartial('_code_block', [
    'label' => 'Base URL',
    'value' => $apiBaseUrl,
]) ?>

<?= $renderPartial('_code_block', [
    'label' => 'Token de acceso',
    'value' => $token,
]) ?>

<?= $renderPartial('_info_box', [
    'title' => 'Próximos pasos',
    'items' => [
        'Guarda este correo en un lugar seguro.',
        'Usa el token en el header Authorization: Bearer para autenticar tus peticiones.',
        'Consulta la documentación de integración para ver ejemplos y endpoints.',
        'Conecta tu instancia de WhatsApp siguiendo la guía técnica.',
    ],
]) ?>

<?php if ($showDocsCta): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <?= $renderPartial('_cta_button', [
                'url'   => $docsUrl,
                'label' => 'Ver documentación',
            ]) ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<?= $renderPartial('_warning_box', [
    'content' => '<strong>Importante:</strong><br><br>'
        . '• Estas credenciales son personales y confidenciales.<br>'
        . '• El token de acceso <strong>no volverá a mostrarse</strong>.<br>'
        . '• No compartas tus credenciales con terceros.<br>'
        . '• Si sospechas que tu token fue comprometido, contacta a '
        . '<a href="mailto:soporte@lebytek.com" style="color:#856404;">soporte@lebytek.com</a>.<br>'
        . '• El uso de la API está sujeto a nuestras políticas de seguridad y uso responsable.',
]) ?>

<p style="margin:0; line-height:1.7;">
    Gracias por confiar en <strong>Lebytek</strong>. Estamos emocionados de formar parte de tu proyecto
    y ayudarte a integrar WhatsApp en tus aplicaciones.
</p>

<?= $renderPartial('_shell_close', ['empresaNombre' => $empresaNombre ?? null]) ?>
