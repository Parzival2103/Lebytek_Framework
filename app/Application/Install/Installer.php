<?php

declare(strict_types=1);

namespace App\Application\Install;

use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;
use App\Infrastructure\Install\SqlFileRunner;

final class Installer
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly DependencyResolver $resolver,
        private readonly MigrationRepositoryInterface $migraciones,
        private readonly ModuleStateRepositoryInterface $modulos,
        private readonly SqlFileRunner $runner,
        private readonly string $migracionesDir,
        private readonly string $seedsDir,
    ) {}

    /**
     * Comprobaciones de entorno. Cada item: [clave, ok, detalle].
     *
     * @return list<array{clave:string,ok:bool,detalle:string}>
     */
    public function requisitosCheck(): array
    {
        $checks = [];

        $checks[] = [
            'clave'   => 'php',
            'ok'      => PHP_VERSION_ID >= 80100,
            'detalle' => 'PHP ' . PHP_VERSION . ' (se requiere ≥ 8.1).',
        ];
        $checks[] = [
            'clave'   => 'pdo_mysql',
            'ok'      => extension_loaded('pdo_mysql'),
            'detalle' => extension_loaded('pdo_mysql') ? 'Extensión pdo_mysql cargada.' : 'Falta extensión pdo_mysql.',
        ];
        $storageOk = is_writable(ROOT_PATH . '/storage');
        $checks[] = [
            'clave'   => 'storage',
            'ok'      => $storageOk,
            'detalle' => $storageOk ? 'storage/ escribible.' : 'storage/ no es escribible.',
        ];
        $envOk = is_file(ROOT_PATH . '/.env');
        $checks[] = [
            'clave'   => 'env',
            'ok'      => $envOk,
            'detalle' => $envOk ? '.env presente.' : 'Falta archivo .env.',
        ];

        $conexionOk = false;
        $detalleConn = 'No se pudo conectar a la BD.';
        try {
            $this->migraciones->existeTabla('cfg_modulos');
            $conexionOk = true;
            $detalleConn = 'Conexión a la base de datos correcta.';
        } catch (\Throwable $e) {
            $detalleConn = 'Error de conexión: ' . $e->getMessage();
        }
        $checks[] = ['clave' => 'bd', 'ok' => $conexionOk, 'detalle' => $detalleConn];

        return $checks;
    }

    /**
     * Calcula el plan sin ejecutar nada (preview / dry-run).
     *
     * @param list<string> $seleccion
     */
    public function plan(array $seleccion): InstallPlan
    {
        $orden     = $this->resolver->resolver($this->registry->all(), $seleccion);
        $aplicadas = $this->migraciones->aplicadas();
        $nueva     = $this->modulos->instalados() === [];

        $migPend = [];
        $seedPend = [];
        $modificados = [];
        $modulosPlan = [];

        foreach ($orden as $clave) {
            $manifest = $this->registry->get($clave);
            if ($manifest === null) {
                continue;
            }
            $modulosPlan[] = ['clave' => $clave, 'version' => $manifest->version];

            foreach ($manifest->migraciones as $archivo) {
                $this->clasificar($clave, $archivo, $this->migracionesDir, $aplicadas, $migPend, $modificados);
            }
            foreach ($manifest->seeds as $archivo) {
                $this->clasificar($clave, $archivo, $this->seedsDir, $aplicadas, $seedPend, $modificados);
            }
        }

        return new InstallPlan($nueva, $migPend, $seedPend, $modulosPlan, $modificados);
    }

    /**
     * @param array<string,string> $aplicadas
     * @param list<array{modulo:string,archivo:string,ruta:string,checksum:string}> $pendientes
     * @param list<array{modulo:string,archivo:string}> $modificados
     */
    private function clasificar(string $clave, string $archivo, string $dir, array $aplicadas, array &$pendientes, array &$modificados): void
    {
        $ruta     = rtrim($dir, '/\\') . '/' . $archivo;
        $checksum = $this->runner->checksum($ruta);

        if (!isset($aplicadas[$archivo])) {
            $pendientes[] = ['modulo' => $clave, 'archivo' => $archivo, 'ruta' => $ruta, 'checksum' => $checksum];
            return;
        }
        if ($aplicadas[$archivo] !== $checksum) {
            $modificados[] = ['modulo' => $clave, 'archivo' => $archivo];
        }
    }

    public function aplicar(InstallPlan $plan): void
    {
        foreach ($plan->migracionesPendientes as $item) {
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
        }
        foreach ($plan->seedsPendientes as $item) {
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
        }
        foreach ($plan->modulos as $mod) {
            $this->modulos->registrar($mod['clave'], $mod['version'], true);
        }
    }

    /**
     * Adopta un deploy legacy: marca como aplicadas las migraciones/seeds
     * presentes (sin ejecutarlas) y registra los módulos detectados.
     */
    public function baseline(): void
    {
        $aplicadas = $this->migraciones->aplicadas();

        foreach ($this->registry->all() as $clave => $manifest) {
            foreach ($manifest->migraciones as $archivo) {
                $this->baselineArchivo($clave, $archivo, $this->migracionesDir, $aplicadas);
            }
            foreach ($manifest->seeds as $archivo) {
                $this->baselineArchivo($clave, $archivo, $this->seedsDir, $aplicadas);
            }
            $this->modulos->registrar($clave, $manifest->version, true);
        }
    }

    /** @param array<string,string> $aplicadas */
    private function baselineArchivo(string $clave, string $archivo, string $dir, array $aplicadas): void
    {
        if (isset($aplicadas[$archivo])) {
            return;
        }
        $ruta = rtrim($dir, '/\\') . '/' . $archivo;
        if (!is_file($ruta)) {
            return;
        }
        $this->migraciones->registrar($clave, $archivo, $this->runner->checksum($ruta));
    }
}
