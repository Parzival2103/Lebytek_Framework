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
        assert_true(
            !str_contains($content, 'return confirm('),
            'Native confirm() inline in: ' . $relative
        );
    }
});

test('Layout base incluye partial confirm_modal', function (): void {
    $layoutPath = APP_PATH . '/Presentation/Views/layouts/base.php';
    $content = (string) file_get_contents($layoutPath);
    assert_true(str_contains($content, "partial('confirm_modal')"));
});

test('Partial confirm_modal incluye slot de icono y elementos requeridos', function (): void {
    $path = APP_PATH . '/Presentation/Views/partials/confirm_modal.php';
    $content = (string) file_get_contents($path);

    assert_true(str_contains($content, 'id="confirmModalIcon"'), 'Falta slot de icono');
    assert_true(
        str_contains($content, 'class="ct-confirm-icon d-none"'),
        'El slot de icono debe iniciar oculto (d-none)'
    );
    assert_true(str_contains($content, 'id="confirmModalTitle"'));
    assert_true(str_contains($content, 'id="confirmModalBody"'));
    assert_true(str_contains($content, 'id="confirmModalOk"'));
    assert_true(str_contains($content, 'id="confirmModalCancel"'));
});

test('app.js soporta las opciones extendidas del confirm', function (): void {
    $path = dirname(APP_PATH) . '/public/assets/js/app.js';
    $content = (string) file_get_contents($path);

    assert_true(str_contains($content, 'confirmIcon'), 'Falta lectura de data-confirm-icon');
    assert_true(str_contains($content, 'confirmEmphasis'), 'Falta lectura de data-confirm-emphasis');
    assert_true(str_contains($content, 'confirmCancelVariant'), 'Falta lectura de data-confirm-cancel-variant');
    assert_true(str_contains($content, 'ct-confirm-emphasis'), 'Falta render de énfasis');
    assert_true(str_contains($content, 'confirmModalIcon'), 'Falta manejo del slot de icono');
    assert_true(str_contains($content, 'window.Lebytek'), 'Falta API global window.Lebytek.confirm');
});
