<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\LeadEmailVerification;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

final class PersistLeadHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly LeadRepositoryInterface $repo) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $token = LeadEmailVerification::generateToken();
        $code = LeadEmailVerification::generateCode();

        $id = $this->repo->guardar($draft, [
            'token'      => $token,
            'code_hash'  => LeadEmailVerification::hashCode($code),
            'expires_at' => LeadEmailVerification::expiresAtFromNow(),
        ]);

        return $resultadoPrevio->withLeadId($id)->withEmailVerification($token, $code);
    }
}
