# Boundary FPS: Framework vs Portal

**Fuente diseño:** `docs/superpowers/specs/2026-07-17-framework-portal-separation-roadmap-design.md`  
**Baseline Git:** `docs/superpowers/FPS-git-baseline.md`  
**Plan histórico (no ejecutar monolítico):** `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`

## Hecho crítico sobre `main`

El commit `2c71d3f` (PR #5) fusionó `feature/backoffice-api-integration` en `main` y dejó en `main` **tanto plataforma como código Portal** (`app/Domain/Marketing`, landing, LebytekApi, etc.). La rama `consolidation/framework-portal-separation` parte de ese estado. El objetivo final es que el paquete `lebytek/framework` **no** autoload-e `App\\` ni contenga CRM; eso se logra en Plans 04–06, no revirtiendo `main`.

## Roles finales

| Rol | Repo / path | Composer |
|-----|-------------|----------|
| Plataforma | `Lebytek_Framework/src/`, SQL plataforma, `skeleton/` mínimo | `lebytek/framework` library |
| Portal Lebytek | `Lebytek_Portal/` (sibling) | `lebytek/portal` project |
| Tenant nuevo | copia de `skeleton/` | project propio |

## Clasificación de paths del delta (`main..feature`)

Reglas aplicadas al archivo `FPS-delta-paths-main-to-feature.txt`:

| Clase | Criterio | Acción en consolidación |
|-------|----------|-------------------------|
| **plataforma** | `src/`, SQL módulos plataforma (`payments.sql`, `integrations.sql`, …), tests plataforma (`tests/Payments`, `tests/Kernel`, …) | Trasladar selectivamente (Plan 01+) |
| **portal** | `app/**`, `tests/Marketing/**`, `database/migrations/*mkt*`, `database/schema/modules/marketing*.sql`, `config/cruds/mkt_*`, `routes/marketing.php`, `public/assets/publico/**` | Copiar en Plan 05 desde SHA congelado; retirar de Framework en Plan 06 |
| **mixto** | Archivos que mezclan plataforma y negocio | Reimplementar solo la parte genérica; **no** `git checkout` del archivo completo |
| **descartado** | Worktrees, logs, `.superpowers/sdd/*` locales | Ignorar |

### Paths plataforma — allowlist Plan 01 (Payments genérico)

Trasladar desde `dad0590` con `git checkout dad0590 -- <path>`:

```text
src/Domain/Payments/
src/Application/Payments/
src/Infrastructure/Payments/
database/schema/modules/payments.sql
config/modules/payments.php
config/payments.php
tests/Payments/
composer.json          # solo añadir stripe/stripe-php
.env.example             # solo bloque STRIPE_* / PAYMENTS_*
skeleton/config/payments.php
```

Ediciones manuales permitidas en Plan 01:

- `config/vertical.php` — añadir `'payments' => false` en `modules`
- `skeleton/config/vertical.php` — `'payments' => false`
- `config/container.php` — **solo** bloque plataforma (registry + repo), líneas equivalentes a:

```php
if ((bool) Config::get('vertical.modules.payments', false)) {
    $container->singleton(
        \Lebytek\Framework\Application\Payments\PaymentGatewayRegistry::class,
        static fn () => \Lebytek\Framework\Application\Payments\PaymentsFactory::registry()
    );
    $container->singleton(
        \Lebytek\Framework\Domain\Payments\PaymentEventLogRepositoryInterface::class,
        static fn () => new \Lebytek\Framework\Infrastructure\Payments\PdoPaymentEventLogRepository()
    );
}
```

### Paths prohibidos en Plan 01

```text
app/**
database/migrations/*mkt*
database/schema/modules/marketing*.sql
config/cruds/mkt_*.json
config/modules/marketing.php
routes/marketing.php
app/Presentation/Controllers/Publico/StripeWebhookController.php
app/Application/Marketing/IniciarPagoStripeUseCase.php
app/Application/Marketing/ConfirmarPagoStripeUseCase.php
config/container.php   # bindings Marketing/Stripe de negocio (ConfirmarPago, CompraController stripe, etc.)
```

### Paths portal — inventario representativo (Plan 05)

```text
app/Application/Marketing/
app/Domain/Marketing/
app/Infrastructure/Marketing/
app/Infrastructure/Integrations/LebytekApi/
app/Presentation/Controllers/Publico/
app/Presentation/Views/publico/
tests/Marketing/
database/migrations/20260714200000_mkt_membership_orders.sql
database/migrations/20260715120000_mkt_ordenes_stripe.sql
database/schema/modules/marketing.sql
config/cruds/mkt_leads.json
config/cruds/mkt_ordenes.json
docs/integration/
```

### Archivos mixtos documentados

| Archivo | Parte plataforma | Parte Portal | Resolución |
|---------|------------------|--------------|------------|
| `config/container.php` | bloque `PaymentGatewayRegistry` + `PaymentEventLogRepositoryInterface` | bloque Marketing + Stripe use cases | Plan 01: solo plataforma; Plan 05/06: Portal posee bindings negocio |
| `config/vertical.php` | flags `payments`, `integrations` | flag `marketing` | Cada plan edita solo sus claves |
| `composer.json` | `stripe/stripe-php` en require | — | Plan 01 añade dependencia; no tocar autoload `App\\` hasta Plan 06 |

## Explicit NO (todos los planes FPS)

- **Nunca** merge `feature/backoffice-api-integration` → `main` sin orden explícita del usuario
- Deploy / SSH / push remoto sin orden explícita
- Editar `vendor/`
- Copiar `schema.sql` plataforma al Portal como SoT
- Clonar Portal para bootstrapping de cliente externo
- Extraer `lebytek/module-marketing` (YAGNI)

## Deuda bloqueante (D1–D11)

Ver tabla completa en Plan 00 (`2026-07-17-fps-00-inventory-consolidation-branch.md`, sección *Deuda técnica D1–D11*). Resumen: D1/D5/D11 → Plan 06; D2/D8 → Plan 03; D3/D4/D7/D9/D10 → Plan 04; D6 → Planes 07–08.
