<?php

declare(strict_types=1);

namespace App\Kernel\Logging;

/*
|--------------------------------------------------------------------------
| AppLogger — Logger simple con niveles PSR-3
|--------------------------------------------------------------------------
| Escribe logs en storage/logs/app-YYYY-MM-DD.log
| Sin dependencias externas.
*/

final class AppLogger
{
    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('CRITICAL', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $logDir = STORAGE_PATH . '/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $date    = date('Y-m-d');
        $time    = date('Y-m-d H:i:s');
        $file    = "{$logDir}/app-{$date}.log";
        $ctx     = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line    = "[{$time}] [{$level}] {$message}{$ctx}" . PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
