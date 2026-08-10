# Modulo Invoicing (`invoicing`)

Modulo opcional de plataforma para emitir CFDI tipo I mediante Facturapi. El
Framework aporta puertos, VOs, orquestadores, provider y ledger `inv_*`; el
consumidor aporta el origen de datos de negocio y decide cuando facturar.

## Framework vs consumidor

| Responsabilidad | Owner | Ruta / namespace |
|-----------------|-------|------------------|
| Puertos, VOs, excepciones y enums CFDI I | Framework | `Lebytek\Framework\Domain\Invoicing\` |
| `IssueInvoiceFromSource`, cancelacion, descarga, email y reconcile | Framework | `Lebytek\Framework\Application\Invoicing\` |
| Adapter Facturapi y repositorios PDO | Framework | `Lebytek\Framework\Infrastructure\Invoicing\` |
| Ledger y cache tecnica | Framework | `database/schema/modules/invoicing.sql` (`inv_events`, `inv_organizations`) |
| Source fiscal desde `dom_*` / tabla propia | Consumidor | `App\Infrastructure\Invoicing\...` |
| Reglas de cuando emitir, UI, rutas, folios internos y datos fiscales ampliados | Consumidor | `App\` + SQL de negocio |

No compartir `Money` con Payments: Invoicing usa
`Lebytek\Framework\Domain\Invoicing\ValueObjects\Money`, independiente de
Payments. Tampoco comparte ledger (`inv_events` vs `pay_events`) para evitar
acoplar ciclos fiscales a eventos de cobro.

## Variables de entorno

| Variable | Requerida | Default | Uso |
|----------|-----------|---------|-----|
| `FACTURAPI_ENABLED` | Si se emite | `false` | Habilita el provider `facturapi` en `config/invoicing.php`. |
| `FACTURAPI_SECRET_KEY` | Si `FACTURAPI_ENABLED=true` | vacio | Secret key de Facturapi. No registrar en logs ni docs operativas. |
| `FACTURAPI_WEBHOOK_SECRET` | Si se expone webhook | vacio | Shared secret para validar `Facturapi-Signature` antes de aplicar eventos. |
| `FACTURAPI_MODE` | No | `test` | Modo de organizacion cacheada (`test` o `live`). |
| `INVOICING_DEFAULT_PROVIDER` | No | `facturapi` | Provider usado por los casos de uso si no se pasa uno explicito. |
| `INVOICING_RECONCILE_MIN_CLAIM_AGE_SECONDS` | No | `120` | Guarda de edad para reconciliar o barrer huerfanos con `reconcile_min_claim_age_seconds`. |

Regla de modo/llave (A18): FACTURAPI_MODE=test requiere `sk_test_`; `live` requiere `sk_live_`.
Mantener una sola `FACTURAPI_SECRET_KEY` por despliegue; staging y produccion
usan ambientes separados.

## Habilitar vertical y bootstrap SQL

1. Confirmar runtime PHP `>=8.2` y dependencia `facturapi/facturapi-php`.
2. Definir las variables anteriores en el `.env` del consumidor.
3. Encender el vertical:

   ```php
   // config/vertical.php
   'modules' => [
       'invoicing' => true,
   ],
   ```

4. Cargar el bootstrap SQL del modulo:

   ```bash
   php scripts/install.php --modules=core,invoicing
   ```

   El manifiesto `config/modules/invoicing.php` apunta a
   `database/schema/modules/invoicing.sql`.

## Implementar `InvoiceableSourceInterface`

Ejemplo minimo. El consumidor lee sus tablas `dom_*` y devuelve un
`InvoiceDraft`; el Framework no consulta tablas de dominio.

```php
<?php
declare(strict_types=1);

namespace App\Infrastructure\Invoicing;

use Lebytek\Framework\Domain\Invoicing\CfdiUse;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Domain\Invoicing\PaymentMethod;
use Lebytek\Framework\Domain\Invoicing\PaymentForm;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Address;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\FiscalCustomer;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceDraft;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceItem;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\InvoiceTax;
use Lebytek\Framework\Domain\Invoicing\ValueObjects\Money;

