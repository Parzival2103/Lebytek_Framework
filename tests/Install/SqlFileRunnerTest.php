<?php

declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Install\SqlFileRunner;

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

// --- Conciencia de literales de cadena (issue #39) ---
//
// partir() acumulaba líneas y cortaba en cuanto una línea *terminaba* en ";".
// Cualquier .sql con HTML/CSS embebido se partía a media cadena, porque cada
// declaración CSS ocupa su propia línea terminada en ";".

test('SqlFileRunner::partir no corta dentro de un literal de cadena simple', function (): void {
    $stmts = (new SqlFileRunner())->partir("INSERT INTO t (a) VALUES ('uno; dos; tres');");
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir no corta en CSS inline multilínea dentro de una cadena', function (): void {
    $sql = <<<SQL
    INSERT INTO t (cuerpo) VALUES ('
    <a style="
    display:inline-block;
    background:#0d6efd;
    border-radius:8px;
    ">Hola</a>
    ');
    SQL;
    $stmts = (new SqlFileRunner())->partir($sql);
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir respeta comillas dobles y backticks', function (): void {
    $stmts = (new SqlFileRunner())->partir('SELECT "a;b", `c;d` FROM t;');
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir respeta comilla escapada con barra', function (): void {
    $stmts = (new SqlFileRunner())->partir("SELECT 'O\\'Brien; x';");
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir respeta comilla duplicada', function (): void {
    $stmts = (new SqlFileRunner())->partir("SELECT 'O''Brien; x';");
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir ignora punto y coma en comentarios de línea -- y #', function (): void {
    $sql = "-- comentario; con punto y coma\nSELECT 1;\n# otro; comentario\nSELECT 2;";
    $stmts = (new SqlFileRunner())->partir($sql);
    assert_same(2, count($stmts));
    assert_same('SELECT 1;', $stmts[0]);
    assert_same('SELECT 2;', $stmts[1], 'el comentario # no viaja pegado a la sentencia');
});

test('SqlFileRunner::partir ignora punto y coma en comentario de bloque', function (): void {
    $stmts = (new SqlFileRunner())->partir('SELECT /* a; b */ 1;');
    assert_same(1, count($stmts));
    assert_false(str_contains($stmts[0], 'a; b'), 'el comentario de bloque se descarta');
});

test('SqlFileRunner::partir no trata -- dentro de una cadena como comentario', function (): void {
    $stmts = (new SqlFileRunner())->partir("INSERT INTO t VALUES ('a -- b; c');");
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir exige espacio tras -- para que sea comentario, como MySQL', function (): void {
    // Sin espacio no es comentario: el resto de la línea sigue siendo parte de la sentencia.
    $stmts = (new SqlFileRunner())->partir("SELECT 1--2;\nSELECT 3;");
    assert_same(2, count($stmts));
    assert_true(str_contains($stmts[0], '--2'), 'el texto pegado a -- no se descarta');
});

test('SqlFileRunner::partir descarta la cola vacía tras el último punto y coma', function (): void {
    $stmts = (new SqlFileRunner())->partir("SELECT 1;\n\n   \n");
    assert_same(1, count($stmts));
});

test('SqlFileRunner::partir conserva una sentencia final sin punto y coma', function (): void {
    $stmts = (new SqlFileRunner())->partir("SELECT 1;\nSELECT 2");
    assert_same(2, count($stmts));
});

/**
 * Contraste medido del issue #39 sobre el seed de plantillas de Portal (613
 * líneas de correo con style="…" inline), congelado como corpus en
 * tests/fixtures/sql/. La regla por líneas devolvía 158 fragmentos —casi todos
 * SQL inválido a media cadena—; las sentencias reales son 6 (5 INSERT + 1 UPDATE).
 */
test('SqlFileRunner::partir rinde 6 sentencias donde la regla por líneas daba 158', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/tests/fixtures/sql/plantillas_seed_catalog.sql');
    assert_true($sql !== '', 'el corpus de regresión existe y no está vacío');

    // Regla anterior, reproducida para probar que el corpus sigue provocando el
    // defecto: si alguien lo edita y deja de cortarse, este test lo delata.
    $porLineas = 0;
    foreach (preg_split('/\R/', $sql) ?: [] as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--')) {
            continue;
        }
        if (preg_match('/;\s*$/', rtrim($line))) {
            $porLineas++;
        }
    }
    assert_same(158, $porLineas, 'el corpus debe seguir teniendo 158 líneas terminadas en ;');

    $stmts = (new SqlFileRunner())->partir($sql);
    assert_same(6, count($stmts));

    // Ninguna quedó cortada a media cadena: todas abren con un verbo SQL.
    foreach ($stmts as $stmt) {
        assert_true(
            (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|SET|REPLACE)\b/i', $stmt),
            'fragmento cortado a media cadena: ' . substr($stmt, 0, 60)
        );
    }
});
