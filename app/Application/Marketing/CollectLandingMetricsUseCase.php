<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\CollectRateLimiterInterface;
use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

/**
 * Valida y persiste eventos del colector first-party de la landing
 * (`POST /marketing/collect`, endpoint público CSRF-exempt).
 *
 * Endurecimientos obligatorios (Anti-deuda §C/K/L/Q/R/T/V) — ver
 * `docs/superpowers/plans/2026-07-15-landing-experiments-metrics.md`:
 *
 * - `event_type` allowlist; **nunca** `lead_submit` desde cliente (§N — el
 *   servidor lo emite en el flujo de leads, Task 7).
 * - `visitor_id` / `session_public_id` UUID v4 estricto (§V); cookie `lb_vid`
 *   preferida sobre el body cuando es válida (§C.4, anti-spoof).
 * - `variant_slug` resuelto preview cookie → sticky `lb_var` → body (§R);
 *   nunca confiar en el body si hay cookie conocida por el registry.
 * - `is_preview` se deriva **solo** de la cookie `lb_preview`; el body es
 *   spoofeable y se ignora (§K).
 * - `meta` allowlist por `event_type`, claves desconocidas se descartan, y se
 *   rechaza si el JSON codificado excede 2KB (§T).
 * - `heartbeat` actualiza la sesión (duración/scroll) pero **no** inserta fila
 *   en `dom_mkt_landing_events` para evitar flood (§Q, `persist_heartbeat_events`).
 * - Rate limit vía `CollectRateLimiterInterface` (`sys_kv`, §L) — nunca `Session`.
 */
final class CollectLandingMetricsUseCase
{
    private const ALLOWED_EVENT_TYPES = [
        'pageview',
        'scroll_depth',
        'section_view',
        'heartbeat',
        'exit',
        'cta_click',
    ];

    /** Anti-deuda §V — misma regex para visitor_id y session_public_id. */
    private const UUID_V4_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const META_MAX_BYTES = 2048;

    /** Anti-deuda §T — allowlist de claves `meta` por `event_type`. */
    private const META_ALLOWLIST = [
        'pageview'     => [],
        'scroll_depth' => ['pct'],
        'section_view' => ['section'],
        'cta_click'    => ['cta'],
        'exit'         => ['max_scroll_pct', 'exit_section'],
        'heartbeat'    => [],
    ];

    /**
     * @param array{
     *   cookie_preview_name?:string,cookie_var_name?:string,
     *   persist_heartbeat_events?:bool
     * } $config `config/marketing/landing_experiments.php`
     */
    public function __construct(
        private readonly LandingMetricsRepositoryInterface $metrics,
        private readonly LandingVariantRegistry $registry,
        private readonly CollectRateLimiterInterface $rateLimiter,
        private readonly array $config,
    ) {}

    /**
     * @param array<string, mixed> $input Payload crudo (`$_POST` / `all()`).
     * @return array{ok:bool,error?:string}
     */
    public function handle(
        array $input,
        ?string $cookieVisitorId = null,
        ?string $cookiePreview = null,
        ?string $cookieVariant = null,
    ): array {
        $eventType = (string) ($input['event_type'] ?? '');
        if (!in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            return ['ok' => false, 'error' => 'invalid_event_type'];
        }

        $visitorId = (string) ($input['visitor_id'] ?? '');
        if ($cookieVisitorId !== null && $cookieVisitorId !== '' && $this->isUuidV4($cookieVisitorId)) {
            // Anti-deuda §C.4 — cookie lb_vid siempre gana sobre el body si es válida.
            $visitorId = $cookieVisitorId;
        }
        if (!$this->isUuidV4($visitorId)) {
            return ['ok' => false, 'error' => 'invalid_visitor_id'];
        }

        $sessionPublicId = (string) ($input['session_public_id'] ?? '');
        if (!$this->isUuidV4($sessionPublicId)) {
            return ['ok' => false, 'error' => 'invalid_session_public_id'];
        }

        $variantSlug = $this->resolveVariantSlug($input, $cookiePreview, $cookieVariant);
        if ($variantSlug === null) {
            return ['ok' => false, 'error' => 'invalid_variant_slug'];
        }

        if (!$this->rateLimiter->allow('land_collect:' . $visitorId)) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }

        $meta = $this->sanitizeMeta($eventType, $input['meta'] ?? null);
        if ($meta === false) {
            return ['ok' => false, 'error' => 'invalid_meta'];
        }

        // Anti-deuda §K — is_preview solo desde la cookie lb_preview; el body no cuenta.
        $isPreview = $cookiePreview !== null && $cookiePreview !== '';

