<?php

declare(strict_types=1);

use App\Infrastructure\Install\SqlFileRunner;

test('SqlFileRunner::checksum es sha256 del contenido del archivo', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'sql');
    file_put_contents($tmp, "SELECT 1;\n");
    $runner = new SqlFileRunner();
    assert_same(hash('sha256', "SELECT 1;\n"), $runner->checksum($tmp));
    unlink($tmp);
});

test('SqlFileRunner::partir separa sentencias e ignora comentarios y vacías', function (): void {
    $runner = new SqlFileRunner();
    $stmts = $runner->partir("-- comentario\nSELECT 1;\n\nSELECT 2;\n");
    assert_same(2, count($stmts));
    assert_same('SELECT 1;', trim($stmts[0]));
});

test('SqlFileRunner::partir conserva COMMENT con punto y coma dentro de CREATE TABLE', function (): void {
    $runner = new SqlFileRunner();
    $sql = "CREATE TABLE t (\n"
        . "  c VARCHAR(1) COMMENT 'a; b'\n"
        . ");\n";
    $stmts = $runner->partir($sql);
    assert_same(1, count($stmts));
    assert_true(str_contains($stmts[0], "COMMENT 'a; b'"));
});
