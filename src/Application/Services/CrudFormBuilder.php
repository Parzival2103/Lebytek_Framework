<?php

declare(strict_types=1);

namespace Lebytek\Framework\Application\Services;

use Lebytek\Framework\Domain\Entities\CrudFieldDefinition;
use Lebytek\Framework\Domain\Entities\CrudResourceDefinition;

final class CrudFormBuilder
{
    public function __construct(private readonly ?CrudRelationService $relationService = null) {}

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
            $options = $field->options();

            // Campo type: relation -> se renderiza como select con opciones de la relación.
            if ($type === 'relation') {
                $type = 'select';
                $relationName = $field->relation();
                $relation = $relationName !== null ? $definition->relation($relationName) : null;
                if ($relation !== null && $this->relationService !== null) {
                    $options = $this->relationService->optionsFor($relation);
                }
            }

            $fields[] = [
                'name' => $name,
                'label' => $field->label(),
                'type' => $type,
                'required' => $field->required(),
                'readonly' => $readonly,
                'readonlyPreservePost' => $readonly && in_array($type, ['select', 'checkbox'], true),
                'hidden' => $field->hidden(),
                'col' => $field->col(),
                'options' => $options,
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
