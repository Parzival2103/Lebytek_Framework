<?php

declare(strict_types=1);

namespace App\Kernel\Http;

final class Response
{
    private int    $statusCode = 200;
    private array  $headers    = [];
    private string $body       = '';

    private static ?\Closure $notFoundRenderer = null;
    private static ?\Closure $forbiddenRenderer = null;
    private static ?\Closure $internalErrorRenderer = null;

    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body       = $body;
        $this->statusCode = $statusCode;
        $this->headers    = $headers;
    }

    // ── Configuración de renderizadores ────────────────────────────────────────

    public static function setNotFoundRenderer(\Closure $renderer): void
    {
        self::$notFoundRenderer = $renderer;
    }

    public static function setForbiddenRenderer(\Closure $renderer): void
    {
        self::$forbiddenRenderer = $renderer;
    }

    public static function setInternalErrorRenderer(\Closure $renderer): void
    {
        self::$internalErrorRenderer = $renderer;
    }

    public static function renderInternalError(): string
    {
        if (self::$internalErrorRenderer !== null) {
            return (self::$internalErrorRenderer)();
        }
        return '';
    }

    // ── Factorías ─────────────────────────────────────────────────────────────

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function notFound(): self
    {
        $body = '';
        if (self::$notFoundRenderer !== null) {
            $body = (self::$notFoundRenderer)();
        }
        return new self($body, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function forbidden(): self
    {
        $body = '';
        if (self::$forbiddenRenderer !== null) {
            $body = (self::$forbiddenRenderer)();
        }
        return new self($body, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // ── Envío ─────────────────────────────────────────────────────────────────

    public function send(): void
    {
        if (!headers_sent()) {
            $statusText = self::$statusTexts[$this->statusCode] ?? 'Unknown';
            header("HTTP/1.1 {$this->statusCode} {$statusText}");

            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo $this->body;
    }

    public function getStatusCode(): int    { return $this->statusCode; }
    public function getBody(): string       { return $this->body;       }
    public function getHeaders(): array     { return $this->headers;    }

    public function withHeader(string $key, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$key] = $value;
        return $clone;
    }

    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }
}
