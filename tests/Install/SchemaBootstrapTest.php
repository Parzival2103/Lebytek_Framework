<?php

declare(strict_types=1);

use App\Application\Install\ModuleRegistry;

test('schema.sql incluye bootstrap admin y datos iniciales', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/schema.sql');
    assert_true($sql !== false);
    assert_true(str_contains($sql, 'DATOS INICIALES'));
    assert_true(str_contains($sql, 'admin@sistema.local'));
    assert_true(str_contains($sql, 'INSERT IGNORE'));
});

test('schema.sql no referencia migraciones archivadas', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/schema.sql');
    assert_true($sql !== false);
    assert_true(!str_contains($sql, '20260503100000_deprecate_legacy'));
    assert_true(!str_contains($sql, '20260428132500_crud_engine_demo'));
});

test('crud-engine manifiesto declara bootstrap_sql y archivo existe', function (): void {
    $registry = new ModuleRegistry(ROOT_PATH . '/config/modules');
    $crud = $registry->get('crud-engine');
    assert_true($crud !== null);
    assert_same('database/schema/modules/crud-engine.sql', $crud->bootstrapSql);
    assert_true(is_file(ROOT_PATH . '/' . $crud->bootstrapSql));
});

test('migrations/ y seeds/ no tienen SQL sueltos (solo baseline consolidado)', function (): void {
    $migraciones = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
    $seeds       = glob(ROOT_PATH . '/database/seeds/*.sql') ?: [];
    assert_same([], $migraciones);
    assert_same([], $seeds);
});
