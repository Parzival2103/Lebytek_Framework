# Manual Membership Purchase (Hardening) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the post-implementation gaps for bank-transfer membership purchase so WhatsApp admin deep-links, fresh schema installs, api contract mirror, permission seeding, and authorize idempotent-replay (`token: null`) match the design before ops enables `MKT_MEMBERSHIP_AUTHORIZE_ENABLED` in production.

**Architecture:** The checkout → `dom_mkt_ordenes` → admin authorize → `LebytekApiClient::activatePlan` path already ships on `feature/backoffice-api-integration` (commits `35f69d3`, `e66a02a`, merge `5f4e913`). This plan is a **hardening pass only** — do not reimplement Create/Authorize use cases or the public purchase UI. Companion api `POST …/activate-plan` already lives on WhatsApiLebytek `main` (PR #14).

**Tech Stack:** PHP 8.1+, Lebytek Onion (`app/`), PDO marketing repos, microtest (`php tests/run.php Marketing`), docs under `docs/integration/`.

**Spec:** `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md`

**Companion plans (same purpose, three roles):**

| Role | Repo | Plan |
|------|------|------|
| Historical ship (done) | WhatsApiLebytek | `docs/superpowers/plans/2026-07-14-plan-activation-and-package-limits.md` |
| Api residual tests + VPS smoke | WhatsApiLebytek | `docs/superpowers/plans/2026-07-14-plan-activation-closure.md` |
| Framework hardening + authorize flag | this | **this file** (Tasks 1–7 + Task 8 human) |

Also: api design `docs/superpowers/specs/2026-07-14-plan-activation-and-package-limits-design.md`; canonical contract on api `docs/integration/waapi-api-contract.md` § `POST /tenants/{publicId}/activate-plan` (**Implementado**).

## Cross-repo alignment (canonical)

Shared purpose: **admin Autorizar pago → api activate-plan → same tenant/instance + new Bearer**, with catalog quotas.

Copy of this block must stay identical in WhatsApiLebytek `2026-07-14-plan-activation-and-package-limits.md` § Cross-repo alignment (and the sequence text in the api closure plan).

### Shared invariants (must match api plan)

1. **Catalog SoT:** api `config/plans.php` (Starter 5000 / Business 80000 / rates). Framework `dom_mkt_paquetes` mirrors display limits for checkout snapshot; authorize must **not** send `messagesMonthlyLimit` for `starter`/`business`.
2. **Unlock slugs (Framework rule):** Framework → api only `starter` \| `business` \| `empresa`. Never `demo`. Checkout already omits `empresa` from purchasable; authorize must also refuse `demo` / empty / unknown slug before HTTP call (**this plan Task 2**).
3. **Api vs caller on `demo`:** `demo` stays in api catalog for rate fallback. Shipped FormRequest may still accept `planSlug=demo`; contract table may list it. That is **not** a Framework unlock path — Framework never sends it. Do not “fix” api to reject `demo` as part of this hardening plan.
4. **Payload map:**

| Api body field | Framework source |
|----------------|------------------|
| `planSlug` | `dom_mkt_ordenes.paquete_slug` |
| `billingCycle` | `ciclo` (`monthly` \| `annual`) |
| `orderExternalRef` | `public_id` |
| `tokenName` | `membresia-{slug}` (api default `cliente-{slug}` OK if omitted) |
| `messagesMonthlyLimit` | **only** if `paquete_slug === 'empresa'` and snapshot non-null; omit otherwise |

5. **Two idempotency layers:** (a) same `Idempotency-Key` → HTTP cache of first response (often 201 + token); (b) Framework `LebytekApiClient` **always** mints a new UUID on writes → admin retry hits **semantic** HTTP **200** + `token: null` (same `planSlug` + `orderExternalRef`), not the cache. Client treats any `<400` as success (200 and 201 both OK).
6. **Framework authorize on semantic 200:** `markPaid` (already clears `api_activation_error`) + **no** email #3 when `token` is null/blank after `trim` (**this plan Task 2** — **blocker** before enabling the flag).
7. **Ops gate:** keep `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` until **all** of: (a) api VPS `meta` + smoke 201/200 (api closure Task 4), (b) this plan Task 2 deployed, (c) Framework VPS migrations + `MKT_BANK_*`. Then enable only via **this plan Task 8**.
8. **No merge** `feature/backoffice-api-integration` → `main` without explicit user order.

| Concern | Api (WhatsApiLebytek) | This plan (Framework) |
|---------|----------------------|------------------------|
| Catalog / rates | `config/plans.php` | `dom_mkt_paquetes`; no invented limits on authorize |
| Unlock HTTP | `POST …/activate-plan` on `main` | `LebytekApiClient::activatePlan` (shipped) |
| First success | HTTP **201** + `token` string | `markPaid` + email #3 |
| Semantic replay | HTTP **200** + `token: null` | **Task 2** — paid, no email #3 |
| Refuse `demo` unlock | Catalog/rates only; FormRequest may still accept | Authorize pre-flight (**Task 2**) |
| Residual tests / VPS | api `plan-activation-closure.md` | Hardening Tasks 1–7 + Task 8 ops |

**Sequence (prod) — identical wording on api historical, api closure, and Framework plans:**

```
1. Api activate-plan on main (historical Tasks 1–7)    ✅ shipped
2. Api closure: residual tests + VPS meta/smoke        → closure plan (Task 4 = human)
3. Framework hardening Tasks 1–7 (incl. Task 2)        → this plan
4. Framework VPS migrations + MKT_BANK_*               → this plan Task 8 (human)
5. MKT_MEMBERSHIP_AUTHORIZE_ENABLED=true               → this plan Task 8
                                                           after sequence steps 2, 3, and 4
```

Do **not** enable authorize before sequence steps 2 **and** 3 (api smoke + null-token fix). Step 4 (Framework VPS bank/env) is also required before flipping the flag.

## Global Constraints

- Work only on branch `feature/backoffice-api-integration` (or a short-lived hardening branch cut from it).
- **Do not** merge `feature/backoffice-api-integration` → `main` unless the user explicitly orders it.
- `compras=1` remains UI-only; never use it to authorize paid API features.
- Authorize stays behind RBAC `marketing.ordenes` + kill switch `MKT_MEMBERSHIP_AUTHORIZE_ENABLED`.
- Do not implement Mercado Pago / Stripe / PayPal capture.
- Do not change Green instance / force re-QR.
- Do not run VPS deploy, SSH, or edit production `.env` from this plan — ops checklist is human-only (Task 8).
- Do not run WhatsApiLebytek VPS ops from this repo — that is api closure Task 4.
- Platform packages: Starter `2199` / Business `4499` (MXN monthly); limits `5000` / `80000` (must match api `config/plans.php`); Enterprise slug `empresa` (Contactar, not checkout).
- Token plaintext appears only in email #3 / authorize success path — never in public transfer view.
- **Commits:** only when the user asks (messages below are suggested).
- Prefer api catalog numbers over inventing Framework-side overrides for paid slugs.
- **Unlock `planSlug`:** only `starter` \| `business` \| `empresa` on activate-plan; never send `demo` (enforce in authorize even if api FormRequest still accepts `demo`).

---

## File Structure (this hardening pass)

| Path | Role |
|------|------|
| `app/Infrastructure/Marketing/PurchaseWhatsAppNotifier.php` | Fix admin deep-link `/admin/crud/...` |
| `tests/Marketing/PurchaseWhatsAppNotifierTest.php` | Assert `/admin` prefix |
| `app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php` | Semantic 200 `token: null` → paid/no email #3; refuse `demo`/unknown unlock slug |
| `tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php` | Null-token + refuse-demo + happy-path coverage |
| `database/migrations/20260714200000_mkt_membership_orders.sql` | Fix broken `auth_permisos` column names (`clave` → `slug`/`nombre`) |
| `database/migrations/20260715100000_mkt_ordenes_permission_slug.sql` | Idempotent permission repair for DBs that already ran the broken INSERT |
| `database/schema/modules/marketing.sql` | Bootstrap: `dom_mkt_ordenes`, package columns/prices/slugs, `marketing.ordenes`, menu |
| `tests/Marketing/SchemaBootstrapTest.php` | Assert new table/permission/prices in bootstrap SQL |
| `docs/integration/waapi-api-contract.md` | Mirror `POST …/activate-plan` + PATCH warning |
| `docs/integration/lebytek-implementation-real.md` | Short authorize → activatePlan ops note + env flags |
| `tests/Marketing/CompraControllerContractTest.php` | Source-level checkout CSRF + rate-limit contract (no Kernel HTTP harness) |
| `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md` | Mark hardening items done after code lands |

**Already shipped (do not rebuild):** `CompraController`, `CrearOrdenMembresiaUseCase`, `AutorizarOrdenMembresiaUseCase` (core flow), `PdoMembershipOrderRepository`, `config/cruds/mkt_ordenes.json`, purchase views/emails, `LebytekApiClient::activatePlan`, Pricing/`?compras=1` gate.

---

### Task 1: Fix WhatsApp admin deep-link

**Files:**
- Modify: `app/Infrastructure/Marketing/PurchaseWhatsAppNotifier.php`
- Modify: `tests/Marketing/PurchaseWhatsAppNotifierTest.php`

**Interfaces:**
- Consumes: `EnvLoader::get('APP_URL')`, order `id`
- Produces: alert body containing `{APP_URL}/admin/crud/mkt_ordenes/{id}` (same pattern as lead churn dashboard URLs)

- [ ] **Step 1: Update the failing assertion first**

In `tests/Marketing/PurchaseWhatsAppNotifierTest.php`, change the URL assertion in `PurchaseWhatsAppNotifier sends once per configured number`:

```php
assert_true(str_contains($channel->requests[0]->body, 'https://lebytek.com/admin/crud/mkt_ordenes/9'));
assert_true(! str_contains($channel->requests[0]->body, "https://lebytek.com/crud/mkt_ordenes/9\n"), 'must not omit /admin');
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php tests/run.php Marketing/PurchaseWhatsAppNotifierTest
```

Expected: FAIL on the `/admin/crud` assertion (current body still has `/crud/mkt_ordenes/9` without `admin`).

- [ ] **Step 3: Minimal fix**

In `PurchaseWhatsAppNotifier::notifyTransferPending`, replace the URL build:

```php
$adminUrl = $base.'/admin/crud/mkt_ordenes/'.$id;
```

(full surrounding context for the edit)

```php
$base = rtrim((string) EnvLoader::get('APP_URL', ''), '/');
$id = (int) ($order['id'] ?? 0);
$publicId = (string) ($order['public_id'] ?? '');
$adminUrl = $base.'/admin/crud/mkt_ordenes/'.$id;
```

- [ ] **Step 4: Run test — expect PASS**

```bash
php tests/run.php Marketing/PurchaseWhatsAppNotifierTest
```

Expected: all three tests PASS.

- [ ] **Step 5: Commit (suggested; only if user approved)**

```bash
git add app/Infrastructure/Marketing/PurchaseWhatsAppNotifier.php tests/Marketing/PurchaseWhatsAppNotifierTest.php
git commit -m "$(cat <<'EOF'
fix(marketing): prefix purchase WhatsApp admin link with /admin

CRUD Engine lives under /admin/crud; ops alerts were pointing at a 404 path.
EOF
)"
```

---

### Task 2: Authorize — semantic `token: null` + refuse non-unlock slugs

**Why this is the critical cross-repo hinge:**  
`LebytekApiClient` always sends a **fresh** `Idempotency-Key` UUID on writes. A second Autorizar after api already activated the same `planSlug` + `orderExternalRef` therefore receives HTTP **200** with `token: null` (api semantic replay) — **not** a cached 201. Today Framework throws `"API no devolvió token…"` and the order stays unpaid forever. Align with api contract Respuesta 200.

Also enforce the shared unlock rule: never call activate-plan with `demo` / empty / unknown slug even though api FormRequest may still accept `demo`.

**Files:**
- Modify: `app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php`
- Modify: `tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php`

**Interfaces:**
- Consumes: `LebytekApiClient::activatePlan(string $tenantPublicId, array $payload): array` — decoded body may include `token` string (first activation) or JSON `null` / missing (semantic replay). HTTP 200 and 201 both succeed (`status < 400`).
- Produces: order `paid` via `markPaid` (repo already sets `api_activation_error = NULL`); email #3 **only** when plaintext token is non-empty after `trim`
- Does **not** invent `messagesMonthlyLimit` for `starter`/`business` (payload map already correct)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php`:

```php
test('AutorizarOrdenMembresiaUseCase marca paid sin correo si api reusa activate-plan con token null', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $transport->responses[] = [
        'status' => 200,
        'body' => '{"token":null,"tenant":{"publicId":"01JTENANT0000000000000001","commercialStatus":"active","planSlug":"starter"},"plan":{"slug":"starter","name":"Starter","messagesMonthlyLimit":5000,"billingCycle":"monthly"}}',
        'error' => '',
    ];
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[4] = [
        'id' => 4,
        'public_id' => '01JORD00000000000000000004',
        'paquete_slug' => 'starter',
        'ciclo' => 'monthly',
        'precio_snapshot' => 2199,
        'mensajes_mes_limite_snapshot' => 5000,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
        'api_activation_error' => 'API no devolvió token de membresía.',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase($orders, $api, $mailer);
    $uc->ejecutar(4, 7);

    assert_true($orders->paid);
    assert_same('paid', $orders->rows[4]['status']);
    assert_same(0, count($mailer->sent), 'sin token nuevo no se reenvía email #3');
    assert_true(
        ($orders->rows[4]['api_activation_error'] ?? null) === null
            || $orders->rows[4]['api_activation_error'] === '',
        'markPaid must clear stale activation error'
    );
    assert_same(1, count($transport->requests), 'debió llamar activate-plan una vez');
});

test('AutorizarOrdenMembresiaUseCase rechaza slug demo sin llamar api', function (): void {
    $transport = new AuthorizeRecordingTransport();
    $api = new LebytekApiClient('https://api.test/v1', 'platform-token', 5, 1, $transport);

    $orders = new AuthorizeMemOrderRepo();
    $orders->rows[5] = [
        'id' => 5,
        'public_id' => '01JORD00000000000000000005',
        'paquete_slug' => 'demo',
        'ciclo' => 'monthly',
        'precio_snapshot' => 0,
        'mensajes_mes_limite_snapshot' => 100,
        'nombre' => 'Buyer',
        'email' => 'buyer@test.com',
        'status' => 'pending_transfer',
        'api_tenant_public_id' => '01JTENANT0000000000000001',
    ];

    $mailer = new SpyMembershipMailer();
    $uc = new AutorizarOrdenMembresiaUseCase($orders, $api, $mailer);

    $thrown = false;
    try {
        $uc->ejecutar(5, 7);
    } catch (\InvalidArgumentException $e) {
        $thrown = true;
        assert_true(str_contains($e->getMessage(), 'autorizable') || str_contains($e->getMessage(), 'demo'));
    }
    assert_true($thrown, 'debe rechazar slug demo');
    assert_same(0, count($transport->requests), 'no llamar activate-plan con demo');
    assert_true(empty($orders->paid), 'orden no paid');
});
```

If `AuthorizeMemOrderRepo::markPaid` does not clear `api_activation_error` in the in-memory stub, mirror production (`api_activation_error = NULL`) in the stub before asserting.

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php tests/run.php Marketing/AutorizarOrdenMembresiaUseCaseTest
```

Expected: FAIL — current code throws `LebytekApiException('API no devolvió token de membresía.')` on null token; demo slug still calls api (or would).

- [ ] **Step 3: Minimal implementation**

In `AutorizarOrdenMembresiaUseCase::ejecutar`, **before** building `$payload`, after resolving `$slug`:

```php
        $slug = (string) ($order['paquete_slug'] ?? '');
        if (! in_array($slug, ['starter', 'business', 'empresa'], true)) {
            throw new \InvalidArgumentException(
                'El paquete de la orden no es autorizable vía activate-plan (use starter, business o empresa).'
            );
        }
```

Replace the post-`activatePlan` block (after the catch) with:

```php
        // Api 201 → string token; api semantic 200 → JSON null (decode as PHP null).
        // Fresh Idempotency-Key on every client call means retries land here, not on HTTP cache replay.
        $plainToken = trim((string) ($response['token'] ?? ''));

        $this->orders->markPaid($orderId, $authorizedBy);

        if ($plainToken !== '') {
            try {
                $this->sendMembershipEmail($order, $plainToken);
            } catch (\Throwable $mailError) {
                $this->orders->setApiActivationError($orderId, 'Correo: '.$mailError->getMessage());
            }
        }

        $updated = $this->orders->findById($orderId);

        return $updated ?? $order;
```

Remove the previous early throw when `$plainToken === ''`.  
Production `PdoMembershipOrderRepository::markPaid` already nulls `api_activation_error` — keep that behavior in any in-memory test double.

Do **not** add `messagesMonthlyLimit` for `starter`/`business` (api FormRequest `prohibited_unless:planSlug,empresa` → 422).

- [ ] **Step 4: Run tests — expect PASS**

```bash
php tests/run.php Marketing/AutorizarOrdenMembresiaUseCaseTest
```

Expected: happy path, api-fail, no-tenant, null-token, and demo-slug tests all PASS.

- [ ] **Step 5: Commit (suggested; only if user approved)**

```bash
git add app/Application/Marketing/AutorizarOrdenMembresiaUseCase.php tests/Marketing/AutorizarOrdenMembresiaUseCaseTest.php
git commit -m "$(cat <<'EOF'
fix(marketing): handle activate-plan null token and refuse demo unlock

Api semantic 200 reuses activation without re-issuing Bearer; unpaid stuck orders and demo→activate-plan are both blocked.
EOF
)"
```

---

### Task 3: Repair `marketing.ordenes` permission INSERT (migration uses wrong columns)

**Files:**
- Modify: `database/migrations/20260714200000_mkt_membership_orders.sql`
- Create: `database/migrations/20260715100000_mkt_ordenes_permission_slug.sql`
- Modify: `config/modules/marketing.php` (only if the migraciones list must include the new file — mirror how other module migrations are registered)

**Interfaces:**
- Consumes: `auth_permisos` schema (`nombre`, `slug`, `modulo`, `descripcion`) — **not** `clave`
- Produces: permission slug `marketing.ordenes` + grant to role `administrador`

- [ ] **Step 1: Confirm the bug**

Open `database/migrations/20260714200000_mkt_membership_orders.sql` lines 57–60 — they insert into (`clave`, `descripcion`, `modulo`). Real table columns are `nombre` / `slug` (`database/schema/schema.sql`).

- [ ] **Step 2: Fix the original migration INSERT (for fresh runners)**

Replace the permission block at the end of `20260714200000_mkt_membership_orders.sql` with:

```sql
INSERT INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`)
SELECT 'Autorizar órdenes de membresía', 'marketing.ordenes', 'marketing', 'Autorizar órdenes de membresía'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `slug` = 'marketing.ordenes');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'marketing.ordenes'
WHERE `r`.`slug` = 'administrador';
```

- [ ] **Step 3: Add repair migration for environments that already applied the broken INSERT**

Create `database/migrations/20260715100000_mkt_ordenes_permission_slug.sql`:

```sql
-- Repair: earlier membership-orders migration used non-existent auth_permisos.clave.
INSERT INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`)
SELECT 'Autorizar órdenes de membresía', 'marketing.ordenes', 'marketing', 'Autorizar órdenes de membresía'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `slug` = 'marketing.ordenes');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'marketing.ordenes'
WHERE `r`.`slug` = 'administrador';
```

