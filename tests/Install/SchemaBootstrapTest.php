<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Install\ModuleRegistry;

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
    $registry = new ModuleRegistry(SKELETON_PATH . '/config/modules');
    $crud = $registry->get('crud-engine');
    assert_true($crud !== null);
    assert_same('database/schema/modules/crud-engine.sql', $crud->bootstrapSql);
    assert_true(is_file(ROOT_PATH . '/' . $crud->bootstrapSql));
});

test('calendario manifiesto declara bootstrap_sql, crud y archivo existe', function (): void {
    $registry = new ModuleRegistry(SKELETON_PATH . '/config/modules');
    $cal = $registry->get('calendario');
    assert_true($cal !== null);
    assert_same('database/schema/modules/calendario.sql', $cal->bootstrapSql);
    assert_true(is_file(ROOT_PATH . '/' . $cal->bootstrapSql));
    assert_true(in_array('demo_citas', $cal->cruds, true), 'declara el crud demo_citas');
    assert_true(in_array('crud-engine', $cal->requiere, true), 'depende de crud-engine');
    assert_true(is_file(SKELETON_PATH . '/config/calendars/demo_citas.json'), 'calendario demo existe');
});

test('seeds/ no tiene SQL sueltos (solo baseline consolidado)', function (): void {
    $seeds = glob(SKELETON_PATH . '/database/seeds/*.sql') ?: [];
    assert_same([], $seeds);
});

test('migraciones incrementales post-baseline están declaradas en un manifiesto', function (): void {
    $registry    = new ModuleRegistry(SKELETON_PATH . '/config/modules');
    $migraciones = array_map('basename', glob(SKELETON_PATH . '/database/migrations/*.sql') ?: []);
    $declaradas  = [];
    foreach ($registry->all() as $manifest) {
        $declaradas = array_merge($declaradas, $manifest->migraciones);
    }
    sort($migraciones);
    sort($declaradas);
    assert_same($declaradas, $migraciones);
});
