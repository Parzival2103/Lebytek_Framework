<?php
// tests/Marketing/RoutesWiringTest.php
declare(strict_types=1);

test('web.php incluye marketing de forma condicional al toggle', function (): void {
    $web = file_get_contents(ROOT_PATH . '/routes/web.php');
    assert_true($web !== false);
    assert_true(str_contains($web, "vertical.modules.marketing"), 'lee el toggle');
    assert_true(str_contains($web, "routes/marketing.php"), 'incluye routes/marketing.php');
});

test('web.php registra el / por defecto SOLO si marketing está apagado', function (): void {
    $web = file_get_contents(ROOT_PATH . '/routes/web.php');
    assert_true(str_contains($web, '$marketingActivo'), 'usa la bandera de toggle');
    assert_true(str_contains($web, "if (!\$marketingActivo)"), 'guarda el / por defecto');
});

test('routes/marketing.php registra la raíz pública hacia LandingController', function (): void {
    $mkt = file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true($mkt !== false);
    assert_true(str_contains($mkt, "LandingController"), 'apunta a LandingController');
    assert_true(str_contains($mkt, "->get('/'"), 'registra GET /');
});

test('LandingController es clase válida y tiene index', function (): void {
    assert_true(class_exists(\App\Presentation\Controllers\Publico\LandingController::class), 'clase existe');
    assert_true(method_exists(\App\Presentation\Controllers\Publico\LandingController::class, 'index'), 'tiene index');
});

test('routes/marketing.php registra GET y POST /verificar-demo/{token}', function (): void {
    $mkt = file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true($mkt !== false);
    assert_true(str_contains($mkt, "LeadEmailVerificationController"), 'apunta a LeadEmailVerificationController');
    assert_true(str_contains($mkt, "->get('/verificar-demo/{token}'"), 'registra GET /verificar-demo/{token}');
    assert_true(str_contains($mkt, "->post('/verificar-demo/{token}'"), 'registra POST /verificar-demo/{token}');
});

test('LeadEmailVerificationController es clase válida y tiene show/submit', function (): void {
    assert_true(class_exists(\App\Presentation\Controllers\Publico\LeadEmailVerificationController::class), 'clase existe');
    assert_true(method_exists(\App\Presentation\Controllers\Publico\LeadEmailVerificationController::class, 'show'), 'tiene show');
    assert_true(method_exists(\App\Presentation\Controllers\Publico\LeadEmailVerificationController::class, 'submit'), 'tiene submit');
});