- [ ] **Step 4: Register migration on the marketing module**

In `config/modules/marketing.php`, append the repair file to `migraciones` so it becomes:

```php
    'migraciones'   => [
        '20260713120000_mkt_leads_email_verify.sql',
        '20260714200000_mkt_membership_orders.sql',
        '20260715100000_mkt_ordenes_permission_slug.sql',
    ],
```

- [ ] **Step 5: Sanity — greppable contract test**

**Create:** `tests/Marketing/MembershipOrdersMigrationTest.php`:

```php
<?php

declare(strict_types=1);

test('membership orders migration uses slug not clave for auth_permisos', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH.'/database/migrations/20260714200000_mkt_membership_orders.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_ordenes`'));
    assert_true(str_contains($sql, "'marketing.ordenes'"));
    assert_true(! str_contains($sql, '`clave`'), 'auth_permisos insert must use slug');
});

test('repair migration seeds marketing.ordenes with slug column', function (): void {
    $path = ROOT_PATH.'/database/migrations/20260715100000_mkt_ordenes_permission_slug.sql';
    assert_true(is_file($path), 'repair migration exists');
    $sql = (string) file_get_contents($path);
    assert_true(str_contains($sql, "'marketing.ordenes'"));
    assert_true(str_contains($sql, '`slug`'));
    assert_true(! str_contains($sql, '`clave`'), 'must not use obsolete clave column');
});

