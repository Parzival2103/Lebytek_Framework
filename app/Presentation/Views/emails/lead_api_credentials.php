<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $token */
/** @var string $apiBaseUrl */
/** @var string $docsUrl */
/** @var bool $showDocsCta */
/** @var string|null $dashboardUrl */
/** @var bool $showDashboardCta */
/** @var string|null $packagesUrl */
/** @var bool $showPackagesCta */
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

<?= $renderPartial('_copyable_field', [
    'label' => 'Base URL',
    'value' => $apiBaseUrl,
    'hint'  => 'Copia esta URL tal cual para el prefijo de tus peticiones a la API.',
    'showCopyButton' => true,
]) ?>

<?= $renderPartial('_copyable_field', [
    'label' => 'Token de acceso',
    'value' => $token,
    'hint'  => 'Copia <strong>todo</strong> el texto del recuadro, incluido el número y el símbolo <strong>|</strong> (ej. <code style="font-family:monospace;">15|abc…</code>). Ese valor completo es tu token Bearer.',
    'showCopyButton' => true,
]) ?>

<?= $renderPartial('_info_box', [
    'title' => 'Próximos pasos',
    'items' => [
        'Copia la Base URL y el Token desde los recuadros de arriba (usa el botón Copiar o selecciona todo el texto).',
        'Usa el token completo en el header <code style="font-family:monospace;">Authorization: Bearer &lt;token&gt;</code>.',
        'Consulta la documentación de integración para ver ejemplos y endpoints.',
        'Abre el <strong>Sandbox demo</strong> en la documentación: pega tu token, escanea el QR y envía tu primer WhatsApp en minutos.',
    ],
]) ?>

<?php if ($showDocsCta): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <?= $renderPartial('_cta_button', [
                'url'   => rtrim($docsUrl, '/').'/#sandbox',
                'label' => 'Probar demo (5 min)',
            ]) ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<?php if (! empty($showDashboardCta) && ! empty($dashboardUrl)): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <?= $renderPartial('_cta_button', [
                'url'   => $dashboardUrl,
                'label' => 'Ver métricas de uso',
            ]) ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<?php if (! empty($showPackagesCta) && ! empty($packagesUrl)): ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <?= $renderPartial('_cta_button', [
                'url'   => $packagesUrl,
                'label' => 'Ver paquetes',
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
