# Patrón: dominio Onion (cuando el CRUD Engine no basta)

> **Referencia, no plantilla.** Esto NO se clona. Lee este patrón para entender
> cómo el framework implementa un dominio real con lógica propia, y planea tu
> vertical imitando la estructura. El contrato formal está en
> `docs/modules/uso-de-modulo-dominio.md`; este documento es el ejemplo trabajado
> sobre el módulo `marketing`.

## Cuándo usar este patrón

- El recurso **encaja en el CRUD Engine** → quédate en SQL + JSON (+ handlers). NO
  uses este patrón.
- Hay **lógica que el motor no cubre**: orquestación multi-paso, contratos de
  extensión, providers intercambiables, integraciones → entonces sí, capas Onion.

## Capas (ejemplo: módulo marketing)

### Domain — contratos e invariantes (sin dependencias externas)
- Contratos: `app/Domain/Marketing/Contracts/` — p. ej.
  `LeadCaptureHandlerInterface`, `LandingContentProviderInterface`,
  `CommercialPackageSourceInterface`, `LeadRepositoryInterface`,
  `ProvisionAdapterInterface`.
- Value objects: `app/Domain/Marketing/ValueObjects/` — `Lead`, `LeadDraft`,
  `LeadResult`, `MagicLinkToken`, `Provision`, `ProvisionResult`.

### Application — orquestación (use cases)
- `app/Application/Marketing/CapturarLeadUseCase.php` — pipeline de captura.
- `app/Application/Marketing/RenderLandingUseCase.php` — armado de la landing.

### Infrastructure — implementaciones concretas
- Providers de contenido/paquetes: `app/Infrastructure/Marketing/CrudLandingContentProvider.php`,
  `CrudCommercialPackageSource.php`.
- Pipeline de handlers de captura:
  `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php`,
  `AutoresponderHandler.php`, `NotifyInternalHandler.php`.
- Settings providers: `app/Infrastructure/Marketing/Settings/*SettingsProvider.php`.
- Repositorio PDO: `app/Infrastructure/Repositories/PdoMarketingContentRepository.php`.

### Cableado (plataforma)
- Bindings condicionales por toggle en `config/container.php` (bloque
  `if vertical.modules.marketing`).
- Rutas en `routes/marketing.php` (incluidas condicionalmente).
- Manifiesto `config/modules/marketing.php` con `permisos` poblados y `bootstrap_sql`.

## Regla de dependencias (Onion)

`Presentation → Application → Domain ← Infrastructure`. El Domain no importa nada
de fuera; Infrastructure implementa las interfaces del Domain. Detalle:
`docs/core/arquitectura.md`.