test('marketing module lists membership permission repair migration', function (): void {
    $manifest = require ROOT_PATH.'/config/modules/marketing.php';
    assert_true(in_array('20260715100000_mkt_ordenes_permission_slug.sql', $manifest['migraciones'], true));
});
```

Run:

```bash
php tests/run.php Marketing/MembershipOrdersMigrationTest
```

Expected: PASS.

- [ ] **Step 6: Commit (suggested; only if user approved)**

```bash
git add database/migrations/20260714200000_mkt_membership_orders.sql \
  database/migrations/20260715100000_mkt_ordenes_permission_slug.sql \
  config/modules/marketing.php \
  tests/Marketing/MembershipOrdersMigrationTest.php
git commit -m "$(cat <<'EOF'
fix(marketing): seed marketing.ordenes permission with auth_permisos.slug

The membership-orders migration referenced a non-existent clave column.
EOF
)"
```

---

### Task 4: Align `marketing.sql` bootstrap (orders table + commercial packages)

**Files:**
- Modify: `database/schema/modules/marketing.sql`
- Modify: `tests/Marketing/SchemaBootstrapTest.php`

**Interfaces:**
- Consumes: same column set as `20260714200000_mkt_membership_orders.sql` + package columns from `20260706120100_mkt_paquetes_limits.sql`
- Produces: fresh `php scripts/install.php` installs that include `dom_mkt_ordenes` and commercial seed prices without relying on migrations alone

- [ ] **Step 1: Write failing SchemaBootstrap assertions**

Replace/extend tests in `tests/Marketing/SchemaBootstrapTest.php`:

```php
test('marketing.sql crea todas las tablas dom_mkt_* de forma idempotente', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true($sql !== false, 'archivo existe');
    foreach ([
        'dom_mkt_leads', 'dom_mkt_provisiones', 'dom_mkt_paquetes',
        'dom_mkt_bloques', 'dom_mkt_plantillas', 'dom_mkt_secuencias', 'dom_mkt_paginas',
        'dom_mkt_ordenes',
    ] as $tabla) {
        assert_true(str_contains($sql, "CREATE TABLE IF NOT EXISTS `{$tabla}`"), "crea {$tabla}");
    }
});

