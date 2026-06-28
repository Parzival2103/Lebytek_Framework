<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Http;

/*
|--------------------------------------------------------------------------
| Request — Abstracción de la solicitud HTTP entrante
|--------------------------------------------------------------------------
| Encapsula $_GET, $_POST, $_SERVER, $_FILES y $_COOKIE.
| Proporciona acceso seguro con sanitización básica.
*/

final class Request
{
    private string $method;
    private string $uri;
    private array  $query;
    private array  $body;
    private array  $headers;
    private array  $files;
    private array  $cookies;
    private array  $server;
    private array  $routeParams = [];

    public function __construct(
        string $method,
        string $uri,
        array  $query   = [],
        array  $body    = [],
        array  $headers = [],
        array  $files   = [],
        array  $cookies = [],
        array  $server  = []
    ) {
        $this->method  = strtoupper($method);
        $this->uri     = $uri;
        $this->query   = $query;
        $this->body    = $body;
        $this->headers = $headers;
        $this->files   = $files;
        $this->cookies = $cookies;
        $this->server  = $server;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Soportar method override por campo _method en formularios
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
                $method = $override;
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);

        // Normalizar base path para subdirectorios
        $basePath = self::resolveBasePath();
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . ltrim($uri, '/');

        return new self(
            $method,
            $uri,
            $_GET,
            $_POST,
            getallheaders() ?: [],
            $_FILES,
            $_COOKIE,
            $_SERVER
        );
    }

    private static function resolveBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        return rtrim(dirname($scriptName), '/\\');
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool  { return $this->method === 'GET';    }
    public function isPost(): bool { return $this->method === 'POST';   }
    public function isPut(): bool  { return $this->method === 'PUT';    }
    public function isDelete(): bool { return $this->method === 'DELETE'; }
    public function isAjax(): bool
    {
        return ($this->headers['X-Requested-With'] ?? '') === 'XMLHttpRequest';
    }

    // ── Entrada ───────────────────────────────────────────────────────────────

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $normalized = strtolower($key);
        foreach ($this->headers as $hKey => $hVal) {
            if (strtolower($hKey) === $normalized) {
                return $hVal;
            }
        }
        return $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                return trim(explode(',', $this->server[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    // ── Parámetros de ruta ────────────────────────────────────────────────────

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->routeParams;
    }

    // ── Sanitización ──────────────────────────────────────────────────────────

    public function sanitizedInput(string $key, mixed $default = null): string
    {
        $value = $this->input($key, $default);
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }
}
