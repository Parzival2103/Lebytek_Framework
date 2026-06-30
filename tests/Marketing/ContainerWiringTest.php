<?php
// tests/Marketing/ContainerWiringTest.php
declare(strict_types=1);

test('container.php agrupa los bindings de marketing bajo el guard del toggle', function (): void {
    $src = file_get_contents(ROOT_PATH . '/config/container.php');
    assert_true($src !== false);
    assert_true(str_contains($src, "Config::get('vertical.modules.marketing'"), 'lee el toggle del módulo');
    assert_true(str_contains($src, 'Publico\\LandingController'), 'registra LandingController');
});
