<?php

declare(strict_types=1);

require_once ROOT_PATH . '/tests/fixtures/install_repos.php';

use Lebytek\Framework\Application\Install\Installer;
use Lebytek\Framework\Application\Install\ModuleRegistry;
use Lebytek\Framework\Application\Install\DependencyResolver;
use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;

test('Installer::baseline marca presentes como aplicadas y registra módulos', function (): void {
    $migDir  = install_fixture_dir(['m1.sql' => "SELECT 1;\n"]);
    $seedDir = install_fixture_dir(['010.sql' => "SELECT 2;\n"]);

    $migRepo = new FakeMigrationRepository([]);
    $modRepo = new FakeModuleStateRepository();

    $installer = new Installer(
        new ModuleRegistry(ROOT_PATH . '/tests/fixtures/modules_plan'),
        new DependencyResolver(),
        $migRepo,
        $modRepo,
        new SqlFileRunner(),
        $migDir,
        $seedDir
    );

    $installer->baseline();

    $plan = $installer->plan(['core']);
    assert_same(0, count($plan->migracionesPendientes));
    assert_same(0, count($plan->seedsPendientes));
    assert_true(isset($modRepo->instalados()['core']));
});
