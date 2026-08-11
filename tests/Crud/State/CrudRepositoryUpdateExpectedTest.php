<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/fixtures/fake_crud_repository.php';

test('updateRecord with expected returns 0 when predicate misses', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'autorizado', 'deleted' => 0];
    $n = $repo->updateRecord('dom_x', 'id', 1, ['status' => 'x'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(0, $n);
    assert_same('autorizado', $repo->rowsById[1]['status']);
});

test('updateRecord with expected updates when predicate matches', function (): void {
    $repo = new FakeCrudRepository();
    $repo->rowsById[1] = ['id' => 1, 'status' => 'pendiente', 'deleted' => 0];
    $n = $repo->updateRecord('dom_x', 'id', 1, ['status' => 'autorizado'], ['status' => 'pendiente', 'deleted' => 0]);
    assert_same(1, $n);
    assert_same('autorizado', $repo->rowsById[1]['status']);
});
