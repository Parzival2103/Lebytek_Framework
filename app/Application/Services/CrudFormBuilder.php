<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CrudFieldDefinition;
use App\Domain\Entities\CrudResourceDefinition;

final class CrudFormBuilder
{
    public function build(
        CrudResourceDefinition $definition,
        array $values = [],
        array $errors = [],
        string $action = '',
        bool $isEdit = false
    ): array {
        $fields = [];

        foreach ($definition->formFields() as $field) {
            if (!$field instanceof CrudFieldDefinition) {
                continue;
            }

            $name = $field->name();
            $readonly = $field->readonly();
            $type = $field->type();
            $fields[] = [
                'name' => $name,
                'label' => $field->label(),
                'type' => $type,
                'required' => $field->required(),
                'readonly' => $readonly,
                'readonlyPreservePost' => $readonly && in_array($type, ['select', 'checkbox'], true),
                'hidden' => $field->hidden(),
                'col' => $field->col(),
                'options' => $field->options(),
                'validation' => $field->validation(),
                'help_text' => $field->helpText(),
                'value' => $values[$name] ?? $field->defaultValue(),
                'errors' => $errors[$name] ?? [],
            ];
        }

        return [
            'resource' => $definition->key(),
            'title' => $definition->title(),
            'action' => $action,
            'method' => 'POST',
            'isEdit' => $isEdit,
            'fields' => $fields,
        ];
    }
}
