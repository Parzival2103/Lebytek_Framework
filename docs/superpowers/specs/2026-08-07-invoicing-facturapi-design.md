# Invoicing (Facturapi) — estructura plataforma + scaffold CFDI I

**Fecha:** 2026-08-07  
**Estado:** diseño aprobado; plan de implementación en `docs/superpowers/plans/2026-08-07-invoicing-facturapi.md`  
**Módulo:** `invoicing` (vertical OFF by default)  
**Proveedor v1:** Facturapi (SDK oficial `facturapi/facturapi-php`)

## Objetivo

Preparar en `Lebytek_Framework` la estructura de un módulo de facturación electrónica
(CFDI México) para que, cuando un consumidor construya su dominio (`dom_*` / “tabla X”),
la conexión sea mínima, tipada y documentada:

1. Implementar `InvoiceableSourceInterface` (mapear tabla X → `InvoiceDraft`).
2. Encender `vertical.modules.invoicing`.
3. Llamar al scaffold Application (`IssueInvoiceFromSource`, cancel, PDF/XML/email).

Esta fase **no** implementa negocio Portal (membresías, pedidos, CRM fiscal completo).

## Decisiones

| Decisión | Elección | Motivo |
|----------|----------|--------|
| Alcance | Estructura + scaffold emisión (crear / cancelar / PDF / XML / email) | Opción C del brainstorming |
| Obtención de datos | Puerto Facturapi + `InvoiceableSourceInterface` + orquestador Application | El Framework no conoce tablas de dominio; el consumidor las mapea |
| Cliente HTTP | SDK oficial `facturapi/facturapi-php` | Menos superficie propia; alineado a Stripe en Payments |
| PHP | Subir requisito del paquete a `>=8.2` | SDK Facturapi v4 exige 8.2+ |
| CFDI v1 | Solo tipo **I** (ingreso) | YAGNI; puerto extensible a E/P después |
| Persistencia plataforma | Ledger `inv_events` + cache org `inv_organizations` | Idempotencia + settings mínimos; negocio en `dom_*` |
| Nombre módulo | `invoicing` | Inglés, alineado a `payments` / `integrations` |
| Enfoque | Mirror Payments + orquestador | Consistencia estructural del paquete |
| Prefix SQL | `inv_` | Paralelo a `pay_`; documentar en convención de prefijos |

## Frontera Framework vs consumidor

| Capa | Repo | Responsabilidad |
|------|------|-----------------|
| Puertos + VOs | Framework `src/Domain/Invoicing/` | `InvoiceProviderInterface`, `InvoiceableSourceInterface`, drafts, issued, enums |
| Orquestación scaffold | Framework `src/Application/Invoicing/` | Factory, registry, issue/cancel/download/send |
| Adapter Facturapi | Framework `src/Infrastructure/Invoicing/` | SDK wrapper; mapeo excepciones |
| SQL plataforma | Framework `database/schema/modules/invoicing.sql` | `inv_events`, `inv_organizations` |
| Config / vertical | Framework + skeleton copies | OFF by default; env `FACTURAPI_*` |
| Docs conexión | Framework `docs/modules/modulo-invoicing.md` | Checklist bind source → emitir |
| Source de dominio | Consumidor `App\` | Lee tabla X / `dom_*` → `InvoiceDraft` |
| Cuándo facturar / UI / rutas | Consumidor | Reglas de negocio, controladores, menús |
| Clientes fiscales como CRM | Consumidor `dom_*` | No modelo completo en plataforma |

## Arquitectura

```
Consumer (App\)                         Framework (Lebytek\Framework\)
─────────────────                       ──────────────────────────────
dom_* / tabla X
      │
      ▼
InvoiceableSourceInterface ──────────► Application\IssueInvoiceFromSource
(App implementation)                         │
                                             ├─► Domain VOs (InvoiceDraft…)
                                             ▼
                                      InvoiceProviderInterface
                                             │
                                             ▼
                                      Infrastructure\FacturapiInvoiceProvider
                                             │
                                             ▼
                                      facturapi/facturapi-php (SDK)