final class DomOrderInvoiceSource implements InvoiceableSourceInterface
{
    public function findDraft(string $sourceRef): ?InvoiceDraft
    {
        // SELECT ... FROM dom_orders / dom_customer_tax_profiles WHERE ref = ?
        $row = $this->findOrderRow($sourceRef);
        if ($row === null) {
            return null;
        }

        return new InvoiceDraft(
            sourceRef: $sourceRef,
            customer: new FiscalCustomer(
                legalName: $row['legal_name'],
                taxId: $row['tax_id'],
                taxSystem: $row['tax_system'],
                address: new Address(zip: $row['zip']),
                email: $row['email'] ?? null,
            ),
            items: [
                new InvoiceItem(
                    quantity: 1,
                    description: $row['description'],
                    productKey: $row['sat_product_key'],
                    unitPrice: Money::fromMinor((int) $row['amount_minor'], 'MXN'),
                    taxes: [new InvoiceTax('IVA', 0.16)],
                    unitKey: 'E48',
                ),
            ],
            paymentForm: PaymentForm::Transferencia,
            paymentMethod: PaymentMethod::Pue,
            cfdiUse: CfdiUse::G03,
            metadata: ['source_ref' => $sourceRef],
        );
    }

    /** @return array<string, mixed>|null */
    private function findOrderRow(string $sourceRef): ?array
    {
        // Implementacion del consumidor.
        return null;
    }
}
```

## Bind del source y caso de uso

Bind `InvoiceableSourceInterface` en el contenedor del consumidor cuando
`vertical.modules.invoicing` este activo. El contenedor del Framework registra
`IssueInvoiceFromSource` solo si ese puerto existe; alternativamente usa el
helper `InvoicingFactory::makeIssueInvoiceFromSource`.

```php
use App\Infrastructure\Invoicing\DomOrderInvoiceSource;
use Lebytek\Framework\Application\Invoicing\InvoicingFactory;
use Lebytek\Framework\Application\Invoicing\IssueInvoiceFromSource;
use Lebytek\Framework\Domain\Invoicing\InvoiceableSourceInterface;
use Lebytek\Framework\Kernel\Config\Config;
use Lebytek\Framework\Kernel\Container\Container;

