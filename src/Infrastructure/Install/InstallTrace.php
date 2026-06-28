<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Install;

/**
 * Trazas unificadas del wizard/CLI de instalación en error_log.
 */
final class InstallTrace
{
    public static function log(string $message): void
    {
        error_log('[install] ' . $message);
    }
}
