<?php
declare(strict_types=1);

namespace App\Application\Reporte;

use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Reporte\ReporteFuente;
use App\Kernel\Logging\AppLogger;

/**
 * Carga fuentes reportables desde config/reportes/{key}.json, validándolas contra las
 * columnas declaradas del recurso CRUD (sin tocar la base de datos). Espejo de
 * CalendarConfigLoader.
 */
final class ReporteConfigLoader
{
    private const DIR = ROOT_PATH . '/config/reportes';
    private const CRUD_DIR = ROOT_PATH . '/config/cruds';

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

        $file = self::DIR . '/' . $key . '.json';
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
        $file = self::CRUD_DIR . '/' . $resource . '.json';
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
        if (!is_dir(self::DIR)) {
            return $out;
        }
        foreach (scandir(self::DIR) ?: [] as $file) {
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
