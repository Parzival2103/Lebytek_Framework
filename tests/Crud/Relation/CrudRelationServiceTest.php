<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudRelationService;
use Lebytek\Framework\Domain\Entities\Crud\CrudRelationDefinition;

require_once dirname(__DIR__, 2) . '/fixtures/relation_repos.php';

test('CrudRelationService: optionsFor returns value=>label for belongsTo', function (): void {
    $repo = new FakeRelationRepository();
    $repo->options['dom_demo_categorias'] = ['1' => 'Bebidas', '2' => 'Snacks'];
    $svc = new CrudRelationService($repo);

    $rel = CrudRelationDefinition::fromArray('categoria', [
        'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
        'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
    ]);
    assert_same(['1' => 'Bebidas', '2' => 'Snacks'], $svc->optionsFor($rel));
});

test('CrudRelationService: optionsFor returns empty for hasMany (not a select source)', function (): void {
    $repo = new FakeRelationRepository();
    $svc = new CrudRelationService($repo);
    $rel = CrudRelationDefinition::fromArray('items', ['type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id']);
    assert_same([], $svc->optionsFor($rel));
});

test('CrudRelationService: childrenFor passes parent id and returns rows', function (): void {
    $repo = new FakeRelationRepository();
    $repo->children['dom_demo_pedido_items'] = [['id' => 1, 'cantidad' => 3]];
    $svc = new CrudRelationService($repo);

    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
        'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']], 'order_by' => 'id', 'direction' => 'asc', 'limit' => 10,
    ]);
    $rows = $svc->childrenFor($rel, 42);
    assert_same([['id' => 1, 'cantidad' => 3]], $rows);
    assert_same(42, $repo->childCalls[0]['parent']);
    assert_same('ASC', $repo->childCalls[0]['dir']);
});

test('CrudRelationService: childrenFor returns empty for belongsTo', function (): void {
    $repo = new FakeRelationRepository();
    $svc = new CrudRelationService($repo);
    $rel = CrudRelationDefinition::fromArray('categoria', ['type' => 'belongsTo', 'table' => 'dom_demo_categorias', 'foreign_key' => 'categoria_id']);
    assert_same([], $svc->childrenFor($rel, 1));
});
