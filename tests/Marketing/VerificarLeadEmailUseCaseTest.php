<?php
// tests/Marketing/VerificarLeadEmailUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\VerificarLeadEmailUseCase;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\Contracts\LeadTeamAlertNotifierInterface;
use App\Domain\Marketing\LeadEmailVerification;
use App\Domain\Marketing\ValueObjects\LeadDraft;

/** In-memory repo keyed by id; findByEmailVerifyToken ignores burned (null) tokens. */
final class InMemoryLeadRepository implements LeadRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $leads = [];

    private int $nextId = 1;

    /** @param array<string, mixed> $lead */
    public function seed(array $lead): int
    {
        $id = $this->nextId++;
        $this->leads[$id] = array_merge([
            'id'                     => $id,
            'nombre'                 => 'Ana',
            'email'                  => 'ana@x.com',
            'telefono'               => null,
            'mensaje'                => null,
            'estado'                 => 'pendiente',
            'email_verify_token'     => null,
            'email_verify_code_hash' => null,
            'email_verify_expires_at' => null,
            'email_verified_at'      => null,
            'email_verify_attempts'  => 0,
        ], $lead, ['id' => $id]);

        return $id;
    }

    public function guardar(LeadDraft $draft, ?array $emailVerification = null): int { return 0; }

    public function findById(int $id): ?array
    {
        return $this->leads[$id] ?? null;
    }

    public function findByEmailVerifyToken(string $token): ?array
    {
        foreach ($this->leads as $lead) {
            if (($lead['email_verify_token'] ?? null) === $token) {
                return $lead;
            }
        }

        return null;
    }

    public function incrementEmailVerifyAttempts(int $leadId): void
    {
        if (isset($this->leads[$leadId])) {
            $this->leads[$leadId]['email_verify_attempts'] = (int) $this->leads[$leadId]['email_verify_attempts'] + 1;
        }
    }

    public function markEmailVerified(int $leadId): void
    {
        if (isset($this->leads[$leadId])) {
            $this->leads[$leadId]['estado'] = 'validada';
            $this->leads[$leadId]['email_verified_at'] = '2026-07-13 12:00:00';
            $this->leads[$leadId]['email_verify_token'] = null;
            $this->leads[$leadId]['email_verify_code_hash'] = null;
        }
    }

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
}

final class SpyLeadTeamAlertNotifier implements LeadTeamAlertNotifierInterface
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function notifyLeadVerified(array $lead): void
    {
        $this->calls[] = $lead;
    }
}

final class ThrowingLeadTeamAlertNotifier implements LeadTeamAlertNotifierInterface
{
    public int $calls = 0;

    public function notifyLeadVerified(array $lead): void
    {
        $this->calls++;
        throw new \RuntimeException('whatsapp caido');
    }
}

test('token desconocido devuelve invalid', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('token-que-no-existe', 'AB12CD');

    assert_same('invalid', $res['status']);
    assert_same([], $notifier->calls);
});

test('lead ya verificado devuelve already_verified', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $repo->seed([
        'email_verify_token'      => 'tok-verificado',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'email_verified_at'       => '2026-07-01 10:00:00',
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-verificado', null);

    assert_same('already_verified', $res['status']);
    assert_same([], $notifier->calls);
});

test('token expirado devuelve expired', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $repo->seed([
        'email_verify_token'      => 'tok-expirado',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-expirado', 'AB12CD');

    assert_same('expired', $res['status']);
    assert_same([], $notifier->calls);
});

test('intentos agotados devuelve locked', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $repo->seed([
        'email_verify_token'      => 'tok-bloqueado',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'email_verify_attempts'   => LeadEmailVerification::MAX_ATTEMPTS,
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-bloqueado', 'AB12CD');

    assert_same('locked', $res['status']);
    assert_same([], $notifier->calls);
});

test('sin codigo (GET) devuelve form', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $repo->seed([
        'email_verify_token'      => 'tok-form',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-form', null);

    assert_same('form', $res['status']);
    assert_same([], $notifier->calls);
});

test('codigo incorrecto devuelve wrong_code e incrementa intentos', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $id = $repo->seed([
        'email_verify_token'      => 'tok-incorrecto',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-incorrecto', 'ZZZZZZ');

    assert_same('wrong_code', $res['status']);
    assert_same(1, $repo->findById($id)['email_verify_attempts']);
    assert_same([], $notifier->calls);
});

test('codigo vacio contra hash vacio devuelve wrong_code', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $repo->seed([
        'email_verify_token'      => 'tok-sin-hash',
        'email_verify_code_hash'  => null,
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-sin-hash', '');

    assert_same('wrong_code', $res['status']);
});

test('codigo correcto marca verificado, notifica y devuelve ok', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new SpyLeadTeamAlertNotifier();
    $id = $repo->seed([
        'email_verify_token'      => 'tok-correcto',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-correcto', 'ab12cd');

    assert_same('ok', $res['status']);
    assert_same('validada', $repo->findById($id)['estado']);
    assert_true($repo->findById($id)['email_verified_at'] !== null);
    assert_null($repo->findById($id)['email_verify_token']);
    assert_same(1, count($notifier->calls));
    assert_same($id, $notifier->calls[0]['id']);

    // El token quemado ya no se encuentra por findByEmailVerifyToken.
    assert_null($repo->findByEmailVerifyToken('tok-correcto'));
});

test('si el notifier falla, la verificacion sigue devolviendo ok', function (): void {
    $repo = new InMemoryLeadRepository();
    $notifier = new ThrowingLeadTeamAlertNotifier();
    $id = $repo->seed([
        'email_verify_token'      => 'tok-notifier-falla',
        'email_verify_code_hash'  => LeadEmailVerification::hashCode('AB12CD'),
        'email_verify_expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    $uc = new VerificarLeadEmailUseCase($repo, $notifier);

    $res = $uc->execute('tok-notifier-falla', 'AB12CD');

    assert_same('ok', $res['status']);
    assert_same('validada', $repo->findById($id)['estado']);
    assert_same(1, $notifier->calls);
});
