<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Invoicing;

use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;

/*
|--------------------------------------------------------------------------
| InvoiceProviderRegistry — resuelve clave de proveedor → instancia (lazy + memoizada).
|--------------------------------------------------------------------------
| Se construye desde config/invoicing.php (vía InvoicingFactory en Task 8).
| Guarda el "driver" por proveedor para el logging, sin acoplar la interfaz
| de provider a ese detalle.
*/
final class InvoiceProviderRegistry
{
    /** @var array<string, InvoiceProviderInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array{driver:string, factory:callable():InvoiceProviderInterface}> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function has(string $providerKey): bool
    {
        return isset($this->definitions[$providerKey]);
    }

    public function get(string $providerKey): InvoiceProviderInterface
    {
        if (!$this->has($providerKey)) {
            throw new \RuntimeException("Proveedor de facturación no registrado: {$providerKey}");
        }

        if (!isset($this->resolved[$providerKey])) {
            $factory = $this->definitions[$providerKey]['factory'];
            $this->resolved[$providerKey] = $factory();
        }

        return $this->resolved[$providerKey];
    }

    public function driver(string $providerKey): string
    {
        return (string) ($this->definitions[$providerKey]['driver'] ?? 'unknown');
    }
}
