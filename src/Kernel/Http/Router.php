<?php

declare(strict_types=1);

namespace Lebytek\Framework\Kernel\Http;

use Lebytek\Framework\Kernel\Container\Container;
use Lebytek\Framework\Kernel\Exceptions\HttpException;

final class Router
{
    private array  $routes      = [];
    private string $currentPrefix      = '';
    private array  $currentMiddlewares = [];
    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    // ── Registro de rutas ─────────────────────────────────────────────────────

    public function get(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middlewares);
    }

    public function post(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middlewares);
    }

    public function put(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }

    public function patch(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $middlewares);
    }

    public function delete(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }

    public function any(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        foreach (['GET','POST','PUT','PATCH','DELETE'] as $method) {
            $this->addRoute($method, $pattern, $handler, $middlewares);
        }
    }

    public function group(array $options, callable $callback): void
    {
        $previousPrefix      = $this->currentPrefix;
        $previousMiddlewares = $this->currentMiddlewares;

        $this->currentPrefix      .= ($options['prefix'] ?? '');
        $this->currentMiddlewares  = array_merge(
            $this->currentMiddlewares,
            $options['middlewares'] ?? []
        );

        $callback($this);

        $this->currentPrefix      = $previousPrefix;
        $this->currentMiddlewares = $previousMiddlewares;
    }

    private function addRoute(string $method, string $pattern, array|callable $handler, array $middlewares): void
    {
        $fullPattern     = $this->currentPrefix . $pattern;
        $fullMiddlewares = array_merge($this->currentMiddlewares, $middlewares);

        $this->routes[] = [
            'method'      => $method,
            'pattern'     => $fullPattern,
            'handler'     => $handler,
            'middlewares' => $fullMiddlewares,
        ];
    }

    // ── Despacho ──────────────────────────────────────────────────────────────

    public function dispatch(Request $request): void
    {
        $uri    = $request->uri();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            if ($this->matchRoute($route['pattern'], $uri, $params)) {
                $request->setRouteParams($params);
                $response = $this->runMiddlewares($route['middlewares'], $request, function (Request $req) use ($route) {
                    return $this->callHandler($route['handler'], $req);
                });

                $response->send();
                return;
            }
        }

        foreach ($this->routes as $route) {
            $params = [];
            if ($this->matchRoute($route['pattern'], $uri, $params)) {
                Response::html('', 405)->withHeader('Allow', $route['method'])->send();
                return;
            }
        }

        Response::notFound()->send();
    }

    private function matchRoute(string $pattern, string $uri, array &$params): bool
    {
        $regex = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^/]*)?', $pattern);
        $regex = preg_replace('/\{(\w+)\}/',   '(?P<$1>[^/]+)',  $regex);
        $regex = '@^' . $regex . '$@';

        if (!preg_match($regex, $uri, $matches)) {
            return false;
        }

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return true;
    }

    private function runMiddlewares(array $middlewares, Request $request, callable $final): Response
    {
        if (empty($middlewares)) {
            return $final($request);
        }

        $middleware = array_shift($middlewares);

        if (is_string($middleware)) {
            $instance = new $middleware();
        } else {
            $instance = $middleware;
        }

        return $instance->handle($request, function (Request $req) use ($middlewares, $final) {
            return $this->runMiddlewares($middlewares, $req, $final);
        });
    }

    private function callHandler(array|callable $handler, Request $request): Response
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        [$controllerClass, $action] = $handler;

        if (!class_exists($controllerClass)) {
            throw new HttpException("Controlador {$controllerClass} no encontrado.", 500);
        }

        $controller = ($this->container !== null && $this->container->has($controllerClass))
            ? $this->container->get($controllerClass)
            : new $controllerClass();

        if (!method_exists($controller, $action)) {
            throw new HttpException("Acción {$action} no existe en {$controllerClass}.", 500);
        }

        return $controller->$action($request);
    }
}
