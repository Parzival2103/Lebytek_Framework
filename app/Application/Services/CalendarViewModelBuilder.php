<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\CalendarDefinition;
use App\Domain\Entities\CrudResourceDefinition;

/**
 * Construye los datos del shell del calendario: vistas, filtros (con opciones),
 * leyenda de colores, capacidades RBAC y metadatos del feed. No toca la base de
 * datos: el prefijo de permisos y las etiquetas de estado salen del JSON del CRUD.
 */
final class CalendarViewModelBuilder
{
    public function __construct(
        private readonly CalendarConfigLoader $calendarLoader,
        private readonly RbacService $rbacService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function build(string $key): array
    {
        $def  = $this->calendarLoader->load($key);
        $crud = $this->calendarLoader->crudDefinition($def->resource());
        $prefix = $crud->permissionPrefix();

        // Permiso de lectura obligatorio (lanza AccesoException si falta).
        $this->rbacService->verificar($prefix . '.ver');

        return [
            'title'        => $def->title(),
            'key'          => $def->key(),
            'resource'     => $def->resource(),
            'startField'   => $def->mappingStart(),
            'icon'         => $def->icon(),
            'feedUrl'      => '/admin/calendario/' . $def->key() . '/eventos',
            'crudBaseUrl'  => '/admin/crud/' . $def->resource(),
            'views'        => [
                'default' => $def->viewsDefault(),
                'enabled' => $def->viewsEnabled(),
            ],
            'allDay'       => $def->mappingAllDay() === true,
            'filters'      => $this->filters($def, $crud),
            'legend'       => $this->legend($def, $crud),
            'capabilities' => [
                'canCreate' => $def->createOnSlot() && $this->rbacService->puede($prefix . '.crear'),
                'canEdit'   => $def->editFromEvent() && $this->rbacService->puede($prefix . '.editar'),
                'canDelete' => $this->rbacService->puede($prefix . '.eliminar'),
                'openDetail'=> $def->openDetail(),
            ],
        ];
    }

    /**
     * @return list<array{field:string,label:string,options:array<string,string>}>
     */
    private function filters(CalendarDefinition $def, CrudResourceDefinition $crud): array
    {
        $out = [];
        foreach ($def->filters() as $filter) {
            $field = (string) ($filter['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $out[] = [
                'field'   => $field,
                'label'   => (string) ($filter['label'] ?? ucfirst($field)),
                'options' => $this->optionsFor($field, $def, $crud),
            ];
        }
        return $out;
    }

    /**
     * Opciones de un filtro: estados desde la máquina de estados del CRUD; en su
     * defecto, las claves del color.map del calendario.
     *
     * @return array<string,string> valor => etiqueta
     */
    private function optionsFor(string $field, CalendarDefinition $def, CrudResourceDefinition $crud): array
    {
        $sm = $crud->stateMachine();
        if ($sm !== null && $sm->column() === $field) {
            $options = [];
            foreach (array_keys($sm->values()) as $value) {
                $value = (string) $value;
                $options[$value] = $sm->label($value) ?? $this->humanize($value);
            }
            return $options;
        }

        $options = [];
        foreach (array_keys($def->colorMap()) as $value) {
            $options[(string) $value] = $this->humanize((string) $value);
        }
        return $options;
    }

    /**
     * Leyenda de colores: une color.map (tono Bootstrap) con la etiqueta del estado.
     *
     * @return list<array{value:string,label:string,tone:string}>
     */
    private function legend(CalendarDefinition $def, CrudResourceDefinition $crud): array
    {
        if ($def->colorBy() !== 'estado') {
            return [];
        }
        $sm = $crud->stateMachine();
        $legend = [];
        foreach ($def->colorMap() as $value => $tone) {
            $value = (string) $value;
            $legend[] = [
                'value' => $value,
                'label' => $sm?->label($value) ?? $this->humanize($value),
                'tone'  => (string) $tone,
            ];
        }
        return $legend;
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }
}
