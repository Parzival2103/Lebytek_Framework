<?php
// tests/Marketing/ComputeVariantScoresUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\ComputeVariantScoresUseCase;
use App\Domain\Marketing\Contracts\LandingMetricsRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

final class FakeAggregateMetrics implements LandingMetricsRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function findSessionByPublicId(string $publicId): ?array
    {
        return null;
    }

    public function ensureSession(array $data): int
    {
        return 0;
    }

    public function updateSessionMetrics(string $publicId, int $durationMs, int $maxScrollPct, ?string $exitSection): void {}

    public function insertEvent(array $data): void {}

    public function insertLeadSubmitEvent(string $visitorId, string $variantSlug): void {}

    /** @return list<array<string, mixed>> */
    public function aggregateForScore(int $windowDays): array
    {
        return $this->rows;
    }

    public function purgeOlderThan(\DateTimeImmutable $cutoff): array
    {
        return ['sessions' => 0, 'events' => 0];
    }
}

final class FakeScoreWeights implements VariantWeightRepositoryInterface
{
    /** @param array<string,float> $weights */
    public function __construct(private array $weights) {}

    public function all(): array
    {
        return $this->weights;
    }

    public function get(string $slug): ?float
    {
        return $this->weights[$slug] ?? null;
    }

    public function upsert(string $slug, float $weight): void
    {
        $this->weights[$slug] = $weight;
    }

    public function seedMissing(array $defaults): void
    {
        foreach ($defaults as $slug => $weight) {
            if (!isset($this->weights[$slug])) {
                $this->weights[$slug] = $weight;
            }
        }
    }
}

final class FakeProposals implements VariantProposalRepositoryInterface
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];

    private int $nextId = 1;

    /** @param array<string, mixed> $payload */
    public function insertPending(array $payload): int
    {
        $id = $this->nextId++;
        $this->rows[] = ['id' => $id, 'status' => 'pending', 'payload' => $payload];

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function findPending(): array
    {
        return array_values(array_filter($this->rows, static fn (array $r): bool => $r['status'] === 'pending'));
    }

    /** @return array<string, mixed>|null */
    public function findLatestPending(): ?array
    {
        $pending = $this->findPending();

        return $pending === [] ? null : $pending[count($pending) - 1];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function acceptAtomically(int $id, int $userId, callable $applyWeights): bool
    {
        foreach ($this->rows as &$row) {
            if ($row['id'] === $id) {
                if ($row['status'] !== 'pending') {
                    return false;
                }
                $row['status'] = 'accepted';
                $applyWeights();

                return true;
            }
        }

        return false;
    }

    public function markRejected(int $id, int $userId): int
    {
        foreach ($this->rows as &$row) {
            if ($row['id'] === $id) {
                if ($row['status'] !== 'pending') {
                    return 0;
                }
                $row['status'] = 'rejected';

                return 1;
            }
        }

        return 0;
    }

    public function supersedeAllPending(): int
    {
        $n = 0;
        foreach ($this->rows as &$row) {
            if ($row['status'] === 'pending') {
                $row['status'] = 'superseded';
                $n++;
            }
        }

        return $n;
    }
}

/** @return array{ComputeVariantScoresUseCase, FakeScoreWeights, FakeProposals} */
function makeComputeUseCase(FakeAggregateMetrics $metrics, array $initialWeights): array
{
    $weights = new FakeScoreWeights($initialWeights);
    $props = new FakeProposals();
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    $uc = new ComputeVariantScoresUseCase($metrics, $weights, $props, new LandingVariantRegistry($cfg), $exp);

    return [$uc, $weights, $props];
}

test('compute crea proposal pending cuando delta material', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug' => 'v1', 'sessions' => 100, 'avg_scroll' => 40, 'avg_duration_ms' => 20000, 'leads' => 2, 'top_exit_section' => 'pricing', 'sections_seen_avg' => 3],
        ['variant_slug' => 'v2', 'sessions' => 100, 'avg_scroll' => 80, 'avg_duration_ms' => 60000, 'leads' => 10, 'top_exit_section' => 'faq', 'sections_seen_avg' => 6],
    ]);
    [$uc, $weights, $props] = makeComputeUseCase($metrics, ['v1' => 0.5, 'v2' => 0.5]);
    $out = $uc->ejecutar();
    assert_true($out['proposals_created'] >= 1, 'crea proposal');
    assert_same('pending', $props->rows[0]['status']);
    assert_same(0.5, $weights->get('v1'), 'no muta pesos');
});

