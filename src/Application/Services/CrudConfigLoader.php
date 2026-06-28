<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;
use Lebytek\Framework\Domain\Exceptions\ValidationException;
use Lebytek\Framework\Kernel\Logging\AppLogger;
use Lebytek\Framework\Kernel\Paths;

final class CrudConfigLoader
{
    private static function crudConfigDir(): string
    {
        return Paths::appRoot() . '/config/cruds';
    }

    /** @var array<string, CrudResourceDefinition> */
    private array $cache = [];

    public function __construct(
        private readonly CrudConfigValidator $validator
    ) {}

    public function load(string $resource): CrudResourceDefinition
    {
        $resource = trim($resource);
        if (isset($this->cache[$resource])) {
            return $this->cache[$resource];
        }

        $filePath = self::crudConfigDir() . '/' . $resource . '.json';
        if (!is_readable($filePath)) {
            throw new ValidationException("No existe configuración CRUD para el recurso {$resource}.");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            AppLogger::error('CRUD config: archivo vacío o ilegible', [
                'resource' => $resource,
                'file' => $filePath,
            ]);
            throw new ValidationException("El archivo de configuración {$resource}.json está vacío.");
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            AppLogger::error('CRUD config: JSON inválido', [
                'resource' => $resource,
                'file' => $filePath,
                'json_error' => json_last_error_msg(),
            ]);
            throw new ValidationException("El JSON de {$resource}.json es inválido.");
        }

        try {
            $this->validator->validate($config);
        } catch (ValidationException $e) {
            AppLogger::error('CRUD config: validación fallida', [
                'resource' => $resource,
                'file' => $filePath,
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ]);
            throw $e;
        }
        $definition = CrudResourceDefinition::fromArray($config);

        if ($definition->key() !== $resource) {
            throw new ValidationException("resource.key ({$definition->key()}) debe coincidir con el nombre del archivo ({$resource}).");
        }

        $this->cache[$resource] = $definition;
        return $definition;
    }

    public function listResources(): array
    {
        $resources = [];
        if (!is_dir(self::crudConfigDir())) {
            return $resources;
        }

        $files = scandir(self::crudConfigDir()) ?: [];
        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }

            $resource = pathinfo($file, PATHINFO_FILENAME);
            try {
                $definition = $this->load($resource);
                $resources[$definition->key()] = $definition->title();
            } catch (\Throwable $e) {
                AppLogger::warning('CRUD config inválida omitida del listado', [
                    'resource' => $resource,
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $resources;
    }
}
