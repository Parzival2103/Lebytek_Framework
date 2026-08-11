<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing\ValueObjects;

use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceDraftInvalid;

final readonly class InvoiceCancellation
{
    private const VALID_MOTIVES = ['01', '02', '03', '04'];

    private string $motive;
    private ?string $substitution;

    public function __construct(
        string $motive,
        ?string $substitution = null,
    ) {
        $normalizedMotive = trim($motive);
        if (! in_array($normalizedMotive, self::VALID_MOTIVES, true)) {
            throw new InvoiceDraftInvalid('Invoice cancellation motive must be one of 01, 02, 03 or 04.');
        }

        $normalizedSubstitution = $substitution !== null ? trim($substitution) : null;
        if ($normalizedSubstitution === '') {
            $normalizedSubstitution = null;
        }

        if ($normalizedMotive === '01' && $normalizedSubstitution === null) {
            throw new InvoiceDraftInvalid('Invoice cancellation motive 01 requires a substitution UUID.');
        }

        $this->motive = $normalizedMotive;
        $this->substitution = $normalizedSubstitution;
    }

    public function motive(): string
    {
        return $this->motive;
    }

    public function substitution(): ?string
    {
        return $this->substitution;
    }
}