test('compute no propone kill bajo minimo de sesiones', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug' => 'v1', 'sessions' => 10, 'avg_scroll' => 10, 'avg_duration_ms' => 1000, 'leads' => 0, 'top_exit_section' => 'hero', 'sections_seen_avg' => 1],
        ['variant_slug' => 'v2', 'sessions' => 100, 'avg_scroll' => 80, 'avg_duration_ms' => 60000, 'leads' => 10, 'top_exit_section' => 'faq', 'sections_seen_avg' => 6],
    ]);
    [$uc, , $props] = makeComputeUseCase($metrics, ['v1' => 0.5, 'v2' => 0.5]);
    $out = $uc->ejecutar();
    if ($out['proposals_created'] > 0) {
        $sug = $props->rows[0]['payload']['suggested_weights'];
        assert_true((float) $sug['v1'] > 0.0, 'no kill v1 por sample bajo');
    }
});

test('compute supersede pending previa en segundo run con pesos distintos', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug' => 'v1', 'sessions' => 100, 'avg_scroll' => 40, 'avg_duration_ms' => 20000, 'leads' => 2, 'top_exit_section' => 'pricing', 'sections_seen_avg' => 3],
        ['variant_slug' => 'v2', 'sessions' => 100, 'avg_scroll' => 80, 'avg_duration_ms' => 60000, 'leads' => 12, 'top_exit_section' => 'faq', 'sections_seen_avg' => 6],
    ]);
    [$uc, $weights, $props] = makeComputeUseCase($metrics, ['v1' => 0.9, 'v2' => 0.1]);
    $uc->ejecutar();
    $weights->upsert('v1', 0.85);
    $weights->upsert('v2', 0.15);
    $uc->ejecutar();
    $pending = array_values(array_filter($props->rows, static fn (array $r): bool => $r['status'] === 'pending'));
    assert_same(1, count($pending), 'solo una pending');
});

test('compute nunca llama upsert de pesos', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Application/Marketing/ComputeVariantScoresUseCase.php');
    assert_true(!str_contains($src, '->upsert('), 'no upsert de pesos desde compute (Anti-deuda §E/§W)');
});

test('compute suggested_weights suma 1.0 y variante bajo muestra respeta piso de exploracion', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug' => 'v1', 'sessions' => 2, 'avg_scroll' => 5, 'avg_duration_ms' => 500, 'leads' => 0, 'top_exit_section' => 'hero', 'sections_seen_avg' => 1],
        ['variant_slug' => 'v2', 'sessions' => 100, 'avg_scroll' => 80, 'avg_duration_ms' => 60000, 'leads' => 10, 'top_exit_section' => 'faq', 'sections_seen_avg' => 6],
    ]);
    [$uc, , $props] = makeComputeUseCase($metrics, ['v1' => 0.0, 'v2' => 1.0]);
    $out = $uc->ejecutar();
    assert_true($out['proposals_created'] >= 1, 'crea proposal por delta material');
    $sug = $props->rows[0]['payload']['suggested_weights'];
    $sum = array_sum(array_map('floatval', $sug));
    assert_true(abs($sum - 1.0) < 0.0001, 'suma 1.0');
    $exp = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    assert_true((float) $sug['v1'] >= (float) $exp['min_explore_weight'] - 0.0001, 'piso de exploracion aplicado a v1');
});

test('compute no crea segunda proposal si suggested_weights es idéntico', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug' => 'v1', 'sessions' => 100, 'avg_scroll' => 40, 'avg_duration_ms' => 20000, 'leads' => 2, 'top_exit_section' => 'pricing', 'sections_seen_avg' => 3],
        ['variant_slug' => 'v2', 'sessions' => 100, 'avg_scroll' => 80, 'avg_duration_ms' => 60000, 'leads' => 12, 'top_exit_section' => 'faq', 'sections_seen_avg' => 6],
    ]);
    [$uc, , $props] = makeComputeUseCase($metrics, ['v1' => 0.9, 'v2' => 0.1]);
    $out1 = $uc->ejecutar();
    assert_true($out1['proposals_created'] >= 1, 'primer run crea proposal');
    $out2 = $uc->ejecutar();
    assert_same(0, $out2['proposals_created'], 'sin cambios en aggregate, no duplica proposal');
    $pending = array_values(array_filter($props->rows, static fn (array $r): bool => $r['status'] === 'pending'));
    assert_same(1, count($pending), 'sigue habiendo solo una pending');
});
