<?php

declare(strict_types=1);

namespace Lebytek\Framework\Domain\Integrations;

interface MessageSenderInterface
{
    public function send(MessageRequest $request): MessageResult;
}
