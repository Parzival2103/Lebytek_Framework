<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;
use Lebytek\Framework\Kernel\Config\Config;

final class InvoicingFactory
{
    private static ?InvoiceProviderRegistry $cached = null;

    public static function resetCached(): void
    {
        self::$cached = null;
    }

    public static function registry(): InvoiceProviderRegistry
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $config = (array) Config::get('invoicing', []);
        return self::$cached = new InvoiceProviderRegistry(
            self::buildProviders((array) ($config['providers'] ?? []))
        );
    }

    /**
     * @param array<string, array{driver?:string, enabled?:bool, config?:array}> $providersConfig
     * @return array<string, array{driver:string, factory:callable():InvoiceProviderInterface}>
     */
    public static function buildProviders(array $providersConfig): array
    {
        $out = [];
        foreach ($providersConfig as $key => $def) {
            if (! (bool) ($def['enabled'] ?? false)) {
                continue;
            }
            $driver = (string) ($def['driver'] ?? $key);
            if ($driver !== 'facturapi') {
                throw new \RuntimeException("Driver de proveedor no soportado: {$driver}");
            }
            $cfg = (array) ($def['config'] ?? []);
            $out[$key] = [
                'driver' => $driver,
                'factory' => static function () use ($driver, $cfg): InvoiceProviderInterface {
                    return match ($driver) {
                        'facturapi' => FacturapiInvoiceProvider::fromSecretKey(
                            (string) ($cfg['secret_key'] ?? ''),
                            array_intersect_key($cfg, array_flip(['apiVersion', 'timeout', 'httpClient']))
                        ),
                        default => throw new \RuntimeException("Driver de proveedor no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }
}
