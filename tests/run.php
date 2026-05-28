<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/microtest.php';

$filter = $argv[1] ?? null;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $entry) {
    if (!$entry->isFile()) {
        continue;
    }
    $path = $entry->getPathname();
    if (!str_ends_with($path, 'Test.php')) {
        continue;
    }
    if ($filter !== null && !str_contains(str_replace('\\', '/', $path), $filter)) {
        continue;
    }
    $files[] = $path;
}

sort($files);
foreach ($files as $file) {
    require $file;
}

microtest_summary();
