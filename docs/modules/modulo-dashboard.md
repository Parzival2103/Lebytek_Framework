# Módulo Dashboard (plataforma)

El **dashboard admin** es un módulo **de plataforma**, no de dominio `dom_*`. No tiene tablas obligatorias: la pantalla se arma en **Application** a partir de proveedores registrados en configuración.

Documentación relacionada: [uso-de-modulo-dominio.md](./uso-de-modulo-dominio.md) (checklist de dominio), [modulo-menu.md](./modulo-menu.md) (barra lateral / topnav desde `core_menu_items`) y este documento (solo dashboard).

## Anatomía visual fija

La pantalla `/admin/dashboard` se divide en regiones reutilizables (parciales bajo `app/Presentation/Views/partials/dashboard/`):

| Sección | Partial | Contenido |
|---------|---------|------------|
| KPIs | `kpi_grid.php` | Cuadrícula de tarjetas: icono Bootstrap Icons, valor opcional, etiqueta, descripción opcional, enlace opcional (o tarjeta estática sin enlace si `url` es `#`) |
| Actividad reciente | `recent_activity.php` | Lista de líneas (icono + texto + meta opcional). Si no hay ítems, se muestra texto de *placeholder* configurado en el caso de uso |
| Accesos rápidos | `quick_links.php` | Botones compactos (URL + icono + texto) |
| Estado del sistema | `system_status.php` | Cabecera con título + *badge* tonal; debajo, lista de líneas con tono semántico opcional (`muted`, `success`, `warning`, `danger`) |

El título de la vista y el *breadcrumb* siguen viniendo del layout (`$titulo`), alineado al `pageTitle` del `DashboardViewModel`.

## Flujo de datos

1. [`DashboardController`](../../app/Presentation/Controllers/Admin/DashboardController.php) lee sesión (`auth_user`, `auth_permisos`, `auth_roles`) y construye [`DashboardBuildContext`](../../app/Domain/Dashboard/DashboardBuildContext.php).
2. [`BuildDashboardViewModelUseCase`](../../app/Application/UseCases/Dashboard/BuildDashboardViewModelUseCase.php) invoca en orden **prioridad numérica** ascendente (y luego orden estable) a cada implementación de [`DashboardContributionProviderInterface`](../../app/Domain/Interfaces/DashboardContributionProviderInterface.php).
3. Cada proveedor devuelve un [`DashboardContribution`](../../app/Domain/Dashboard/DashboardContribution.php) con listas parciales; el caso de uso **concatena** KPIs, ítems de actividad y accesos rápidos y fusiona líneas de estado.
4. Resultado: [`DashboardViewModel`](../../app/Application/DTO/Dashboard/DashboardViewModel.php) pasado a [`admin/dashboard/index.php`](../../app/Presentation/Views/admin/dashboard/index.php).

## Formato de arrays (contrato para proveedores)

### KPI (`kpis[]`)

- `label` (string, requerido)
- `value` (string, opcional; vacío = no mostrar número grande)
- `icon` (clase icono, p. ej. `bi-people-fill`)
- `color` (sufijo tema existente: `primary`, `secondary`, `info`, `success`, …)
- `url` (string; usar `#` para tarjeta informativa sin enlace)
- `description` (string opcional, texto secundario)

### Actividad (`activityItems[]`)

- `icon`, `text` (requeridos)
- `meta` (opcional, línea secundaria discreta)

### Accesos rápidos (`quickAccess[]`)

- `url`, `icon`, `label`

### Bloque de estado (`statusBlock` en contribución, opcional)

```php
[
    'badge'     => 'OK',
    'badgeTone' => 'success', // success|warning|danger|secondary|muted
    'lines'     => [
        ['text' => '…', 'tone' => 'muted'],
    ],
]
```

Al fusionar varios proveedores, el **último** `badge`/`badgeTone` definido no vacío tiene prioridad sobre el bloque final; las **líneas** se concatenan.

## Registro de un proveedor nuevo

1. Crear una clase en **`Infrastructure`** que implemente `DashboardContributionProviderInterface` (p. ej. aportar KPIs cuando exista un nuevo `dom_*`).
2. Registrar el FQCN en [`config/dashboard.php`](../../config/dashboard.php) dentro del array `providers` (orden = orden de registro; `priority()` dentro de la interfaz ordena la ejecución dentro del conjunto).
3. Opcional: encadenar dependencias (repositorios de dominio) vía constructor **y** registrar el `singleton` en [`config/container.php`](../../config/container.php) si el contenedor no puede instanciar el constructor vacío (hoy el contenedor hace `new $class()` para clases sin constructor con dependencias).

**No** es necesario crear rutas nuevas ni tocar `DashboardController` si el proveedor solo contribuye datos.

### Ejemplo mínimo (pseudo-código)

```php
final class InventarioDashboardProvider implements DashboardContributionProviderInterface
{
    public function priority(): int { return 40; }

    public function contribute(DashboardBuildContext $context): DashboardContribution
    {
        if (!$context->tienePermiso('inventario.ver')) {
            return DashboardContribution::vacia();
        }

        return new DashboardContribution(
            kpis: [
                ['label' => 'SKU activos', 'value' => '42', 'icon' => 'bi-box-seam',
                 'color' => 'primary', 'url' => '/admin/inventario', 'description' => ''],
            ],
            activityItems: [],
            quickAccess: [],
            statusBlock: null,
        );
    }
}
```

Añadir en `config/dashboard.php`: `\App\Infrastructure\...\InventarioDashboardProvider::class` y dar de alta dependencias en el contenedor si hace falta.

## Estado futuro opcional

- Proveedor que lee [`log_bitacora`](../../database/schema/schema.sql) para rellenar actividad con eventos auditables (documentar orden y límites al implementarlo).

---

Las clases CSS del panel (`kpi-card`, `fade-stagger`, etc.) están en los estilos existentes del proyecto; al añadir secciones, reutilizar esas clases antes de crear nuevas.