```

Gates:

- `config/vertical.php` → `'invoicing' => false`
- Bindings en `config/container.php` solo si el flag está ON
- Provider `facturapi` habilitado vía `config/invoicing.php` + env

## Componentes

### Domain (`Lebytek\Framework\Domain\Invoicing`)

**Puertos**

- `InvoiceProviderInterface`
  - `key(): string`
  - `createInvoice(InvoiceDraft $draft): IssuedInvoice`
  - `cancelInvoice(string $providerInvoiceId, InvoiceCancellation $cancellation): IssuedInvoice`
  - `downloadPdf(string $providerInvoiceId): string` (bytes)
  - `downloadXml(string $providerInvoiceId): string` (bytes)
  - `sendByEmail(string $providerInvoiceId, string $email): void`
- `InvoiceableSourceInterface`
  - `findDraft(string $sourceRef): ?InvoiceDraft`
- `InvoiceEventLogRepositoryInterface`
  - `hasProcessed(string $provider, string $idempotencyKey): bool`
  - `tryClaim(string $provider, string $idempotencyKey, string $sourceRef, string $type, array $meta = []): bool` — INSERT UNIQUE; `true` si este caller posee el claim
  - `releaseClaim(string $provider, string $idempotencyKey): void` — DELETE del claim fallido (mismo espíritu que Payments `#21` C4)
  - `markIssued(string $provider, string $idempotencyKey, IssuedInvoice $invoice): void` — UPDATE de la fila claim con ids/status provider
  - `findByIdempotencyKey(string $provider, string $idempotencyKey): ?IssuedInvoice`
  - `findBySourceRef(string $sourceRef): ?IssuedInvoice` (lookup scaffold; la más reciente issued)
- `OrganizationSettingsRepositoryInterface`
  - `get(string $providerKey): ?OrganizationSettings`
  - `upsert(OrganizationSettings $settings): void`

**Value objects / enums**

- `InvoiceDraft` — `sourceRef`, `FiscalCustomer`, `InvoiceItem[]`, `paymentForm`, `cfdiUse` (default `G01`), `currency` (default `MXN`), `metadata`
- `FiscalCustomer` — `legalName`, `taxId` (RFC), `taxSystem`, `email?`, `Address` (`zip`, `country` default `MEX`, street opcional)
- `InvoiceItem` — `quantity`, `description`, `productKey` (SAT), `unitKey?`, `unitPrice` (`Money`), `taxes?`
- `IssuedInvoice` — `providerInvoiceId`, `uuid`, `folioNumber?`, `status`, `sourceRef?`, `pdfUrl?`, `xmlUrl?`, raw meta segura
- `InvoiceCancellation` — `motive` (SAT), `substitution?`
- `OrganizationSettings` — `providerKey`, `externalOrgId?`, `mode` (`test`|`live`), `label?`, `meta`
- `InvoiceStatus` — al menos `draft`, `valid`, `canceled`, `pending` (mapear desde Facturapi)
- `PaymentForm`, `CfdiUse` — subset de códigos SAT usados en scaffold (backed enums o constantes finales)
- `Money` — VO **local** en `Domain\Invoicing\ValueObjects` (amount + currency). No acoplar al módulo Payments.

**Excepciones Domain**

- `InvoiceSourceNotFound`
- `InvoiceDraftInvalid`
- `InvoiceProviderException`
- `InvoiceAlreadyProcessed`
- `InvoiceNotCancellable`

Sin tipos del SDK en Domain.

### Application (`Lebytek\Framework\Application\Invoicing`)

- `InvoicingFactory` — lee `config/invoicing.php`, instancia drivers habilitados
- `InvoiceProviderRegistry` — lazy get by key
- `IssueInvoiceFromSource` — claim → source → validate draft → create → markIssued
- `CancelIssuedInvoice` — resolve id (directo o por `source_ref`) → cancel → log
- `DownloadInvoiceDocument` — `pdf` | `xml`
- `SendInvoiceByEmail` — delegación thin

El orquestador **no** conoce SQL de dominio; solo el puerto `InvoiceableSourceInterface`.

### Infrastructure (`Lebytek\Framework\Infrastructure\Invoicing`)

