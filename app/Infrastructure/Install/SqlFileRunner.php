<?php

declare(strict_types=1);

namespace App\Infrastructure\Install;

use App\Kernel\Database\Connection;
use RuntimeException;

/**
 * Lee y ejecuta archivos .sql multi-statement (reutiliza el partido de
 * sentencias probado en scripts/seed.php) y calcula su checksum sha256.
 */
final class SqlFileRunner
{
    public function checksum(string $ruta): string
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer {$ruta}");
        }
        return hash('sha256', $contenido);
    }

    /**
     * @return list<string>
     */
    public function partir(string $sql): array
    {
        $lines  = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $out    = [];

        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '--')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (preg_match('/;\s*$/', rtrim($line))) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $out[] = $tail;
        }

        return $out;
    }

    public function ejecutar(string $ruta): void
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer {$ruta}");
        }
        $pdo = Connection::getInstance();
        foreach ($this->partir($contenido) as $statement) {
            $pdo->exec($statement);
        }
    }
}
