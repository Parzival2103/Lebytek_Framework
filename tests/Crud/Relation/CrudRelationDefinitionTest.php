<?php

declare(strict_types=1);

use App\Domain\Entities\Crud\CrudRelationDefinition;

test('CrudRelationDefinition: belongsTo expone columnas value/label y filtro', function (): void {
    $rel = CrudRelationDefinition::fromArray('categoria', [
        'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
        'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
        'filter' => ['activa' => 1], 'order_by' => 'nombre',
    ]);
    assert_same('categoria', $rel->name());
    assert_true($rel->isBelongsTo());
    assert_true(!$rel->isHasMany());
    assert_same('dom_demo_categorias', $rel->table());
    assert_same('categoria_id', $rel->foreignKey());
    assert_same('id', $rel->valueColumn());
    assert_same('nombre', $rel->labelColumn());
    assert_same(['activa' => 1], $rel->filter());
    assert_same('nombre', $rel->orderBy());
});

test('CrudRelationDefinition: hasMany expone columnas, direccion y limite', function (): void {
    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
        'columns' => [['name' => 'cantidad', 'label' => 'Cantidad']],
        'order_by' => 'id', 'direction' => 'asc', 'limit' => 25,
    ]);
    assert_true($rel->isHasMany());
    assert_same('pedido_id', $rel->foreignKey());
    assert_same('ASC', $rel->direction());
    assert_same(25, $rel->limit());
    assert_same([['name' => 'cantidad', 'label' => 'Cantidad']], $rel->columns());
});

test('CrudRelationDefinition: hasMany aplica defaults (DESC, limit 50)', function (): void {
    $rel = CrudRelationDefinition::fromArray('items', [
        'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
    ]);
    assert_same('DESC', $rel->direction());
    assert_same(50, $rel->limit());
    assert_same('id', $rel->orderBy());
});
