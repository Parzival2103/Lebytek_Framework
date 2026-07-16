<?php
declare(strict_types=1);

test('MembershipOrderRepositoryInterface expone métodos stripe', function (): void {
    $ref = new ReflectionClass(\App\Domain\Marketing\Contracts\MembershipOrderRepositoryInterface::class);
    assert_true($ref->hasMethod('markPaymentPending'));
    assert_true($ref->hasMethod('savePaymentRef'));
    assert_true($ref->hasMethod('findByPaymentRef'));
});
