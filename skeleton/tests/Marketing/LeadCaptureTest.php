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
        public function guardar(LeadDraft $draft): int { return 7; }
    };
    $h = new PersistLeadHandler($repo);
    $res = $h->handle(new LeadDraft('Ana', 'ana@x.com'), new LeadResult(true));
    assert_same(true, $res->ok());
    assert_same(7, $res->leadId());
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
