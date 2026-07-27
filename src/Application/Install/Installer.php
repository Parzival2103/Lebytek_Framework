<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Install;

use Lebytek\Framework\Domain\Interfaces\MigrationRepositoryInterface;
use Lebytek\Framework\Domain\Interfaces\ModuleStateRepositoryInterface;
use Lebytek\Framework\Infrastructure\Install\InstallTrace;
use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;
use Lebytek\Framework\Kernel\PackagePaths;

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
        $ruta = $this->resolveInstallFile($archivo, $dir, $clave);
        $checksum = $this->runner->checksum($ruta);

        if (!isset($aplicadas[$archivo])) {
            $pendientes[] = ['modulo' => $clave, 'archivo' => $archivo, 'ruta' => $ruta, 'checksum' => $checksum];
            return;
        }
        if ($aplicadas[$archivo] !== $checksum) {
            $modificados[] = ['modulo' => $clave, 'archivo' => $archivo];
        }
    }

    private function resolveMigrationFile(string $archivo): string
    {
        return PackagePaths::resolveDataFile('database/migrations/' . ltrim($archivo, '/'));
    }

    private function resolveSeedFile(string $archivo): string
    {
        return PackagePaths::resolveDataFile('database/seeds/' . ltrim($archivo, '/'));
    }

    /**
     * PackagePaths es SoT; $dir (constructor) solo como fallback BC / fixtures.
     *
     * Con $modulo (p. ej. en plan()), un archivo ausente lanza RuntimeException
     * accionable que nombra módulo y entrada del manifiesto. Sin $modulo
     * (baseline legacy) devuelve la ruta resuelta aunque no exista en disco.
     */
    private function resolveInstallFile(string $archivo, string $dir, ?string $modulo = null): string
    {
        $esSeed = $dir === $this->seedsDir || str_contains($dir, 'seeds');
        $fallback = rtrim($dir, '/\\') . '/' . $archivo;

        try {
            $ruta = $esSeed
                ? $this->resolveSeedFile($archivo)
                : $this->resolveMigrationFile($archivo);
        } catch (\RuntimeException) {
            $ruta = $fallback;
        }

        if ($modulo !== null && ! is_file($ruta)) {
            $tipo = $esSeed ? 'seed' : 'migración';
            throw new \RuntimeException(
                "El manifiesto del módulo «{$modulo}» declara {$tipo} «{$archivo}» "
                . "pero el archivo no existe (PackagePaths ni {$fallback})."
            );
        }

        return $ruta;
    }

    public function aplicar(InstallPlan $plan): void
    {
        foreach ($plan->migracionesPendientes as $item) {
            InstallTrace::log('migracion inicio | ' . $item['archivo']);
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
            InstallTrace::log('migracion OK | ' . $item['archivo']);
        }
        foreach ($plan->seedsPendientes as $item) {
            InstallTrace::log('seed inicio | ' . $item['archivo']);
            $this->runner->ejecutar($item['ruta']);
            $this->migraciones->registrar($item['modulo'], $item['archivo'], $item['checksum']);
            InstallTrace::log('seed OK | ' . $item['archivo']);
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
        $ruta = $this->resolveInstallFile($archivo, $dir);
        if (!is_file($ruta)) {
            return;
        }
        $this->migraciones->registrar($clave, $archivo, $this->runner->checksum($ruta));
    }
}