if ((bool) Config::get('vertical.modules.invoicing', false)) {
    $container->singleton(
        InvoiceableSourceInterface::class,
        static fn () => new DomOrderInvoiceSource()
    );

    // Omitir este bind si se confia en el bind condicional del Framework.
    $container->bind(
        IssueInvoiceFromSource::class,
        static fn (Container $c) => InvoicingFactory::makeIssueInvoiceFromSource(
            $c->get(InvoiceableSourceInterface::class)
        )
    );
}
```

## Secuencia de emision en test mode

1. `FACTURAPI_ENABLED=true`, `FACTURAPI_MODE=test`,
   `INVOICING_DEFAULT_PROVIDER=facturapi`.
2. `vertical.modules.invoicing=true` y SQL `inv_*` cargado.
3. Bind del source del consumidor.
4. Construir una idempotency key estable por operacion de negocio
   (`invoice:{sourceRef}:v1`).
5. Llamar `IssueInvoiceFromSource->handle($sourceRef, $idempotencyKey)`.
6. Persistir en `dom_*` solo los datos de negocio necesarios
   (`providerInvoiceId`, `uuid`, estado visible), sin copiar secretos.
7. Descargar PDF/XML o enviar email con los use cases del Framework si el flujo
   del consumidor lo requiere.

## Runbook A1/D1: `InvoiceNeedsReconcile`

Cuando `IssueInvoiceFromSource` lanza `InvoiceNeedsReconcile`, significa que
Facturapi ya devolvio un provider invoice id pero el ledger local no pudo cerrar
el `markIssued`.

1. Registrar incidente con provider, idempotency key, `source_ref` y mensaje
   seguro.
2. Leer el id tipado con `InvoiceNeedsReconcile::providerInvoiceId()`; no
   parsear mensajes.
3. Consultar pendientes con `ReconcileIssuedInvoice->listNeedsReconcile()`.
4. Ejecutar `ReconcileIssuedInvoice->handle($idempotencyKey)`.
5. ReconcileIssuedInvoice recupera la factura remota con `retrieveInvoice` y
   promueve la fila local segun el estado remoto.
6. Confirmar que `inv_events.status` paso de `needs_reconcile` a `issued` o
   `canceled`; si Facturapi devuelve pendiente, conservar
   `provider_status=pending` en meta sin forzar `Valid`.
7. Si no aparece fila reconciliable, usar el runbook de huerfanos antes de
   considerar accion manual.

Regla critica: nunca re-emitir con una idempotency key nueva hasta que ops
confirme el estado remoto y local. Re-emitir puede duplicar el timbrado.

## Runbook hardening A11-A27

### Ambiguous create (A11)

Ambiguous create (A11): no liberar el claim cuando `createInvoice` ya fue
invocado y el proceso no sabe si Facturapi timbro. La fila queda en `claimed`
sin `provider_invoice_id` para impedir retries ciegos. En este estado no crear una `idempotencyKey` nueva; todo retry usa la misma key o falla cerrado hasta que reconcile determine el estado remoto.

### `external_id` A23

El `external_id` de Facturapi identifica el intento fiscal, no el `sourceRef`:

```text
external_id = `lebytek:invoice:{hex(sha256(providerKey."\x1f".idempotencyKey))[0:40]}`
```

Es por intento, nunca derivado de `sourceRef` ni truncado. Un mismo `sourceRef`
puede tener varias facturas legitimas por sustitucion o re-emision con una
`idempotencyKey` nueva; por eso el hash usa `providerKey`, separador `\x1f` e
`idempotencyKey`.

### Reconcile remoto, huerfanos y barrido

Para un huerfano `claimed` sin id, `handle()` aplica A22/A27:

1. Respeta `reconcile_min_claim_age_seconds`; si el claim es mas joven, falla
   con "claim too fresh" porque podria haber un issue en vuelo.
2. Calcula el `external_id` A23 y consulta `listByExternalId(A23)` antes de
   cualquier operacion manual.
3. Con 1 hit, adjunta el `provider_invoice_id` de forma condicional y luego
   recupera remoto con `retrieveInvoice`.
4. Con mas de 1 hit, falla cerrado; con A23 eso indica corrupcion o duplicidad
   remota inesperada.
5. Con 0 hits, mantiene el claim y solo deja abierta la salida A26.

El barrido ops debe invocar `findOrphanClaims` con la misma guarda de edad; el
Framework no agenda cron por si mismo.

### A26: re-emision forzada de huerfano 0 hits

`forceReissueOrphanClaim` es un procedimiento ops separado de `handle()`;
requiere `invoicing.reconciliar` y estas 3 precondiciones obligatorias:

1. `listByExternalId devuelve 0 hits` para el `external_id` A23.
2. La edad del claim supera `reconcile_min_claim_age_seconds`.
3. Hay invocacion explicita de ops; nunca se ejecuta desde el barrido ni desde
   `handle()`.

Reusa la misma `idempotencyKey` y el mismo `external_id`; no genera claves
nuevas. Es seguro y no puede doble-timbrar porque 0 hits prueba que no existe
factura remota para ese intento y Facturapi mantiene idempotencia remota para la
misma key.

## Reglas de resolucion A2

Cancelacion, descarga PDF/XML y email reciben `providerInvoiceId` o resuelven por
`source_ref`. Si el `source_ref` no tiene exactamente una factura issued, el
Framework falla cerrado con `InvoiceAmbiguousSource` o equivalente; no elige la
fila mas reciente de forma silenciosa.

Cancelacion hardening A17:

- `CancelIssuedInvoice` hace claim-before con `cancel:{providerInvoiceId}` antes
  de llamar a Facturapi.
- El motivo SAT debe ser `01`, `02`, `03` o `04`; motivo `01` requiere
  sustitucion no vacia.
- La fila fiscal de issue se localiza con `findIssueByProviderInvoiceId` y esa
  fila cambia a `canceled`; la fila `cancel:*` solo audita la operacion.

## RBAC obligatorio en consumidores

Consumer routes must enforce RBAC slugs en cualquier ruta admin/API que invoque
casos de uso mutantes o de descarga:

| Slug | Uso |
|------|-----|
| `invoicing.emitir` | Emitir CFDI |
| `invoicing.cancelar` | Cancelar CFDI |
| `invoicing.descargar` | Descargar PDF/XML |
| `invoicing.enviar` | Enviar por email |
| `invoicing.reconciliar` | Reconcile, `findOrphanClaims` y A26 |

El Framework publica slugs en manifiesto y SQL; no registra rutas HTTP ni menus
Portal. El consumidor debe asignar roles antes de produccion.

## Webhooks Facturapi

El Framework valida firma y aplica eventos, pero el consumidor expone la ruta:

```text
POST /webhooks/facturapi  (CSRF exempt)
raw body + header Facturapi-Signature
provider = InvoicingFactory/InvoiceProviderRegistry -> Facturapi concrete
event = FacturapiInvoiceProvider::parseWebhook(rawBody, signature)
ApplyInvoiceProviderEvent->handle(event)
responder 200 rapido
```

`FacturapiInvoiceProvider::parseWebhook` vive en el provider concreto, no en el
port `InvoiceProviderInterface`. Los consumidores que necesiten webhooks deben
obtener el provider Facturapi concreto por el patron existente de
factory/registry usado para Facturapi; no anadir un metodo nuevo al port solo
para parsear webhooks. Configurar `FACTURAPI_WEBHOOK_SECRET`, validar
`Facturapi-Signature` y no registrar payload fiscal completo (RFC, conceptos,
customer/items, PDF/XML o body crudo). El webhook firmado usa shared-secret auth,
no RBAC.

## Estrategia de release D7/A9

Este modulo sube el piso del paquete a PHP >=8.2 por el SDK
`facturapi/facturapi-php`. La recomendacion de release es publicar un major
semver para consumidores, incluso si no activan `invoicing`, porque el runtime
minimo cambia.

Facturapi sigue el patron `require` de Stripe: el SDK oficial queda en Composer
del paquete para reducir superficie propia. Ese peso de dependencia se acepta en
v1 y debe aparecer en las notas de release del tag.

## Futuro y residual aceptado

- webhooks / `RefreshInvoiceStatus` para estados asincronos (D10) evolucionan
  desde `ApplyInvoiceProviderEvent`; el HTTP queda en consumidor.
- Posible ISP split para documentos (D9): separar create/cancel de
  download/email si aparecen providers con capacidades parciales.
- Residual: catalogo SAT completo.
- Residual: controlador HTTP de webhook en Framework.
- Residual: CI live contra Facturapi.
- Residual: cron worker para `findOrphanClaims`.
- Operaciones residuales antes de produccion: configurar
  `FACTURAPI_WEBHOOK_SECRET`, proteger rutas con RBAC, asignar roles y programar
  el barrido externo.

## Invariantes A1-A3

- A1: si `createInvoice` devolvio provider id, no liberar claim; marcar
  `needs_reconcile` y resolver con `ReconcileIssuedInvoice`.
- A2: `source_ref` no es autoridad unica cuando existen multiples facturas; las
  operaciones posteriores requieren provider id o una unica fila issued.
- A3: claims sin `provider_invoice_id` son incompletos; no hay retry ciego de
  create despues de timeout.
- A11-A27: create ambiguo conserva claim; remote reconcile verifica Facturapi;
  huerfanos usan A23 `external_id`; A26 es manual con RBAC; pending fiscal se
  conserva como `provider_status=pending`.

## Pruebas

Regresion recomendada para consumidores que habiliten el modulo:

```bash
php tests/run.php Invoicing
php tests/run.php Kernel/SkeletonPurity
php tests/run.php Payments
```
