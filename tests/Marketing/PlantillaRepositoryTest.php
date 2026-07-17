<?php

declare(strict_types=1);

use App\Domain\Marketing\Contracts\PlantillaRepositoryInterface;
use App\Infrastructure\Marketing\PdoPlantillaRepository;

test('marketing module registers plantilla migrations', function (): void {
    $manifest = require ROOT_PATH.'/config/modules/marketing.php';
    $migrations = $manifest['migraciones'] ?? [];
    assert_true(in_array('20260715200000_mkt_plantillas_unique_clave.sql', $migrations, true));
    assert_true(in_array('20260715200100_mkt_plantillas_seed_catalog.sql', $migrations, true));
});

test('marketing.sql defines unique clave on dom_mkt_plantillas', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH.'/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, 'UNIQUE KEY `uq_mkt_plantillas_clave`'));
});

test('plantilla seed migration includes catalog claves', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH.'/database/migrations/20260715200100_mkt_plantillas_seed_catalog.sql');
    foreach ([
        'lead_welcome',
        'lead_api_credentials',
        'membership_activated',
        'membership_payment_failed',
        'membership_cancelled_reactivate',
    ] as $clave) {
        assert_true(str_contains($sql, "'{$clave}'"), "seed includes {$clave}");
    }
    assert_true(str_contains($sql, 'lead_autoresponder'), 'legacy key alignment');
});

test('PdoPlantillaRepository implements findActiveByClave', function (): void {
    $repo = new PdoPlantillaRepository();
    assert_true($repo instanceof PlantillaRepositoryInterface);
});
