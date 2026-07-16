<?php
declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\ConfirmarPagoStripeUseCase;
use Lebytek\Framework\Application\Payments\PaymentGatewayRegistry;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;

final class StripeWebhookController extends BaseController
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ConfirmarPagoStripeUseCase $confirmar,
    ) {}

    public function handle(Request $request): Response
    {
        // Request does not consume php://input; Stripe signature verification requires its exact raw body.
        $payload = (string) file_get_contents('php://input');
        $signature = (string) ($request->header('Stripe-Signature') ?? $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $event = $this->gateways->get('stripe')->parseWebhook($payload, $signature);
            $this->confirmar->ejecutar($event);
        } catch (\Throwable) {
            return Response::json(['error' => 'invalid webhook'], 400);
        }

        return Response::json(['received' => true], 200);
    }
}