test('marketing.sql inserta permisos y menú con INSERT IGNORE', function (): void {
    $sql = file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "'marketing.ver'"), 'permiso ver');
    assert_true(str_contains($sql, "'marketing.gestionar'"), 'permiso gestionar');
    assert_true(str_contains($sql, "'marketing.leads'"), 'permiso leads');
    assert_true(str_contains($sql, "'marketing.ordenes'"), 'permiso ordenes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `auth_permisos`'), 'permisos idempotentes');
    assert_true(str_contains($sql, 'INSERT IGNORE INTO `core_menu_items`'), 'menú idempotente');
    assert_true(str_contains($sql, "'marketing'"), 'menú padre marketing');
    assert_true(str_contains($sql, '/admin/crud/mkt_ordenes'), 'menú órdenes');
});

test('marketing.sql siembra paquetes comerciales con slug y precios VPS', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, "`slug`"), 'columna slug en paquetes');
    assert_true(str_contains($sql, "`mensajes_mes_limite`"), 'columna mensajes_mes_limite');
    assert_true(str_contains($sql, "'starter'"), 'slug starter');
    assert_true(str_contains($sql, "'business'"), 'slug business');
    assert_true(str_contains($sql, "'empresa'"), 'slug empresa');
    assert_true(str_contains($sql, '2199.00'), 'precio Starter comercial');
    assert_true(str_contains($sql, '4499.00'), 'precio Business comercial');
    assert_true(! str_contains($sql, '499.00'), 'precio seed viejo Starter eliminado');
});
```

Keep the existing `access_token` / `NOT EXISTS` / no-FK tests unchanged (or fold `NOT EXISTS` into the packages test).

- [ ] **Step 2: Run — expect FAIL**

```bash
php tests/run.php Marketing/SchemaBootstrapTest
```

Expected: FAIL missing `dom_mkt_ordenes`, `marketing.ordenes`, commercial prices.

- [ ] **Step 3: Update `dom_mkt_paquetes` CREATE + seeds**

In `database/schema/modules/marketing.sql`, change the `dom_mkt_paquetes` CREATE to include limit columns:

```sql
CREATE TABLE IF NOT EXISTS `dom_mkt_paquetes` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`              VARCHAR(150)    NOT NULL,
  `slug`                VARCHAR(50)     DEFAULT NULL,
  `precio_mensual`      DECIMAL(10,2)   DEFAULT NULL,
  `precio_anual`        DECIMAL(10,2)   DEFAULT NULL,
  `features`            JSON            DEFAULT NULL,
  `mensajes_mes_limite` INT UNSIGNED    DEFAULT NULL,
  `demo_dias`           INT UNSIGNED    DEFAULT NULL,
  `destacado`           TINYINT(1)      NOT NULL DEFAULT 0,
  `badge`               VARCHAR(60)     DEFAULT NULL,
  `orden`               INT             NOT NULL DEFAULT 0,
  `activo`              TINYINT(1)      NOT NULL DEFAULT 1,
  `deleted`             TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`          BIGINT UNSIGNED DEFAULT NULL,
  `updated_at`          DATETIME        DEFAULT NULL,
  `updated_by`          BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at`          DATETIME        DEFAULT NULL,
  `deleted_by`          BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dom_mkt_paquetes_slug_unique` (`slug`),
  KEY `idx_mkt_paquetes_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

After `dom_mkt_paginas` (and before permissions), add `dom_mkt_ordenes` — copy the CREATE TABLE block exactly from `database/migrations/20260714200000_mkt_membership_orders.sql` (lines 3–38).

Replace the three commercial package INSERT blocks and add Demo + correct limits:

```sql
INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Demo' AS nombre, 'demo' AS slug, 0.00 AS precio_mensual, 0.00 AS precio_anual,
         JSON_ARRAY('100 mensajes/mes', '30 días de prueba', '1 número WhatsApp') AS features,
         100 AS mensajes_mes_limite, 30 AS demo_dias,
         0 AS destacado, NULL AS badge, 0 AS orden, 0 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'demo');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Starter' AS nombre, 'starter' AS slug, 2199.00 AS precio_mensual, 21990.00 AS precio_anual,
         JSON_ARRAY('1 instancia WhatsApp', 'Hasta ~5000 mensajes/mes', 'Soporte por correo') AS features,
         5000 AS mensajes_mes_limite, NULL AS demo_dias,
         0 AS destacado, NULL AS badge, 1 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'starter');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Business' AS nombre, 'business' AS slug, 4499.00 AS precio_mensual, 44990.00 AS precio_anual,
         JSON_ARRAY('Hasta 3 instancias WhatsApp', 'Hasta ~80000 mensajes/mes', 'Campañas + plantillas', 'Soporte prioritario') AS features,
         80000 AS mensajes_mes_limite, NULL AS demo_dias,
         1 AS destacado, 'Más popular' AS badge, 2 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'business');

