<?php

declare(strict_types=1);

/**
 * Comprobación mínima sin PHPUnit (útil cuando dev deps no están instaladas).
 * Ejecutar: php tests/smoke.php
 */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    fwrite(STDERR, "vendor/autoload.php no encontrado. Ejecute: composer install\n");
    exit(1);
}
require_once $autoload;

fwrite(STDOUT, "smoke: autoload OK\n");
exit(0);
