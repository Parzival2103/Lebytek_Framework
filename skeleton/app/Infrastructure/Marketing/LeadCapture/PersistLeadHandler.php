<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing\LeadCapture;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\Contracts\LeadRepositoryInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

final class PersistLeadHandler implements LeadCaptureHandlerInterface
{
    public function __construct(private readonly LeadRepositoryInterface $repo) {}

    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult
    {
        $id = $this->repo->guardar($draft);
        return $resultadoPrevio->withLeadId($id);
    }
}
