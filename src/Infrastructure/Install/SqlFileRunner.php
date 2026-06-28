<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Install;

use Lebytek\Framework\Kernel\Database\Connection;
use RuntimeException;
use Throwable;

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
        $pdo        = Connection::getInstance();
        $sentencias = $this->partir($contenido);
        $total      = count($sentencias);

        InstallTrace::log('sql inicio | ruta=' . $ruta . ' | stmts=' . $total);

        foreach ($sentencias as $indice => $statement) {
            try {
                $this->ejecutarSentencia($pdo, $statement);
            } catch (Throwable $e) {
                $preview = mb_substr(preg_replace('/\s+/', ' ', $statement) ?: '', 0, 120);
                InstallTrace::log(
                    'sql FATAL | ruta=' . $ruta
                    . ' | stmt=' . ($indice + 1) . '/' . $total
                    . ' | preview=' . $preview
                    . ' | msg=' . $e->getMessage()
                );
                throw $e;
            }
        }

        InstallTrace::log('sql OK | ruta=' . $ruta);
    }

    /**
     * Ejecuta una sentencia y consume cualquier result set (SELECT en migraciones).
     */
    private function ejecutarSentencia(\PDO $pdo, string $statement): void
    {
        $stmt = $pdo->prepare($statement);
        $stmt->execute();
        do {
            $stmt->fetchAll();
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
    }
}
