<?php
declare(strict_types=1);

namespace Lebytek\Framework\Domain\Invoicing;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Valid = 'valid';
    case Canceled = 'canceled';
    case NeedsReconcile = 'needs_reconcile';
    case Unknown = 'unknown';

    public static function fromProvider(string $raw): self
    {
        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            'draft' => self::Draft,
            'pending' => self::Pending,
            'valid' => self::Valid,
            'canceled', 'cancelled' => self::Canceled,
            'needs_reconcile' => self::NeedsReconcile,
            default => self::Unknown,
        };
    }
}
