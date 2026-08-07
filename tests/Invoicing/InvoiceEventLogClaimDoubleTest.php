<?php
declare(strict_types=1);

require_once __DIR__ . '/Support/InMemoryInvoiceEventLog.php';
require_once __DIR__ . '/Support/InvoiceEventLogContract.php';

test('InvoiceEventLog shared contract passes for InMemory implementation', function (): void {
    run_invoice_event_log_contract(new InMemoryInvoiceEventLog());
});
