<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Payments\PaymentsFactory;

test('PaymentsFactory lanza si driver no soportado', function (): void {
    PaymentsFactory::resetCached();
    assert_throws(\RuntimeException::class, function (): void {
        PaymentsFactory::buildGateways([
            'bad' => ['driver' => 'unknown', 'enabled' => true, 'config' => []],
        ]);
    });
});
