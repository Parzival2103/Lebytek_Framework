# CheckoutRequest — idempotency key opcional

**Estado:** diseño (consumidor: Portal enlaces de cobro admin, 2026-08-15)  
**Repo:** Lebytek_Framework  
**Superficie:** Domain Payments + StripeGateway  
**Breaking:** no (default = comportamiento actual)

## Problema

`StripeGateway::createCheckout` usa siempre `idempotency_key = $request->externalRef()`. Eso impide crear una **nueva** Checkout Session para el mismo `externalRef` cuando la session anterior expiró (~24h) pero el enlace de negocio del tenant sigue vigente (p. ej. 7 días).

Portal necesita: mismo `order_public_id` / `externalRef` en metadata para el webhook, con clave de idempotencia distinta por intento de session.

## Comportamiento esperado

1. `CheckoutRequest` acepta un parámetro opcional `?string $idempotencyKey = null`.  
2. Getter `idempotencyKey(): ?string`.  
3. `StripeGateway::createCheckout`:  
   - si `idempotencyKey()` no es null/vacío → usarla en la request options de Stripe;  
   - si no → `externalRef()` (comportamiento actual).  
4. Metadata `order_public_id` sigue siendo `externalRef()` (sin cambio).  
5. Callers existentes que no pasan la key no cambian de comportamiento.

## Superficie API / config

- `Lebytek\Framework\Domain\Payments\ValueObjects\CheckoutRequest`  
- `Lebytek\Framework\Infrastructure\Payments\StripeGateway::createCheckout`  
- Tests en `tests/Payments/` (o equivalente actual del paquete)

No requiere config env nueva.

## Impacto consumidores

| Consumidor | Impacto |
|------------|---------|
| Portal compra membresía (`IniciarPagoStripeUseCase`) | Ninguno si no pasa key |
| Portal cobros admin (nuevo) | Pasará key por intento, p. ej. `{public_id}:{n}` o `{public_id}:{unix}` |
| Skeleton / otros | Ninguno |

## Criterios de aceptación

1. Sin `idempotencyKey`: misma session/idempotency que hoy para el mismo `externalRef`.  
2. Con `idempotencyKey` distinta y mismo `externalRef`: Stripe acepta nueva session; metadata de lookup sigue siendo `externalRef`.  
3. Unit test cubre ambos caminos.  
4. Release semver (probablemente patch/minor según política del repo) documentado; Portal actualiza `composer.lock` después del tag.

## Fuera de alcance

- Stripe Payment Links API  
- Cambiar el nombre del metadata key `order_public_id`  
- Subscription checkout / billing portal (salvo que reutilicen el mismo VO; no obligatorio en este cambio)
