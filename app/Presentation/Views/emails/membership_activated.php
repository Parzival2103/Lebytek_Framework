<?php

use Lebytek\Framework\Kernel\Helpers\ViewHelper;

/** @var string $nombre */
/** @var string $planNombre */
/** @var string $ciclo */
/** @var string $cuota */
/** @var string $apiBaseUrl */
/** @var string $token */

$renderPartial = static function (string $name, array $data = []): string {
    return ViewHelper::renderFile(ViewHelper::resolve('emails/partials/'.$name), $data);
};

echo $renderPartial('_shell_open', [
    'preheader'      => 'Tu membresía Lebytek está activa. Aquí tienes tu nuevo token de acceso.',
    'headerBg'       => '#198754',
    'headerTitle'    => 'Membresía activada',
    'headerSubtitle' => 'Gracias por confiar en Lebytek',
]);
?>

<p style="margin:0 0 12px; font-size:16px;">
    Hola <strong><?= ViewHelper::e($nombre) ?></strong>,
</p>

<p style="margin:0 0 24px; line-height:1.7;">
    Tu pago fue confirmado. Tu cuenta WhatsApp existente se actualizó al plan
    <strong><?= ViewHelper::e($planNombre) ?></strong> (<?= ViewHelper::e($ciclo) ?>).
    Cuota: <strong>$<?= ViewHelper::e($cuota) ?> MXN</strong>.
</p>

<?= $renderPartial('_copyable_field', [
    'label' => 'Base URL',
    'value' => $apiBaseUrl,
    'hint'  => 'Usa esta URL como prefijo de tus peticiones a la API.',
    'showCopyButton' => true,
]) ?>

<?= $renderPartial('_copyable_field', [
    'label' => 'Nuevo token de acceso',
    'value' => $token,
    'hint'  => 'Copia el token completo para el header Authorization: Bearer.',
    'showCopyButton' => true,
]) ?>

<?= $renderPartial('_warning_box', [
    'content' => '<strong>Importante:</strong><br><br>'
        . '• El token de demo anterior fue revocado por seguridad.<br>'
        . '• Este token <strong>no volverá a mostrarse</strong>. Guárdalo en un lugar seguro.<br>'
        . '• No necesitas escanear un nuevo QR: tu instancia WhatsApp sigue siendo la misma.',
]) ?>

<p style="margin:0; line-height:1.7;">
    Si tienes dudas, escríbenos a
    <a href="mailto:soporte@lebytek.com" style="color:#0d6efd;">soporte@lebytek.com</a>.
</p>

<?= $renderPartial('_shell_close') ?>
