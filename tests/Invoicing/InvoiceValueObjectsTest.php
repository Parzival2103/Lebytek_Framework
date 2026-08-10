<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceNeedsReconcile;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;

test('Money fromMinor normaliza currency a mayúsculas', function (): void {
    $money = Money::fromMinor(219900, 'mxn');
    assert_same(219900, $money->amountMinor());
    assert_same('MXN', $money->currency());
});

test('Money fromMinor acepta currency distinta de MXN sin hard-fail', function (): void {
    $money = Money::fromMinor(10000, 'usd');
    assert_same(10000, $money->amountMinor());
    assert_same('USD', $money->currency());
});

test('Money equals compara minor y currency', function (): void {
    assert_true(Money::fromMinor(100, 'MXN')->equals(Money::fromMinor(100, 'mxn')));
    assert_true(! Money::fromMinor(100, 'MXN')->equals(Money::fromMinor(101, 'MXN')));
});

test('InvoiceDraft aplica defaults cfdiUse G01 y currency MXN', function (): void {
    $customer = new FiscalCustomer(
        legalName: 'Acme SA de CV',
        taxId: 'ACM010101ABC',
        taxSystem: '601',
        address: new Address(zip: '01000'),
    );
    $item = new InvoiceItem(
        quantity: 1.0,
        description: 'Servicio mensual',
        productKey: '80101500',
        unitPrice: Money::fromMinor(100000, 'MXN'),
        taxes: [new InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa')],
    );
    $draft = new InvoiceDraft(
        sourceRef: 'order:01JABCDEF',
        customer: $customer,
        items: [$item],
        paymentForm: PaymentForm::Transferencia,
    );
    assert_same(CfdiUse::G01, $draft->cfdiUse());
    assert_same('MXN', $draft->currency());
    assert_same('order:01JABCDEF', $draft->sourceRef());
});

test('InvoiceStatus fromProvider mapea estados conocidos', function (): void {
    assert_same(InvoiceStatus::Draft, InvoiceStatus::fromProvider('draft'));
    assert_same(InvoiceStatus::Pending, InvoiceStatus::fromProvider('pending'));
    assert_same(InvoiceStatus::Valid, InvoiceStatus::fromProvider('valid'));
    assert_same(InvoiceStatus::Canceled, InvoiceStatus::fromProvider('canceled'));
});

test('InvoiceStatus fromProvider devuelve Unknown para strings desconocidos', function (): void {
    assert_same(InvoiceStatus::Unknown, InvoiceStatus::fromProvider('weird_status'));
    assert_same(InvoiceStatus::Unknown, InvoiceStatus::fromProvider(''));
});

test('InvoiceItem expone líneas de impuesto y taxExempt', function (): void {
    $tax = new InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa');
    $item = new InvoiceItem(
        quantity: 2.0,
        description: 'Licencia',
        productKey: '43231500',
        unitPrice: Money::fromMinor(50000, 'MXN'),
        taxes: [$tax],
        taxExempt: false,
    );
    assert_same(1, count($item->taxes()));
    assert_same('IVA', $item->taxes()[0]->type());
    assert_same(0.16, $item->taxes()[0]->rate());
    assert_same('Tasa', $item->taxes()[0]->factor());
    assert_false($item->taxExempt());
});

test('InvoiceNeedsReconcile es excepción de dominio instanciable', function (): void {
    $previous = new RuntimeException('local persist failed');
    $ex = new InvoiceNeedsReconcile(
        'remote issued but local persist failed',
        'inv_needs_reconcile',
        'facturapi',
        'idem:needs-reconcile',
        $previous,
    );

    assert_true($ex instanceof \RuntimeException);
    assert_same('remote issued but local persist failed', $ex->getMessage());
    assert_same('inv_needs_reconcile', $ex->providerInvoiceId());
    assert_same('facturapi', $ex->providerKey());
    assert_same('idem:needs-reconcile', $ex->idempotencyKey());
    assert_same($previous, $ex->getPrevious());
});