INSERT INTO `dom_mkt_paquetes` (`nombre`, `slug`, `precio_mensual`, `precio_anual`, `features`, `mensajes_mes_limite`, `demo_dias`, `destacado`, `badge`, `orden`, `activo`)
SELECT * FROM (
  SELECT 'Enterprise' AS nombre, 'empresa' AS slug, NULL AS precio_mensual, NULL AS precio_anual,
         JSON_ARRAY('Instancias a medida', 'Volumen personalizado', 'SLA dedicado', 'Integración API') AS features,
         NULL AS mensajes_mes_limite, NULL AS demo_dias,
         0 AS destacado, NULL AS badge, 3 AS orden, 1 AS activo
) AS t
WHERE NOT EXISTS (SELECT 1 FROM `dom_mkt_paquetes` WHERE `slug` = 'empresa');
```

Update permission INSERTs to add `marketing.ordenes` and include it in the admin role join list:

```sql
INSERT IGNORE INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`) VALUES
('Ver marketing',       'marketing.ver',       'marketing', 'Acceso de lectura al módulo de marketing'),
('Crear en marketing',  'marketing.crear',     'marketing', 'Crear contenido/paquetes/plantillas'),
('Editar en marketing', 'marketing.editar',    'marketing', 'Editar contenido/paquetes/plantillas'),
('Eliminar en marketing','marketing.eliminar', 'marketing', 'Eliminar lógico en marketing'),
('Gestionar marketing', 'marketing.gestionar', 'marketing', 'Gestionar ajustes del módulo de marketing'),
('Gestionar leads',     'marketing.leads',     'marketing', 'Gestionar la bandeja de leads'),
('Publicar contenido',  'marketing.publicar',  'marketing', 'Publicar páginas y contenido público'),
('Autorizar órdenes',   'marketing.ordenes',   'marketing', 'Autorizar órdenes de membresía');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` IN (
  'marketing.ver','marketing.crear','marketing.editar','marketing.eliminar',
  'marketing.gestionar','marketing.leads','marketing.publicar','marketing.ordenes'
)
WHERE `r`.`slug` = 'administrador';
```

Add menu item after plantillas:

```sql
INSERT IGNORE INTO `core_menu_items`
(`parent_id`, `orden`, `slug`, `label`, `icon`, `url`, `match`, `permiso_slug`, `vertical_module`, `activo`)
SELECT p.id, 5, 'marketing-ordenes', 'Órdenes', 'bi-receipt', '/admin/crud/mkt_ordenes', '/admin/crud/mkt_ordenes', 'marketing.ordenes', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing';
```

- [ ] **Step 4: Run SchemaBootstrapTest — expect PASS**

```bash
php tests/run.php Marketing/SchemaBootstrapTest
```

Expected: PASS.

- [ ] **Step 5: Commit (suggested; only if user approved)**

```bash
git add database/schema/modules/marketing.sql tests/Marketing/SchemaBootstrapTest.php
git commit -m "$(cat <<'EOF'
fix(marketing): sync bootstrap schema with membership orders and commercial prices

