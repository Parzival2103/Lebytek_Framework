<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use App\Domain\Marketing\ValueObjects\LeadDraft;

interface LeadRepositoryInterface
{
    /** Persiste un lead y devuelve su id. */
    public function guardar(LeadDraft $draft): int;
}
