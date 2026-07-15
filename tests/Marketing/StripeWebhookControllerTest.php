<?php
declare(strict_types=1);

test('ruta webhook stripe sin CsrfMiddleware', function (): void {
    $controllerClass = 'App\\Presentation\\Controllers\\Publico\\StripeWebhookController';
    assert_true(class_exists($controllerClass));

    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true(str_contains($routes, '/webhooks/stripe'));
    assert_true(str_contains($routes, 'StripeWebhookController'));

    foreach (preg_split('/\R/', $routes) as $line) {
        if (str_contains($line, '/webhooks/stripe')) {
            assert_true(! str_contains($line, 'CsrfMiddleware'), $line);
        }
    }
});

test('StripeWebhookController lee el cuerpo raw y no filtra mensajes internos', function (): void {
    $source = (string) file_get_contents(
        ROOT_PATH . '/app/Presentation/Controllers/Publico/StripeWebhookController.php'
    );

    assert_true(str_contains($source, "file_get_contents('php://input')"));
    assert_true(! str_contains($source, 'getMessage()'), 'no leak exception message to client');
    assert_true(str_contains($source, "'invalid webhook'"));
    assert_true(str_contains($source, "Response::json(['error' => 'invalid webhook'], 400)"));
    assert_true(str_contains($source, "Response::json(['received' => true], 200)"));
});

test('container registra webhook solo con marketing y payments activos', function (): void {
    $container = (string) file_get_contents(ROOT_PATH . '/config/container.php');

    assert_true(str_contains($container, 'StripeWebhookController::class'));
    assert_true(str_contains($container, 'ConfirmarPagoStripeUseCase::class'));
    assert_true(str_contains($container, "Config::get('vertical.modules.payments', false)"));
});
