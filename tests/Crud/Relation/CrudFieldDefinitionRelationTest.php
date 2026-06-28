<?php

declare(strict_types=1);

use Lebytek\Framework\Domain\Entities\CrudFieldDefinition;

test('CrudFieldDefinition: relation is null by default', function (): void {
    $f = CrudFieldDefinition::fromArray(['name' => 'nombre', 'label' => 'Nombre']);
    assert_null($f->relation());
});

test('CrudFieldDefinition: relation is parsed from config', function (): void {
    $f = CrudFieldDefinition::fromArray(['name' => 'categoria_id', 'label' => 'Categoría', 'type' => 'relation', 'relation' => 'categoria']);
    assert_same('categoria', $f->relation());
    assert_same('relation', $f->type());
});
