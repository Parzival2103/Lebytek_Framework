<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

interface MessageSenderInterface
{
    public function send(MessageRequest $request): MessageResult;
}
