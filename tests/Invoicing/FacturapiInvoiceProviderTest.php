<?php
declare(strict_types=1);

use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Domain\Invoicing\InvoiceStatus;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceCancellation;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;
use Lebytek\Framework\Infrastructure\Invoicing\Facturapi\FacturapiTransportInterface;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;

/** @return array<string, mixed> */
function facturapi_fixture(string $name): array
{
    $path = __DIR__ . '/fixtures/' . $name . '.json';
    $json = file_get_contents($path);
    assert_true(is_string($json), "Fixture {$name} must be readable");
    $decoded = json_decode($json, true);
    assert_true(is_array($decoded), "Fixture {$name} must decode to array");

    return $decoded;
}

function assert_facturapi_payload(array $expected, mixed $actual): void
{
    assert_true(is_array($actual), 'payload must be array');
    assert_same($expected, $actual, 'payload');
}

/** @param list<string> $forbidden */
function assert_no_secret_leak(Throwable $exception, array $forbidden): void
{
    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        foreach ($forbidden as $token) {
            assert_true(
                !str_contains($current->getMessage(), $token),
                'exception chain leaked secret token: ' . $token,
            );
        }
    }
}

function make_facturapi_fake_provider(): array
{
    if (!interface_exists(FacturapiTransportInterface::class) || !class_exists(FacturapiInvoiceProvider::class)) {
        throw new RuntimeException('Facturapi provider infrastructure is not implemented');
    }

    $transport = new class implements FacturapiTransportInterface {
        /** @var array<int, array<string, mixed>> */
        public array $createdPayloads = [];
        /** @var array<int, array{id: string, payload: array<string, mixed>}> */
        public array $cancelCalls = [];
        /** @var array<int, array{id: string, email: string}> */
        public array $emailCalls = [];
        public bool $throwOnCreate = false;
        public string $throwOnCreateMessage = 'Facturapi rejected request for sk_test_SECRET';

        /** @param array<string, mixed> $payload */
        public function create(array $payload): array
        {
            if ($this->throwOnCreate) {
                throw new RuntimeException($this->throwOnCreateMessage);
            }

            $this->createdPayloads[] = $payload;

            return [
                'id' => 'fact_inv_123',
                'uuid' => '39c85a3f-275b-4341-b259-e8971d9f8a94',
                'status' => 'valid',
                'folio_number' => 914,
                'pdf_url' => 'https://example.test/invoice.pdf',
                'xml_url' => 'https://example.test/invoice.xml',
            ];
        }

        /** @param array<string, mixed> $payload */
        public function cancel(string $providerInvoiceId, array $payload): array
        {
            $this->cancelCalls[] = ['id' => $providerInvoiceId, 'payload' => $payload];

            return [
                'id' => $providerInvoiceId,
                'uuid' => '39c85a3f-275b-4341-b259-e8971d9f8a94',
                'status' => 'canceled',
                'folio_number' => '914',
            ];
        }

        public function pdf(string $providerInvoiceId): string
        {
            return '%PDF-' . $providerInvoiceId;
        }

        public function xml(string $providerInvoiceId): string
        {
            return '<cfdi id="' . $providerInvoiceId . '"/>';
        }

        public function email(string $providerInvoiceId, string $email): array
        {
            $this->emailCalls[] = ['id' => $providerInvoiceId, 'email' => $email];

            return ['sent' => true];
        }
    };

    return [new FacturapiInvoiceProvider($transport), $transport];
}

function facturapi_iva16_draft(): InvoiceDraft
{
    return new InvoiceDraft(
        sourceRef: 'order:iva16',
        customer: new FiscalCustomer(
            legalName: 'ACME SA DE CV',
            taxId: 'ACM010101ABC',
            taxSystem: '601',
            address: new Address(zip: '01000', country: 'MEX', street: 'Av Siempre Viva 123'),
            email: 'billing@example.test',
        ),
        items: [
            new InvoiceItem(
                quantity: 2.0,
                description: 'Servicio mensual',
                productKey: '81112100',
                unitPrice: Money::fromMinor(123456, 'MXN'),
                taxes: [new InvoiceTax(type: 'IVA', rate: 0.16, factor: 'Tasa')],
                taxExempt: false,
                unitKey: 'E48',
            ),
        ],
        paymentForm: PaymentForm::Transferencia,
        cfdiUse: CfdiUse::G03,
        currency: 'MXN',
    );
}

