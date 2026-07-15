<?php
// tests/Marketing/PdoVariantWeightRepositoryContractTest.php
declare(strict_types=1);

test('PdoVariantWeightRepository implementa la interfaz de pesos', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Infrastructure/Marketing/PdoVariantWeightRepository.php');
    assert_true(str_contains($src, 'implements \\App\\Domain\\Marketing\\Contracts\\VariantWeightRepositoryInterface')
        || str_contains($src, 'implements VariantWeightRepositoryInterface'), 'implements');
    assert_true(str_contains($src, 'function seedMissing'), 'seedMissing');
    assert_true(str_contains($src, 'function upsert'), 'upsert');
});
