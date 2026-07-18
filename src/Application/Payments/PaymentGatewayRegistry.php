<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Payments;

use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;

/*
|--------------------------------------------------------------------------
| PaymentGatewayRegistry — resuelve clave de pasarela → instancia (lazy + memoizada).
|--------------------------------------------------------------------------
| Se construye desde config/payments.php (vía PaymentsFactory).
| Guarda el "driver" por pasarela para el logging, sin acoplar la interfaz
| de gateway a ese detalle.
*/
final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array{driver:string, factory:callable():PaymentGatewayInterface}> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function has(string $gatewayKey): bool
    {
        return isset($this->definitions[$gatewayKey]);
    }

    public function get(string $gatewayKey): PaymentGatewayInterface
    {
        if (!$this->has($gatewayKey)) {
            throw new \RuntimeException("Pasarela de pago no registrada: {$gatewayKey}");
        }

        if (!isset($this->resolved[$gatewayKey])) {
            $factory = $this->definitions[$gatewayKey]['factory'];
            $this->resolved[$gatewayKey] = $factory();
        }

        return $this->resolved[$gatewayKey];
    }

    public function driver(string $gatewayKey): string
    {
        return (string) ($this->definitions[$gatewayKey]['driver'] ?? 'unknown');
    }
}
