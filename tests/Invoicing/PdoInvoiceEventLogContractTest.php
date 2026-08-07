<?php
declare(strict_types=1);

use Lebytek\Framework\Infrastructure\Invoicing\PdoInvoiceEventLogRepository;
use Lebytek\Framework\Kernel\Database\Connection;

require_once __DIR__ . '/Support/InvoiceEventLogContract.php';

test('InvoiceEventLog shared contract passes for PDO implementation when DB is available', function (): void {
    assert_true(class_exists(PdoInvoiceEventLogRepository::class), 'PdoInvoiceEventLogRepository class exists');

    try {
        $pdo = Connection::getInstance();
    } catch (Throwable $e) {
        fwrite(STDOUT, "  SKIP  PDO invoicing contract: DB unavailable ({$e->getMessage()})\n");
        return;
    }

    $pdo->exec((string) file_get_contents(ROOT_PATH . '/database/schema/modules/invoicing.sql'));

    run_invoice_event_log_contract(new PdoInvoiceEventLogRepository());
});