Fresh installs skipped the orders table and still seeded 499/999 demo prices.
EOF
)"
```

---

### Task 5: Mirror `activate-plan` in Framework integration docs

**Files:**
- Modify: `docs/integration/waapi-api-contract.md`
- Modify: `docs/integration/lebytek-implementation-real.md`

**Interfaces:**
- Consumes: WhatsApiLebytek **live** § `POST /tenants/{publicId}/activate-plan` (status **Implementado** on api `main` — do **not** paste historical “Planificado” drafts from the ship plan)
- Produces: Framework mirror so back-office agents do not invent a bare `PATCH` for paid unlock

- [ ] **Step 1: Patch warning on existing Framework PATCH section**

In `docs/integration/waapi-api-contract.md`, under `### PATCH /tenants/{publicId}`, after the permission lines and before the body example, insert (same wording as api):

```markdown
> Para desbloqueo pago (demo → plan), usar `POST …/activate-plan` (revoke + token nuevo). `PATCH` comercial sin rotar tokens queda desaconsejado.
```

- [ ] **Step 2: Insert full activate-plan section after tokens**

1. Open WhatsApiLebytek `docs/integration/waapi-api-contract.md` from heading `### POST /tenants/{publicId}/activate-plan` through the rate-limits paragraph (stop before `### POST /instances`).
2. Paste that entire block into Framework `docs/integration/waapi-api-contract.md` immediately after the tokens section (before `### POST /instances`).
3. Keep status as **Implementado** (api is source of truth). Under that status blockquote add:

> **Consumidor Framework:** `LebytekApiClient::activatePlan` → `AutorizarOrdenMembresiaUseCase` (kill switch `MKT_MEMBERSHIP_AUTHORIZE_ENABLED`).  
> Cliente envía `Idempotency-Key` nuevo en cada POST; reintento admin tras activación exitosa → api **200** + `token: null` (semántica), no cache HTTP.  
> Framework **nunca** envía `planSlug=demo` (aunque el FormRequest api lo acepte); authorize solo `starter` \| `business` \| `empresa`.  
> `tokenName` desde Framework: `membresia-{slug}` (default api `cliente-{slug}`).

4. Body/response (201 + token; 200 + `token: null`) and catalog numbers (5000 / 80000) must match api. If the pasted `planSlug` row lists `demo` as accepted, **keep** it as api validation note and ensure the Consumidor blockquote above states the Framework never-send rule (do not invent a second conflicting table).

