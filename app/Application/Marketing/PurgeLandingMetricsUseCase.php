<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;

/**
 * Purga sesiones y eventos de landing anteriores al cutoff de retención.
 *
 * Anti-deuda: solo toca `dom_mkt_landing_events` y `dom_mkt_landing_sessions`
 * vía `LandingMetricsRepositoryInterface::purgeOlderThan()` — nunca elimina
 * pesos (`dom_mkt_variant_weights`) ni propuestas (`dom_mkt_variant_proposals`).
 *
 * El repositorio elimina eventos (`created_at < cutoff`) antes que sesiones
 * (`last_seen_at < cutoff`) para evitar consultas huérfanas.
 */
final class PurgeLandingMetricsUseCase
{
    /** @param array<string, mixed> $config `config/marketing/landing_experiments.php` */
    public function __construct(
        private readonly LandingMetricsRepositoryInterface $metrics,
        private readonly array $config,
    ) {
    }

    /** @return array{sessions:int,events:int} filas eliminadas */
    public function ejecutar(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $retentionDays = (int) ($this->config['retention_days'] ?? 90);
        $cutoff = $now->modify(sprintf('-%d days', $retentionDays));

        return $this->metrics->purgeOlderThan($cutoff);
    }
}
