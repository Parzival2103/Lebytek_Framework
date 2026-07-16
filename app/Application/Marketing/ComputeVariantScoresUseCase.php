<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

/**
 * Calcula el score híbrido (engagement + conversión) por variante de landing
 * a partir de `LandingMetricsRepositoryInterface::aggregateForScore()`
 * (siempre `is_preview = 0`) y, si el cambio de pesos sugerido respecto a
 * los pesos vigentes es material, encola una propuesta `pending` en
 * `dom_mkt_variant_proposals` para revisión manual.
 *
 * **Nunca** escribe `dom_mkt_variant_weights` — el ajuste de pesos sigue
 * siendo 100% decisión de ops vía accept/reject (Task 9, Anti-deuda §W).
 * Esta clase solo lee `VariantWeightRepositoryInterface` (nunca `upsert()`).
 *
 * Reglas de negocio (override Task 8, Anti-deuda §E):
 * - A lo sumo una propuesta `pending` a la vez: `supersedeAllPending()` se
 *   llama antes de insertar una nueva.
 * - Si la `pending` existente ya tiene los mismos `suggested_weights`
 *   (comparación por claves ordenadas, valores redondeados), no se inserta
 *   duplicado y `proposals_created` es `0`.
 * - Kill (peso sugerido → 0) solo aplica a variantes con
 *   `sessions >= min_sessions` cuyo score es el más bajo del grupo con
 *   muestra suficiente **y** la brecha contra el segundo lugar es
 *   `>= proposal_min_delta`; nunca se mata una variante bajo el mínimo de
 *   muestra (`conversion` sigue siendo `null` para esas — ver
 *   `scoreVariants()`).
 * - Las variantes activas por debajo del mínimo de muestra conservan su
 *   peso vigente; si ese peso queda por debajo del piso de exploración
 *   (`min_explore_weight`), se eleva a ese piso restando la masa
 *   proporcionalmente del resto de variantes.
 * - `suggested_weights` siempre se normaliza para sumar `1.0`.
 */
final class ComputeVariantScoresUseCase
{
    /** Anti-deuda / brief Task 8 — caps de normalización del score de engagement. */
    private const CAP_SCROLL_PCT = 100.0;
    private const CAP_DURATION_MS = 180000.0;
    private const CAP_SECTIONS = 7.0;

    /** @param array<string, mixed> $config `config/marketing/landing_experiments.php` */
    public function __construct(
        private readonly LandingMetricsRepositoryInterface $metrics,
        private readonly VariantWeightRepositoryInterface $weights,
        private readonly VariantProposalRepositoryInterface $proposals,
        private readonly LandingVariantRegistry $registry,
        private readonly array $config,
    ) {
    }

    /** @return array{proposals_created:int, rankings:list<array<string, mixed>>} */
    public function ejecutar(): array
    {
        $windowDays = (int) ($this->config['score_window_days'] ?? 14);
        $minSessions = (int) ($this->config['min_sessions'] ?? 50);
        $minDelta = (float) ($this->config['proposal_min_delta'] ?? 0.05);
        $minExplore = (float) ($this->config['min_explore_weight'] ?? 0.05);

        [$rankings, $scored] = $this->scoreVariants($this->metrics->aggregateForScore($windowDays), $minSessions);

        $activeSlugs = $this->registry->activeSlugs();
        $currentWeights = [];
        foreach ($activeSlugs as $slug) {
            $currentWeights[$slug] = $this->weights->get($slug) ?? $this->registry->weightDefault($slug);
        }

        ['weights' => $suggested, 'killed' => $killedSlug] = $this->suggestWeights(
            $activeSlugs,
            $scored,
            $currentWeights,
            $minSessions,
            $minDelta,
            $minExplore,
        );

        $maxDelta = 0.0;
        foreach ($activeSlugs as $slug) {
            $maxDelta = max($maxDelta, abs(($suggested[$slug] ?? 0.0) - ($currentWeights[$slug] ?? 0.0)));
        }

        if ($maxDelta < $minDelta) {
            return ['proposals_created' => 0, 'rankings' => $rankings];
        }

        $existing = $this->proposals->findLatestPending();
        $existingSuggested = is_array($existing) && is_array($existing['payload']['suggested_weights'] ?? null)
            ? $existing['payload']['suggested_weights']
            : null;
        if ($existingSuggested !== null && $this->sameWeights($existingSuggested, $suggested)) {
            return ['proposals_created' => 0, 'rankings' => $rankings];
        }

        $payload = [
            'window_days' => $windowDays,
            'rankings' => $rankings,
            'current_weights' => $this->roundAll($currentWeights),
            'suggested_weights' => $this->roundAll($suggested),
            'reason' => $killedSlug !== null ? 'kill_low_performer' : 'material_delta',
        ];

        $this->proposals->supersedeAllPending();
        $this->proposals->insertPending($payload);

        return ['proposals_created' => 1, 'rankings' => $rankings];
    }

