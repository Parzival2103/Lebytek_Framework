<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Interfaces\ModuleStateRepositoryInterface;

/**
 * Fuente única del estado del despliegue: versión de plataforma, módulos
 * (declarada vs instalada), migraciones pendientes, checksums modificados y
 * health checks. La consumen la página admin y el CLI status.
 */
final class DeploymentStatus
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Installer $installer,
        private readonly ModuleStateRepositoryInterface $modulos,
        private readonly string $plataformaVersion,
    ) {}

    /**
     * @return array{
     *   plataformaVersion:string,
     *   modulos:array<string,array{declarada:string,instalada:?string,activo:bool,actualizacionDisponible:bool}>,
     *   migracionesPendientes:list<array{modulo:string,archivo:string}>,
     *   checksumsModificados:list<array{modulo:string,archivo:string}>,
     *   healthChecks:list<array{clave:string,ok:bool,detalle:string}>
     * }
     */
    public function reporte(): array
    {
        $instalados = $this->modulos->instalados();
        $manifests  = $this->registry->all();

        $modulos = [];
        foreach ($manifests as $clave => $manifest) {
            $instalada = $instalados[$clave]['version'] ?? null;
            $modulos[$clave] = [
                'declarada'                => $manifest->version,
                'instalada'                => $instalada,
                'activo'                   => $instalados[$clave]['activo'] ?? false,
                'actualizacionDisponible'  => $instalada !== null && $instalada !== $manifest->version,
            ];
        }

        // Plan sobre todos los módulos declarados → pendientes y checksums.
        $plan = $this->installer->plan(array_keys($manifests));

        $migracionesPendientes = array_map(
            static fn(array $p): array => ['modulo' => $p['modulo'], 'archivo' => $p['archivo']],
            array_merge($plan->migracionesPendientes, $plan->seedsPendientes)
        );

        return [
            'plataformaVersion'     => $this->plataformaVersion,
            'modulos'               => $modulos,
            'migracionesPendientes' => $migracionesPendientes,
            'checksumsModificados'  => $plan->checksumsModificados,
            'healthChecks'          => $this->installer->requisitosCheck(),
        ];
    }
}
