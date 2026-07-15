<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\LebytekApi;

use RuntimeException;

final class LebytekApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?array $errors = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
