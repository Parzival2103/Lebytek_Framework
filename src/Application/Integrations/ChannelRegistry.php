<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Integrations;

use Lebytek\Framework\Domain\Integrations\MessageChannelInterface;

/*
|--------------------------------------------------------------------------
| ChannelRegistry — resuelve clave de canal → instancia (lazy + memoizada).
|--------------------------------------------------------------------------
| Se construye desde config/integrations.php (vía IntegrationsFactory).
| Guarda el "driver" por canal para el logging, sin acoplar la interfaz
| de canal a ese detalle.
*/
final class ChannelRegistry
{
    /** @var array<string, MessageChannelInterface> */
    private array $resolved = [];

    /**
     * @param array<string, array{driver:string, factory:callable():MessageChannelInterface}> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function has(string $channelKey): bool
    {
        return isset($this->definitions[$channelKey]);
    }

    public function get(string $channelKey): MessageChannelInterface
    {
        if (!$this->has($channelKey)) {
            throw new \RuntimeException("Canal de integración no registrado: {$channelKey}");
        }

        if (!isset($this->resolved[$channelKey])) {
            $factory = $this->definitions[$channelKey]['factory'];
            $this->resolved[$channelKey] = $factory();
        }

        return $this->resolved[$channelKey];
    }

    public function driver(string $channelKey): string
    {
        return (string) ($this->definitions[$channelKey]['driver'] ?? 'unknown');
    }
}