        $sessionId = $this->metrics->ensureSession([
            'public_id'    => $sessionPublicId,
            'visitor_id'   => $visitorId,
            'variant_slug' => $variantSlug,
            'is_preview'   => $isPreview,
        ]);

        $persistHeartbeatEvents = (bool) ($this->config['persist_heartbeat_events'] ?? false);

        if (in_array($eventType, ['heartbeat', 'exit'], true)) {
            $this->applySessionMetrics($sessionPublicId, $input);
        }

        if ($eventType === 'heartbeat' && !$persistHeartbeatEvents) {
            // Anti-deuda §Q — heartbeat actualiza sesión pero no crece landing_events.
            return ['ok' => true];
        }

        $this->metrics->insertEvent([
            'session_id'   => $sessionId,
            'visitor_id'   => $visitorId,
            'variant_slug' => $variantSlug,
            'event_type'   => $eventType,
            'meta'         => $meta,
            'is_preview'   => $isPreview,
        ]);

        return ['ok' => true];
    }

    /** @param array<string, mixed> $input */
    private function applySessionMetrics(string $sessionPublicId, array $input): void
    {
        $durationMs = isset($input['duration_ms']) ? max(0, (int) $input['duration_ms']) : 0;
        $maxScrollPct = isset($input['max_scroll_pct'])
            ? max(0, min(100, (int) $input['max_scroll_pct']))
            : 0;
        $exitSection = isset($input['exit_section']) && $input['exit_section'] !== ''
            ? mb_substr((string) $input['exit_section'], 0, 60)
            : null;

        $this->metrics->updateSessionMetrics($sessionPublicId, $durationMs, $maxScrollPct, $exitSection);
    }

    /**
     * Anti-deuda §R — orden de resolución: preview cookie → sticky lb_var → body.
     * Solo acepta slugs conocidos por el registry (`get() !== null`); rechaza lo demás.
     *
     * @param array<string, mixed> $input
     */
    private function resolveVariantSlug(array $input, ?string $cookiePreview, ?string $cookieVariant): ?string
    {
        if ($cookiePreview !== null && $cookiePreview !== '' && $this->registry->get($cookiePreview) !== null) {
            return $cookiePreview;
        }

        if ($cookieVariant !== null && $cookieVariant !== '' && $this->registry->get($cookieVariant) !== null) {
            return $cookieVariant;
        }

        $bodySlug = (string) ($input['variant_slug'] ?? '');
        if ($bodySlug !== '' && $this->registry->get($bodySlug) !== null) {
            return $bodySlug;
        }

        return null;
    }

    /**
     * Anti-deuda §T — descarta claves fuera del allowlist del `event_type`;
     * rechaza (devuelve `false`) si el payload excede 2KB codificado.
     *
     * @return array<string, mixed>|false|null `null` = sin meta que persistir.
     */
    private function sanitizeMeta(string $eventType, mixed $rawMeta): array|false|null
    {
        if ($rawMeta === null || $rawMeta === '') {
            return null;
        }

        if (is_string($rawMeta)) {
            if (strlen($rawMeta) > self::META_MAX_BYTES) {
                return false;
            }
            $decoded = json_decode($rawMeta, true);
            $rawMeta = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawMeta)) {
            return null;
        }

        $encodedInbound = json_encode($rawMeta);
        if ($encodedInbound !== false && strlen($encodedInbound) > self::META_MAX_BYTES) {
            return false;
        }

        $allowedKeys = self::META_ALLOWLIST[$eventType] ?? [];
        $out = [];
        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $rawMeta)) {
                continue;
            }
            $value = $this->sanitizeMetaValue($key, $rawMeta[$key]);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out === [] ? null : $out;
    }

    private function sanitizeMetaValue(string $key, mixed $value): int|string|null
    {
        return match ($key) {
            'pct' => in_array((int) $value, [25, 50, 75, 100], true) ? (int) $value : null,
            'max_scroll_pct' => is_scalar($value) ? max(0, min(100, (int) $value)) : null,
            'section' => is_scalar($value) && (string) $value !== '' ? mb_substr((string) $value, 0, 60) : null,
            'exit_section' => is_scalar($value) && (string) $value !== '' ? mb_substr((string) $value, 0, 60) : null,
            'cta' => is_scalar($value) && (string) $value !== '' ? mb_substr((string) $value, 0, 40) : null,
            default => null,
        };
    }

    private function isUuidV4(string $value): bool
    {
        return preg_match(self::UUID_V4_REGEX, $value) === 1;
    }
}
