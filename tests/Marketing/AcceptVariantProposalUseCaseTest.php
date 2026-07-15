<?php
// tests/Marketing/AcceptVariantProposalUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\AcceptVariantProposalUseCase;
use App\Application\Marketing\RejectVariantProposalUseCase;
use App\Domain\Marketing\Contracts\VariantProposalRepositoryInterface;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;

final class FakeExperimentWeights implements VariantWeightRepositoryInterface
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

final class FakeExperimentProposals implements VariantProposalRepositoryInterface
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];

    private int $nextId = 1;

    /** @param array<string, mixed> $payload */
    public function insertPending(array $payload): int
    {
        $id = $this->nextId++;
        $this->rows[] = ['id' => $id, 'status' => 'pending', 'payload' => $payload, 'created_at' => '2026-07-15 00:00:00'];

        return $id;
    }

    public function findPending(): array
    {
        return array_values(array_filter($this->rows, static fn (array $r): bool => $r['status'] === 'pending'));
    }

    public function findLatestPending(): ?array
    {
        $pending = $this->findPending();

        return $pending === [] ? null : $pending[count($pending) - 1];
    }

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

test('accept aplica suggested_weights y cierra proposal', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.2, 'v2' => 0.8],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 42);
    assert_same(0.2, $weights->get('v1'));
    assert_same(0.8, $weights->get('v2'));
    assert_same('accepted', $props->findById($id)['status']);
});

test('accept rechaza propuesta stale si pesos drift', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.9, 'v2' => 0.1]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.2, 'v2' => 0.8],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $threw = false;
    try {
        $uc->ejecutar($id, 42);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'stale');
    assert_same(0.9, $weights->get('v1'), 'no muta');
    assert_same('pending', $props->findById($id)['status']);
});

test('accept normaliza pesos a suma 1', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 1.0, 'v2' => 1.0],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 1);
    $sum = (float) $weights->get('v1') + (float) $weights->get('v2');
    assert_true(abs($sum - 1.0) < 1e-6, 'normalizado');
});

test('accept es no-op/fail si proposal ya no pending (doble submit)', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.2, 'v2' => 0.8],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 1);
    $threw = false;
    try {
        $uc->ejecutar($id, 2);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'segunda accept falla');
    assert_same(0.2, $weights->get('v1'), 'pesos inalterados en 2do intento');
});

test('accept rechaza si suggested_weights suma <= 0', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.0, 'v2' => 0.0],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $threw = false;
    try {
        $uc->ejecutar($id, 1);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'suma invalida rechazada');
    assert_same('pending', $props->findById($id)['status'], 'no se resuelve si suma invalida');
});

test('reject no cambia pesos', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.1, 'v2' => 0.9],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new RejectVariantProposalUseCase($props);
    $uc->ejecutar($id, 7);
    assert_same(0.5, $weights->get('v1'));
    assert_same('rejected', $props->findById($id)['status']);
});

test('reject es no-op/fail si proposal ya no pending (doble submit)', function (): void {
    $props = new FakeExperimentProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1' => 0.1, 'v2' => 0.9],
        'current_weights' => ['v1' => 0.5, 'v2' => 0.5],
    ]);
    $uc = new RejectVariantProposalUseCase($props);
    $uc->ejecutar($id, 7);
    $threw = false;
    try {
        $uc->ejecutar($id, 8);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'segundo reject falla');
});

test('accept lanza si proposal no existe o no esta pending', function (): void {
    $weights = new FakeExperimentWeights(['v1' => 0.5, 'v2' => 0.5]);
    $props = new FakeExperimentProposals();
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $threw = false;
    try {
        $uc->ejecutar(999, 1);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'proposal inexistente rechazada');
});
