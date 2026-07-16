<?php

declare(strict_types=1);

use App\Presentation\Controllers\Publico\CompraController;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Container\Container;

/**
 * Regression: CompraController must resolve through the real DI container when
 * marketing is ON and payments is OFF (transfer-only deploys). Before the fix,
 * IniciarPagoStripeUseCase was resolved eagerly and required
 * PaymentGatewayRegistry (only bound with payments ON), so the container fell
 * back to `new PaymentGatewayRegistry()` with no constructor args and threw
 * ArgumentCountError — breaking the transfer purchase flow entirely.
 *
 * @return mixed
 */
function withVerticalFlagsForCompra(bool $marketing, bool $payments, callable $fn)
{
    $originalMarketing = Config::get('vertical.modules.marketing');
    $originalPayments = Config::get('vertical.modules.payments');

    Config::set('vertical.modules.marketing', $marketing);
    Config::set('vertical.modules.payments', $payments);

    try {
        return $fn();
    } finally {
        Config::set('vertical.modules.marketing', $originalMarketing);
        Config::set('vertical.modules.payments', $originalPayments);
    }
}

test('CompraController resuelve via container real con marketing ON y payments OFF', function (): void {
    $buildContainer = require ROOT_PATH.'/config/container.php';

    $controller = withVerticalFlagsForCompra(true, false, function () use ($buildContainer) {
        $container = new Container();
        $buildContainer($container);

        return $container->get(CompraController::class);
    });

    assert_true($controller instanceof CompraController, 'CompraController debe resolverse sin ArgumentCountError');
});

test('CompraController resuelve via container real con marketing y payments ON', function (): void {
    $buildContainer = require ROOT_PATH.'/config/container.php';

    $controller = withVerticalFlagsForCompra(true, true, function () use ($buildContainer) {
        $container = new Container();
        $buildContainer($container);

        return $container->get(CompraController::class);
    });

    assert_true($controller instanceof CompraController, 'CompraController debe resolverse con Stripe disponible');
});
