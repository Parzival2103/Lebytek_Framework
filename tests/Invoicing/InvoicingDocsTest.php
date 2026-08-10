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

test('modulo invoicing documenta runbook de hardening productivo A11-A27', function (): void {
    $doc = invoicingDoc('docs/modules/modulo-invoicing.md');

    $required = [
        'ambiguous create keeps claim' => 'Ambiguous create (A11): no liberar el claim',
        'no new idempotency key' => 'no crear una `idempotencyKey` nueva',
        'typed reconcile id' => 'InvoiceNeedsReconcile::providerInvoiceId()',
        'remote retrieve' => 'ReconcileIssuedInvoice recupera la factura remota con `retrieveInvoice`',
        'orphan list by external id' => 'listByExternalId(A23)',
        'min claim age' => 'reconcile_min_claim_age_seconds',
        'external id algorithm' => 'external_id = `lebytek:invoice:{hex(sha256(providerKey."\\x1f".idempotencyKey))[0:40]}`',
        'external id per attempt' => 'por intento, nunca derivado de `sourceRef` ni truncado',
        'cancel claim before' => 'claim-before',
        'find issue row for cancel' => 'findIssueByProviderInvoiceId',
        'rbac hard rule' => 'Consumer routes must enforce RBAC slugs',
        'webhook wiring' => 'FacturapiInvoiceProvider::parseWebhook',
        'webhook secret env' => 'FACTURAPI_WEBHOOK_SECRET',
        'no fiscal payload logs' => 'no registrar payload fiscal completo',
        'mode key prefix' => 'FACTURAPI_MODE=test requiere `sk_test_`; `live` requiere `sk_live_`',
        'force reissue method' => 'forceReissueOrphanClaim',
        'force precondition zero hits' => 'listByExternalId devuelve 0 hits',
        'force precondition age' => 'edad del claim supera `reconcile_min_claim_age_seconds`',
        'force precondition explicit call' => 'invocacion explicita de ops',
        'force rbac' => 'requiere `invoicing.reconciliar`',
        'force double stamp safety' => 'no puede doble-timbrar',
        'orphan sweep' => 'findOrphanClaims',
        'pending status' => 'provider_status=pending',
        'residual sat catalog' => 'Residual: catalogo SAT completo',
        'residual controller' => 'Residual: controlador HTTP de webhook en Framework',
        'residual live ci' => 'Residual: CI live contra Facturapi',
        'residual cron' => 'Residual: cron worker para `findOrphanClaims`',
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
    assert_doc_contains($spec, 'Production hardening amendments A11-A27 supersede the v1 plan where they disagree', 'hardening amendments pointer');
    assert_doc_contains($spec, 'docs/superpowers/plans/2026-08-07-invoicing-facturapi.md', 'plan link');
    assert_doc_contains($spec, 'docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md', 'hardening plan link');
});

test('planes invoicing apuntan deuda superseded y cierre de hardening task 10', function (): void {
    $v1Plan = invoicingDoc('docs/superpowers/plans/2026-08-07-invoicing-facturapi.md');
    assert_doc_contains($v1Plan, 'D1 superseded by production hardening A15/A22/A24/A26/A27', 'v1 D1 superseded pointer');
    assert_doc_contains($v1Plan, 'D10 superseded by production hardening A19', 'v1 D10 superseded pointer');

    $hardening = invoicingDoc('docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md');
    assert_doc_contains($hardening, '**Completed / total:** 10 / 10', 'hardening completion total');
    assert_doc_contains($hardening, '**Next executable task:** none / closed', 'hardening closed next');
    assert_doc_contains($hardening, 'PR #112', 'hardening PR note');
    assert_doc_contains($hardening, 'cursor/invoicing-hardening-p02-p10-896b', 'hardening branch note');
});

test('contrato RBAC invoicing: manifest y SQL alineados con los cinco slugs operativos', function (): void {
    $expected = [
        'invoicing.emitir',
        'invoicing.cancelar',
        'invoicing.descargar',
        'invoicing.enviar',
        'invoicing.reconciliar',
    ];
    $mod = require ROOT_PATH . '/config/modules/invoicing.php';
    $skel = require ROOT_PATH . '/skeleton/config/modules/invoicing.php';
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql');

    foreach ($expected as $slug) {
        assert_true(in_array($slug, $mod['permisos'] ?? [], true), "manifest missing {$slug}");
        assert_true(in_array($slug, $skel['permisos'] ?? [], true), "skeleton manifest missing {$slug}");
        assert_true(str_contains($sql, $slug), "SQL missing {$slug}");
    }
});
