<?php

declare(strict_types=1);

$dir = dirname(__DIR__).'/.superpowers/sdd';
$escape = static function (string $html): string {
    return str_replace("'", "''", $html);
};

$templates = [
    ['lead_welcome', 'Recibimos tu solicitud — Lebytek', file_get_contents($dir.'/seed-lead_welcome.html')],
    ['lead_api_credentials', 'Tus credenciales demo — Lebytek', file_get_contents($dir.'/seed-lead_api_credentials.html')],
    ['membership_activated', 'Tu membresía Lebytek está activa', file_get_contents($dir.'/seed-membership_activated.html')],
    ['membership_payment_failed', 'Problema con tu pago — acción requerida',
        '<p>Hola {{nombre}},</p><p>No pudimos cobrar tu plan {{plan}} ({{ciclo}}). Tienes {{grace_hours}} horas para actualizar el pago:</p><p><a href="{{retry_url}}">Reintentar pago</a></p>'],
    ['membership_cancelled_reactivate', 'Tu cuenta fue cancelada — puedes reactivar',
        '<p>Hola {{nombre}},</p><p>Cancelamos {{cuenta}} por falta de pago. Reactiva cuando quieras:</p><p><a href="{{retry_url}}">Reactivar membresía</a></p>'],
];

$lines = ["-- Idempotent plantilla catalog seed by clave.\n"];
foreach ($templates as [$clave, $asunto, $cuerpo]) {
    if ($cuerpo === false) {
        fwrite(STDERR, "missing body for {$clave}\n");
        exit(1);
    }
    $lines[] = "INSERT INTO `dom_mkt_plantillas` (`clave`, `asunto`, `cuerpo`, `activo`)";
    $lines[] = "SELECT '{$clave}', '{$asunto}', '".$escape($cuerpo)."', 1";
    $lines[] = "FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = '{$clave}');\n";
}

$lines[] = "-- Align legacy stub key if present";
$lines[] = "UPDATE `dom_mkt_plantillas`";
$lines[] = "SET `clave` = 'lead_welcome',";
$lines[] = "    `asunto` = 'Recibimos tu solicitud — Lebytek'";
$lines[] = "WHERE `clave` = 'lead_autoresponder'";
$lines[] = "  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `dom_mkt_plantillas` WHERE `clave` = 'lead_welcome') t);";

$out = dirname(__DIR__).'/database/migrations/20260715200100_mkt_plantillas_seed_catalog.sql';
file_put_contents($out, implode("\n", $lines)."\n");
fwrite(STDOUT, "wrote {$out}\n");
