<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Payments;

use Lebytek\Framework\Domain\Payments\PaymentGatewayInterface;
use Lebytek\Framework\Infrastructure\Payments\StripeGateway;
use Lebytek\Framework\Kernel\Config\Config;

final class PaymentsFactory
{
    private static ?PaymentGatewayRegistry $cached = null;

    public static function resetCached(): void
    {
        self::$cached = null;
    }

    public static function registry(): PaymentGatewayRegistry
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $config = (array) Config::get('payments', []);
        return self::$cached = new PaymentGatewayRegistry(
            self::buildGateways((array) ($config['gateways'] ?? []))
        );
    }

    /**
     * @param array<string, array{driver?:string, enabled?:bool, config?:array}> $gatewaysConfig
     * @return array<string, array{driver:string, factory:callable():PaymentGatewayInterface}>
     */
    public static function buildGateways(array $gatewaysConfig): array
    {
        $out = [];
        foreach ($gatewaysConfig as $key => $def) {
            if (! (bool) ($def['enabled'] ?? false)) {
                continue;
            }
            $driver = (string) ($def['driver'] ?? $key);
            if ($driver !== 'stripe') {
                throw new \RuntimeException("Driver de pasarela no soportado: {$driver}");
            }
            $cfg = (array) ($def['config'] ?? []);
            $out[$key] = [
                'driver' => $driver,
                'factory' => static function () use ($driver, $cfg): PaymentGatewayInterface {
                    return match ($driver) {
                        'stripe' => new StripeGateway($cfg),
                        default  => throw new \RuntimeException("Driver de pasarela no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }
}
