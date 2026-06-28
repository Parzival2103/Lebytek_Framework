<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface MessageChannelInterface
{
    /** Clave estable del canal, p. ej. "whatsapp" | "email". */
    public function key(): string;

    public function send(MessageRequest $request): MessageResult;
}
