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
| `FACTURAPI_MODE` | No | `test` | Modo de organizacion cacheada (`test` o `live`). |
| `INVOICING_DEFAULT_PROVIDER` | No | `facturapi` | Provider usado por los casos de uso si no se pasa uno explicito. |

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
2. Consultar pendientes con `ReconcileIssuedInvoice->listNeedsReconcile()`.
3. Ejecutar `ReconcileIssuedInvoice->handle($idempotencyKey)`.
4. Confirmar que `inv_events.status` paso de `needs_reconcile` a `issued`.
5. Si no aparece fila reconciliable, escalar a ops para revisar Facturapi y DB.

Regla critica: nunca re-emitir con una idempotency key nueva hasta que ops
confirme el estado remoto y local. Re-emitir puede duplicar el timbrado.

## Reglas de resolucion A2

Cancelacion, descarga PDF/XML y email reciben `providerInvoiceId` o resuelven por
`source_ref`. Si el `source_ref` no tiene exactamente una factura issued, el
Framework falla cerrado con `InvoiceAmbiguousSource` o equivalente; no elige la
fila mas reciente de forma silenciosa.

## Estrategia de release D7/A9

Este modulo sube el piso del paquete a PHP >=8.2 por el SDK
`facturapi/facturapi-php`. La recomendacion de release es publicar un major
semver para consumidores, incluso si no activan `invoicing`, porque el runtime
minimo cambia.

Facturapi sigue el patron `require` de Stripe: el SDK oficial queda en Composer
del paquete para reducir superficie propia. Ese peso de dependencia se acepta en
v1 y debe aparecer en las notas de release del tag.

## Futuro y residual aceptado

- webhooks / `RefreshInvoiceStatus` para estados asincronos (D10).
- Posible ISP split para documentos (D9): separar create/cancel de
  download/email si aparecen providers con capacidades parciales.
- No catalogo SAT completo ni CI live contra Facturapi en v1.

## Invariantes A1-A3

- A1: si `createInvoice` devolvio provider id, no liberar claim; marcar
  `needs_reconcile` y resolver con `ReconcileIssuedInvoice`.
- A2: `source_ref` no es autoridad unica cuando existen multiples facturas; las
  operaciones posteriores requieren provider id o una unica fila issued.
- A3: claims sin `provider_invoice_id` son incompletos; no hay retry ciego de
  create despues de timeout.

## Pruebas

Regresion recomendada para consumidores que habiliten el modulo:

```bash
php tests/run.php Invoicing
php tests/run.php Kernel/SkeletonPurity
php tests/run.php Payments
```
