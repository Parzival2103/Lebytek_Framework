<?php

declare(strict_types=1);

namespace App\Application\Marketing;

use App\Domain\Marketing\Contracts\LeadCaptureHandlerInterface;
use App\Domain\Marketing\ValueObjects\LeadDraft;
use App\Domain\Marketing\ValueObjects\LeadResult;

final class CapturarLeadUseCase
{
    /** @param list<LeadCaptureHandlerInterface> $handlers */
    public function __construct(private readonly array $handlers) {}

    public function ejecutar(LeadDraft $draft): LeadResult
    {
        $resultado = new LeadResult(true);
        foreach ($this->handlers as $handler) {
            $resultado = $handler->handle($draft, $resultado);
            if (!$resultado->ok()) {
                return $resultado; // aborta la cadena
            }
        }
        return $resultado;
    }
}
