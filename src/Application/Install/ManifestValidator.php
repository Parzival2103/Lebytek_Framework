<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Install;

/**
 * Valida un conjunto de manifiestos contra el estado real del filesystem.
 * Acumula errores (estilo CrudConfigValidator); no lanza excepciones.
 */
final class ManifestValidator
{
    /**
     * @param array<string,ModuleManifest> $manifests
     * @param array{migraciones:list<string>,seeds:list<string>,cruds:list<string>} $contexto
     * @return list<string>
     */
    public static function errores(array $manifests, array $contexto): array
    {
        $errores = [];

        $clavesValidas = array_keys($manifests);

        // 1) Dependencias resueltas.
        foreach ($manifests as $clave => $m) {
            foreach ($m->requiere as $dep) {
                if (!in_array($dep, $clavesValidas, true)) {
                    $errores[] = "Módulo {$clave}: requiere \"{$dep}\" que no existe.";
                }
            }
        }

        // 2) Dueño único de cada migración y seed presentes en disco.
        $errores = array_merge($errores, self::erroresPropiedad(
            $manifests,
            $contexto['migraciones'] ?? [],
            static fn(ModuleManifest $m): array => $m->migraciones,
            'migración'
        ));
        $errores = array_merge($errores, self::erroresPropiedad(
            $manifests,
            $contexto['seeds'] ?? [],
            static fn(ModuleManifest $m): array => $m->seeds,
            'seed'
        ));

        // 3) Cruds declarados existen.
        $crudsPresentes = $contexto['cruds'] ?? [];
        foreach ($manifests as $clave => $m) {
            foreach ($m->cruds as $crud) {
                if (!in_array($crud, $crudsPresentes, true)) {
                    $errores[] = "Módulo {$clave}: crud \"{$crud}\" no existe en config/cruds/.";
                }
            }
        }

        return $errores;
    }

    /**
     * @param array<string,ModuleManifest> $manifests
     * @param list<string> $presentes archivos reales en disco
     * @param callable(ModuleManifest):list<string> $extraer
     * @return list<string>
     */
    private static function erroresPropiedad(array $manifests, array $presentes, callable $extraer, string $tipo): array
    {
        $errores = [];

        // Conteo de dueños por archivo declarado.
        $duenos = [];
        foreach ($manifests as $clave => $m) {
            foreach ($extraer($m) as $archivo) {
                $duenos[$archivo][] = $clave;
                if (!in_array($archivo, $presentes, true)) {
                    $errores[] = "Módulo {$clave}: {$tipo} \"{$archivo}\" declarada pero ausente en disco.";
                }
            }
        }

        // Doble dueño.
        foreach ($duenos as $archivo => $claves) {
            if (count($claves) > 1) {
                $errores[] = "El {$tipo} \"{$archivo}\" tiene múltiples dueños: " . implode(', ', $claves) . '.';
            }
        }

        // Huérfanos (en disco sin dueño).
        foreach ($presentes as $archivo) {
            if (!isset($duenos[$archivo])) {
                $errores[] = "El {$tipo} \"{$archivo}\" no tiene dueño en ningún manifiesto.";
            }
        }

        return $errores;
    }
}
