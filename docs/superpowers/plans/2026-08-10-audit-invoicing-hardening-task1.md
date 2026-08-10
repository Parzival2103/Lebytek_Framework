# Invoicing Hardening Task 1 — Mode/Key Fail-Fast Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar **Task 1** del plan de hardening Facturapi: rechazar proveedores habilitados con secret vacío o desalineado con `FACTURAPI_MODE` (`test` ↔ `sk_test_*`, `live` ↔ `sk_live_*`) antes de cualquier llamada de red.

**Architecture:** Validación en dos capas (defensa en profundidad): `InvoicingFactory::buildProviders` inspecciona `config.mode` + `config.secret_key` al registrar el factory closure; `FacturapiInvoiceProvider::fromSecretKey` recibe `mode` explícito y repite la comprobación. Sin cambios de dominio ni de use cases Issue/Cancel. Vertical `invoicing` permanece OFF.

**Tech Stack:** PHP `>=8.2`, harness `php tests/run.php Invoicing`, `Lebytek\Framework\{Application,Domain,Infrastructure}\Invoicing\`, config `config/invoicing.php` (`mode`, `secret_key`).

**Source spec:** `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md` (Task 1, enmienda **A18**) · **Modo:** continuación  
**Source audit PR:** ninguno — continuación del plan activo (auditoría pre-producción mergeada en #101; enmiendas #103)  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`487ccd8132e7c42eabd2a0e3b335b075ccc123e1`); rama `cursor/invoicing-hardening-p01-mode-key` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

**Plan padre:** `docs/superpowers/plans/2026-08-08-invoicing-facturapi-production-hardening.md`  
**Desbloquea:** Task 3 (create seguro asume provider configurado)

## Global Constraints

- Solo plataforma Framework (`src/`, `tests/`); sin negocio Portal ni `dom_*`.
- No editar `vendor/`; no habilitar vertical en harness/skeleton.
- No introducir `FACTURAPI_SECRET_KEY_TEST` / `FACTURAPI_SECRET_KEY_LIVE` (A18 YAGNI).
- Mensajes de error **sin** valor del secret (ni substring).
- Rama verificada: `git ls-remote origin refs/heads/main` → existe.

## Requisitos → tareas

| Requisito (A18 / 🔥 #3) | Owner | Tarea | Criterio |
|------------------------|-------|-------|----------|
| Secret no vacío si enabled | Framework | Task 1 | Factory + fromSecretKey lanzan antes de SDK |
| `mode=test` exige `sk_test_*` | Framework | Task 1 | Test explícito rechaza `sk_live_*` |
| `mode=live` exige `sk_live_*` | Framework | Task 1 | Test explícito rechaza `sk_test_*` |
| Happy path test key | Framework | Task 1 | `sk_test_abc` + mode test construye registry |

**Fuera de alcance (Task 1):** redacción ampliada (Task 2), issue/cancel, webhooks, RBAC, semver tag, Portal.

## Deuda abierta relacionada (no bloquea código Task 1)

| ID | Deuda | Impacto en Task 1 |
|----|-------|---------------------|
| REL-C1 | Tag `v1.2.7` no publicado (PR #105) | Ninguno — implementación en rama feature |
| INV-E1/E2 | Hardening 0/10 | Este plan cierra el primer ítem |

---

### Task 1: Fail-fast `FACTURAPI_MODE` ↔ key prefix + empty secret

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `cursor/invoicing-hardening-p01-mode-key`  
**Depends on:** None  
**Files:**
- Modify: `src/Application/Invoicing/InvoicingFactory.php` (`buildProviders`, ~L56–81)
- Modify: `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php` (`fromSecretKey`, ~L28–32)
- Modify: `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php` (`fromSecretKey`, ~L15–18) — guard opcional secret vacío
- Modify: `tests/Invoicing/InvoicingFactoryTest.php` (actualizar happy path L30: `sk_test` → `sk_test_unit`)
- Create: `tests/Invoicing/FacturapiSecretKeyValidationTest.php`

**Interfaces:**
- Consumes: `config.providers.{key}.config.secret_key` (string), `config.providers.{key}.config.mode` (`test`|`live`, default `test`)
- Produces: `FacturapiInvoiceProvider::fromSecretKey(string $secretKey, array $sdkConfig = [], string $mode = 'test'): self` — lanza `InvoiceProviderException` si inválido; `InvoicingFactory::buildProviders` lanza la misma excepción al **construir** el closure si la config enabled es inválida (fail-fast al primer `registry()->get()`)

**Reglas de validación (A18):**

```php
// Pseudocódigo compartido (extraer a FacturapiInvoiceProvider::assertSecretKeyMatchesMode o private static en Factory)
$secret = trim($secretKey);
if ($secret === '') {
    throw new InvoiceProviderException('Facturapi secret_key vacío con proveedor habilitado.');
}
$mode = strtolower(trim($mode)) === 'live' ? 'live' : 'test';
$expectedPrefix = $mode === 'live' ? 'sk_live_' : 'sk_test_';
if (!str_starts_with($secret, $expectedPrefix)) {
    throw new InvoiceProviderException(
        "Facturapi secret_key no coincide con mode={$mode} (se espera prefijo {$expectedPrefix})."
    );
}
```

- [x] **Step 1: Escribir el test que falla** — crear `tests/Invoicing/FacturapiSecretKeyValidationTest.php`:

```php
<?php
declare(strict_types=1);

use Lebytek\Framework\Application\Invoicing\InvoicingFactory;
use Lebytek\Framework\Domain\Invoicing\Exceptions\InvoiceProviderException;
use Lebytek\Framework\Infrastructure\Invoicing\FacturapiInvoiceProvider;