function facturapi_exento_draft(): InvoiceDraft
{
    return new InvoiceDraft(
        sourceRef: 'order:exento',
        customer: new FiscalCustomer(
            legalName: 'SERVICIOS EXENTOS SA DE CV',
            taxId: 'SEX0101019A0',
            taxSystem: '601',
            address: new Address(zip: '03100'),
        ),
        items: [
            new InvoiceItem(
                quantity: 1.0,
                description: 'Servicio exento',
                productKey: '80141600',
                unitPrice: Money::fromMinor(50000, 'MXN'),
                taxes: [],
                taxExempt: true,
                unitKey: 'E48',
            ),
        ],
        paymentForm: PaymentForm::PorDefinir,
    );
}

test('FacturapiInvoiceProvider expone key facturapi', function (): void {
    [$provider] = make_facturapi_fake_provider();

    assert_same('facturapi', $provider->key());
});

test('FacturapiInvoiceProvider mapea IVA 16 a payload golden y convierte Money a precio mayor', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();

    $issued = $provider->createInvoice(facturapi_iva16_draft());

    assert_same('fact_inv_123', $issued->providerInvoiceId());
    assert_same('39c85a3f-275b-4341-b259-e8971d9f8a94', $issued->uuid());
    assert_same(InvoiceStatus::Valid, $issued->status());
    assert_same('914', $issued->folioNumber());
    assert_same('order:iva16', $issued->sourceRef());
    assert_facturapi_payload(facturapi_fixture('facturapi_payload_iva16'), $transport->createdPayloads[0] ?? null);
});

test('FacturapiInvoiceProvider mapea taxExempt a IVA Exento en payload golden', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();

    $provider->createInvoice(facturapi_exento_draft());

    assert_facturapi_payload(facturapi_fixture('facturapi_payload_exento'), $transport->createdPayloads[0] ?? null);
});

test('FacturapiInvoiceProvider delega cancel, pdf, xml y email al transporte', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();

    $canceled = $provider->cancelInvoice('fact_inv_123', new InvoiceCancellation('02', 'uuid-substitution'));

    assert_same(InvoiceStatus::Canceled, $canceled->status());
    assert_same([
        'id' => 'fact_inv_123',
        'payload' => ['motive' => '02', 'substitution' => 'uuid-substitution'],
    ], $transport->cancelCalls[0] ?? null);
    assert_same('%PDF-fact_inv_123', $provider->downloadPdf('fact_inv_123'));
    assert_same('<cfdi id="fact_inv_123"/>', $provider->downloadXml('fact_inv_123'));

    $provider->sendByEmail('fact_inv_123', 'billing@example.test');

    assert_same(['id' => 'fact_inv_123', 'email' => 'billing@example.test'], $transport->emailCalls[0] ?? null);
});

test('FacturapiInvoiceProvider envuelve errores del transporte sin filtrar secretos', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();
    $transport->throwOnCreate = true;

    try {
        $provider->createInvoice(facturapi_iva16_draft());
    } catch (InvoiceProviderException $exception) {
        assert_true(str_contains($exception->getMessage(), 'Facturapi create invoice failed'));
        assert_no_secret_leak($exception, ['sk_test', 'SECRET']);
        return;
    }

    throw new RuntimeException('expected InvoiceProviderException');
});

test('FacturapiInvoiceProvider redacta sk_user en la cadena de excepciones', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();
    $transport->throwOnCreate = true;
    $transport->throwOnCreateMessage = 'Facturapi rejected request for sk_user_ABC123';

    try {
        $provider->createInvoice(facturapi_iva16_draft());
    } catch (InvoiceProviderException $exception) {
        assert_no_secret_leak($exception, ['sk_user_ABC123', 'ABC123']);
        return;
    }

    throw new RuntimeException('expected InvoiceProviderException');
});

test('FacturapiInvoiceProvider redacta Bearer en la cadena de excepciones', function (): void {
    [$provider, $transport] = make_facturapi_fake_provider();
    $transport->throwOnCreate = true;
    $transport->throwOnCreateMessage = 'Unauthorized Bearer eyJhbGciOiJIUzI1NiJ9.payload.signature';

    try {
        $provider->createInvoice(facturapi_iva16_draft());
    } catch (InvoiceProviderException $exception) {
        assert_no_secret_leak($exception, ['Bearer eyJhbGciOiJIUzI1NiJ9', 'eyJhbGciOiJIUzI1NiJ9']);
        return;
    }

    throw new RuntimeException('expected InvoiceProviderException');
});
