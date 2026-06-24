<?php
// tests/Crud/CrudConfigBoundaryTest.php
declare(strict_types=1);

test('todo config/cruds/*.json apunta a una tabla existente en database/schema', function (): void {
    // 1) Concatenar todo el SQL de schema activo (base + módulos).
    $schemaFiles = array_merge(
        glob(ROOT_PATH . '/database/schema/*.sql') ?: [],
        glob(ROOT_PATH . '/database/schema/modules/*.sql') ?: []
    );
    assert_true(count($schemaFiles) > 0, 'debe haber archivos de schema');

    $schema = '';
    foreach ($schemaFiles as $file) {
        $schema .= "\n" . (string) file_get_contents($file);
    }

    // 2) Cada CRUD JSON debe declarar resource.table y existir un CREATE TABLE para ella.
    $cruds = glob(ROOT_PATH . '/config/cruds/*.json') ?: [];
    assert_true(count($cruds) > 0, 'debe haber configs CRUD');

    foreach ($cruds as $path) {
        $name = basename($path);
        $cfg = json_decode((string) file_get_contents($path), true);
        assert_true(is_array($cfg), "{$name} es JSON válido");

        $table = $cfg['resource']['table'] ?? null;
        assert_true(is_string($table) && $table !== '', "{$name} declara resource.table");

        $pattern = '/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?/i';
        assert_true(
            preg_match($pattern, $schema) === 1,
            "{$name}: tabla '{$table}' debe tener CREATE TABLE en database/schema"
        );
    }
});