    /**
     * @param list<array<string, mixed>> $aggregate
     * @return array{
     *   0: list<array<string, mixed>>,
     *   1: array<string, array{sessions:int, score:float, sufficient:bool}>
     * }
     */
    private function scoreVariants(array $aggregate, int $minSessions): array
    {
        $wEng = (float) ($this->config['w_eng'] ?? 0.35);
        $wConv = (float) ($this->config['w_conv'] ?? 0.65);

        $rankings = [];
        $scored = [];
        foreach ($aggregate as $row) {
            $slug = (string) $row['variant_slug'];
            $sessions = (int) $row['sessions'];
            $leads = (int) $row['leads'];

            $engagement = $this->engagement($row);
            // Anti-deuda override T8 — conversion es null (no proponer kill) si la muestra
            // no alcanza min_sessions; el score de ranking usa solo engagement en ese caso.
            $conversion = $sessions >= $minSessions && $sessions > 0 ? $leads / $sessions : null;
            $score = $conversion !== null ? ($wEng * $engagement + $wConv * $conversion) : $engagement;

            $scored[$slug] = [
                'sessions' => $sessions,
                'score' => $score,
                'sufficient' => $conversion !== null,
            ];

            $rankings[] = [
                'slug' => $slug,
                'score' => round($score, 4),
                'sessions' => $sessions,
                'leads' => $leads,
                'engagement' => round($engagement, 4),
                'conversion' => $conversion !== null ? round($conversion, 4) : null,
            ];
        }

        usort($rankings, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return [$rankings, $scored];
    }

    /** @param array{avg_scroll:mixed, avg_duration_ms:mixed, sections_seen_avg:mixed} $row */
    private function engagement(array $row): float
    {
        $normScroll = min(1.0, ((float) $row['avg_scroll']) / self::CAP_SCROLL_PCT);
        $normDuration = min(1.0, ((float) $row['avg_duration_ms']) / self::CAP_DURATION_MS);
        $normSections = min(1.0, ((float) $row['sections_seen_avg']) / self::CAP_SECTIONS);

        return 0.4 * $normScroll + 0.4 * $normDuration + 0.2 * $normSections;
    }

    /**
     * @param list<string> $activeSlugs
     * @param array<string, array{sessions:int, score:float, sufficient:bool}> $scored
     * @param array<string, float> $currentWeights
     * @return array{weights: array<string, float>, killed: ?string}
     */
    private function suggestWeights(
        array $activeSlugs,
        array $scored,
        array $currentWeights,
        int $minSessions,
        float $minDelta,
        float $minExplore,
    ): array {
        $underSample = [];
        $sufficient = [];
        foreach ($activeSlugs as $slug) {
            $row = $scored[$slug] ?? null;
            if ($row !== null && $row['sessions'] >= $minSessions) {
                $sufficient[] = $slug;
            } else {
                $underSample[] = $slug;
            }
        }

        // Anti-deuda override T8 — variantes bajo muestra conservan su peso vigente;
        // consumen esa "masa" del total antes de repartir el resto por score.
        $suggested = [];
        $keptMass = 0.0;
        foreach ($underSample as $slug) {
            $kept = $currentWeights[$slug] ?? 0.0;
            $suggested[$slug] = $kept;
            $keptMass += $kept;
        }

        $killedSlug = $this->pickKill($sufficient, $scored, $minDelta);
        if ($killedSlug !== null) {
            $suggested[$killedSlug] = 0.0;
        }

        $pool = array_values(array_filter($sufficient, static fn (string $s): bool => $s !== $killedSlug));
        $remainingMass = max(0.0, 1.0 - $keptMass);

        if ($pool !== []) {
            $totalScore = 0.0;
            foreach ($pool as $slug) {
                $totalScore += max(0.0, $scored[$slug]['score']);
            }

            if ($totalScore > 0.0) {
                foreach ($pool as $slug) {
                    $suggested[$slug] = $remainingMass * (max(0.0, $scored[$slug]['score']) / $totalScore);
                }
            } else {
                $share = $remainingMass / count($pool);
                foreach ($pool as $slug) {
                    $suggested[$slug] = $share;
                }
            }
        }

        $suggested = $this->normalize($suggested, $activeSlugs);
        $suggested = $this->applyExplorationFloor($suggested, $underSample, $minExplore);

        return ['weights' => $suggested, 'killed' => $killedSlug];
    }

    /**
     * Kill solo si hay >= 2 variantes con muestra suficiente y la brecha entre
     * la más baja y la segunda más baja es >= proposal_min_delta.
     *
     * @param list<string> $sufficient
     * @param array<string, array{sessions:int, score:float, sufficient:bool}> $scored
     */
    private function pickKill(array $sufficient, array $scored, float $minDelta): ?string
    {
        if (count($sufficient) < 2) {
            return null;
        }

        $withScores = array_map(
            static fn (string $slug): array => ['slug' => $slug, 'score' => $scored[$slug]['score']],
            $sufficient,
        );
        usort($withScores, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        $lowest = $withScores[0];
        $second = $withScores[1];

        if (($second['score'] - $lowest['score']) >= $minDelta) {
            return (string) $lowest['slug'];
        }

        return null;
    }

    /**
     * @param array<string, float> $weights
     * @param list<string> $activeSlugs
     * @return array<string, float>
     */
    private function normalize(array $weights, array $activeSlugs): array
    {
        foreach ($activeSlugs as $slug) {
            $weights[$slug] ??= 0.0;
        }

        $sum = array_sum($weights);
        if ($sum <= 0.0) {
            $share = $activeSlugs === [] ? 0.0 : 1.0 / count($activeSlugs);
            foreach ($activeSlugs as $slug) {
                $weights[$slug] = $share;
            }

            return $weights;
        }

        foreach ($weights as $slug => $weight) {
            $weights[$slug] = $weight / $sum;
        }

        return $weights;
    }

    /**
     * Eleva el peso sugerido de variantes activas bajo muestra al piso de
     * exploración (`min_explore_weight`) si quedaron por debajo, restando la
     * masa faltante proporcionalmente del resto (mantiene suma = 1.0).
     *
     * @param array<string, float> $weights
     * @param list<string> $underSample
     * @return array<string, float>
     */
    private function applyExplorationFloor(array $weights, array $underSample, float $minExplore): array
    {
        $needsFloor = array_values(array_filter(
            $underSample,
            static fn (string $slug): bool => ($weights[$slug] ?? 0.0) < $minExplore,
        ));

        if ($needsFloor === []) {
            return $weights;
        }

        $floorMass = 0.0;
        foreach ($needsFloor as $slug) {
            $floorMass += $minExplore - ($weights[$slug] ?? 0.0);
            $weights[$slug] = $minExplore;
        }

        $others = array_values(array_filter(
            array_keys($weights),
            static fn (string $slug): bool => !in_array($slug, $needsFloor, true),
        ));

        $othersMass = 0.0;
        foreach ($others as $slug) {
            $othersMass += $weights[$slug];
        }

        if ($othersMass > 0.0) {
            $shrinkRatio = max(0.0, ($othersMass - $floorMass) / $othersMass);
            foreach ($others as $slug) {
                $weights[$slug] *= $shrinkRatio;
            }
        }

        return $weights;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, float> $suggested
     */
    private function sameWeights(array $existing, array $suggested): bool
    {
        return $this->weightsFingerprint($existing) === $this->weightsFingerprint($suggested);
    }

    /** @param array<string, mixed> $weights */
    private function weightsFingerprint(array $weights): string
    {
        $rounded = [];
        foreach ($weights as $slug => $value) {
            $rounded[(string) $slug] = round((float) $value, 6);
        }
        ksort($rounded);

        return json_encode($rounded, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, float> $weights @return array<string, float> */
    private function roundAll(array $weights): array
    {
        return array_map(static fn (float $w): float => round($w, 6), $weights);
    }
}
