<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use App\Application\Install\DeploymentStatus;
use App\Application\Install\Installer;
use App\Application\Install\ModuleRegistry;
use App\Application\Install\DependencyResolver;
use App\Infrastructure\Install\SqlFileRunner;

function status_para(array $instalados): DeploymentStatus
{
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $registry = new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan');

    $installer = new Installer(
        $registry,
        new DependencyResolver(),
        new FakeMigrationRepository([]),
        new FakeModuleStateRepository($instalados),
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );

    return new DeploymentStatus(
        $registry,
        $installer,
        new FakeModuleStateRepository($instalados),
        '1.0.0'
    );
}

test('DeploymentStatus: módulo no instalado figura como instalada=null', function (): void {
    $rep = status_para([])->reporte();
    assert_same('1.0.0', $rep['plataformaVersion']);
    assert_same(null, $rep['modulos']['core']['instalada']);
    assert_same('1.2.3', $rep['modulos']['core']['declarada']);
});

test('DeploymentStatus: versión instalada distinta marca actualización disponible', function (): void {
    $rep = status_para(['core' => ['version' => '1.0.0', 'activo' => true]])->reporte();
    assert_same('1.0.0', $rep['modulos']['core']['instalada']);
    assert_true($rep['modulos']['core']['actualizacionDisponible']);
});

test('DeploymentStatus: versión igual no marca actualización', function (): void {
    $rep = status_para(['core' => ['version' => '1.2.3', 'activo' => true]])->reporte();
    assert_same(false, $rep['modulos']['core']['actualizacionDisponible']);
});