- `FacturapiInvoiceProvider` — construye `Facturapi` con secret key; mapea draft ↔ payload SDK; atrapa `FacturapiException` → `InvoiceProviderException`
- `PdoInvoiceEventLogRepository`
- `PdoOrganizationSettingsRepository`

Driver v1 único: `facturapi`. El registry queda listo para futuros providers.

### Config / module manifest / skeleton

| Archivo | Rol |
|---------|-----|
| `config/invoicing.php` | Gateways: `facturapi` → `enabled`, `secret_key`, `user_key?`, `mode` |
| `config/modules/invoicing.php` | Manifest opcional + `bootstrap_sql` → `invoicing.sql` |
| `config/vertical.php` (+ skeleton) | `'invoicing' => false` |
| `config/container.php` (+ skeleton) | Bindings gated |
| `.env.example` (+ skeleton) | `FACTURAPI_SECRET_KEY` (requerida si enabled), `FACTURAPI_MODE=test`, `INVOICING_DEFAULT_PROVIDER=facturapi`. `FACTURAPI_USER_KEY` solo si un flujo futuro de org/user lo necesita — fuera del scaffold v1 de emisión |
| `composer.json` | `"php": ">=8.2"`, require `facturapi/facturapi-php` |

Documentar el bump de PHP en `docs/ARCHITECTURE-CONSUMER.md` / release notes del tag que lo introduzca: consumidores en 8.1 deben subir runtime antes de consumir esa versión.

### SQL plataforma

Prefix `inv_` (añadir a `docs/core/table-prefix-convention.md`).

**`inv_events`** (espíritu de `pay_events`: claim = INSERT UNIQUE; release = DELETE; éxito = UPDATE)

- `id` BIGINT PK
- `provider` VARCHAR(40) NOT NULL
- `idempotency_key` VARCHAR(190) NOT NULL
- `source_ref` VARCHAR(64) NULL
- `type` VARCHAR(60) NOT NULL — p. ej. `issue`, `cancel`, `send_email`
- `provider_invoice_id` VARCHAR(190) NULL — se llena en `markIssued`
- `uuid` VARCHAR(50) NULL — UUID fiscal CFDI cuando exista
- `folio_number` VARCHAR(40) NULL
- `status` VARCHAR(40) NULL
- `meta` JSON NULL — sin secretos
- `created_at` / `updated_at` DATETIME
- UNIQUE (`provider`, `idempotency_key`); INDEX (`source_ref`)

**`inv_organizations`**

- `id`, `provider_key` (unique)
- `external_org_id` nullable
- `mode` (`test`|`live`)
- `label` nullable
- `meta_json`
- `created_at`, `updated_at`

No tablas `dom_*` ni modelos de factura de negocio en el paquete.

## Flujos

### Emitir (camino feliz)

1. Consumidor llama `IssueInvoiceFromSource->handle(sourceRef, idempotencyKey)`.
2. `tryClaim` en `inv_events`.
3. `InvoiceableSourceInterface->findDraft(sourceRef)` → `InvoiceDraft` o `InvoiceSourceNotFound`.
4. Validación mínima Application (RFC, zip, ≥1 ítem, payment_form, product_key).
5. `InvoiceProviderRegistry->get('facturapi')->createInvoice(draft)`.
6. `markIssued` + retorno `IssuedInvoice`.
7. Consumidor persiste lo que necesite en `dom_*`.

**Idempotencia (regla explícita):**

1. `tryClaim` → `true`: este caller emite; ante cualquier fallo posterior → `releaseClaim` + rethrow.
2. `tryClaim` → `false` y `findByIdempotencyKey` tiene `provider_invoice_id`: **devolver** ese `IssuedInvoice` (replay seguro; no relanzar al SAT).
3. `tryClaim` → `false` y la fila existe sin `provider_invoice_id`: otro request en vuelo o claim huérfano → lanzar `InvoiceAlreadyProcessed` (no segundo create). Ops/retry tras timeout debe asegurar `releaseClaim` en el path fallido del primer caller.

### Cancelar / PDF / XML / email

Application recibe `providerInvoiceId` o resuelve por `source_ref` vía event log; delega al provider; registra evento de auditoría en ledger cuando aplique.

### Conexión del dominio (consumidor)

