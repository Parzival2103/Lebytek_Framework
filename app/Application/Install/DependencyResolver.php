<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Exceptions\InstallerException;

final class DependencyResolver
{
    /**
     * Devuelve las claves a instalar en orden topológico (dependencias primero),
     * expandiendo la selección con sus dependencias transitivas. 'core' siempre
     * se incluye si existe.
     *
     * @param array<string,ModuleManifest> $manifests
     * @param list<string> $seleccion
     * @return list<string>
     */
    public function resolver(array $manifests, array $seleccion): array
    {
        $objetivo = $seleccion;
        if (isset($manifests['core'])) {
            $objetivo[] = 'core';
        }

        $orden    = [];
        $visitado = []; // clave => true (resuelto), false (en proceso → ciclo)

        $visit = function (string $clave) use (&$visit, &$orden, &$visitado, $manifests): void {
            if (($visitado[$clave] ?? null) === true) {
                return;
            }
            if (($visitado[$clave] ?? null) === false) {
                throw InstallerException::cicloDependencias([$clave]);
            }
            $manifest = $manifests[$clave] ?? null;
            if ($manifest === null) {
                throw InstallerException::manifiestoInvalido("dependencia \"{$clave}\" no existe.");
            }
            $visitado[$clave] = false;
            foreach ($manifest->requiere as $dep) {
                $visit($dep);
            }
            $visitado[$clave] = true;
            $orden[] = $clave;
        };

        foreach (array_unique($objetivo) as $clave) {
            $visit($clave);
        }

        return $orden;
    }
}
