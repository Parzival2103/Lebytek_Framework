<?php

declare(strict_types=1);

use App\Domain\Interfaces\MigrationRepositoryInterface;
use App\Domain\Interfaces\ModuleStateRepositoryInterface;

final class FakeMigrationRepository implements MigrationRepositoryInterface
{
    /** @param array<string,string> $aplicadas archivo => checksum */
    public function __construct(private array $aplicadas = [], private array $tablas = []) {}

    public function aplicadas(): array { return $this->aplicadas; }

    public function registrar(string $modulo, string $archivo, string $checksum): void
    {
        $this->aplicadas[$archivo] = $checksum;
    }

    public function existeTabla(string $nombre): bool { return in_array($nombre, $this->tablas, true); }
}

final class FakeModuleStateRepository implements ModuleStateRepositoryInterface
{
    /** @param array<string,array{version:string,activo:bool}> $estado */
    public function __construct(private array $estado = []) {}

    public function instalados(): array { return $this->estado; }

    public function registrar(string $clave, string $version, bool $activo): void
    {
        $this->estado[$clave] = ['version' => $version, 'activo' => $activo];
    }
}

/**
 * Crea un directorio temporal con archivos .sql de contenido conocido.
 *
 * @param array<string,string> $archivos nombre => contenido
 */
function install_fixture_dir(array $archivos): string
{
    $dir = sys_get_temp_dir() . '/inst_' . bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    foreach ($archivos as $nombre => $contenido) {
        file_put_contents($dir . '/' . $nombre, $contenido);
    }
    return $dir;
}
