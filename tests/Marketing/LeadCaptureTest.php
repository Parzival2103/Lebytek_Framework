<?php
// tests/Marketing/LeadCaptureTest.php
declare(strict_types=1);

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;
use App\Application\Marketing\CapturarLeadUseCase;
use App\Infrastructure\Marketing\LeadCapture\PersistLeadHandler;

test('PersistLeadHandler guarda y rellena leadId', function (): void {
    $repo = new class implements LeadRepositoryInterface {
        public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 7; }
        public function findById(int $id): ?array { return null; }
        public function findByEmailVerifyToken(string $token): ?array { return null; }
        public function incrementEmailVerifyAttempts(int $leadId): void {}
        public function markEmailVerified(int $leadId): void {}
        public function markApiProvisioned(
            int $leadId,
            string $tenantPublicId,
            string $externalRef,
            string $instancePublicId = '',
            ?int $paqueteId = null,
            string $planSlug = 'demo',
            int $demoDays = 30,
        ): void {}
        public function markApiProvisionError(int $leadId, string $error): void {}
        public function markApiDeprovisionInitiated(int $leadId): void {}
        public function markApiDeprovisionCompleted(int $leadId): void {}
        public function findDemosOlderThanDays(int $days): array { return []; }
        public function findDemosExpired(): array { return []; }
        public function findPendingDeprovisions(): array { return []; }
        public function findDemoPackageBySlug(string $slug): ?array { return null; }
        public function findLatestByEmail(string $email): ?array { return null; }
    };
    $h = new PersistLeadHandler($repo);
    $res = $h->handle(new LeadDraft('Ana', 'ana@x.com'), new LeadResult(true));
    assert_same(true, $res->ok());
    assert_same(7, $res->leadId());
    assert_true($res->emailVerifyToken() !== null);
    assert_true($res->emailVerifyCode() !== null);
});

test('CapturarLeadUseCase recorre la cadena en orden', function (): void {
    $marca = [];
    $h1 = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'a'; return $r->withLeadId(1); }
    };
    $h2 = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'b'; return $r; }
    };
    $uc = new CapturarLeadUseCase([$h1, $h2]);
    $res = $uc->ejecutar(new LeadDraft('Ana', 'ana@x.com'));
    assert_same(['a','b'], $marca);
    assert_same(true, $res->ok());
    assert_same(1, $res->leadId());
});

test('CapturarLeadUseCase aborta la cadena si un paso falla', function (): void {
    $marca = [];
    $falla = new class implements LeadCaptureHandlerInterface {
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { return new LeadResult(false, null, ['x' => 'no']); }
    };
    $nuncaCorre = new class($marca) implements LeadCaptureHandlerInterface {
        public function __construct(private array &$m) {}
        public function handle(LeadDraft $d, LeadResult $r): LeadResult { $this->m[] = 'corrió'; return $r; }
    };
    $uc = new CapturarLeadUseCase([$falla, $nuncaCorre]);
    $res = $uc->ejecutar(new LeadDraft('Ana', 'ana@x.com'));
    assert_same(false, $res->ok());
    assert_same([], $marca);
});
