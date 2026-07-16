<?php
declare(strict_types=1);

test('ruta webhook stripe sin CsrfMiddleware y gateada por payments', function (): void {
    $controllerClass = 'App\\Presentation\\Controllers\\Publico\\StripeWebhookController';
    assert_true(class_exists($controllerClass));

    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');
    assert_true(str_contains($routes, '/webhooks/stripe'));
    assert_true(str_contains($routes, 'StripeWebhookController'));
    assert_true(str_contains($routes, "Config::get('vertical.modules.payments'"));

    $webhookInsidePaymentsGate = false;
    $paymentsGateSeen = false;

    foreach (preg_split('/\R/', $routes) as $line) {
        if (str_contains($line, 'vertical.modules.payments')) {
            $paymentsGateSeen = true;
        }
        if ($paymentsGateSeen && str_contains($line, '/webhooks/stripe')) {
            $webhookInsidePaymentsGate = true;
            assert_true(! str_contains($line, 'CsrfMiddleware'), $line);
        }
    }

    assert_true($webhookInsidePaymentsGate, 'webhook route must be registered inside payments module gate');
});

test('ruta webhook stripe no se registra con marketing ON y payments OFF', function (): void {
    $routes = (string) file_get_contents(ROOT_PATH . '/routes/marketing.php');

    assert_true(str_contains($routes, "Config::get('vertical.modules.payments', false)"));

    $lines = preg_split('/\R/', $routes);
    $unconditionalWebhook = false;

    foreach ($lines as $index => $line) {
        if (! str_contains($line, '$router->post(\'/webhooks/stripe\'')) {
            continue;
        }

        $context = implode("\n", array_slice($lines, max(0, $index - 5), 6));
        if (! str_contains($context, 'vertical.modules.payments')) {
            $unconditionalWebhook = true;
        }
    }

    assert_true(! $unconditionalWebhook, 'webhook route must not register without payments module flag');
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
