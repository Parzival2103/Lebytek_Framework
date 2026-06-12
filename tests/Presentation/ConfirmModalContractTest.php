<?php

declare(strict_types=1);

test('Presentation views: no crudDeleteModal ni window.confirm en markup', function (): void {
    $viewsPath = APP_PATH . '/Presentation/Views';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewsPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = (string) file_get_contents($file->getPathname());
        $relative = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

        assert_true(
            !str_contains($content, 'crudDeleteModal'),
            'Legacy modal reference in: ' . $relative
        );
        assert_true(
            !str_contains($content, 'window.confirm'),
            'Native confirm reference in: ' . $relative
        );
    }
});

test('Layout base incluye partial confirm_modal', function (): void {
    $layoutPath = APP_PATH . '/Presentation/Views/layouts/base.php';
    $content = (string) file_get_contents($layoutPath);
    assert_true(str_contains($content, "partial('confirm_modal')"));
});
