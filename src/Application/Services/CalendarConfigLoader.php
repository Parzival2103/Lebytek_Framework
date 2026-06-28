<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\CalendarDefinition;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Logging\AppLogger;

final class CalendarConfigLoader
{
    private const DIR = ROOT_PATH . '/config/calendars';
    private const CRUD_DIR = ROOT_PATH . '/config/cruds';

    /** @var array<string, CalendarDefinition> */
    private array $cache = [];

    public function __construct(
        private readonly CalendarConfigValidator $validator,
    ) {}

    public function load(string $key): CalendarDefinition
    {
        $key = trim($key);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = self::DIR . '/' . $key . '.json';
        if (!is_readable($file)) {
            throw new ValidationException("No existe configuración de calendario para {$key}.");
        }

        $raw = file_get_contents($file);
        $config = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($config)) {
            AppLogger::error('Calendar config: JSON inválido', ['key' => $key, 'file' => $file]);
            throw new ValidationException("El JSON de {$key}.json es inválido.");
        }

        $resourceKey = (string)($config['calendar']['resource'] ?? '');
        $columns = $this->resourceColumns($resourceKey);

        $this->validator->validate($config, $columns);

        $definition = CalendarDefinition::fromArray($config);
        if ($definition->key() !== $key) {
            throw new ValidationException("calendar.key ({$definition->key()}) debe coincidir con el archivo ({$key}).");
        }

        $this->cache[$key] = $definition;
        return $definition;
    }

    /**
     * Definición del recurso CRUD subyacente, construida directamente desde su JSON
     * (sin tocar la base de datos). Suficiente para leer columnas declaradas, prefijo
     * de permisos y máquina de estados; la validación CRUD completa contra el esquema
     * real ocurre en su propio flujo.
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

    /**
     * Columnas declaradas del recurso CRUD (sin tocar la base de datos).
     *
     * @return list<string>
     */
    private function resourceColumns(string $resource): array
    {
        return $this->crudDefinition($resource)->columnNames();
    }

    /**
     * Clave del calendario vinculado a un recurso CRUD, si existe.
     */
    public function findKeyForResource(string $resource): ?string
    {
        $resource = trim($resource);
        if ($resource === '') {
            return null;
        }

        foreach ($this->listCalendars() as $key => $_title) {
            try {
                if ($this->load($key)->resource() === $resource) {
                    return $key;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** @return array<string,string> key => título */
    public function listCalendars(): array
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
                AppLogger::warning('Calendar config inválida omitida', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}
