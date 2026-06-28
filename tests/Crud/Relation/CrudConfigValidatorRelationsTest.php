<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('CrudConfigValidator: relation con type inválido es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'manyToMany', 'table' => 'dom_x', 'foreign_key' => 'x_id']],
    ]);
    assert_true(in_array("relations.x.type debe ser 'belongsTo' o 'hasMany'.", $errors, true), 'falta error de type');
});

test('CrudConfigValidator: relation con tabla de prefijo bloqueado es error', function (): void {
    $errors = CrudConfigValidator::relationsBlockErrors([
        'relations' => ['x' => ['type' => 'belongsTo', 'table' => 'auth_usuarios', 'foreign_key' => 'x_id', 'value' => 'id', 'label' => 'nombre']],
    ]);
    assert_true(in_array("relations.x.table (auth_usuarios) usa un prefijo bloqueado.", $errors, true), 'falta error de prefijo');
});

test('CrudConfigValidator: detail tab relation debe referenciar una relación existente', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'relations' => [],
        'detail' => ['tabs' => [['key' => 'items', 'type' => 'relation', 'relation' => 'items']]],
    ]);
    assert_true(in_array("detail.tabs[0] (relation) referencia una relación inexistente: 'items'.", $errors, true), 'falta error de relación');
});

test('CrudConfigValidator: detail tab component con .. (traversal) es error', function (): void {
    $errors = CrudConfigValidator::detailBlockErrors([
        'detail' => ['tabs' => [['key' => 'c', 'type' => 'component', 'view' => '../../etc/passwd']]],
    ]);
    assert_true(in_array("detail.tabs[0] (component) tiene una vista con ruta inválida.", $errors, true), 'falta error de traversal');
});

test('CrudConfigValidator: relations/detail vacíos no generan errores', function (): void {
    assert_same([], CrudConfigValidator::relationsBlockErrors([]));
    assert_same([], CrudConfigValidator::detailBlockErrors([]));
});

test('CrudConfigValidator: belongsTo con foreign_key en la tabla local no es falso positivo', function (): void {
    // Reproduce el bug: categoria_id es la FK del recurso (vive en
    // dom_demo_productos), NO en la tabla relacionada dom_demo_categorias.
    $config = [
        'resource' => ['table' => 'dom_demo_productos'],
        'relations' => [
            'categoria' => [
                'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
                'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
            ],
        ],
    ];
    $schema = [
        'dom_demo_productos'  => ['id', 'nombre', 'categoria_id', 'status'],
        'dom_demo_categorias' => ['id', 'nombre', 'activa'],
    ];
    assert_same([], CrudConfigValidator::relationsSchemaErrors($config, $schema));
});

test('CrudConfigValidator: belongsTo reporta foreign_key faltante en la tabla local', function (): void {
    $config = [
        'resource' => ['table' => 'dom_demo_productos'],
        'relations' => [
            'categoria' => [
                'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
                'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
            ],
        ],
    ];
    $schema = [
        'dom_demo_productos'  => ['id', 'nombre', 'status'], // sin categoria_id
        'dom_demo_categorias' => ['id', 'nombre', 'activa'],
    ];
    $errors = CrudConfigValidator::relationsSchemaErrors($config, $schema);
    assert_true(
        in_array('relations.categoria.foreign_key (categoria_id) no existe en dom_demo_productos.', $errors, true),
        'falta error de foreign_key local'
    );
});

test('CrudConfigValidator: belongsTo reporta value/label faltante en la tabla relacionada', function (): void {
    $config = [
        'resource' => ['table' => 'dom_demo_productos'],
        'relations' => [
            'categoria' => [
                'type' => 'belongsTo', 'table' => 'dom_demo_categorias',
                'foreign_key' => 'categoria_id', 'value' => 'id', 'label' => 'nombre',
            ],
        ],
    ];
    $schema = [
        'dom_demo_productos'  => ['id', 'nombre', 'categoria_id'],
        'dom_demo_categorias' => ['id', 'activa'], // sin columna nombre
    ];
    $errors = CrudConfigValidator::relationsSchemaErrors($config, $schema);
    assert_true(
        in_array('relations.categoria.label (nombre) no existe en dom_demo_categorias.', $errors, true),
        'falta error de label en tabla relacionada'
    );
});

test('CrudConfigValidator: hasMany valida foreign_key en la tabla hija', function (): void {
    $config = [
        'resource' => ['table' => 'dom_demo_pedidos'],
        'relations' => [
            'items' => [
                'type' => 'hasMany', 'table' => 'dom_demo_pedido_items', 'foreign_key' => 'pedido_id',
            ],
        ],
    ];
    $ok = [
        'dom_demo_pedidos'      => ['id', 'folio'],
        'dom_demo_pedido_items' => ['id', 'pedido_id', 'descripcion'],
    ];
    assert_same([], CrudConfigValidator::relationsSchemaErrors($config, $ok));

    $bad = [
        'dom_demo_pedidos'      => ['id', 'folio'],
        'dom_demo_pedido_items' => ['id', 'descripcion'], // sin pedido_id
    ];
    $errors = CrudConfigValidator::relationsSchemaErrors($config, $bad);
    assert_true(
        in_array('relations.items.foreign_key (pedido_id) no existe en dom_demo_pedido_items.', $errors, true),
        'falta error de foreign_key en tabla hija'
    );
});

test('CrudConfigValidator: tabla relacionada ausente del esquema se omite', function (): void {
    $config = [
        'resource' => ['table' => 'dom_demo_pedidos'],
        'relations' => [
            'cliente' => [
                'type' => 'belongsTo', 'table' => 'dom_demo_clientes',
                'foreign_key' => 'cliente_id', 'value' => 'id', 'label' => 'nombre',
            ],
        ],
    ];
    // dom_demo_clientes no está en el esquema (su inexistencia la reporta validate()).
    $schema = ['dom_demo_pedidos' => ['id', 'folio', 'cliente_id']];
    assert_same([], CrudConfigValidator::relationsSchemaErrors($config, $schema));
});