- [ ] **Step 3: Ops note in implementation guide**

In `docs/integration/lebytek-implementation-real.md`, under section `## 2. Variables .env`, append after the LEBYTEK_API_* block:

```env
# Membresías (transferencia → admin authorize)
MKT_BANK_NAME=
MKT_BANK_BENEFICIARY=
MKT_BANK_CLABE=
MKT_BANK_ACCOUNT=
MKT_BANK_PROOF_GUIDE=
MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS=
MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false
```

Add a short subsection titled **"Autorizar membresía (activate-plan)"**:

```markdown
## Autorizar membresía (activate-plan)

Flujo: CRUD `mkt_ordenes` → **Autorizar pago** → `AutorizarOrdenMembresiaUseCase` →
`LebytekApiClient::activatePlan($tenantPublicId, $payload)`.

Companion api: WhatsApiLebytek `POST /tenants/{publicId}/activate-plan` (catálogo `config/plans.php`).
VPS smoke / `meta`: ver api plan `2026-07-14-plan-activation-closure.md` Task 4.

| Campo Framework → api | Fuente |
|-----------------------|--------|
| `planSlug` | `dom_mkt_ordenes.paquete_slug` (`starter`\|`business`\|`empresa`; nunca `demo`) |
| `billingCycle` | `ciclo` (`monthly`\|`annual`) |
| `orderExternalRef` | `public_id` |
| `tokenName` | `membresia-{slug}` |
| `messagesMonthlyLimit` | solo si slug `empresa` y snapshot no null |

**Reglas ops:**

1. Dejar `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` hasta **sequence steps 2–4** (api VPS smoke + this plan Task 2 deployed + Framework VPS bank/env).
2. Orden debe tener `api_tenant_public_id` (desde lead demo o edición CRUD).
3. Email #3 envía el Bearer **solo** si api devolvió `token` no vacío; replay 200 con `token: null` marca `paid` sin reenviar correo.
4. Nunca autorizar órdenes con `paquete_slug=demo` (pre-flight Task 2).
5. No usar `PATCH /tenants/{id}` para cobro — deja tokens demo válidos.
6. No merge Feature → `main` sin orden explícita.
7. Secuencia canónica: api smoke → hardening 1–7 → VPS Framework → flag (Task 8).
```

- [ ] **Step 4: Commit (suggested; only if user approved)**

```bash
git add docs/integration/waapi-api-contract.md docs/integration/lebytek-implementation-real.md
git commit -m "$(cat <<'EOF'
docs(integration): mirror activate-plan contract for membership authorize

Keep Framework docs aligned with api so ops does not use bare PATCH for paid unlock.
EOF
)"
```

---

### Task 6: Checkout controller contract test (substitutes missing Kernel HTTP Feature)

**Files:**
- Create: `tests/Marketing/CompraControllerContractTest.php`

**Interfaces:**
- Consumes: source of `app/Presentation/Controllers/Publico/CompraController.php`
- Produces: regression guard for CSRF, session rate-limit (10/h), purchasable slugs, transfer redirect shape

Framework has no Laravel-style HTTP Kernel Feature harness; this matches existing `RoutesWiringTest` style while covering the security rules from the spec.

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

test('CompraController enforces CSRF, rate limit, and purchasable slugs', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/CompraController.php');

    assert_true(str_contains($src, 'verifyCsrf'), 'POST checkout must verify CSRF');
    assert_true(str_contains($src, "compra_posts"), 'rate-limit session key');
    assert_true(str_contains($src, '>= 10'), 'max 10 posts per hour window');
    assert_true(str_contains($src, "3600"), '1h window');
    assert_true(str_contains($src, "'starter'"), 'starter purchasable');
    assert_true(str_contains($src, "'business'"), 'business purchasable');
    assert_true(! str_contains($src, "'empresa'"), 'enterprise must not be in PURCHASABLE_SLUGS list');
    assert_true(str_contains($src, '/comprar/orden/'), 'redirect to transfer view by public_id');
    assert_true(str_contains($src, '/transferencia'), 'transfer path suffix');
});

test('compra routes wire CompraController submit and transferencia', function (): void {
    $routes = (string) file_get_contents(ROOT_PATH.'/routes/marketing.php');
    assert_true(str_contains($routes, "CompraController"), 'controller bound');
    assert_true(str_contains($routes, '/comprar/{slug}'), 'checkout path');
    assert_true(str_contains($routes, '/comprar/orden/{publicId}/transferencia'), 'transfer path');
});
```

- [ ] **Step 2: Run — expect PASS**

```bash
php tests/run.php Marketing/CompraControllerContractTest
```

Expected: PASS (guards already-shipped controller; fails only if someone removes rate-limit/CSRF).

- [ ] **Step 3: Commit (suggested; only if user approved)**

```bash
git add tests/Marketing/CompraControllerContractTest.php
git commit -m "$(cat <<'EOF'
test(marketing): lock checkout CSRF and rate-limit contracts

