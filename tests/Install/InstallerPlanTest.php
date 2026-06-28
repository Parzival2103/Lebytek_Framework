<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use Lebytek\Framework\Application\Install\Installer;
use Lebytek\Framework\Application\Install\ModuleRegistry;
use Lebytek\Framework\Application\Install\DependencyResolver;
use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;

function installer_con(array $aplicadas, string $migDir, string $seedDir): Installer
{
    return new Installer(
        new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan'),
        new DependencyResolver(),
        new FakeMigrationRepository($aplicadas),
        new FakeModuleStateRepository(),
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );
}

test('Installer::plan en instalación nueva marca todo pendiente', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $plan = installer_con([], $migDir, $seedDir)->plan(['core']);

    assert_true($plan->nueva);
    assert_same(1, count($plan->migracionesPendientes));
    assert_same('m1.sql', $plan->migracionesPendientes[0]['archivo']);
    assert_same(1, count($plan->seedsPendientes));
});

test('Installer::plan no repite migración ya aplicada con mismo checksum', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $checksum = (new SqlFileRunner())->checksum($migDir . '/m1.sql');
    $plan = installer_con(['m1.sql' => $checksum], $migDir, $seedDir)->plan(['core']);

    // No vuelve a listar la migración ya aplicada con el mismo checksum.
    assert_same(0, count($plan->migracionesPendientes));
});

test('Installer::plan reporta checksum modificado sin re-aplicar', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);
    $plan = installer_con(['m1.sql' => 'checksum-viejo-distinto'], $migDir, $seedDir)->plan(['core']);

    assert_same(0, count($plan->migracionesPendientes));
    assert_same(1, count($plan->checksumsModificados));
    assert_same('m1.sql', $plan->checksumsModificados[0]['archivo']);
});
