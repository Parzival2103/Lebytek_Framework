<?php

declare(strict_types=1);

namespace App\Domain\Marketing;

/** Fase asíncrona del ciclo de vida demo en api.lebytek.com (Green API). */
final class LeadApiLifecycleStatus
{
    public const NONE = 'none';

    /** Tenant + instancia creados en API; Green provisiona en cola. */
    public const PROVISION_INITIATED = 'provision_initiated';

    /** DELETE aceptado (202); Green elimina en cola. */
    public const DEPROVISION_INITIATED = 'deprovision_initiated';

    /** Instancias ya no existen en la API. */
    public const DEPROVISIONED = 'deprovisioned';
}