No Kernel HTTP harness; source contract covers the security rules from the purchase spec.
EOF
)"
```

---

### Task 7: Update design spec status + full Marketing suite

**Files:**
- Modify: `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md`

- [ ] **Step 1: Run full Marketing suite**

```bash
php tests/run.php Marketing
```

Expected: all Marketing tests PASS (including Integration only if you also run `php tests/run.php Integration` for `LebytekApiClientTest` — recommended once):

```bash
php tests/run.php Integration/LebytekApiClientTest
```

- [ ] **Step 2: Update gap table in the design spec**

In `docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md`:

1. Set header status to note hardening complete (code) / ops still pending.
2. In **Partial / known gaps**, mark as Done (or move to Done table):
   - WhatsApp admin deep-link
   - Schema seed vs migration (`dom_mkt_ordenes` + commercial prices)
   - Framework contract mirror `activate-plan`
   - Dedicated plan checklist (this file)
   - HTTP Feature substitute (`CompraControllerContractTest`)
3. Keep **Not done (ops / next)** as human checklist synced with Task 8 + api closure Task 4:
   - Api VPS `meta` + activate-plan smoke (201 / semantic 200) first
   - Framework VPS migration + `MKT_BANK_*` + purchase alert numbers
   - `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` until api smoke **and** Task 2 deployed; then enable
   - Smoke E2E landing → order → transfer → authorize → email #3
   - No merge to `main` without explicit order
4. In **Remaining follow-ups**, remove items closed by Tasks 1–6; leave only ops smoke.
5. Under Partial gaps, strike “Dedicated plan checklist / No plan was written” — this file is the checklist.
6. Under Cross-repo contract, note Framework mirror done after Task 5; api remains source of truth.

- [ ] **Step 3: Commit (suggested; only if user approved)**

```bash
git add docs/superpowers/specs/2026-07-14-manual-membership-purchase-design.md
git commit -m "$(cat <<'EOF'
docs: mark membership purchase hardening gaps closed in design spec

Ops enablement of authorize remains a human checklist on VPS.
EOF
)"
```

---

### Task 8: Human ops checklist (no agent deploy)

**Files:** none (runbook only)

Do **not** SSH or edit production `.env` from the agent. Operator confirms — **order matters** (same as Cross-repo sequence steps 2→5):

1. **Sequence step 2 — Api first (WhatsApiLebytek closure Task 4):** VPS has `meta` on `core_tenants`; platform smoke `POST …/activate-plan` → **201** + token; semantic replay (new Idempotency-Key, same `orderExternalRef`) → **200** + `token: null`; old demo token 401; same Green instance.
2. **Sequence step 3 — code:** this plan Tasks 1–7 already merged to the deploy branch (especially Task 2: null-token + refuse demo).
3. **Sequence step 4 — Framework VPS** (`feature/backoffice-api-integration`): apply membership + permission repair migrations if pending; fill `MKT_BANK_*`, `MKT_PURCHASE_ALERT_WHATSAPP_NUMBERS` (or fallback `MKT_ALERT_WHATSAPP_NUMBERS`).
4. Keep `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=false` until sequence steps 2–4 are all true. Api closure Task 4 alone is **not** enough to flip the flag.
5. **Sequence step 5:** set `MKT_MEMBERSHIP_AUTHORIZE_ENABLED=true`.
6. E2E: `/?compras=1#paquetes` → Comprar Starter → transfer view → WhatsApp alert opens `/admin/crud/mkt_ordenes/{id}` → associate `api_tenant_public_id` if needed → Autorizar → email #3 with new token; demo instance unchanged (no re-QR). Optional: re-click Autorizar on already-activated order must mark `paid` without stuck error (semantic 200 + `token: null`, no second email #3).
7. Confirm Enterprise CTA remains **Contactar** → `#demo`.
8. **Do not** merge Feature → `main` unless the user explicitly orders it.

No commit for this task.

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|------------------|------|
| WhatsApp admin URL `/admin/crud/...` | Task 1 |
| `awaiting_review` reserved / authorize from `pending_transfer` | Already shipped; no new UI (out of hardening scope) |
| Schema `marketing.sql` + orders + commercial prices (2199/4499, limits 5000/80000) | Task 4 — must match api `config/plans.php` |
| Mirror activate-plan (**Implementado**) in Framework `docs/integration` | Task 5 |
| Optional checkout Feature-style coverage | Task 6 (contract test) |
| Ops gate + smoke after api VPS; flag only on Task 8 after sequence steps 2–4 | Task 8 |
| Authorize + email #3 + activatePlan | Already shipped; hardened token-null + refuse `demo` in Task 2 |
| Api semantic 200 / Framework retry with new Idempotency-Key | Task 2 (aligned with api contract) |
| Unlock slugs never `demo`; limits SoT on api | Global constraints + Cross-repo invariants + Task 2 pre-flight |
| Permission `marketing.ordenes` seed correctness | Task 3 |
| Query gate / Ver paquetes / Enterprise Contactar | Already shipped; not reimplemented |
| Dedicated plan checklist exists | This file (design gap “no plan written” closed in Task 7) |

### Placeholder scan

No TBD / “implement later” steps. Each code task includes full snippets and exact commands.

### Type consistency

- `activatePlan(string $tenantPublicId, array $payload): array` unchanged.
- Admin URL pattern aligned with `/admin/crud/mkt_leads` used elsewhere.
- Permission slug remains `marketing.ordenes` (not `mkt_ordenes.autorizar`).
- Field names stay camelCase on the wire (`planSlug`, `orderExternalRef`, `messagesMonthlyLimit`) matching api FormRequest / contract.
- HTTP **200** and **201** both succeed in `LebytekApiClient` (`status < 400`); authorize must not require 201-only.

---

## Execution handoff

Hardening plan — **ready to execute** on `feature/backoffice-api-integration` (do not reimplement Create/Authorize core).

**Order relative to api:**

1. Prefer api closure Tasks 1–3 locally first (optional in parallel); api **closure Task 4 VPS smoke** must precede enabling the flag.
2. Execute **this plan Tasks 1–7** (Task 2 is the cross-repo blocker).
3. Human **Task 8** only after sequence steps 2–4.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task + review between tasks  
2. **Inline Execution** — executing-plans in this session with checkpoints  

Which approach?
