<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;

interface InvoiceableSourceInterface
{
    public function findDraft(string $sourceRef): ?InvoiceDraft;
}
