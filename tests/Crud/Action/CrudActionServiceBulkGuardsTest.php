<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);

test('runBulk source re-checks visible_when and enabled_when like run (G1)', function () use ($root): void {
    $src = (string) file_get_contents($root . '/src/Application/Services/CrudActionService.php');
    $bulkPos = strpos($src, 'function runBulk');
    $runPos = strpos($src, 'function run(');
    assert_true($bulkPos !== false && $runPos !== false);
    $bulk = substr($src, $bulkPos, 1400);
    assert_true(
        str_contains($bulk, 'isVisibleFor') && str_contains($bulk, 'isEnabledFor'),
        'runBulk must re-check isVisibleFor/isEnabledFor before dispatch (G1)'
    );
    assert_true(
        str_contains($bulk, 'La acción no está disponible para este registro.'),
        'runBulk must use the same ValidationException message as run()'
    );
});
