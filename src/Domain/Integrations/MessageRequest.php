<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Integrations;

/*
|--------------------------------------------------------------------------
| MessageRequest — lo único que un módulo de negocio construye para enviar.
|--------------------------------------------------------------------------
| El caller nunca conoce el proveedor: solo declara canal, destinatario,
| cuerpo y metadatos (source, record_id, subject, dedupe_key, ...).
*/
final class MessageRequest
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $body,
        public readonly array $meta = []
    ) {
    }
}
