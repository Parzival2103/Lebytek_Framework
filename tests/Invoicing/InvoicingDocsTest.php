<?php
declare(strict_types=1);

function invoicingDoc(string $relativePath): string
{
    $path = ROOT_PATH . '/' . $relativePath;
    assert_true(is_readable($path), "expected readable doc {$relativePath}");

    return (string) file_get_contents($path);
}

function assert_doc_contains(string $haystack, string $needle, string $label): void
{
    assert_true(str_contains($haystack, $needle), "missing {$label}: {$needle}");
}

test('modulo invoicing documenta el checklist contractual completo', function (): void {
    $doc = invoicingDoc('docs/modules/modulo-invoicing.md');

    $required = [
        'Framework vs consumidor' => 'Framework vs consumidor',
        'sin Money compartido con Payments' => 'No compartir `Money` con Payments',
        'tabla env vars' => '| Variable | Requerida | Default | Uso |',
        'vertical y SQL' => 'php scripts/install.php --modules=core,invoicing',
        'source interface' => 'InvoiceableSourceInterface',
        'bind source' => 'Bind `InvoiceableSourceInterface`',
        'conditional Issue' => 'IssueInvoiceFromSource',
        'factory helper' => 'InvoicingFactory::makeIssueInvoiceFromSource',
        'test mode sequence' => 'Secuencia de emision en test mode',
        'runbook needs reconcile' => 'Runbook A1/D1',
        'call reconcile' => 'ReconcileIssuedInvoice',
        'never reissue' => 'nunca re-emitir con una idempotency key nueva',
        'A2 rules' => 'Reglas de resolucion A2',
        'release strategy' => 'Estrategia de release D7/A9',
        'php 8.2' => 'PHP >=8.2',
        'stripe require pattern' => 'patron `require` de Stripe',
        'future webhooks' => 'webhooks',
        'future refresh' => 'RefreshInvoiceStatus',
        'document ISP split' => 'ISP split para documentos',
        'invariants' => 'Invariantes A1-A3',
    ];

    foreach ($required as $label => $needle) {
        assert_doc_contains($doc, $needle, $label);
    }
});

test('documentacion transversal menciona ownership invoicing inv y vertical', function (): void {
    $architecture = invoicingDoc('docs/ARCHITECTURE-CONSUMER.md');
    assert_doc_contains($architecture, 'Invoicing generic', 'architecture invoicing row');
    assert_doc_contains($architecture, 'Lebytek\\Framework\\Domain\\Invoicing\\', 'architecture framework namespace');
    assert_doc_contains($architecture, 'InvoiceableSourceInterface', 'architecture source ownership');

    $prefixes = invoicingDoc('docs/core/table-prefix-convention.md');
    assert_doc_contains($prefixes, '`inv_`', 'inv prefix');
    assert_doc_contains($prefixes, 'Facturacion electronica', 'inv prefix role');

    $onboarding = invoicingDoc('docs/core/vertical-onboarding.md');
    assert_doc_contains($onboarding, 'invoicing', 'vertical onboarding flag');
    assert_doc_contains($onboarding, 'docs/modules/modulo-invoicing.md', 'vertical onboarding guide');
});

test('spec invoicing queda marcada plan-ready implementada con puntero a enmiendas', function (): void {
    $spec = invoicingDoc('docs/superpowers/specs/2026-08-07-invoicing-facturapi-design.md');
    assert_doc_contains($spec, '**Estado:** plan-ready / implemented', 'spec status');
    assert_doc_contains($spec, 'Plan amendments A1-A10 supersede this spec where they disagree', 'amendments pointer');
    assert_doc_contains($spec, 'docs/superpowers/plans/2026-08-07-invoicing-facturapi.md', 'plan link');
});
