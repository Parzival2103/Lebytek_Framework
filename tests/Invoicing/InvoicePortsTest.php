<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceEventLogRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\InvoiceProviderInterface;
use Lebytek\Framework\Domain\Invoicing\OrganizationSettingsRepositoryInterface;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\IssuedInvoice;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\OrganizationSettings;

/**
 * @param array<int, array{name: string, type?: string, optional?: bool, default?: mixed}> $expectedParams
 */
function assert_interface_method(ReflectionClass $ref, string $method, array $expectedParams, ?string $returnType = null): void
{
    assert_true($ref->hasMethod($method), "{$ref->getName()} missing method {$method}");
    $m = $ref->getMethod($method);
    assert_same(count($expectedParams), $m->getNumberOfParameters(), "{$method} param count");

    foreach ($expectedParams as $i => $expected) {
        $p = $m->getParameters()[$i];
        assert_same($expected['name'], $p->getName(), "{$method} param {$i} name");
        if (isset($expected['type'])) {
            $actual = $p->getType();
            assert_true($actual !== null, "{$method}::{$expected['name']} must be typed");
            assert_same($expected['type'], (string) $actual, "{$method}::{$expected['name']} type");
        }
        if (isset($expected['optional'])) {
            assert_same($expected['optional'], $p->isOptional(), "{$method}::{$expected['name']} optional flag");
        }
        if (array_key_exists('default', $expected)) {
            assert_same($expected['default'], $p->getDefaultValue(), "{$method}::{$expected['name']} default");
        }
    }

    if ($returnType !== null) {
        $rt = $m->getReturnType();
        assert_true($rt !== null, "{$method} must declare return type");
        assert_same($returnType, (string) $rt, "{$method} return type");
    }
}

test('InvoiceProviderInterface expone métodos del contrato v1', function (): void {
    $ref = new ReflectionClass(InvoiceProviderInterface::class);
    assert_true($ref->isInterface());

    assert_interface_method($ref, 'key', [], 'string');
    assert_interface_method($ref, 'createInvoice', [
        ['name' => 'draft', 'type' => InvoiceDraft::class],
    ], IssuedInvoice::class);
    assert_interface_method($ref, 'cancelInvoice', [
        ['name' => 'providerInvoiceId', 'type' => 'string'],
        ['name' => 'cancellation', 'type' => InvoiceCancellation::class],
    ], IssuedInvoice::class);
    assert_interface_method($ref, 'downloadPdf', [
        ['name' => 'providerInvoiceId', 'type' => 'string'],
    ], 'string');
    assert_interface_method($ref, 'downloadXml', [
        ['name' => 'providerInvoiceId', 'type' => 'string'],
    ], 'string');
    assert_interface_method($ref, 'sendByEmail', [
        ['name' => 'providerInvoiceId', 'type' => 'string'],
        ['name' => 'email', 'type' => 'string'],
    ], 'void');
});

test('InvoiceableSourceInterface expone findDraft', function (): void {
    $ref = new ReflectionClass(InvoiceableSourceInterface::class);
    assert_true($ref->isInterface());
    assert_interface_method($ref, 'findDraft', [
        ['name' => 'sourceRef', 'type' => 'string'],
    ], '?'.InvoiceDraft::class);
});

test('InvoiceEventLogRepositoryInterface expone ledger incluyendo reconcile (D1)', function (): void {
    $ref = new ReflectionClass(InvoiceEventLogRepositoryInterface::class);
    assert_true($ref->isInterface());

    assert_interface_method($ref, 'hasProcessed', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
    ], 'bool');
    assert_interface_method($ref, 'tryClaim', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
        ['name' => 'sourceRef', 'type' => 'string'],
        ['name' => 'type', 'type' => 'string'],
        ['name' => 'meta', 'type' => 'array', 'optional' => true, 'default' => []],
    ], 'bool');
    assert_interface_method($ref, 'releaseClaim', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
    ], 'void');
    assert_interface_method($ref, 'markIssued', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
        ['name' => 'invoice', 'type' => IssuedInvoice::class],
    ], 'void');
    assert_interface_method($ref, 'markNeedsReconcile', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
        ['name' => 'invoice', 'type' => IssuedInvoice::class],
    ], 'void');
    assert_interface_method($ref, 'findByIdempotencyKey', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'idempotencyKey', 'type' => 'string'],
    ], '?'.IssuedInvoice::class);
    assert_interface_method($ref, 'findIssuedBySourceRef', [
        ['name' => 'sourceRef', 'type' => 'string'],
    ], 'array');
    assert_interface_method($ref, 'findNeedsReconcile', [
        ['name' => 'provider', 'type' => 'string'],
        ['name' => 'limit', 'type' => 'int', 'optional' => true, 'default' => 100],
    ], 'array');
});

test('OrganizationSettingsRepositoryInterface expone get y upsert', function (): void {
    $ref = new ReflectionClass(OrganizationSettingsRepositoryInterface::class);
    assert_true($ref->isInterface());

    assert_interface_method($ref, 'get', [
        ['name' => 'providerKey', 'type' => 'string'],
        ['name' => 'externalOrgId', 'type' => 'string', 'optional' => true, 'default' => ''],
    ], '?'.OrganizationSettings::class);
    assert_interface_method($ref, 'upsert', [
        ['name' => 'settings', 'type' => OrganizationSettings::class],
    ], 'void');
});