test('fromSecretKey rechaza secret vacío', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('', [], 'test');
    });
});

test('fromSecretKey rechaza mode=test con sk_live_', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('sk_live_abc123', [], 'test');
    });
});

test('fromSecretKey rechaza mode=live con sk_test_', function (): void {
    assert_throws(InvoiceProviderException::class, function (): void {
        FacturapiInvoiceProvider::fromSecretKey('sk_test_abc123', [], 'live');
    });
});

test('fromSecretKey acepta sk_test_ con mode=test', function (): void {
    $provider = FacturapiInvoiceProvider::fromSecretKey('sk_test_abc123', [], 'test');
    assert_same('facturapi', $provider->key());
});

test('InvoicingFactory buildProviders rechaza enabled con secret vacío', function (): void {
    InvoicingFactory::resetCached();
    assert_throws(InvoiceProviderException::class, function (): void {
        InvoicingFactory::buildProviders([
            'facturapi' => [
                'driver' => 'facturapi',
                'enabled' => true,
                'config' => ['secret_key' => '   ', 'mode' => 'test'],
            ],
        ]);
    });
});

test('InvoicingFactory buildProviders rechaza mismatch mode/key al instanciar', function (): void {
    InvoicingFactory::resetCached();
    $providers = InvoicingFactory::buildProviders([
        'facturapi' => [
            'driver' => 'facturapi',
            'enabled' => true,
            'config' => ['secret_key' => 'sk_live_x', 'mode' => 'test'],
        ],
    ]);
    assert_throws(InvoiceProviderException::class, function () use ($providers): void {
        ($providers['facturapi']['factory'])();
    });
});
```

- [x] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Invoicing/FacturapiSecretKeyValidation` / Expected: FAIL — `fromSecretKey` acepta `sk_live_x` con mode test (sin validación) o tests no encontrados si el archivo aún no existe.

- [x] **Step 3: Implementar el cambio mínimo**
  1. Añadir método privado estático `assertSecretKeyMatchesMode(string $secretKey, string $mode): void` en `FacturapiInvoiceProvider` que lance `InvoiceProviderException` según reglas A18; invocarlo al inicio de `fromSecretKey`.
  2. Cambiar firma: `fromSecretKey(string $secretKey, array $sdkConfig = [], string $mode = 'test'): self`.
  3. En `InvoicingFactory::buildProviders`, leer `$mode = (string) ($cfg['mode'] ?? 'test')`, llamar `assertSecretKeyMatchesMode` **antes** de registrar el closure; pasar `$mode` al closure: `FacturapiInvoiceProvider::fromSecretKey($secret, $sdkSlice, $mode)`.
  4. (Opcional defensa) En `SdkFacturapiTransport::fromSecretKey`, si `trim($secretKey) === ''`, lanzar `InvoiceProviderException` antes de `new Facturapi(...)`.
  5. Actualizar `InvoicingFactoryTest.php` L30: `'secret_key' => 'sk_test_unit'` (prefijo válido).

- [x] **Step 4: Verificación enfocada** — Run: `php tests/run.php Invoicing/FacturapiSecretKeyValidation Invoicing/InvoicingFactory` / Expected: PASS (6 tests nuevos + 3 existentes; happy path factory sigue verde).

- [x] **Step 5: Regresión relevante** — Run: `php tests/run.php Invoicing` / Expected: PASS — suite Invoicing completa sin regresiones en Issue/Reconcile/Provider tests.

- [x] **Step 6: Commit** — archivos: `src/Application/Invoicing/InvoicingFactory.php`, `src/Infrastructure/Invoicing/FacturapiInvoiceProvider.php`, `src/Infrastructure/Invoicing/Facturapi/SdkFacturapiTransport.php` (si tocado), `tests/Invoicing/FacturapiSecretKeyValidationTest.php`, `tests/Invoicing/InvoicingFactoryTest.php` — mensaje: `fix(invoicing): enforce Facturapi mode/key prefix and reject empty secrets`

## Criterios finales de aceptación

- [x] Enabled + secret vacío → `InvoiceProviderException` sin filtrar secret.
- [x] `mode=test` + `sk_live_*` → excepción; `mode=live` + `sk_test_*` → excepción.
- [x] `mode=test` + `sk_test_*` válido → provider instanciable vía factory y directo.
- [x] Ningún cambio en use cases Issue/Cancel/Reconcile.
- [x] `php tests/run.php Invoicing` PASS.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Test existente usa `sk_test` sin guion bajo | Actualizar a `sk_test_unit` en Step 3 |
| Consumidor pasa key live en env test | Comportamiento deseado (fail-fast A18) |
| Firma `fromSecretKey` usada en tests directos | Grep `fromSecretKey(` en `tests/Invoicing/` y ajustar tercer arg si necesario |

**Rollback:** revertir commit único de Task 1; sin migraciones SQL.

## Evidencia que debe recopilar el ejecutor

- Salida PASS de `php tests/run.php Invoicing/FacturapiSecretKeyValidation Invoicing/InvoicingFactory` y `php tests/run.php Invoicing`.
- PR URL + SHA merge.
- Checkbox Task 1 marcado en plan padre (`2026-08-08-invoicing-facturapi-production-hardening.md`) en commit de seguimiento separado o PR de cierre parcial.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-10 |
| Framework `origin/main` referencia | `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |
| Tareas completadas / totales | 1 / 1 |
| Siguiente tarea ejecutable | — (cerrado; continuar plan padre Task 2+) |
| Prerrequisitos | PHP ≥8.2, `composer install` |
| Bloqueos | Ninguno |
| Estado | Completado en PR #109 |
