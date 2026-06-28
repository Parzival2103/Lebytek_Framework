<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Install;

use Lebytek\Framework\Domain\Exceptions\InstallerException;

final class ModuleManifest
{
    /**
     * @param list<string> $requiere
     * @param list<string> $migraciones
     * @param list<string> $seeds
     * @param list<string> $cruds
     * @param list<string> $permisos
     * @param list<string> $menu
     * @param list<string> $providers
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $version,
        public readonly bool $obligatorio,
        public readonly array $requiere,
        public readonly array $migraciones,
        public readonly array $seeds,
        public readonly array $cruds,
        public readonly array $permisos,
        public readonly array $menu,
        public readonly array $providers,
        public readonly ?string $bootstrapSql = null,
    ) {}

    public static function fromArray(array $raw): self
    {
        $clave = (string) ($raw['clave'] ?? '');
        if ($clave === '') {
            throw InstallerException::manifiestoInvalido('falta "clave".');
        }
        $version = (string) ($raw['version'] ?? '');
        if ($version === '') {
            throw InstallerException::manifiestoInvalido("módulo {$clave}: falta \"version\".");
        }

        $strList = static fn(mixed $v): array => array_values(array_map(
            'strval',
            array_filter(is_array($v) ? $v : [], static fn($x) => is_scalar($x))
        ));

        return new self(
            clave:       $clave,
            nombre:      (string) ($raw['nombre'] ?? $clave),
            descripcion: (string) ($raw['descripcion'] ?? ''),
            version:     $version,
            obligatorio: (bool) ($raw['obligatorio'] ?? false),
            requiere:    $strList($raw['requiere']    ?? []),
            migraciones: $strList($raw['migraciones'] ?? []),
            seeds:       $strList($raw['seeds']        ?? []),
            cruds:       $strList($raw['cruds']        ?? []),
            permisos:    $strList($raw['permisos']     ?? []),
            menu:        $strList($raw['menu']         ?? []),
            providers:   $strList($raw['providers']    ?? []),
            bootstrapSql: isset($raw['bootstrap_sql']) && is_string($raw['bootstrap_sql']) && $raw['bootstrap_sql'] !== ''
                ? $raw['bootstrap_sql']
                : null,
        );
    }
}
