<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Interfaces\SettingsSectionProviderInterface;

final class SettingsSectionRegistry
{
    /** @var list<SettingsSectionProviderInterface> */
    private array $providers;

    /** @param list<SettingsSectionProviderInterface> $providers */
    public function __construct(array $providers = [])
    {
        $this->providers = array_values($providers);
    }

    /**
     * Providers cuyo permiso posee el usuario.
     * @param list<string> $permisos
     * @return list<SettingsSectionProviderInterface>
     */
    public function visibles(array $permisos): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn(SettingsSectionProviderInterface $p) => in_array($p->permiso(), $permisos, true)
        ));
    }

    /**
     * Nombres de campo planos de los providers visibles (para persistir en guardar()).
     * @param list<string> $permisos
     * @return list<string>
     */
    public function fieldNames(array $permisos): array
    {
        $names = [];
        foreach ($this->visibles($permisos) as $provider) {
            foreach ($provider->campos() as $campo) {
                if (isset($campo['name']) && is_string($campo['name'])) {
                    $names[] = $campo['name'];
                }
            }
        }
        return $names;
    }
}
