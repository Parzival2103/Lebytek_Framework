<?php
declare(strict_types=1);

test('invoicing bootstrap SQL crea inv_events idempotente con claim y reconcile', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `inv_events`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_inv_events_provider_idempotency`'));
    assert_true(str_contains($sql, '`provider`, `idempotency_key`'));
    assert_true(str_contains($sql, 'KEY `idx_inv_events_source_ref` (`source_ref`)'));
    assert_true(str_contains($sql, 'KEY `idx_inv_events_provider_status` (`provider`, `status`)'));
    assert_true(str_contains($sql, '`provider_invoice_id`'));
    assert_true(str_contains($sql, 'DEFAULT NULL'));
    assert_true(str_contains($sql, '`uuid`'));
    assert_true(str_contains($sql, '`folio_number`'));
    assert_true(str_contains($sql, '`status`'));
    foreach (['claimed', 'issued', 'needs_reconcile', 'canceled'] as $status) {
        assert_true(str_contains($sql, $status), "schema must document status {$status}");
    }
});

test('invoicing bootstrap SQL crea inv_organizations con org cache', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `inv_organizations`'));
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_inv_organizations_provider_external`'));
    assert_true(str_contains($sql, '`provider_key`, `external_org_id`'));
    assert_true(str_contains($sql, '`external_org_id` VARCHAR(190)'));
    assert_true(str_contains($sql, "NOT NULL DEFAULT ''"));
    assert_true(str_contains($sql, '`mode`'));
    assert_true(str_contains($sql, '`label`'));
    assert_true(str_contains($sql, '`meta`') && str_contains($sql, 'JSON'));
});

test('invoicing bootstrap SQL no define tablas dom_', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql');
    assert_true(! str_contains($sql, 'dom_'));
});
