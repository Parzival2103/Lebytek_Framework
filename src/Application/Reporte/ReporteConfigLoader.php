<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Domain\Reporte\ReporteFuente;
use Lebytek\Framework\Kernel\Logging\AppLogger;
use Lebytek\Framework\Kernel\Paths;

/**
 * Carga fuentes reportables desde config/reportes/{key}.json, validándolas contra las
 * columnas declaradas del recurso CRUD (sin tocar la base de datos). Espejo de
 * CalendarConfigLoader.
 */
final class ReporteConfigLoader
{
    private static function dir(): string
    {
        return Paths::appRoot() . '/config/reportes';
    }

    private static function crudDir(): string
    {
        return Paths::appRoot() . '/config/cruds';
    }

    /** @var array<string, ReporteFuente> */
    private array $cache = [];

    public function __construct(
        private readonly ReporteConfigValidator $validator,
    ) {}

    public function load(string $key): ReporteFuente
    {
        $key = trim($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = self::dir() . '/' . $key . '.json';
        if ($key === '' || !is_readable($file)) {
            throw new ValidationException("No existe configuración de reporte para {$key}.");
        }

        $raw = file_get_contents($file);
        $config = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($config)) {
            AppLogger::error('Reporte config: JSON inválido', ['key' => $key, 'file' => $file]);
            throw new ValidationException("El JSON de {$key}.json es inválido.");
        }

        $resource = (string) ($config['fuente']['resource'] ?? '');
        $definition = $this->crudDefinition($resource);
        $columns = $definition->columnNames();
        $relations = array_keys($definition->relations());

        $this->validator->validate($config, $columns, $relations);

        $fuente = ReporteFuente::fromArray($key, $config);
        $this->cache[$key] = $fuente;
        return $fuente;
    }

    /**
     * Definición del recurso CRUD subyacente, construida desde su JSON (sin BD).
     */
    public function crudDefinition(string $resource): CrudResourceDefinition
    {
        $resource = trim($resource);
        $file = self::crudDir() . '/' . $resource . '.json';
        if ($resource === '' || !is_readable($file)) {
            throw new ValidationException("No existe configuración CRUD para el recurso {$resource}.");
        }

        $raw = file_get_contents($file);
        $crudConfig = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($crudConfig)) {
            throw new ValidationException("El JSON del recurso CRUD {$resource}.json es inválido.");
        }

        return CrudResourceDefinition::fromArray($crudConfig);
    }

    /** @return array<string,string> key => título */
    public function listFuentes(): array
    {
        $out = [];
        if (!is_dir(self::dir())) {
            return $out;
        }
        foreach (scandir(self::dir()) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $key = pathinfo($file, PATHINFO_FILENAME);
            try {
                $out[$key] = $this->load($key)->title();
            } catch (\Throwable $e) {
                AppLogger::warning('Reporte config inválida omitida', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}
