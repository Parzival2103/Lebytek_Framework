<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
test('modulo-crud-engine documents CAS and bulk re-check', function () use ($root): void {
    $src = (string) file_get_contents($root . '/docs/modules/crud/modulo-crud-engine.md');
    foreach (['CAS', 'deleted = 0', 'El registro cambió', 'runBulk', 'fail-closed'] as $needle) {
        assert_true(str_contains($src, $needle), "docs must mention {$needle}");
    }
});
