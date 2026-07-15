<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface;

final class InMemoryPaymentEventLog implements PaymentEventLogRepositoryInterface
{
    /** @var array<string, true> */
    private array $claimed = [];

    public function hasProcessed(string $provider, string $eventId): bool
    {
        return isset($this->claimed[$provider."\0".$eventId]);
    }

    public function tryClaim(
        string $provider,
        string $eventId,
        string $orderRef,
        string $type,
        string $payloadHash,
        array $meta = [],
    ): bool {
        $key = $provider."\0".$eventId;
        if (isset($this->claimed[$key])) {
            return false;
        }
        $this->claimed[$key] = true;
        return true;
    }
}

test('tryClaim es atómico para el mismo event_id', function (): void {
    $log = new InMemoryPaymentEventLog();
    assert_true($log->tryClaim('stripe', 'evt_1', 'ord', 'checkout.completed', 'hash'));
    assert_true(! $log->tryClaim('stripe', 'evt_1', 'ord', 'checkout.completed', 'hash'));
    assert_true($log->hasProcessed('stripe', 'evt_1'));
});
