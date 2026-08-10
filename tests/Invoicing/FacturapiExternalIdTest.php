<?php
declare(strict_types=1);

$encoderClass = 'Lebytek\\Framework\\Infrastructure\\Invoicing\\FacturapiExternalId';

test('FacturapiExternalId genera external_id estable por provider e idempotencyKey', function () use ($encoderClass): void {
    assert_true(class_exists($encoderClass), 'FacturapiExternalId class must exist');

    $value = $encoderClass::forIssueClaim('facturapi', 'idem:123');
    $again = $encoderClass::forIssueClaim('facturapi', 'idem:123');
    $differentKey = $encoderClass::forIssueClaim('facturapi', 'idem:456');
    $differentProvider = $encoderClass::forIssueClaim('other-provider', 'idem:123');

    assert_same($again, $value);
    assert_same(
        'lebytek:invoice:' . substr(hash('sha256', "facturapi\x1fidem:123"), 0, 40),
        $value,
    );
    assert_true(strlen($value) <= 100, 'external_id must fit Facturapi limit');
    assert_true($differentKey !== $value, 'same sourceRef reissued with a different idempotencyKey must differ');
    assert_true($differentProvider !== $value, 'different provider keys must differ');
});

test('FacturapiExternalId no expone encoder desde sourceRef', function () use ($encoderClass): void {
    assert_true(class_exists($encoderClass), 'FacturapiExternalId class must exist');

    $ref = new ReflectionClass($encoderClass);

    assert_false($ref->hasMethod('fromSourceRef'), 'external_id must never be derived from sourceRef');
});
