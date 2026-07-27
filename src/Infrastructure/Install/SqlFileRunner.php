<?php

declare(strict_types=1);

namespace Lebytek\Framework\Infrastructure\Install;

use Lebytek\Framework\Kernel\Database\Connection;
use RuntimeException;
use Throwable;

/**
 * Lee y ejecuta archivos .sql multi-sentencia y calcula su checksum sha256.
 *
 * Partidor único del paquete: lo consumen el instalador (Installer) y
 * scripts/seed.php. Ver partir() para el alcance del análisis de cadenas.
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
     * Parte un .sql multi-sentencia respetando literales de cadena.
     *
     * Corta en ";" sólo fuera de cadena. Antes acumulaba líneas y cortaba en
     * cuanto una línea *terminaba* en ";", así que cualquier archivo con HTML o
     * CSS embebido se partía a media cadena: cada declaración CSS ocupa su
     * propia línea terminada en ";".
     *
     * Deliberadamente NO soporta DELIMITER: ninguna migración del paquete
     * declara rutinas almacenadas. Si alguna lo hiciera, esto debe crecer antes.
     *
     * @return list<string> sentencias sin las vacías, en orden de aparición
     */
    public function partir(string $sql): array
    {
        $out    = [];
        $buffer = '';
        $len    = strlen($sql);
        $i      = 0;

        // Delimitador de la cadena en curso: null fuera de cadena.
        $quote = null;

        while ($i < $len) {
            $ch   = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($quote !== null) {
                $buffer .= $ch;

                if ($ch === '\\' && $quote !== '`') {
                    // Escape con barra: consume también el carácter escapado.
                    // Los identificadores con backtick no usan barra como escape.
                    if ($next !== '') {
                        $buffer .= $next;
                        $i += 2;
                        continue;
                    }
                } elseif ($ch === $quote) {
                    if ($next === $quote) {
                        // Comilla duplicada: '' o "" o `` — sigue dentro.
                        $buffer .= $next;
                        $i += 2;
                        continue;
                    }
                    $quote = null;
                }

                $i++;
                continue;
            }

            // --- fuera de cadena ---

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote   = $ch;
                $buffer .= $ch;
                $i++;
                continue;
            }

            // Comentario de línea: -- (exige espacio o fin, como MySQL) o #
            if (($ch === '-' && $next === '-' && $this->esInicioComentarioLinea($sql, $i))
                || $ch === '#'
            ) {
                $i += strcspn($sql, "\r\n", $i);
                continue;
            }

            // Comentario de bloque
            if ($ch === '/' && $next === '*') {
                $fin = strpos($sql, '*/', $i + 2);
                $i   = $fin === false ? $len : $fin + 2;
                continue;
            }

            if ($ch === ';') {
                $buffer .= $ch;
                $this->apilar($out, $buffer);
                $buffer = '';
                $i++;
                continue;
            }

            $buffer .= $ch;
            $i++;
        }

        $this->apilar($out, $buffer);

        return $out;
    }

    /**
     * MySQL exige que `--` vaya seguido de espacio, tabulador o fin de línea
     * para ser comentario. Sin esto, `a--b` o un `--` decorativo pegado a texto
     * se comerían el resto de la línea.
     */
    private function esInicioComentarioLinea(string $sql, int $i): bool
    {
        $despues = $sql[$i + 2] ?? "\n";

        return $despues === ' ' || $despues === "\t" || $despues === "\n" || $despues === "\r";
    }

    /** @param list<string> $out */
    private function apilar(array &$out, string $buffer): void
    {
        $stmt = trim($buffer);
        if ($stmt === '' || $stmt === ';') {
            return;
        }
        $out[] = $stmt;
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
