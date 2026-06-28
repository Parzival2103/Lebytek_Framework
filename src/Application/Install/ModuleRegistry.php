<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Install;

final class ModuleRegistry
{
    /** @var array<string,ModuleManifest>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $directorio) {}

    /**
     * @return array<string,ModuleManifest> clave => manifiesto
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $out   = [];
        $files = glob(rtrim($this->directorio, '/\\') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $raw = require $file;
            if (!is_array($raw)) {
                continue;
            }
            $manifest = ModuleManifest::fromArray($raw);
            $out[$manifest->clave] = $manifest;
        }

        return $this->cache = $out;
    }

    public function get(string $clave): ?ModuleManifest
    {
        return $this->all()[$clave] ?? null;
    }
}
