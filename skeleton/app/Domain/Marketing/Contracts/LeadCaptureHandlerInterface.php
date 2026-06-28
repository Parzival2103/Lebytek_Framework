<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

interface LeadCaptureHandlerInterface
{
    /** Procesa un paso del pipeline de captación; devuelve el resultado acumulado. */
    public function handle(LeadDraft $draft, LeadResult $resultadoPrevio): LeadResult;
}
