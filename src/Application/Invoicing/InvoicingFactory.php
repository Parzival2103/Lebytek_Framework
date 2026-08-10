<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;
use Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository;
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

    public static function makeIssueInvoiceFromSource(InvoiceableSourceInterface $source): IssueInvoiceFromSource
    {
        return new IssueInvoiceFromSource(
            source: $source,
            events: new PdoInvoiceEventLogRepository(),
            registry: self::registry(),
            validator: new InvoiceDraftValidator(),
            defaultProviderKey: self::defaultProviderKey(),
        );
    }

    /**
     * `$source`/validator are only required for the ops-only `forceReissueOrphanClaim`
     * (A26); pass a source when wiring reconcile for that flow, omit it otherwise.
     */
    public static function makeReconcileIssuedInvoice(?InvoiceableSourceInterface $source = null): ReconcileIssuedInvoice
    {
        return new ReconcileIssuedInvoice(
            events: new PdoInvoiceEventLogRepository(),
            registry: self::registry(),
            defaultProviderKey: self::defaultProviderKey(),
            source: $source,
            validator: $source !== null ? new InvoiceDraftValidator() : null,
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
            $secret = (string) ($cfg['secret_key'] ?? '');
            $mode = (string) ($cfg['mode'] ?? 'test');
            FacturapiInvoiceProvider::assertSecretKeyMatchesMode($secret, $mode);
            $sdkSlice = array_intersect_key($cfg, array_flip(['apiVersion', 'timeout', 'httpClient']));
            $out[$key] = [
                'driver' => $driver,
                'factory' => static function () use ($driver, $secret, $sdkSlice, $mode): InvoiceProviderInterface {
                    return match ($driver) {
                        'facturapi' => FacturapiInvoiceProvider::fromSecretKey($secret, $sdkSlice, $mode),
                        default => throw new \RuntimeException("Driver de proveedor no soportado: {$driver}"),
                    };
                },
            ];
        }
        return $out;
    }

    private static function defaultProviderKey(): string
    {
        return (string) Config::get('invoicing.default', 'facturapi');
    }
}
