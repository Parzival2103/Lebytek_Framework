<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CalendarDefinition;
use App\Domain\Entities\CrudResourceDefinition;
use App\Domain\Exceptions\ValidationException;
use App\Kernel\Logging\AppLogger;

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
     * Columnas declaradas del recurso CRUD (sin tocar la base de datos): se derivan
     * del JSON de configuración del recurso. Suficiente para validar el mapeo del
     * calendario; la validación CRUD completa (contra el esquema real) ocurre en su
     * propio flujo.
     *
     * @return list<string>
     */
    private function resourceColumns(string $resource): array
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

        return CrudResourceDefinition::fromArray($crudConfig)->columnNames();
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
