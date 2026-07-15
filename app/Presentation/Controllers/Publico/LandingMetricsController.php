<?php

declare(strict_types=1);

namespace App\Presentation\Controllers\Publico;

use App\Application\Marketing\CollectLandingMetricsUseCase;
use Lebytek\Framework\Kernel\BaseClasses\BaseController;
use Lebytek\Framework\Kernel\EnvLoader;
use Lebytek\Framework\Kernel\Http\Request;
use Lebytek\Framework\Kernel\Http\Response;

/**
 * `POST /marketing/collect` — colector first-party de métricas de landing.
 *
 * Ruta pública **sin** `CsrfMiddleware` (primer POST público abierto del
 * proyecto; endurecido en el mismo PR — Anti-deuda §C): tamaño de body
 * acotado, rate limit vía `sys_kv` (no `Session`), y toda la validación de
 * negocio delegada a `CollectLandingMetricsUseCase`. Respuestas siempre
 * "ligeras" (`{"ok":bool}`), nunca stack traces.
 */
final class LandingMetricsController extends BaseController
{
    /**
     * @param array{collect_max_body_bytes?:int,collect_require_origin?:bool} $config
     *   `config/marketing/landing_experiments.php`
     */
    public function __construct(
        private readonly CollectLandingMetricsUseCase $collectMetrics,
        private readonly array $config,
    ) {}

    public function collect(Request $request): Response
    {
        try {
            $maxBytes = (int) ($this->config['collect_max_body_bytes'] ?? 4096);
            if ($maxBytes > 0 && $this->contentLength($request) > $maxBytes) {
                return $this->json(['ok' => false, 'error' => 'payload_too_large'], 413);
            }

            if ((bool) ($this->config['collect_require_origin'] ?? false) && !$this->originAllowed($request)) {
                return $this->json(['ok' => false, 'error' => 'invalid_origin'], 422);
            }

            $result = $this->collectMetrics->handle(
                $request->all(),
                $this->stringCookie($request, 'lb_vid'),
                $this->stringCookie($request, 'lb_preview'),
                $this->stringCookie($request, 'lb_var'),
            );

            if ($result['ok']) {
                return $this->json(['ok' => true]);
            }

            $status = ($result['error'] ?? '') === 'rate_limited' ? 429 : 422;

            return $this->json(['ok' => false], $status);
        } catch (\Throwable) {
            // Anti-deuda §C.7 — endpoint público sin CSRF: nunca dejar escapar
            // una excepción/stack trace, ni siquiera ante fallos inesperados
            // (DB caída, JSON malformado, etc.). Respuesta siempre ligera.
            return $this->json(['ok' => false], 422);
        }
    }

    private function contentLength(Request $request): int
    {
        $header = $request->header('Content-Length', $request->server('CONTENT_LENGTH', '0'));

        return (int) $header;
    }

    private function stringCookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Anti-deuda §C.6 — opcional; default `collect_require_origin=false`. */
    private function originAllowed(Request $request): bool
    {
        $origin = (string) $request->header('Origin', $request->header('Referer', ''));
        if ($origin === '') {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $appHost = parse_url((string) EnvLoader::get('APP_URL', ''), PHP_URL_HOST);

        return $originHost !== null && $appHost !== null && strcasecmp($originHost, $appHost) === 0;
    }
}
