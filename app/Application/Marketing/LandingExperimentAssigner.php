<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

/**
 * Sticky weighted assigner. Pure Application service: takes an AssignInput
 * DTO (no Kernel Request), returns an AssignedLandingVariant with queued
 * CookieSpec objects for Presentation to apply before the response body —
 * this class never touches cookies or headers directly (Anti-deuda §A).
 */
final class LandingExperimentAssigner
{
    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /** @param array<string,mixed> $cfg landing_experiments.php */
    public function __construct(
        private readonly LandingVariantRegistry $registry,
        private readonly VariantWeightRepositoryInterface $weights,
        private readonly array $cfg,
    ) {
    }

    public function assign(AssignInput $input): AssignedLandingVariant
    {
        $this->weights->seedMissing($this->seedDefaults());

        $cookies = [];
        $visitorId = $this->ensureVisitorId($input, $cookies);

        $force = strtolower(trim($input->forceVariant));
        if ($force !== '' && $this->registry->get($force) !== null) {
            $cookies[] = new CookieSpec(
                name: (string) $this->cfg['cookie_preview_name'],
                value: $force,
                maxAgeSeconds: (int) $this->cfg['preview_cookie_ttl_seconds'],
            );

            return new AssignedLandingVariant(
                $force,
                $this->shellOf($force),
                true,
                $visitorId,
                $cookies
            );
        }

        $cookies[] = CookieSpec::delete((string) $this->cfg['cookie_preview_name']);

        $stickySlug = strtolower((string) ($input->cookies[$this->cfg['cookie_var_name']] ?? ''));
        if ($stickySlug !== '' && $this->isEligible($stickySlug)) {
            return new AssignedLandingVariant(
                $stickySlug,
                $this->shellOf($stickySlug),
                false,
                $visitorId,
                $cookies
            );
        }

        $slug = $this->weightedPick($this->eligibleWeights()) ?? $this->fallbackSlug();
        $cookies[] = new CookieSpec(
            name: (string) $this->cfg['cookie_var_name'],
            value: $slug,
            ttlDays: (int) $this->cfg['cookie_ttl_days'],
        );

        return new AssignedLandingVariant(
            $slug,
            $this->shellOf($slug),
            false,
            $visitorId,
            $cookies
        );
    }

    /** @param list<CookieSpec> $cookies */
    private function ensureVisitorId(AssignInput $input, array &$cookies): string
    {
        $vidName = (string) $this->cfg['cookie_vid_name'];
        $existing = (string) ($input->cookies[$vidName] ?? '');
        if ($existing !== '' && preg_match(self::UUID_V4_PATTERN, $existing) === 1) {
            return $existing;
        }

        $visitorId = self::generateUuidV4();
        $cookies[] = new CookieSpec(
            name: $vidName,
            value: $visitorId,
            ttlDays: (int) $this->cfg['cookie_ttl_days'],
        );

        return $visitorId;
    }

    private function isEligible(string $slug): bool
    {
        $row = $this->registry->get($slug);
        if ($row === null || ($row['status'] ?? '') !== 'active') {
            return false;
        }

        $weight = $this->weights->get($slug);

        return $weight !== null && $weight > 0.0;
    }

    /** @return array<string,float> */
    private function eligibleWeights(): array
    {
        $out = [];
        foreach ($this->registry->activeSlugs() as $slug) {
            if ($this->isEligible($slug)) {
                $out[$slug] = (float) $this->weights->get($slug);
            }
        }

        return $out;
    }

    /** @param array<string,float> $weights */
    private function weightedPick(array $weights): ?string
    {
        $sum = array_sum($weights);
        if ($sum <= 0.0) {
            return null;
        }

        $scale = 1_000_000;
        $target = random_int(1, max(1, (int) round($sum * $scale)));
        $accumulated = 0;
        foreach ($weights as $slug => $weight) {
            $accumulated += (int) round($weight * $scale);
            if ($target <= $accumulated) {
                return $slug;
            }
        }

        $slugs = array_keys($weights);

        return $slugs[count($slugs) - 1] ?? null;
    }

    private function fallbackSlug(): string
    {
        $configured = (string) ($this->cfg['fallback_slug'] ?? '');
        $configuredRow = $configured !== '' ? $this->registry->get($configured) : null;
        if ($configuredRow !== null && ($configuredRow['status'] ?? '') === 'active') {
            return $configured;
        }

        $active = $this->registry->activeSlugs();

        return $active[0] ?? $configured;
    }

    /** @return array<string,float> */
    private function seedDefaults(): array
    {
        $known = $this->cfg['seed_weight_defaults'] ?? null;
        /** @var array<string,float> $defaults */
        $defaults = is_callable($known) ? (array) $known() : (is_array($known) ? $known : []);

        foreach ($this->registry->activeSlugs() as $slug) {
            if (!array_key_exists($slug, $defaults)) {
                $defaults[$slug] = $this->registry->weightDefault($slug);
            }
        }

        return $defaults;
    }

    private function shellOf(string $slug): string
    {
        $row = $this->registry->get($slug);

        return (string) ($row['shell'] ?? $slug);
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