```php
// App\Infrastructure\Invoicing\DomOrderInvoiceSource
final class DomOrderInvoiceSource implements InvoiceableSourceInterface
{
    public function findDraft(string $sourceRef): ?InvoiceDraft
    {
        // SELECT … FROM dom_… WHERE id = ?
        // map → FiscalCustomer + InvoiceItem[] + payment_form + cfdi_use
    }
}
```

Binding en `container.php` del consumidor cuando `vertical.modules.invoicing` esté ON.
Checklist completo en `docs/modules/modulo-invoicing.md`.

## Errores

| Situación | Comportamiento |
|-----------|----------------|
| Source no encuentra ref | `InvoiceSourceNotFound` |
| Draft incompleto / inválido | `InvoiceDraftInvalid` (antes del SDK) |
| Error Facturapi / red | `InvoiceProviderException` (mensaje seguro, sin API keys) |
| Vertical OFF / provider disabled | Factory lanza error explícito |
| Factura no cancelable | `InvoiceNotCancellable` |
| Fallo post-claim | `releaseClaim` + rethrow |

## Testing

- Unit VOs / enums / validación draft
- Factory + registry (enabled/disabled)
- Event log claim double (éxito, conflicto, release)
- Adapter Facturapi con client stub/fake (create, cancel, pdf, xml, email)
- Application: source stub → issued; missing source; invalid draft; idempotency hit
- Schema bootstrap `inv_*`
- Skeleton purity: configs OFF, sin bindings Portal
- Suite: `php tests/run.php Invoicing` (y regresión Payments/Kernel)

## Documentación a entregar con la implementación

- `docs/modules/modulo-invoicing.md` — contrato, env, checklist source → bind → ON → emitir en test mode
- Actualizar `docs/ARCHITECTURE-CONSUMER.md` — fila Invoicing (generic FW / negocio en consumidor)
- Actualizar `docs/core/table-prefix-convention.md` — prefix `inv_`
- Actualizar `docs/core/vertical-onboarding.md` — flag `invoicing`
- Mencionar bump PHP ≥8.2 en notas de release del tag

## Criterios de aceptación

- [ ] Vertical `invoicing` existe y está OFF en harness y skeleton
- [ ] Domain ports + VOs sin dependencias Infrastructure/SDK
- [ ] `FacturapiInvoiceProvider` implementa create/cancel/pdf/xml/email para CFDI I
- [ ] `IssueInvoiceFromSource` orquesta source → provider → `inv_events`
- [ ] Tablas `inv_events` y `inv_organizations` bootstrappean vía module SQL
- [ ] `composer.json` declara PHP `>=8.2` y `facturapi/facturapi-php`
- [ ] Docs de conexión permiten a un consumidor implementar solo el source
- [ ] Tests `Invoicing/*` verdes; SkeletonPurity no rompe
- [ ] Ningún `dom_*` ni caso de uso Portal en este repo

## Fuera de alcance (esta fase)

- CFDI tipos E, P, N, T / Carta Porte
- Webhooks Facturapi
- Multi-RFC runtime / organizaciones dinámicas multi-tenant avanzadas
- UI backoffice / menú / settings section (puede añadirse después como provider opcional)
- Catálogo SAT completo como producto
- Reglas de “cuándo timbrar” ligadas a membresías/pedidos (Portal / tenant)
- Emisión real en producción VPS (humano + keys + release semver)

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Consumidores aún en PHP 8.1 | Bump semver major o minor documentado; no mezclar con cambios silenciosos |
| Acoplar VOs a shape exacto del SDK | Mapear en Infrastructure; Domain usa campos SAT estables |
| Tentación de meter `dom_facturas` en FW | Ownership explícito en docs + SkeletonPurity / review |
| Idempotencia mal definida | Copiar espíritu `pay_events` claim/release; tests de doble emisión |

## Orden de implementación sugerido (para el plan)

1. Bump PHP + dependencia Facturapi + vertical/config/skeleton stubs  
2. Domain ports, VOs, exceptions  
3. SQL `inv_*` + repos PDO  
4. `FacturapiInvoiceProvider` + factory/registry  
5. Application scaffold (issue/cancel/download/send)  
6. Tests  
7. Docs módulo + arquitectura + prefijos  
