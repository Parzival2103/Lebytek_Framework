# Landing Experiments + First-Party Metrics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the binary `LANDING_VARIANT` selector with a sticky weighted experiment assigner over N code manifests (starting with shells v1/v2), collect first-party scroll/time/CTA/lead metrics, compute a hybrid score with human-confirmed weight proposals, and expose an ops Accept/Reject UI.

**Architecture:** Manifests in `config/marketing/landing_variants.php` define shell, sections, SEO, and `weight_default`. Runtime weights live in `dom_mkt_variant_weights`. `LandingExperimentAssigner` sticky-assigns from an `AssignInput` DTO (no Kernel `Request` in Application), returns cookie specs for Presentation to apply. Preview (`?variant=` / `?landing=`) never writes `lb_var`; sets short-lived HttpOnly `lb_preview` so collect cannot spoof `is_preview`. Merge + shared `LandingSectionRenderer` (controller → `$sectionsHtml`; views fallback §P). Client `landing_metrics.js` posts form-urlencoded events to CSRF-exempt `POST /marketing/collect` hardened with UUID v4 / slug allowlists, cookie-preferred `visitor_id`+`variant_slug`, `meta` allowlist, `sys_kv` rate limit; heartbeats update sessions only. `lead_submit` is **server-only** after successful capture. CLI scores into at-most-one `pending` proposal; Accept is transactional + optimistic-concurrency + weight-normalized. Retention CLI bounds table growth. Guardrails Anti-deuda §§A–Y.

**Tech Stack:** PHP 8.1+ (strict types), Lebytek Onion (`app/` layers), PDO marketing repos, vanilla JS (`sendBeacon` / `fetch` keepalive), microtest (`php tests/run.php Marketing`), Bootstrap admin views.

**Spec:** `docs/superpowers/specs/2026-07-15-landing-experiments-metrics-design.md`

**Branch:** `feature/backoffice-api-integration` (no merge to `main` unless the user explicitly orders it).

## Global Constraints

- Work only on `feature/backoffice-api-integration` (or a short-lived branch cut from it).
- **Do not** merge `feature/backoffice-api-integration` → `main` unless the user explicitly orders it.
- Do not run VPS deploy, SSH, or edit production `.env` from this plan.
- Telemetry is first-party only — no GA / Meta / third-party pixels.
- `LANDING_VARIANT` must **not** select traffic per request; it is only a **bootstrap seed hint** for `weight_default` when seeding weight rows.
- Cookie `lb_var` TTL = **30 days**; `lb_vid` TTL ≥ 30 days; both `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS. JS **never** reads document.cookie for metrics (uses `window.__LB_METRICS__` only).
- Preview/force (`?variant=` or `?landing=`) sessions and their events are **excluded** from ranking/score (`is_preview=1`).
- Score defaults: window **14 days**, `w_eng=0.35`, `w_conv=0.65`, min sessions **N=50** before kill/zero proposals.
- Hybrid score: `score = w_eng * engagement + w_conv * conversion` where `conversion = leads / sessions`.
- Eligible arms: manifesto `status=active` **and** runtime `weight > 0`. Paused/archived are **not** sticky-eligible; preview may still force any known slug.
- Fallback if all weights 0: slug `v2` if active in manifesto; else first `active` by file order.
- Collect: no PII in payload; rate-limited **sin** PHP Session (`sys_kv`, not `CompraController::allowPost`); silent client failures; no CSRF (beacon-safe, form-urlencoded); validate slug + UUIDs; prefer cookie `lb_vid`; `is_preview` from cookie `lb_preview` (body flag ignored).
- Preview **never** writes sticky `lb_var` (see Anti-deuda §B). Non-preview assign **clears** `lb_preview`.
- Proposal lifecycle: at most one `pending`; Accept checks weight snapshot; normalize weights to sum 1.0.
- Retention: purge metrics older than `retention_days` (default 90) — Task 10.
- Section catalog ids (manifest): `hero`, `trust`, `features`, `pricing`, `testimonios`, `faq`, `lead_form`. Map to existing `data-reveal-id` values where they differ (`testimonios`→`testimonials`, `lead_form`→`cta`). **v1 partials must gain `data-section="<catalog id>"`** (hard gate Task 5/6).
- `LandingVariantSelectionTest` today **locks the old env selector** — Task 5 must rewrite it in the same change that removes `EnvLoader::get('LANDING_VARIANT')` from the controller or Marketing suite stays red.
- Preserve `?compras=` and other orthogonal LandingController query flags when rewriting.
- Commits in steps are **suggested**; only create git commits when the user asks.
- Tests: `php tests/run.php Marketing`. Prefer unit tests with in-memory fakes; source-contract tests where Kernel HTTP harness is unavailable.
- Guardrails Anti-deuda §§A–Y apply to every task.
- `Request::isSecure()` **does not exist** — resolve HTTPS only in Presentation (see §X).
- Heartbeat **must not** insert a row per tick into `dom_mkt_landing_events` (see §Q).
- Views must keep BC for `ViewHelper::render('publico/landing_v2', ['bloques'=>…])` used by `LandingV2ViewTest` (see §P).

## Scope note

This is one vertical (assign → render → collect → score → ops). Do **not** split into separate plans unless a subsystem is deferred; Tasks 1–11 are ordered dependencies. Out of scope (per spec): CMS copy editor, auto-bandit, multi-URL variants, purchase signal in score, visual section builder, cookie-consent CMP, bot/crawler filtering, CMP legal banner.

**Review note (2026-07-15):** Plan reforzado contra el código actual (`LandingController` env selector, `LandingVariantSelectionTest` que lo bloquea, `sys_kv` sin writers, v1 sin markers DOM, Session rate-limit en Compra). Anti-deuda ampliado a §§A–O.

**Review note 2 (2026-07-15 — robustez / deuda residual):** Contraste adicional vs repo: `LandingV2ViewTest` renderiza vistas con `bloques` (rompe si views solo echo `$sectionsHtml`); `Request::isSecure()` **no existe**; heartbeat como event row → crecimiento explosivo; body `variant_slug` spoofeable; Accept no atómico; `meta` sin allowlist; migración huérfana `20260714210000_mkt_landing_copy_seo.sql` fuera de `config/modules/marketing.php`. Anti-deuda ampliado a §§A–Y.

## Anti-deuda técnica (guardrails — obligatorios)

Estos puntos **corrigiendo** o **restringiendo** el diseño base; si un implementer los ignore, nace deuda estructural (scores corruptos, capas rotas, ops ciego, tablas que crecen sin freno).

### A. Capas Onion — sin side-effects ni I/O en Domain/Application “pura”

| Regla | Consecuencia en este plan |
|-------|---------------------------|
| `setcookie()` / headers **solo** en Presentation | `LandingExperimentAssigner` **devuelve** cookie specs (`AssignedLandingVariant::$cookies: list<CookieSpec>`). El `LandingController` las aplica. **Prohibido** `setcookie()` en Application. |
| Domain sin filesystem / `ROOT_PATH` | `LandingVariantRegistry` recibe el array ya cargado. Factory `fromConfig()` vive en Application o binding de `config/container.php` (`require` del config). **No** `require` dentro de Domain. |
| Repos PDO solo en Infrastructure | Agregaciones SQL de score viven en `PdoLandingMetricsRepository`; el use case orquesta, no escribe SQL. |

### B. Preview no debe contaminar sticky ni score (bug crítico del diseño base)

El plan base decía “force + set `lb_var`”. Eso es **incorrecto**: tras `?variant=v2`, la siguiente visita sin query reusaría `v2` **sin** `is_preview=1` y ensuciaría rankings.

**Regla canónica:**

1. `?variant=` / `?landing=` → render force + `isPreview=true` en esa response.
2. **No escribir** cookie `lb_var` en preview (dejar sticky previo intacto).
3. Collector marca `is_preview=1` desde `window.__LB_METRICS__.isPreview` (server-injected), no solo desde body spoofeable.
4. Score / `aggregateForScore` **siempre** `WHERE is_preview = 0`.
5. Test obligatorio: preview force **no** encola Set-Cookie `lb_var`.

### C. Colector abierto (CSRF-exempt) — endurecer sin CSRF

Sin CSRF el endpoint es público; el plan base solo menciona rate-limit. Añadir **todas**:

1. Allowlist `event_type` + `variant_slug` ∈ registry active\|paused (reject unknown slug).
2. `visitor_id` / `session_public_id` UUID v4 strict (`/^[0-9a-f-]{36}$/i`).
3. Body ≤ `collect_max_body_bytes`; `meta` JSON ≤ 2KB; strip unknown keys.
4. Preferir `lb_vid` cookie sobre body si ambos existen y difieren (anti-spoof).
5. Rate limit **no** basado en `Session` (sendBeacon a menudo sin cookie de sesión PHP). Usar clave `sys_kv` / file lock: `land_collect:{visitor_id}` o IP+visitor, ventana 1h, `collect_max_per_hour`.
6. Opcional v1.1 (documentar, implementar si barato): exigir `Origin`/`Referer` host ∈ `parse_url(APP_URL, PHP_URL_HOST)`.
7. Respuestas always-light (`200 {"ok":true}` / `422`/`429`); nunca stack traces.

### D. Persistencia, migraciones y retención

1. DDL: `ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS` (ver `database/migrations/README.md`). **No** `ALTER` bare que rompe re-run.
2. Tras insertar `marketing.experimentos`, **grant** a rol `administrador` (`auth_roles_permisos`) igual que `marketing.ordenes` — si no, menú invisible y “feature muerta”.
3. Índices de cleanup desde día 1: `idx_mkt_land_evt_created (created_at)`, `idx_mkt_land_sess_seen (last_seen_at)`.
4. **Retention policy** (Task 11): purge events/sessions > `retention_days` (default **90**) vía CLI; score solo mira `score_window_days` (14) pero la tabla no puede crecer infinito.
5. FK `landing_events.session_id` → sessions: **ON DELETE SET NULL** o sin FK (beacon race). Preferir sin FK + `session_id` nullable documentado; no deuda de migraciones cruzadas.

### E. Score / proposals — no spam, no accept stale

1. **Una sola** proposal `pending` a la vez: al crear, `UPDATE … SET status='superseded'` (o reject) las pending previas **o** no insertar si existe pending idéntica (`suggested_weights` iguales). Status enum efectivo: `pending|accepted|rejected|superseded`.
2. Accept: exigir `proposal.status === 'pending'`; verificar snapshot `payload.current_weights` ≈ weights actuales (tol `1e-4`); si drift → 409 / flash “propuesta obsoleta, rechaza y re-calcula”.
3. Al Accept: **normalizar** `suggested_weights` a suma **1.0** (o rechazar si suma ≤ 0); floor `min_explore_weight` solo para slugs `active` en manifesto.
4. Compute **nunca** `upsert` weights. Kill (`weight→0`) solo con `sessions ≥ min_sessions` y gap material vs 2º.
5. Atribución leads: contar leads con `landing_variant` + `created_at` en ventana **y** `is_preview` no aplica a leads; preferir join opcional por `visitor_id` cuando no null para evitar double-count futuro — documentar fórmula en use case PHPDoc.

### F. Seed pesos — una sola fuente de verdad

Conflicto del plan base: manifesto `weight_default: 0.5` **y** `seed_weight_defaults()` 0.7/0.3 desde `LANDING_VARIANT`.

**Canónico:**

- `seedMissing()` usa **solo** `landing_experiments.seed_weight_defaults()`.
- `weight_default` en manifesto queda para documentación / futuras armas nuevas sin bias env; al añadir slug nuevo sin fila BD, `seedMissing` usa `weight_default` del manifesto para **ese** slug si no aparece en el seed map, else seed map.
- `LANDING_VARIANT` **no** se lee fuera de `seed_weight_defaults()`.

### G. Views — no duplicar mapas de secciones

`landing.php` y `landing_v2.php` **no** copian el mismo `$map` gigante. Extraer `app/Presentation/Views/publico/partials/_section_loop.php` **o** helper PHP `LandingSectionRenderer` en Application/Presentation que recibe `(shell, sections, bloques, …)` y resuelve prefijo de partials. Un solo sitio al añadir `catalog` id.

### H. Tests — preferir comportamiento sobre source-contract

Source-contract (`str_contains` en `.php`) solo para wiring de rutas/CSRF/assets. Assigner, merge, collect, score, accept: **fakes in-memory** + asserts de estado. No dejar `...` en tests del plan.

### I. Explicitamente diferido (no implementar “por si acaso”)

- Multi-URL `/v/{slug}`, sitemap splits, bandit auto, CMS editor, Meta/GA, purchase-in-score, visual builder.
- Cloaking SEO / `Vary: Cookie` genérico (limitación aceptada del spec).
- Analytics product dashboard beyond ops Accept/Reject.
- Banner de consentimiento de cookies / CMP (legal/product). **Documentar** en Task 11 que `lb_vid`/`lb_var`/`lb_preview` son cookies funcionales first-party de experimento; no sustituyen un aviso de privacidad si negocio lo exige más adelante.
- Bot / crawler UA filtering (Anti-deuda §Y) — Known bias; human Accept + `min_sessions` mitigate.

### J. Application sin Kernel HTTP — `AssignInput` DTO

Hoy **ningún** Marketing use case toma `Lebytek\Framework\Kernel\Http\Request`. Meter `assign(Request $request)` acopla Application al Kernel y facilita side-effects futuros.

**Canónico:**

```php
final class AssignInput
{
    /**
     * @param array<string,string> $cookies  name → value (solo lb_*)
     */
    public function __construct(
        public readonly string $forceVariant, // query variant || landing, ya lowercased; '' si none
        public readonly array $cookies,
        public readonly bool $isHttps,
    ) {}
}
```

`LandingController` construye `AssignInput` desde `$request->query()` / `$request->cookie()`. Assigner **no** importa `Request`.

### K. `is_preview` no spoofeable — cookie `lb_preview`

Anti-deuda §B.3 decía “server-injected en `__LB_METRICS__`”, pero el collect lee el body: un atacante puede `is_preview=0` en sesiones preview (ensucia score) o `=1` en tráfico real (esconde fraude).

**Canónico:**

1. Preview assign → queue CookieSpec `lb_preview=<slug>` HttpOnly, `SameSite=Lax`, TTL corto (**3600s** / Max-Age=3600), path `/`.
2. Assign **no-preview** → queue CookieSpec que **borra** `lb_preview` (`expires` pasado / Max-Age=0).
3. Collect: `is_preview = ($cookieLbPreview !== '')`. **Ignorar** body `is_preview` para persistencia.
4. `__LB_METRICS__.isPreview` sigue para UX/debug JS, no como fuente de verdad server-side.
5. Tests: collect con body `is_preview=0` + cookie `lb_preview=v2` → stored `is_preview=1`; body `=1` sin cookie → stored `0`.

### L. Rate limit `sys_kv` — protocolo concreto (greenfield)

`sys_kv` existe en schema (`clave` PK VARCHAR(100), `valor` TEXT) pero **no hay** writer PHP hoy. `CompraController::allowPost` usa Session — **prohibido** copiarlo (beacons sin sesión PHP).

**Canónico `SysKvCollectRateLimiter` (Infrastructure):**

- Key: `land_collect:{visitor_id}` (≤100 chars: `land_collect:` + UUID = 49).
- Valor JSON: `{"count":int,"window_start":unix}`.
- Ventana: 3600s; max: `collect_max_per_hour` (default 120).
- Concurrencia: aceptamos overcount leve; **no** inventar columna TTL.
- Upsert pattern (una round-trip):

```sql
INSERT INTO sys_kv (clave, valor, updated_at)
VALUES (:k, :v, NOW())
ON DUPLICATE KEY UPDATE
  valor = VALUES(valor),
  updated_at = NOW()
```

Implementación: READ row → decide allow/deny + new JSON → UPSERT. Race = posible +1 extra count; OK for abuse bound.

- Interface: `CollectRateLimiterInterface` en `app/Domain/Marketing/Contracts/`.
- Fake in-memory en tests.
- **No** poner implementación con PDO bajo `app/Application/`.
- Purge de keys `land_collect:*` **no** requerido en v1 (valores tiny; opcional en retention CLI later). Documentar like deferred in §I.

### M. Engagement sesgado v1 — markers DOM hard gate

Partials **v2** ya tienen `data-reveal-id`. Partials **v1** (`publico/partials/_hero.php` etc.) **no** tienen `data-section` ni `data-reveal-id`. Sin eso, IntersectionObserver / exit_section / sections_seen favorecen v2 y corrompen el score híbrido.

**Hard gate (Task 5 o 6, same PR que collector):**

- Cada root `<section>` de partials v1 recibe `data-section="<catalog_id>"` (`hero`, `trust`, …).
- Test source-contract: cada archivo `_hero|_trust|_features|_pricing|_testimonios|_faq|_lead_form` en `partials/` (no v2) contiene `data-section=`.
- JS observa `[data-reveal-id],[data-section]`.

### N. `lead_submit` una sola fuente + leads en preview

Doble emisión (JS `lead_submit` + LeadController insert) infla engagement/conversion.

**Canónico:**

1. JS **no** emite `lead_submit` (solo `cta_click` en botones).
2. Tras `CapturarLeadUseCase` success, `LeadController` inserta evento `lead_submit` vía metrics repo.
3. Leads capturados con cookie `lb_preview` presente: persistir lead normalmente pero **no** insertar evento de score **o** marcar lead excluyéndolo del count. Preferido v1: si `lb_preview` → `insertEvent(..., is_preview=1)` y `aggregateForScore` leads join **only** `is_preview=0` events is wrong for leads table…

Leads viven en `dom_mkt_leads`, not events. Safer:

- Column optional deferred: skip.
- Score leads SQL: `WHERE landing_variant=:slug AND created_at IN window AND deleted=0` **AND** visitor had **no** `is_preview=1` session in window — too heavy.
- **Pragmatic v1:** if request has `lb_preview` cookie at capture time, set `landing_variant` still but store `visitor_id` and document that ops should not Accept proposals while heavy QA; plus server `lead_submit` event with `is_preview=1`. Aggregate leads for score from `dom_mkt_leads` **excluding** leads whose `visitor_id` appears in any `dom_mkt_landing_sessions` with `is_preview=1` in the same window (LEFT JOIN anti-join). If too expensive for v1, simpler fallback: **do not attribute** `landing_variant` when `lb_preview` present (leave NULL) — conversion untouched. **Choose fallback B for v1** (simpler, zero join debt): preview lead posts → `landing_variant=NULL`, no `lead_submit` event / event with preview only for debug.

### O. Capas I/O — puertos y renderer

| Pieza | Capa correcta |
|-------|----------------|
| `CollectRateLimiterInterface` | Domain Contracts |
| `SysKvCollectRateLimiter` | Infrastructure |
| `AssignInput`, `CookieSpec`, `AssignedLandingVariant` | Application DTO |
| `LandingSectionRenderer` | Presentation; controller pre-renders `$sectionsHtml` — **no** View DI |
| PDO repos | Infrastructure + `Connection::getInstance()` (mismo patrón que `PdoLeadRepository`; no PDO-inject solo aquí) |
| Views | echo `$sectionsHtml`; no reimplementar `$map` |

Dual CSRF en admin Accept/Reject: middleware en route **y** `$this->verifyCsrf($request)` como `MarketingOrdenesController` (precedente deuda si solo uno).

Register migration en `config/modules/marketing.php` `migraciones[]` **y** `permisos[]` — olvidar el array = installer skip (precedente: repair `mkt_ordenes_permission_slug`).

### P. Views BC — no romper `LandingV2ViewTest` ni render directo

Hoy `tests/Marketing/LandingV2ViewTest.php` hace `ViewHelper::render('publico/landing_v2', ['bloques' => …], 'publico/layout_v2')` y espera HTML de todas las secciones. Si `landing.php` / `landing_v2.php` **solo** hacen `echo $sectionsHtml`, el suite Marketing queda rojo y se crea deuda de “tests que solo pasan vía controller”.

**Canónico (elige uno; default A):**

**A (preferido):** views con fallback:

```php
if (is_string($sectionsHtml ?? null) && $sectionsHtml !== '') {
    echo $sectionsHtml;
} else {
    $shell = $shell ?? 'v2'; // landing.php → 'v1'
    $sections = is_array($sections ?? null) && $sections !== []
        ? $sections
        : ['hero','trust','features','pricing','testimonios','faq','lead_form'];
    echo (new \App\Presentation\Marketing\LandingSectionRenderer())->render($shell, $sections, [
        'bloques' => $bloques ?? [],
        'paquetes' => $paquetes ?? [],
        'comprasHabilitadas' => $comprasHabilitadas ?? false,
        'landingVariant' => $landingVariant ?? '',
        'visitorId' => $visitorId ?? '',
    ]);
}
```

**B:** reescribir todos los tests de vista para construir `$sectionsHtml` vía renderer (más churn; OK si A no encaja).

Partial `_lead_form` sigue leyendo `Session::flashAll()` si no hay `$flashAll` — no pasar flash por el renderer (no es deuda).

### Q. Heartbeat = update sesión, no flood de events

Un heartbeat cada 15s × N visitas = **cientos de miles** de filas/día en `dom_mkt_landing_events` sin valor analítico (el score usa `duration_ms` / scroll en **sessions**).

**Canónico:**

1. Cliente puede seguir enviando `event_type=heartbeat` (o el server lo acepta).
2. Collect use case: para `heartbeat` → `ensureSession` + `updateSessionMetrics` **solo**; **no** `insertEvent`.
3. Igual para `scroll_depth` opcional: v1 puede upsert session `max_scroll_pct` **y** insertar event (pocos buckets 25/50/75/100 — OK). Heartbeat es el riesgo.
4. Test: N heartbeats → `count($fake->events)` no crece; `duration_ms` de sesión sí.
5. Config flag documentado: `persist_heartbeat_events` default **false** (no exponer toggle UI).

### R. `variant_slug` no spoofeable (como `visitor_id` / `is_preview`)

Un cliente puede POST `variant_slug=v2` mientras cookie sticky es `v1` → infla métricas de la otra arma.

**Canónico en collect:**

1. Si cookie `lb_preview` no vacía y registry `get(preview)≠null` → `variant_slug = preview`.
2. Else si cookie `lb_var` válida (registry known) → `variant_slug = lb_var`.
3. Else usar body slug **solo si** registry `get()≠null` (visitantes sin cookie aún — edge beacon race).
4. Tests: body `v2` + cookie `lb_var=v1` → stored `v1`.

### S. Accept atómico + transacción (no pesos huérfanos)

Dos admins / doble submit: Accept debe ser idempotente-safe.

**Canónico:**

```php
$pdo->beginTransaction();
// 1) UPDATE dom_mkt_variant_proposals SET status='accepted', … WHERE id=? AND status='pending'
//    rowCount === 0 → rollback + throw StaleOrResolvedProposal
// 2) upsert weights normalizados
// 3) commit
```

Stale weight snapshot check **antes** del UPDATE status. Reject: `UPDATE … WHERE id=? AND status='pending'` igual (no re-reject accepted).

### T. `meta` allowlist por `event_type`

Sin allowlist, el endpoint CSRF-exempt se convierte en dump JSON arbitrario (PII accidental, bloat).

**Canónico (strip unknown keys; max 2KB encoded):**

| event_type | meta keys permitidas |
|------------|----------------------|
| `pageview` | _(ninguna / vacía)_ |
| `scroll_depth` | `pct` (int 25\|50\|75\|100) |
| `section_view` | `section` (string ≤60, catalog o reveal id) |
| `cta_click` | `cta` (string ≤40: `demo\|pricing\|nav\|…`) |
| `exit` | `max_scroll_pct`, `exit_section` |
| `heartbeat` | _(ignorado — no event row)_ |

### U. Hygiene del array `migraciones[]`

Al tocar `config/modules/marketing.php`:

1. **Append** la migración nueva; no reordenar ni borrar entradas previas.
2. Hoy existe en disco `database/migrations/20260714210000_mkt_landing_copy_seo.sql` pero **no** está en `migraciones[]` — debt latente del installer. En Task 2, **registrar también** ese archivo (antes de la de experimentos) para que entornos frescos no divergjan del VPS que ya la aplicó a mano / por otro path.
3. Schema mirror en `marketing.sql` sigue siendo la fuente greenfield; migraciones son deltas.

### V. UUID estricta (anti-basura index)

`/^[0-9a-f-]{36}$/i` acepta guiones mal puestos. **Canónico:**

```php
'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
```

Misma regex para `visitor_id` y `session_public_id`. Generación server-side: `Uuid::v4()` o equivalente ya usado en el repo; si no hay helper, función local en Application (no Domain time/random leaking if avoidable — OK in Application).

### W. `seedMissing` sin write amplification oculta

`seedMissing` en **cada** `assign()` está OK si solo INSERT cuando falta fila (SELECT all + insert missing). **Prohibido** `DELETE`+reseed o `upsert` de defaults sobre pesos ya editados por ops. Test: tras Accept weights, siguiente assign no regenera defaults.

### X. HTTPS / `Secure` cookie — sin `Request::isSecure()`

`Lebytek\Framework\Kernel\Http\Request` **no** expone `isSecure()`. **Canónico en Presentation only:**

```php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || str_starts_with((string) EnvLoader::get('APP_URL', ''), 'https');
```

Pasar `$secure` a `AssignInput` (ya tiene `isHttps`) — el controller lo calcula; Assigner solo copia el flag a CookieSpec.secure si se desea, o controller override al `setcookie`. **No** añadir método al Kernel solo por esta feature (out of scope / no bloquear en `src/` salvo producto-wide).

### Y. Bias bots / crawlers (limitación aceptada, no “arreglar” en v1)

Crawlers reciben sticky `lb_var` y generan sessions con engagement basura y 0 leads. `min_sessions` + humano Accept mitigan. **No** implementar UA denylist en v1 (fácil de equivocar). Documentar en ops UI un hint: “No Accept con sample bajo o tráfico anómalo”. Diferido explícito en §I.

---

## File Structure

| Path | Role |
|------|------|
| `config/marketing/landing_variants.php` | Code manifests (v1, v2) + section catalog helpers |
| `config/marketing/landing_experiments.php` | Score weights, window, cookie TTL, rate limits, seed bias from `LANDING_VARIANT`, `preview_cookie_ttl_seconds` |
| `config/app.php` / `.env.example` | Deprecate productive `LANDING_VARIANT` docs; keep seed hint |
| `database/migrations/20260715120000_mkt_landing_experiments.sql` | Tables + lead columns + permission/menu |
| `database/schema/modules/marketing.sql` | Mirror for fresh installs |
| `config/modules/marketing.php` | Register migration + `marketing.experimentos` permiso |
| `app/Domain/Marketing/Contracts/*Experiment*` | Repository + `CollectRateLimiterInterface` |
| `app/Domain/Marketing/LandingVariantRegistry.php` | Registry **puro** (array inyectado; sin `require`) |
| `app/Application/Marketing/AssignInput.php` | DTO force + cookies (no Kernel Request) |
| `app/Application/Marketing/LandingExperimentAssigner.php` | Sticky weighted assignment; returns cookie specs |
| `app/Application/Marketing/AssignedLandingVariant.php` | DTO slug/shell/preview/visitor + `list<CookieSpec>` |
| `app/Application/Marketing/CookieSpec.php` | name, value, ttlDays **or** maxAgeSeconds, httpOnly, sameSite, secure, path, delete flag |
| `app/Application/Marketing/MergeLandingVariantUseCase.php` | Sections + SEO + shallow copy overrides |
| `app/Presentation/Marketing/LandingSectionRenderer.php` | Unique section→partial map for v1/v2 shells |
| `app/Application/Marketing/CollectLandingMetricsUseCase.php` | Validate + persist events/sessions |
| `app/Infrastructure/Marketing/SysKvCollectRateLimiter.php` | Rate limit via `sys_kv` (implements Domain interface) |
| `app/Application/Marketing/ComputeVariantScoresUseCase.php` | Hybrid score + create/supersede proposals |
| `app/Application/Marketing/AcceptVariantProposalUseCase.php` | Optimistic apply + normalize weights |
| `scripts/purge-landing-metrics.php` | Retention purge (default 90d) |
| `app/Infrastructure/Marketing/PdoVariantWeightRepository.php` | Weights CRUD |
| `app/Infrastructure/Marketing/PdoLandingMetricsRepository.php` | Sessions + events |
| `app/Infrastructure/Marketing/PdoVariantProposalRepository.php` | Proposals |
| `app/Presentation/Controllers/Publico/LandingController.php` | AssignInput + assign + merge + render + apply cookies |
| `app/Presentation/Controllers/Publico/LandingMetricsController.php` | `POST /marketing/collect` |
| `app/Presentation/Controllers/Publico/LeadController.php` | Persist `landing_variant` / `visitor_id`; server `lead_submit` |
| `app/Presentation/Controllers/Admin/MarketingExperimentsController.php` | Dashboard + Accept/Reject |
| `app/Presentation/Views/admin/marketing/experiments.php` | Ops UI |
| `app/Presentation/Views/publico/landing.php` / `landing_v2.php` | Echo `$sectionsHtml` only |
| `app/Presentation/Views/publico/layout.php` / `layout_v2.php` | Include `landing_metrics.js` + meta tags from SEO |
| `app/Presentation/Views/publico/partials/_*.php` | **Add** `data-section` on v1 roots |
| `app/Presentation/Views/publico/partials/**/_lead_form.php` | Hidden `landing_variant` / `visitor_id` |
| `public/assets/publico/landing_metrics.js` | First-party collector (**no** `lead_submit`) |
| `scripts/compute-landing-variant-scores.php` | CLI/cron entry |
| `routes/marketing.php` / `routes/marketing_admin.php` | Public collect + admin routes |
| `config/container.php` | DI bindings under marketing vertical guard |
| `tests/Marketing/*` | Assigner, merge, collect, proposal, lead column, schema, v1 markers |

---

### Task 1: Manifest registry + experiment config

**Files:**
- Create: `config/marketing/landing_variants.php`
- Create: `config/marketing/landing_experiments.php`
- Modify: `.env.example` (LANDING_VARIANT comment → seed hint)
- Modify: `config/app.php` (keep key; document as seed hint only)
- Test: `tests/Marketing/LandingVariantsConfigTest.php`

**Interfaces:**
- Consumes: `EnvLoader::get('LANDING_VARIANT', 'v1')` only inside seed bias helper
- Produces:
  - `landing_variants.php` returns `array{catalog: list<string>, variants: array<string, array>}`
  - Each variant: `slug`, `shell` (`v1`\|`v2`), `status`, `sections` (`list<{id,enabled}>`), `copy_overrides`, `seo`, `weight_default`
  - `landing_experiments.php` returns cookie TTL, score weights, window days, min sessions, collect rate limit, `seed_weight_defaults(): array<string,float>`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/LandingVariantsConfigTest.php
declare(strict_types=1);

test('landing_variants.php define catalogo y armas v1/v2 active', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    assert_true(isset($cfg['catalog'], $cfg['variants']), 'estructura base');
    foreach (['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'] as $id) {
        assert_true(in_array($id, $cfg['catalog'], true), "catalog contiene {$id}");
    }
    assert_true(isset($cfg['variants']['v1'], $cfg['variants']['v2']), 'v1 y v2 presentes');
    assert_same('active', $cfg['variants']['v1']['status']);
    assert_same('active', $cfg['variants']['v2']['status']);
    assert_same('v1', $cfg['variants']['v1']['shell']);
    assert_same('v2', $cfg['variants']['v2']['shell']);
    assert_true(isset($cfg['variants']['v1']['seo']['title'], $cfg['variants']['v1']['seo']['description']), 'seo v1');
    assert_true(isset($cfg['variants']['v2']['seo']['title'], $cfg['variants']['v2']['seo']['description']), 'seo v2');
});

test('landing_experiments.php expone defaults de score y seed weights', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    assert_same(30, (int) $cfg['cookie_ttl_days']);
    assert_same(14, (int) $cfg['score_window_days']);
    assert_same(0.35, (float) $cfg['w_eng']);
    assert_same(0.65, (float) $cfg['w_conv']);
    assert_same(50, (int) $cfg['min_sessions']);
    assert_true(is_callable($cfg['seed_weight_defaults'] ?? null) || isset($cfg['seed_weight_defaults']), 'seed helper');
    $seeds = is_callable($cfg['seed_weight_defaults'])
        ? $cfg['seed_weight_defaults']()
        : $cfg['seed_weight_defaults'];
    assert_true(isset($seeds['v1'], $seeds['v2']), 'seeds v1/v2');
    assert_true((float) $seeds['v1'] > 0 && (float) $seeds['v2'] > 0, 'ambos exploratorios > 0');
});

test('.env.example documenta LANDING_VARIANT solo como seed hint', function (): void {
    $env = (string) file_get_contents(ROOT_PATH . '/.env.example');
    assert_true(str_contains($env, 'LANDING_VARIANT'), 'clave presente');
    assert_true(
        str_contains(strtolower($env), 'seed') || str_contains(strtolower($env), 'bootstrap') || str_contains(strtolower($env), 'weight'),
        'comenta que no selecciona trafico'
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing`
Expected: FAIL — missing `config/marketing/landing_variants.php`.

- [ ] **Step 3: Create `config/marketing/landing_variants.php`**

```php
<?php

declare(strict_types=1);

/**
 * Manifiestos de variantes de landing (código). Contrato estable para futura CMS.
 *
 * Section catalog ids → reveal_id actual en markup:
 *   testimonios → testimonials, lead_form → cta (v2); v1 usa data-section.
 */
return [
    'catalog' => ['hero', 'trust', 'features', 'pricing', 'testimonios', 'faq', 'lead_form'],

    'reveal_id_map' => [
        'hero' => 'hero',
        'trust' => 'trust',
        'features' => 'features',
        'pricing' => 'pricing',
        'testimonios' => 'testimonials',
        'faq' => 'faq',
        'lead_form' => 'cta',
    ],

    'variants' => [
        'v1' => [
            'slug' => 'v1',
            'shell' => 'v1',
            'status' => 'active',
            'sections' => [
                ['id' => 'hero', 'enabled' => true],
                ['id' => 'trust', 'enabled' => true],
                ['id' => 'features', 'enabled' => true],
                ['id' => 'pricing', 'enabled' => true],
                ['id' => 'testimonios', 'enabled' => true],
                ['id' => 'faq', 'enabled' => true],
                ['id' => 'lead_form', 'enabled' => true],
            ],
            'copy_overrides' => [],
            'seo' => [
                'title' => 'API WhatsApp Business Lebytek | Automatiza Mensajes y Campañas',
                'description' => 'Automatiza WhatsApp para tu negocio con Lebytek. Envía campañas, notificaciones y respuestas automáticas en minutos. Planes desde $2,199/mes. Demo inmediata.',
            ],
            'weight_default' => 0.5,
        ],
        'v2' => [
            'slug' => 'v2',
            'shell' => 'v2',
            'status' => 'active',
            'sections' => [
                ['id' => 'hero', 'enabled' => true],
                ['id' => 'trust', 'enabled' => true],
                ['id' => 'features', 'enabled' => true],
                ['id' => 'pricing', 'enabled' => true],
                ['id' => 'testimonios', 'enabled' => true],
                ['id' => 'faq', 'enabled' => true],
                ['id' => 'lead_form', 'enabled' => true],
            ],
            'copy_overrides' => [],
            'seo' => [
                'title' => 'WhatsApp Business API Lebytek | Campañas y Automatización',
                'description' => 'Conecta WhatsApp Business en minutos. Campañas, notificaciones y respuestas automáticas. Demo inmediata — Lebytek.',
            ],
            'weight_default' => 0.5,
        ],
    ],
];
```

- [ ] **Step 4: Create `config/marketing/landing_experiments.php`**

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Kernel\EnvLoader;

$bias = strtolower((string) EnvLoader::get('LANDING_VARIANT', 'v1'));

return [
    'cookie_ttl_days' => 30,
    'cookie_vid_name' => 'lb_vid',
    'cookie_var_name' => 'lb_var',
    'cookie_preview_name' => 'lb_preview',
    'preview_cookie_ttl_seconds' => 3600,
    'score_window_days' => 14,
    'w_eng' => 0.35,
    'w_conv' => 0.65,
    'min_sessions' => 50,
    'collect_max_per_hour' => 120,
    'collect_max_body_bytes' => 4096,
    'collect_require_origin' => false,
    'heartbeat_seconds' => 15,
    'fallback_slug' => 'v2',
    'proposal_min_delta' => 0.05,
    'min_explore_weight' => 0.05,
    'retention_days' => 90,
    'persist_heartbeat_events' => false, // Anti-deuda §Q — heartbeat updates session only
    /** @return array<string,float> Bootstrap only — used by seedMissing(); does not select traffic. */
    'seed_weight_defaults' => static function () use ($bias): array {
        if ($bias === 'v2') {
            return ['v1' => 0.3, 'v2' => 0.7];
        }

        return ['v1' => 0.7, 'v2' => 0.3];
    },
];
```

Note: manifesto `weight_default` remains `0.5` as documentation / default for **new** slugs not listed in seed map (see Anti-deuda §F). Do not duplicate conflicting bootstrap logic.
- [ ] **Step 5: Update `.env.example` comment**

Replace the LANDING_VARIANT block with:

```env
# Seed hint for initial landing experiment weights only (bootstrap). Does NOT select traffic per request.
# Runtime selection: weighted sticky assigner (cookies lb_vid / lb_var). See config/marketing/landing_experiments.php
LANDING_VARIANT=v1
```

- [ ] **Step 6: Run tests**

Run: `php tests/run.php Marketing`
Expected: PASS for `LandingVariantsConfigTest` (other Marketing tests still their own).

- [ ] **Step 7: Commit (suggested)**

```bash
git add config/marketing/landing_variants.php config/marketing/landing_experiments.php .env.example tests/Marketing/LandingVariantsConfigTest.php
git commit -m "feat(marketing): add landing variant manifests and experiment config"
```

---

### Task 2: Persistence — migrations + schema mirror

**Files:**
- Create: `database/migrations/20260715120000_mkt_landing_experiments.sql`
- Modify: `database/schema/modules/marketing.sql`
- Modify: `config/modules/marketing.php` (migraciones + permiso)
- Test: `tests/Marketing/LandingExperimentsSchemaTest.php` (+ update `SchemaBootstrapTest.php`)

**Interfaces:**
- Produces tables:
  - `dom_mkt_variant_weights (slug PK, weight DECIMAL, updated_at)`
  - `dom_mkt_variant_proposals (id, status, payload JSON, created_at, resolved_at, resolved_by)`
  - `dom_mkt_landing_sessions (id, visitor_id, variant_slug, is_preview, duration_ms, max_scroll_pct, exit_section, first_seen_at, last_seen_at)`
  - `dom_mkt_landing_events (id, session_id, visitor_id, variant_slug, event_type, meta JSON, is_preview, created_at)`
  - `dom_mkt_leads.landing_variant VARCHAR(40) NULL`, `visitor_id CHAR(36) NULL`
  - permiso `marketing.experimentos` + menú `/admin/marketing/experimentos`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Marketing/LandingExperimentsSchemaTest.php
declare(strict_types=1);

test('migracion landing experiments crea tablas y columnas de atribucion', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/migrations/20260715120000_mkt_landing_experiments.sql');
    foreach ([
        'dom_mkt_variant_weights',
        'dom_mkt_variant_proposals',
        'dom_mkt_landing_sessions',
        'dom_mkt_landing_events',
    ] as $t) {
        assert_true(str_contains($sql, $t), "menciona {$t}");
    }
    assert_true(str_contains($sql, 'landing_variant'), 'columna lead landing_variant');
    assert_true(str_contains($sql, 'visitor_id'), 'columna lead visitor_id');
    assert_true(str_contains($sql, "'marketing.experimentos'"), 'permiso experimentos');
    assert_true(str_contains($sql, '/admin/marketing/experimentos'), 'menu path');
});

test('marketing.sql bootstrap incluye tablas de experimentos', function (): void {
    $sql = (string) file_get_contents(ROOT_PATH . '/database/schema/modules/marketing.sql');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_variant_weights`'), 'weights');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_variant_proposals`'), 'proposals');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_landing_sessions`'), 'sessions');
    assert_true(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `dom_mkt_landing_events`'), 'events');
    assert_true(str_contains($sql, '`landing_variant`'), 'lead column');
    assert_true(str_contains($sql, "'marketing.experimentos'"), 'permiso');
});
```

Also extend `SchemaBootstrapTest` first foreach table list with the four new table names.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php Marketing`
Expected: FAIL — migration file missing.

- [ ] **Step 3: Write migration SQL**

```sql
-- database/migrations/20260715120000_mkt_landing_experiments.sql
-- Sigue database/migrations/README.md: IF NOT EXISTS en DDL; grants idempotentes.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_weights` (
  `slug`       VARCHAR(40)     NOT NULL,
  `weight`     DECIMAL(8,4)    NOT NULL DEFAULT 0,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_variant_proposals` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status`      VARCHAR(20)     NOT NULL DEFAULT 'pending',
  `payload`     JSON            NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME        DEFAULT NULL,
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_var_prop_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_sessions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`       CHAR(36)        NOT NULL,
  `visitor_id`      CHAR(36)        NOT NULL,
  `variant_slug`    VARCHAR(40)     NOT NULL,
  `is_preview`      TINYINT(1)      NOT NULL DEFAULT 0,
  `duration_ms`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `max_scroll_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `exit_section`    VARCHAR(60)     DEFAULT NULL,
  `first_seen_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mkt_land_sess_public` (`public_id`),
  KEY `idx_mkt_land_sess_visitor` (`visitor_id`),
  KEY `idx_mkt_land_sess_variant` (`variant_slug`, `is_preview`, `first_seen_at`),
  KEY `idx_mkt_land_sess_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dom_mkt_landing_events` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`   BIGINT UNSIGNED DEFAULT NULL,
  `visitor_id`   CHAR(36)        NOT NULL,
  `variant_slug` VARCHAR(40)     NOT NULL,
  `event_type`   VARCHAR(40)     NOT NULL,
  `meta`         JSON            DEFAULT NULL,
  `is_preview`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mkt_land_evt_var` (`variant_slug`, `is_preview`, `created_at`),
  KEY `idx_mkt_land_evt_type` (`event_type`),
  KEY `idx_mkt_land_evt_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `dom_mkt_leads`
  ADD COLUMN IF NOT EXISTS `landing_variant` VARCHAR(40) DEFAULT NULL AFTER `utm_campaign`,
  ADD COLUMN IF NOT EXISTS `visitor_id` CHAR(36) DEFAULT NULL AFTER `landing_variant`;

CREATE INDEX IF NOT EXISTS `idx_mkt_leads_landing_variant`
  ON `dom_mkt_leads` (`landing_variant`, `created_at`);

INSERT INTO `auth_permisos` (`nombre`, `slug`, `modulo`, `descripcion`)
SELECT 'Experimentos landing', 'marketing.experimentos', 'marketing', 'Ver métricas y aceptar/rechazar propuestas de peso'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `auth_permisos` WHERE `slug` = 'marketing.experimentos');

INSERT IGNORE INTO `auth_roles_permisos` (`rol_id`, `permiso_id`)
SELECT `r`.`id`, `p`.`id`
FROM `auth_roles` `r`
INNER JOIN `auth_permisos` `p` ON `p`.`slug` = 'marketing.experimentos'
WHERE `r`.`slug` = 'administrador';

INSERT IGNORE INTO `core_menu_items`
  (`parent_id`, `orden`, `slug`, `label`, `icon`, `ruta`, `match_path`, `permiso`, `modulo`, `activo`)
SELECT p.id, 6, 'marketing-experimentos', 'Experimentos', 'bi-graph-up', '/admin/marketing/experimentos', '/admin/marketing/experimentos', 'marketing.experimentos', 'marketing', 1
FROM core_menu_items p WHERE p.slug = 'marketing'
  AND NOT EXISTS (SELECT 1 FROM core_menu_items c WHERE c.slug = 'marketing-experimentos');
```

**No FK** de events→sessions (Anti-deuda §D.5). Mirror CREATE + lead columns + permiso/menu/grant into `database/schema/modules/marketing.sql` (lead columns inline in `CREATE TABLE` for greenfield).

Status values for proposals (document in PHPDoc): `pending`, `accepted`, `rejected`, `superseded`.

Update `config/modules/marketing.php` (**append only** — Anti-deuda §U):

```php
'migraciones' => [
    '20260713120000_mkt_leads_email_verify.sql',
    '20260714200000_mkt_membership_orders.sql',
    '20260714210000_mkt_landing_copy_seo.sql', // existed on disk; register so installer matches VPS
    '20260715100000_mkt_ordenes_permission_slug.sql',
    '20260715120000_mkt_landing_experiments.sql',
],
'permisos' => [
    'marketing.ver', 'marketing.crear', 'marketing.editar', 'marketing.eliminar',
    'marketing.gestionar', 'marketing.leads', 'marketing.publicar', 'marketing.ordenes',
    'marketing.experimentos',
],
```

If `20260714210000` already ran on an environment outside `cfg_migraciones`, the runner should skip by checksum/filename — verify runner idempotency before prod apply (human). Do **not** delete or reorder older entries.
- [ ] **Step 4: Run tests**

Run: `php tests/run.php Marketing`
Expected: PASS for schema tests.

- [ ] **Step 5: Commit (suggested)**

```bash
git add database/migrations/20260715120000_mkt_landing_experiments.sql database/schema/modules/marketing.sql config/modules/marketing.php tests/Marketing/LandingExperimentsSchemaTest.php tests/Marketing/SchemaBootstrapTest.php
git commit -m "feat(marketing): persist landing experiment weights sessions events"
```

---

### Task 3: Domain contracts + PDO repositories

**Files:**
- Create: `app/Domain/Marketing/Contracts/VariantWeightRepositoryInterface.php`
- Create: `app/Domain/Marketing/Contracts/LandingMetricsRepositoryInterface.php`
- Create: `app/Domain/Marketing/Contracts/VariantProposalRepositoryInterface.php`
- Create: `app/Infrastructure/Marketing/PdoVariantWeightRepository.php`
- Create: `app/Infrastructure/Marketing/PdoLandingMetricsRepository.php`
- Create: `app/Infrastructure/Marketing/PdoVariantProposalRepository.php`
- Create: `app/Domain/Marketing/LandingVariantRegistry.php` (puro: constructor array)
- Binding: `config/container.php` carga `landing_variants.php` y construye el registry (único `require`)
- Test: `tests/Marketing/LandingVariantRegistryTest.php`
- Test: `tests/Marketing/PdoVariantWeightRepositoryContractTest.php` (source/method contract if no DB in CI)

**Interfaces:**
- `LandingVariantRegistry::all(): array<string,array>`, `get(string $slug): ?array`, `activeSlugs(): list<string>`, `revealId(string $sectionId): string`
- `VariantWeightRepositoryInterface::all(): array<string,float>`, `get(string $slug): ?float`, `upsert(string $slug, float $weight): void`, `seedMissing(array $defaults): void`
- `LandingMetricsRepositoryInterface::findSessionByPublicId`, `ensureSession`, `updateSessionMetrics`, `insertEvent`, `aggregateForScore`, `purgeOlderThan(DateTimeImmutable): array{sessions:int,events:int}`
- `VariantProposalRepositoryInterface::insertPending`, `findPending`, `findById`, `markAccepted`, `markRejected`, `supersedeAllPending`

- [ ] **Step 1: Write failing registry test**

```php
<?php
// tests/Marketing/LandingVariantRegistryTest.php
declare(strict_types=1);

use App\Domain\Marketing\LandingVariantRegistry;

test('LandingVariantRegistry carga manifiestos y mapea reveal ids', function (): void {
    /** @var array{catalog:list<string>,reveal_id_map:array<string,string>,variants:array<string,array>} $cfg */
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $reg = new LandingVariantRegistry($cfg);
    assert_true($reg->get('v1') !== null, 'v1');
    assert_true($reg->get('v2') !== null, 'v2');
    assert_same('testimonials', $reg->revealId('testimonios'));
    assert_same('cta', $reg->revealId('lead_form'));
    assert_true(in_array('v1', $reg->activeSlugs(), true), 'v1 active');
});
```

**Do not** add `LandingVariantRegistry::fromConfig()` in Domain (Anti-deuda §A). Tests and container may `require` the config file.
- [ ] **Step 2: Run to verify fail**

Run: `php tests/run.php Marketing`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement registry + interfaces + PDO repos**

`LandingVariantRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Marketing;

final class LandingVariantRegistry
{
    /** @param array{catalog:list<string>,reveal_id_map:array<string,string>,variants:array<string,array<string,mixed>>} $config */
    public function __construct(private readonly array $config) {}

    // NO fromConfig() aquí — el container inyecta el array.

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->config['variants'];
    }

    /** @return array<string, mixed>|null */
    public function get(string $slug): ?array
    {
        return $this->config['variants'][$slug] ?? null;
    }

    /** @return list<string> */
    public function activeSlugs(): array
    {
        $out = [];
        foreach ($this->config['variants'] as $slug => $row) {
            if (($row['status'] ?? '') === 'active') {
                $out[] = (string) $slug;
            }
        }

        return $out;
    }

    public function revealId(string $sectionId): string
    {
        return (string) ($this->config['reveal_id_map'][$sectionId] ?? $sectionId);
    }

    /** @return list<string> */
    public function catalog(): array
    {
        return $this->config['catalog'];
    }

    /** weight_default del manifesto para slugs nuevos no listados en seed map */
    public function weightDefault(string $slug): float
    {
        $row = $this->get($slug);

        return $row !== null ? (float) ($row['weight_default'] ?? 0.0) : 0.0;
    }
}
```

`seedMissing` en el weight repo: para cada active slug, si falta fila → usar `seed_weight_defaults()[$slug]` si existe, else `registry->weightDefault($slug)`.
Contracts (abbreviated — implement full PHPDoc in files):

```php
// VariantWeightRepositoryInterface
interface VariantWeightRepositoryInterface {
    /** @return array<string, float> */
    public function all(): array;
    public function get(string $slug): ?float;
    public function upsert(string $slug, float $weight): void;
    /** @param array<string, float> $defaults */
    public function seedMissing(array $defaults): void;
}
```

```php
// LandingMetricsRepositoryInterface key methods
public function findSessionByPublicId(string $publicId): ?array;
/** @param array{public_id:string,visitor_id:string,variant_slug:string,is_preview:bool} $data */
public function ensureSession(array $data): int;
public function updateSessionMetrics(string $publicId, int $durationMs, int $maxScrollPct, ?string $exitSection): void;
/** @param array{session_id:?int,visitor_id:string,variant_slug:string,event_type:string,meta:?array,is_preview:bool} $data */
public function insertEvent(array $data): void;
/**
 * @return list<array{
 *   variant_slug:string,sessions:int,avg_scroll:float,avg_duration_ms:float,
 *   leads:int,top_exit_section:?string,sections_seen_avg:float
 * }>
 */
public function aggregateForScore(int $windowDays): array;
```

```php
// VariantProposalRepositoryInterface
public function insertPending(array $payload): int;
/** @return list<array<string,mixed>> */
public function findPending(): array;
public function findById(int $id): ?array;
public function markAccepted(int $id, int $userId): void;
public function markRejected(int $id, int $userId): void;
/** Marca todas las pending como superseded; retorna filas afectadas. */
public function supersedeAllPending(): int;
```

PDO implementations use `Connection::getInstance()`, prepared statements, `json_encode` for meta/payload. `seedMissing` inserts only slugs not already present (**never** overwrite existing weights — Anti-deuda §W). `aggregateForScore` excludes `is_preview=1`, joins leads by `landing_variant` in the window, counts distinct sessions, averages scroll/duration, mode of `exit_section`.

- [ ] **Step 4: Contract smoke test (methods exist)**

```php
<?php
// tests/Marketing/PdoVariantWeightRepositoryContractTest.php
declare(strict_types=1);

test('PdoVariantWeightRepository implementa la interfaz de pesos', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Infrastructure/Marketing/PdoVariantWeightRepository.php');
    assert_true(str_contains($src, 'implements \\App\\Domain\\Marketing\\Contracts\\VariantWeightRepositoryInterface')
        || str_contains($src, 'implements VariantWeightRepositoryInterface'), 'implements');
    assert_true(str_contains($src, 'function seedMissing'), 'seedMissing');
    assert_true(str_contains($src, 'function upsert'), 'upsert');
});
```

- [ ] **Step 5: Run tests + commit (suggested)**

```bash
git add app/Domain/Marketing app/Infrastructure/Marketing/PdoVariant*.php app/Infrastructure/Marketing/PdoLandingMetricsRepository.php tests/Marketing/LandingVariantRegistryTest.php tests/Marketing/PdoVariantWeightRepositoryContractTest.php
git commit -m "feat(marketing): add landing experiment repositories and registry"
```

---

### Task 4: Sticky assigner service

**Files:**
- Create: `app/Application/Marketing/AssignInput.php`
- Create: `app/Application/Marketing/LandingExperimentAssigner.php`
- Create: `app/Application/Marketing/AssignedLandingVariant.php` (DTO)
- Create: `app/Application/Marketing/CookieSpec.php`
- Test: `tests/Marketing/LandingExperimentAssignerTest.php`

**Interfaces:**
- Consumes: `LandingVariantRegistry`, `VariantWeightRepositoryInterface`, experiment config
- Produces: `AssignedLandingVariant{slug, shell, isPreview, visitorId, cookies: list<CookieSpec>}`
- Method: `assign(AssignInput $input): AssignedLandingVariant` — **no** `Request` type (Anti-deuda §J)
- `CookieSpec`: `name, value, ttlDays=?int, maxAgeSeconds=?int, path='/', httpOnly=true, sameSite='Lax', secure=bool, delete=bool`
- Rules (exact):
  1. Ensure `lb_vid` (UUID v4); **queue** CookieSpec if missing (no `setcookie` here)
  2. Force slug from `$input->forceVariant` if in registry → `isPreview=true`, **do not** queue `lb_var`; **do** queue `lb_preview=<slug>` with `maxAgeSeconds=preview_cookie_ttl_seconds` (Anti-deuda §K)
  3. Else if cookie `lb_var` eligible (`active` + weight>0) → reuse; queue **delete** `lb_preview`; cookies may only include new `lb_vid` + delete preview
  4. Else weighted random among eligible; if none → fallback; queue `lb_var` CookieSpec TTL 30d + delete `lb_preview`
  5. Controller builds `AssignInput` and applies `$assigned->cookies` via `setcookie` **before** body

- [ ] **Step 1: Write failing tests with fake weight repo**

```php
<?php
// tests/Marketing/LandingExperimentAssignerTest.php
declare(strict_types=1);

use App\Application\Marketing\AssignInput;
use App\Application\Marketing\AssignedLandingVariant;
use App\Application\Marketing\LandingExperimentAssigner;
use App\Domain\Marketing\Contracts\VariantWeightRepositoryInterface;
use App\Domain\Marketing\LandingVariantRegistry;

final class FakeWeights implements VariantWeightRepositoryInterface
{
    /** @param array<string,float> $weights */
    public function __construct(private array $weights) {}
    public function all(): array { return $this->weights; }
    public function get(string $slug): ?float { return $this->weights[$slug] ?? null; }
    public function upsert(string $slug, float $weight): void { $this->weights[$slug] = $weight; }
    public function seedMissing(array $defaults): void {
        foreach ($defaults as $s => $w) {
            if (!isset($this->weights[$s])) {
                $this->weights[$s] = $w;
            }
        }
    }
}

function makeAssigner(array $weights, ?array $variantsCfg = null): LandingExperimentAssigner
{
    $exp = require ROOT_PATH . '/config/marketing/landing_experiments.php';
    $cfg = $variantsCfg ?? require ROOT_PATH . '/config/marketing/landing_variants.php';
    return new LandingExperimentAssigner(
        new LandingVariantRegistry($cfg),
        new FakeWeights($weights),
        $exp
    );
}

/** @return list<string> */
function cookieNames(AssignedLandingVariant $out): array
{
    return array_map(static fn ($c) => $c->name, $out->cookies);
}

function input(string $force = '', array $cookies = []): AssignInput
{
    return new AssignInput($force, $cookies, true);
}

test('assigner reusa cookie sticky si variante elegible', function (): void {
    $a = makeAssigner(['v1' => 0.5, 'v2' => 0.5]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v1', $out->slug);
    assert_same(false, $out->isPreview);
    assert_true(!in_array('lb_var', cookieNames($out), true), 'no reescribe sticky si ya válida');
});

test('assigner reasigna si peso es 0', function (): void {
    $a = makeAssigner(['v1' => 0.0, 'v2' => 1.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_true(in_array('lb_var', cookieNames($out), true), 'reescribe sticky al reasignar');
});

test('assigner reasigna si status paused aunque weight > 0', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $cfg['variants']['v1']['status'] = 'paused';
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0], $cfg);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_true(in_array('lb_var', cookieNames($out), true), 'reescribe sticky al pausar');
});

test('assigner ?variant= fuerza preview SIN escribir lb_var y CON lb_preview', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0]);
    $out = $a->assign(input('v2', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
    ]));
    assert_same('v2', $out->slug);
    assert_same(true, $out->isPreview);
    assert_true(!in_array('lb_var', cookieNames($out), true), 'preview no pisa sticky');
    assert_true(in_array('lb_preview', cookieNames($out), true), 'marca preview cookie');
});

test('assigner no-preview borra lb_preview', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 0.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '11111111-1111-4111-8111-111111111111',
        'lb_var' => 'v1',
        'lb_preview' => 'v2',
    ]));
    $previewCookies = array_values(array_filter($out->cookies, static fn ($c) => $c->name === 'lb_preview'));
    assert_true(count($previewCookies) === 1 && $previewCookies[0]->delete === true, 'limpia preview');
});

test('assigner ?landing= llega como forceVariant desde controller', function (): void {
    $a = makeAssigner(['v1' => 1.0, 'v2' => 1.0]);
    $out = $a->assign(input('v1', [
        'lb_vid' => '22222222-2222-4222-8222-222222222222',
    ]));
    assert_same('v1', $out->slug);
    assert_same(true, $out->isPreview);
});

test('assigner fallback a v2 si todos peso 0', function (): void {
    $a = makeAssigner(['v1' => 0.0, 'v2' => 0.0]);
    $out = $a->assign(input('', [
        'lb_vid' => '33333333-3333-4333-8333-333333333333',
    ]));
    assert_same('v2', $out->slug);
});

test('assigner no lee LANDING_VARIANT ni Request ni setcookie', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Application/Marketing/LandingExperimentAssigner.php');
    assert_true(!str_contains($src, 'LANDING_VARIANT'), 'sin env en assign');
    assert_true(!str_contains($src, 'setcookie'), 'sin side-effect cookie en Application');
    assert_true(!str_contains($src, 'Kernel\\Http\\Request'), 'sin Request Kernel');
});
```

- [ ] **Step 2: Run to fail**

Expected: FAIL — class missing.

- [ ] **Step 3: Implement assigner**

Core algorithm sketch (must match tests):

```php
public function assign(AssignInput $input): AssignedLandingVariant
{
    $this->weights->seedMissing($this->seedDefaults());
    $cookies = [];
    $visitorId = $this->ensureVisitorId($input, $cookies);
    $force = $input->forceVariant;
    if ($force !== '' && $this->registry->get($force) !== null) {
        $cookies[] = new CookieSpec(
            name: $this->cfg['cookie_preview_name'],
            value: $force,
            maxAgeSeconds: (int) $this->cfg['preview_cookie_ttl_seconds'],
        );
        return new AssignedLandingVariant(
            $force,
            (string) $this->registry->get($force)['shell'],
            true,
            $visitorId,
            $cookies
        );
    }
    $cookies[] = CookieSpec::delete($this->cfg['cookie_preview_name']);
    $cookie = strtolower((string) ($input->cookies[$this->cfg['cookie_var_name']] ?? ''));
    if ($cookie !== '' && $this->isEligible($cookie)) {
        return new AssignedLandingVariant(
            $cookie,
            (string) $this->registry->get($cookie)['shell'],
            false,
            $visitorId,
            $cookies
        );
    }
    $slug = $this->weightedPick($this->eligibleWeights()) ?? $this->fallbackSlug();
    $cookies[] = new CookieSpec(
        $this->cfg['cookie_var_name'],
        $slug,
        (int) $this->cfg['cookie_ttl_days'],
    );
    return new AssignedLandingVariant(
        $slug,
        (string) $this->registry->get($slug)['shell'],
        false,
        $visitorId,
        $cookies
    );
}
```

`isEligible`: registry status **`=== 'active'`** AND weight > 0 (paused/archived fail even if weight>0).  
`weightedPick`: sum weights, `random_int` scaled (prefer over `mt_rand`).  
`fallbackSlug`: cfg `fallback_slug` if active else first `activeSlugs()[0]`.  
CookieSpec defaults: `httpOnly=true`, `sameSite=Lax`, `secure` resolved in **controller** from `$input->isHttps` / `APP_URL`.

**Do not** read `LANDING_VARIANT` inside `assign()`.  
**Do not** call `setcookie()`.  
**Do not** type-hint Kernel `Request`.

LandingController:

```php
$force = strtolower(trim((string) $request->query('variant', '')));
if ($force === '') {
    $force = strtolower(trim((string) $request->query('landing', '')));
}
$assigned = $this->assigner->assign(new AssignInput(
    $force,
    [
        'lb_vid' => (string) $request->cookie('lb_vid', ''),
        'lb_var' => (string) $request->cookie('lb_var', ''),
        'lb_preview' => (string) $request->cookie('lb_preview', ''),
    ],
    $request->isSecure() || str_starts_with((string) EnvLoader::get('APP_URL', ''), 'https'),
));
foreach ($assigned->cookies as $c) {
    $opts = [
        'path' => $c->path,
        'secure' => $secure,
        'httponly' => $c->httpOnly,
        'samesite' => $c->sameSite,
    ];
    if ($c->delete) {
        $opts['expires'] = time() - 3600;
        setcookie($c->name, '', $opts);
        continue;
    }
    if ($c->maxAgeSeconds !== null) {
        $opts['expires'] = time() + $c->maxAgeSeconds;
    } else {
        $opts['expires'] = time() + $c->ttlDays * 86400;
    }
    setcookie($c->name, $c->value, $opts);
}
```

(If deriving HTTPS: **do not** call `$request->isSecure()` — method missing. Use Anti-deuda §X in the controller only.)

- [ ] **Step 4: Run tests PASS + commit (suggested)**

```bash
git add app/Application/Marketing/AssignInput.php app/Application/Marketing/LandingExperimentAssigner.php app/Application/Marketing/AssignedLandingVariant.php app/Application/Marketing/CookieSpec.php tests/Marketing/LandingExperimentAssignerTest.php
git commit -m "feat(marketing): sticky weighted landing variant assigner"
```

---

### Task 5: Merge sections/SEO/overrides + wire LandingController

**Files:**
- Create: `app/Application/Marketing/MergeLandingVariantUseCase.php`
- Create: `app/Presentation/Marketing/LandingSectionRenderer.php` (mapa único secciones→partials)
- Modify: `app/Presentation/Controllers/Publico/LandingController.php`
- Modify: `app/Presentation/Views/publico/landing.php`
- Modify: `app/Presentation/Views/publico/landing_v2.php`
- Modify: `config/container.php`
- Modify: `tests/Marketing/LandingVariantSelectionTest.php` (rewrite expectations away from env flag traffic selection)
- Test: `tests/Marketing/MergeLandingVariantUseCaseTest.php`

**Interfaces:**
- `MergeLandingVariantUseCase::merge(string $slug, array $bloques): array{slug,shell,sections:list<string>,bloques,seo:array{title,description}}`
- Shallow merge: foreach key in `copy_overrides`, `$bloques[$key] = array_replace_recursive($bloques[$key] ?? [], $patch)`
- Sections: enabled ids in manifesto order
- `LandingSectionRenderer::render(string $shell, array $sections, array $ctx): string` — **único** mapa; v1/v2 views solo llaman al renderer (Anti-deuda §G)
- Controller:
  1. `$assigned = $assigner->assign($request)`
  2. Apply `$assigned->cookies` (Presentation)
  3. `$vm = $renderLanding->ejecutar('home')`
  4. `$merged = $merge->merge($assigned->slug, $vm['bloques'])`
  5. Pick view/layout from `$assigned->shell`
  6. Pass SEO, sections, `landingVariant`, `visitorId`, `isPreview`

- [ ] **Step 1: Failing merge tests**

```php
<?php
// tests/Marketing/MergeLandingVariantUseCaseTest.php
declare(strict_types=1);

use App\Application\Marketing\MergeLandingVariantUseCase;
use App\Domain\Marketing\LandingVariantRegistry;

test('merge aplica seo del manifiesto y shallow override', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    $cfg['variants']['v1']['copy_overrides'] = [
        'hero' => ['titulo' => 'Override Title'],
    ];
    $reg = new LandingVariantRegistry($cfg);
    $uc = new MergeLandingVariantUseCase($reg);
    $out = $uc->merge('v1', [
        'hero' => ['titulo' => 'Original', 'subtitulo' => 'Sub'],
        'faq' => ['items' => []],
    ]);
    assert_same('Override Title', $out['bloques']['hero']['titulo']);
    assert_same('Sub', $out['bloques']['hero']['subtitulo']);
    assert_same($cfg['variants']['v1']['seo']['title'], $out['seo']['title']);
    assert_true(in_array('hero', $out['sections'], true), 'hero enabled');
});

test('merge omite secciones disabled', function (): void {
    $cfg = require ROOT_PATH . '/config/marketing/landing_variants.php';
    foreach ($cfg['variants']['v1']['sections'] as &$s) {
        if ($s['id'] === 'faq') {
            $s['enabled'] = false;
        }
    }
    unset($s);
    $uc = new MergeLandingVariantUseCase(new LandingVariantRegistry($cfg));
    $out = $uc->merge('v1', []);
    assert_true(!in_array('faq', $out['sections'], true), 'faq oculto');
});
```

- [ ] **Step 2: Implement merge use case + shared section renderer**

`LandingSectionRenderer` owns the map (single place):

```php
final class LandingSectionRenderer
{
    /**
     * @param list<string> $sections
     * @param array<string,mixed> $ctx keys: bloques, paquetes, comprasHabilitadas, landingVariant, visitorId
     */
    public function render(string $shell, array $sections, array $ctx): string
    {
        $prefix = $shell === 'v2' ? 'publico/partials/v2/' : 'publico/partials/';
        $bloques = is_array($ctx['bloques'] ?? null) ? $ctx['bloques'] : [];
        $map = [
            'hero' => ['_hero', ['hero' => $bloques['hero'] ?? []]],
            'trust' => ['_trust', ['trust' => $bloques['trust'] ?? []]],
            'features' => ['_features', ['features' => $bloques['features'] ?? []]],
            'pricing' => ['_pricing', [
                'paquetes' => $ctx['paquetes'] ?? [],
                'comprasHabilitadas' => !empty($ctx['comprasHabilitadas']),
            ]],
            'testimonios' => ['_testimonios', ['testimonios' => $bloques['testimonios'] ?? []]],
            'faq' => ['_faq', ['faq' => $bloques['faq'] ?? []]],
            'lead_form' => ['_lead_form', [
                'landingVariant' => (string) ($ctx['landingVariant'] ?? ''),
                'visitorId' => (string) ($ctx['visitorId'] ?? ''),
            ]],
        ];
        $html = '';
        foreach ($sections as $id) {
            if (!isset($map[$id])) {
                continue;
            }
            [$file, $data] = $map[$id];
            $html .= ViewHelper::render($prefix . $file, $data, '');
        }

        return $html;
    }
}
```

Views become thin:

```php
<?= /* @var LandingSectionRenderer $sectionRenderer */ ?>
<?= $sectionRenderer->render($shell ?? 'v1', $sections ?? [], [
    'bloques' => $bloques ?? [],
    'paquetes' => $paquetes ?? [],
    'comprasHabilitadas' => $comprasHabilitadas ?? false,
    'landingVariant' => $landingVariant ?? '',
    'visitorId' => $visitorId ?? '',
]) ?>
```

Or echo from controller into `$sectionsHtml` if ViewHelper injection of renderer is awkward — **locked default:** controller builds `$sectionsHtml` via injected renderer for the happy path; views **must** implement Anti-deuda §P fallback so `LandingV2ViewTest` (and any `ViewHelper::render` without `$sectionsHtml`) keep working. Do **not** invent View DI.

**Hard gate v1 markers (Anti-deuda §M)** — same task:

Add `data-section="<catalog id>"` to each v1 partial root section (`publico/partials/_hero.php`, `_trust.php`, `_features.php`, `_pricing.php`, `_testimonios.php`, `_faq.php`, `_lead_form.php`). Leave v2 `data-reveal-id` as-is.

- [ ] **Step 3: Rewrite `LandingController`**

Replace env-based selection with `AssignInput` + assigner + merge. Preserve `?compras=` and existing UI vars. Pass metrics bootstrap vars to layout:

```php
'landingVariant' => $assigned->slug,
'visitorId' => $assigned->visitorId,
'isPreview' => $assigned->isPreview,
'pageTitle' => $merged['seo']['title'],
'metaDescription' => $merged['seo']['description'],
'sectionsHtml' => $this->sectionRenderer->render($assigned->shell, $merged['sections'], [...]),
'bloques' => $merged['bloques'],
```

Update container bindings for new deps under the marketing vertical `if`.

- [ ] **Step 4: Rewrite `LandingVariantSelectionTest` (critical — old test locks env selector)**

Replace **entire** file expectations:

```php
test('LandingController usa assigner no LANDING_VARIANT por request', function (): void {
    $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Controllers/Publico/LandingController.php');
    assert_true(str_contains($src, 'LandingExperimentAssigner'), 'inyecta assigner');
    assert_true(str_contains($src, 'AssignInput'), 'usa DTO no Request en assigner');
    assert_true(!str_contains($src, "EnvLoader::get('LANDING_VARIANT'"), 'ya no selecciona por env');
    assert_true(str_contains($src, "'publico/landing_v2'") || str_contains($src, 'landing_v2'), 'conserva shell v2');
    assert_true(str_contains($src, "'publico/landing'") || str_contains($src, 'landing'), 'conserva shell v1');
});

test('partials v1 exponen data-section para metrics', function (): void {
    foreach (['hero','trust','features','pricing','testimonios','faq','lead_form'] as $id) {
        $file = $id === 'lead_form' ? '_lead_form.php' : "_{$id}.php";
        $src = (string) file_get_contents(ROOT_PATH . '/app/Presentation/Views/publico/partials/' . $file);
        assert_true(str_contains($src, 'data-section="' . $id . '"'), $file);
    }
});

test('config/app.php conserva landing_variant como seed hint', function (): void {
    $config = require ROOT_PATH . '/config/app.php';
    assert_true(array_key_exists('landing_variant', $config), 'clave presente');
});
```

- [ ] **Step 5: Run Marketing tests + commit (suggested)**

```bash
git add app/Application/Marketing/MergeLandingVariantUseCase.php app/Presentation/Marketing/LandingSectionRenderer.php app/Presentation/Controllers/Publico/LandingController.php app/Presentation/Views/publico/landing.php app/Presentation/Views/publico/landing_v2.php app/Presentation/Views/publico/partials/_hero.php app/Presentation/Views/publico/partials/_trust.php app/Presentation/Views/publico/partials/_features.php app/Presentation/Views/publico/partials/_pricing.php app/Presentation/Views/publico/partials/_testimonios.php app/Presentation/Views/publico/partials/_faq.php app/Presentation/Views/publico/partials/_lead_form.php config/container.php tests/Marketing/MergeLandingVariantUseCaseTest.php tests/Marketing/LandingVariantSelectionTest.php
git commit -m "feat(marketing): merge variant manifests into landing render"
```

---

### Task 6: Client metrics + collect endpoint

**Files:**
- Create: `public/assets/publico/landing_metrics.js`
- Create: `app/Application/Marketing/CollectLandingMetricsUseCase.php`
- Create: `app/Domain/Marketing/Contracts/CollectRateLimiterInterface.php`
- Create: `app/Infrastructure/Marketing/SysKvCollectRateLimiter.php`
- Create: `app/Presentation/Controllers/Publico/LandingMetricsController.php`
- Modify: `routes/marketing.php`
- Modify: `app/Presentation/Views/publico/layout.php`
- Modify: `app/Presentation/Views/publico/layout_v2.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/CollectLandingMetricsUseCaseTest.php`
- Test: `tests/Marketing/LandingMetricsAssetTest.php`

**Interfaces:**
- `CollectLandingMetricsUseCase::handle(array $input, ?string $cookieVisitorId = null, ?string $cookiePreview = null, ?string $cookieVariant = null): array{ok:bool,error?:string}`
- Allowed `event_type`: `pageview`, `scroll_depth`, `section_view`, `heartbeat`, `exit`, `cta_click` — **no** `lead_submit` from client (Anti-deuda §N; server emits in Task 7)
- Required: `visitor_id` (UUID **v4 strict** §V), `variant_slug` resolved per §R, `session_public_id` (UUID v4), `event_type`
- `is_preview` persisted = `($cookiePreview !== null && $cookiePreview !== '')` — **ignore** body `is_preview` (Anti-deuda §K)
- `variant_slug` resolved: preview cookie → sticky `lb_var` → body (Anti-deuda §R)
- If `$cookieVisitorId` valid UUID and differs from body → **use cookie** (Anti-deuda §C.4)
- `meta`: allowlist §T; strip unknown; reject if encoded > 2KB
- `heartbeat`: session metrics update **only** when `persist_heartbeat_events` is false (default) — Anti-deuda §Q
- Rate limit: `CollectRateLimiterInterface` + `SysKvCollectRateLimiter` (`land_collect:{visitor_id}`, Anti-deuda §L). **Never** Session / `CompraController::allowPost`.
- Transport: **form-urlencoded / FormData** so `$_POST` works (`Request::all()` has no cookies — controller must pass cookie args separately)
- Route: `$router->post('/marketing/collect', ...)` **without** `CsrfMiddleware` (first open public POST — harden in same PR)
- Layouts inject before closing `</body>`:

```html
<script>
window.__LB_METRICS__ = {
  variant: <?= json_encode($landingVariant ?? '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
  visitorId: <?= json_encode($visitorId ?? '', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,
  isPreview: <?= !empty($isPreview) ? 'true' : 'false' ?>,
  endpoint: '/marketing/collect'
};
</script>
<script src="/assets/publico/landing_metrics.js" defer></script>
```

- [ ] **Step 1: Failing use-case tests (in-memory fake metrics repo)**

```php
test('collect rechaza event_type desconocido', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'hack',
    ]);
    assert_same(false, $res['ok']);
});

test('collect acepta pageview y crea sesion', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'event_type' => 'pageview',
    ]);
    assert_same(true, $res['ok']);
    assert_same(1, count($fake->events));
});

test('collect prefiera cookie lb_vid sobre body spoof', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $cookieVid = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'event_type' => 'pageview',
    ], $cookieVid);
    assert_same(true, $res['ok']);
    assert_same($cookieVid, $fake->events[0]['visitor_id']);
});

test('collect rechaza variant_slug desconocido', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'nope',
        'session_public_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'event_type' => 'pageview',
    ]);
    assert_same(false, $res['ok']);
});

test('collect is_preview viene de cookie lb_preview no del body', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'event_type' => 'pageview',
        'is_preview' => '0', // spoof attempt
    ], null, 'v2');
    assert_same(true, $res['ok']);
    assert_same(1, (int) $fake->events[0]['is_preview']);

    $res2 = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => '99999999-9999-4999-8999-999999999999',
        'event_type' => 'pageview',
        'is_preview' => '1', // spoof without cookie
    ], null, null);
    assert_same(true, $res2['ok']);
    assert_same(0, (int) $fake->events[1]['is_preview']);
});

test('collect rechaza lead_submit desde cliente', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'lead_submit',
    ]);
    assert_same(false, $res['ok']);
});

test('collect heartbeat no inserta event row (session only)', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $base = [
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    ];
    $uc->handle($base + ['event_type' => 'pageview']);
    $before = count($fake->events);
    $uc->handle($base + ['event_type' => 'heartbeat', 'duration_ms' => 15000, 'max_scroll_pct' => 40]);
    $uc->handle($base + ['event_type' => 'heartbeat', 'duration_ms' => 30000, 'max_scroll_pct' => 50]);
    assert_same($before, count($fake->events), 'heartbeat no flood events');
    assert_true($fake->sessionDurationMs('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa') >= 30000, 'actualiza sesion');
});

test('collect prefiere cookie lb_var sobre body variant_slug', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-4111-8111-111111111111',
        'variant_slug' => 'v2',
        'session_public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'event_type' => 'pageview',
    ], null, null, 'v1');
    assert_same(true, $res['ok']);
    assert_same('v1', $fake->events[0]['variant_slug']);
});

test('collect rechaza UUID no-v4', function (): void {
    $fake = new FakeMetricsRepo();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new CollectLandingMetricsUseCase(
        $fake,
        new LandingVariantRegistry($cfg),
        new FakeCollectRateLimiter(true),
        $exp
    );
    $res = $uc->handle([
        'visitor_id' => '11111111-1111-1111-1111-111111111111', // version nibble ≠ 4
        'variant_slug' => 'v1',
        'session_public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'event_type' => 'pageview',
    ]);
    assert_same(false, $res['ok']);
});
```

- [ ] **Step 2: Implement controller + route + rate limiter**

```php
// routes/marketing.php — SIN CsrfMiddleware
use App\Presentation\Controllers\Publico\LandingMetricsController;
$router->post('/marketing/collect', [LandingMetricsController::class, 'collect']);
```

Controller:
1. Reject if `Content-Length` / raw body > `collect_max_body_bytes` → 413/422
2. Pass `$request->all()` + cookies `lb_vid`, `lb_preview`, `lb_var` into use case (**not** cookies via `all()`)
3. Return `200 {"ok":true}`; invalid → `422`; rate → `429`
4. If `collect_require_origin` true and Origin host ≠ APP_URL host → 422; default **false**

`SysKvCollectRateLimiter` per Anti-deuda §L; Fake for tests. Meta strip per §T. Heartbeat path per §Q.

- [ ] **Step 3: Implement `landing_metrics.js`**

Behavior:
1. Read `window.__LB_METRICS__`; abort if missing variant
2. Ensure `session_public_id` in `sessionStorage` key `lb_sess`
3. On load: send `pageview`
4. Scroll: fire once per bucket 25/50/75/100
5. IntersectionObserver on `[data-reveal-id],[data-section]` → `section_view` with `{section}` (prefer `data-reveal-id` value if both)
6. Heartbeat every 15s while `document.visibilityState==='visible'`
7. `visibilitychange` / `pagehide`: `exit` with max scroll + last section
8. Click on `[data-lb-cta]`: `cta_click`
9. **Never** send `lead_submit` from JS
10. `send(payload)` via `navigator.sendBeacon(endpoint, new URLSearchParams(payload))` else `fetch(..., {method:'POST', keepalive:true, headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})`
11. Swallow all errors; never read `document.cookie`

- [ ] **Step 4: Asset presence test**

```php
test('landing_metrics.js existe y layouts lo incluyen', function (): void {
    assert_true(is_file(ROOT_PATH.'/public/assets/publico/landing_metrics.js'), 'asset');
    $js = (string) file_get_contents(ROOT_PATH.'/public/assets/publico/landing_metrics.js');
    assert_true(!str_contains($js, 'lead_submit'), 'cliente no emite lead_submit');
    assert_true(!str_contains($js, 'document.cookie'), 'no lee cookies');
    foreach (['layout.php','layout_v2.php'] as $f) {
        $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Views/publico/'.$f);
        assert_true(str_contains($src, 'landing_metrics.js'), $f);
        assert_true(str_contains($src, '__LB_METRICS__'), $f.' bootstrap');
    }
});
```

- [ ] **Step 5: Run tests + commit (suggested)**

```bash
git add public/assets/publico/landing_metrics.js app/Application/Marketing/CollectLandingMetricsUseCase.php app/Domain/Marketing/Contracts/CollectRateLimiterInterface.php app/Infrastructure/Marketing/SysKvCollectRateLimiter.php app/Presentation/Controllers/Publico/LandingMetricsController.php routes/marketing.php app/Presentation/Views/publico/layout.php app/Presentation/Views/publico/layout_v2.php config/container.php tests/Marketing/CollectLandingMetricsUseCaseTest.php tests/Marketing/LandingMetricsAssetTest.php
git commit -m "feat(marketing): first-party landing metrics collector"
```

---

### Task 7: Lead attribution (`landing_variant` + `visitor_id`)

**Files:**
- Modify: `app/Domain/Marketing/ValueObjects/LeadDraft.php`
- Modify: `app/Infrastructure/Marketing/PdoLeadRepository.php`
- Modify: `app/Presentation/Controllers/Publico/LeadController.php`
- Modify: `app/Presentation/Views/publico/partials/_lead_form.php`
- Modify: `app/Presentation/Views/publico/partials/v2/_lead_form.php`
- Grep/fix any `new LeadDraft(` call sites (CapturarLeadUseCase itself is fine — only construction sites)
- Update anonymous `LeadRepositoryInterface` fakes that assert INSERT column lists if they break
- Test: `tests/Marketing/LeadVariantAttributionTest.php`

**Interfaces:**
- `LeadDraft` gains optional `?string $landingVariant = null`, `?string $visitorId = null` **at the end** (+ getters) — keep BC for existing positional args
- INSERT adds columns when present (both INSERT branches: with/without email verify)
- Controller resolution order (anti-spam / Anti-deuda §N):
  1. If cookie `lb_preview` non-empty → **do not attribute** (`landing_variant=NULL`, skip score `lead_submit` event) — preview leads must not pollute conversion
  2. Else cookie `lb_var` if matches `/^[a-z0-9_-]{1,40}$/` and registry has slug
  3. Else posted `landing_variant` if same regex + registry
  4. Cookie `lb_vid` if UUID; else posted `visitor_id` if UUID
- After successful capture (non-preview only): insert server-side `lead_submit` via metrics repo with `is_preview=0` (allowlist on collect use case may expose `insertTrustedLeadSubmit` on metrics repo used only by LeadController — prefer metrics repo method `insertEvent` called from Application/Presentation of LeadController with event_type allowed **only** via a dedicated `RecordLeadSubmitUseCase` or metrics repo internal allowlist for trusted callers). Simplest: `LandingMetricsRepositoryInterface::insertLeadSubmitEvent(...)` that hardcodes `event_type=lead_submit` — public collect use case still rejects `lead_submit` from HTTP.
- Do **not** change `CapturarLeadUseCase` signature; only `LeadDraft` payload grows

- [ ] **Step 1: Failing tests**

```php
test('LeadDraft expone landingVariant y visitorId', function (): void {
    $d = new LeadDraft('A', 'a@b.com', null, null, [], 'v2', '11111111-1111-4111-8111-111111111111');
    assert_same('v2', $d->landingVariant());
    assert_same('11111111-1111-4111-8111-111111111111', $d->visitorId());
});

test('PdoLeadRepository INSERT incluye landing_variant', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Infrastructure/Marketing/PdoLeadRepository.php');
    assert_true(str_contains($src, 'landing_variant'), 'columna');
    assert_true(str_contains($src, 'visitor_id'), 'visitor');
});

test('lead forms incluyen hidden landing_variant', function (): void {
    foreach (['_lead_form.php', 'v2/_lead_form.php'] as $rel) {
        $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Views/publico/partials/'.$rel);
        assert_true(str_contains($src, 'name="landing_variant"'), $rel);
    }
});

test('LeadController no atribuye variante bajo lb_preview', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Publico/LeadController.php');
    assert_true(str_contains($src, 'lb_preview'), 'lee preview cookie');
});
```

- [ ] **Step 2: Implement** — extend both INSERT branches in `PdoLeadRepository` (with and without email verification).

Hidden field:

```php
<input type="hidden" name="landing_variant" value="<?= ViewHelper::e((string)($landingVariant ?? '')) ?>">
<input type="hidden" name="visitor_id" value="<?= ViewHelper::e((string)($visitorId ?? '')) ?>">
```

LeadController construction of LeadDraft includes resolved variant/visitor; after success call metrics `insertLeadSubmitEvent` only when attributed.

- [ ] **Step 3: Run tests + commit (suggested)**

```bash
git add app/Domain/Marketing/ValueObjects/LeadDraft.php app/Infrastructure/Marketing/PdoLeadRepository.php app/Presentation/Controllers/Publico/LeadController.php app/Presentation/Views/publico/partials/_lead_form.php app/Presentation/Views/publico/partials/v2/_lead_form.php tests/Marketing/LeadVariantAttributionTest.php
git commit -m "feat(marketing): attribute leads to landing variant and visitor"
```

---

### Task 8: Hybrid score CLI + proposals (no auto weight write)

**Files:**
- Create: `app/Application/Marketing/ComputeVariantScoresUseCase.php`
- Create: `scripts/compute-landing-variant-scores.php`
- Modify: `config/marketing/landing_experiments.php` (`proposal_min_delta`, `min_explore_weight` already in Task 1)
- Modify: `VariantProposalRepositoryInterface` → `supersedeAllPending(): int`, `findLatestPending(): ?array`
- Test: `tests/Marketing/ComputeVariantScoresUseCaseTest.php`

**Interfaces:**
- `ComputeVariantScoresUseCase::ejecutar(): array{proposals_created:int, rankings:list}`
- For each variant in aggregate (`is_preview=0` only):
  - `engagement = 0.4*norm(avg_scroll) + 0.4*norm(avg_duration) + 0.2*norm(sections_seen_avg)` where `norm(x)=min(1, x/cap)` with caps scroll=100, duration=180000ms, sections=7
  - `conversion = sessions >= min_sessions ? leads/sessions : null` (insufficient sample)
  - If conversion null → score = engagement only for ranking display but **do not** propose kill
  - Else `score = w_eng*engagement + w_conv*conversion`
- Leads count: `COUNT(*)` from `dom_mkt_leads` where `landing_variant = slug` AND `created_at` in window AND `deleted = 0` (document in PHPDoc; visitor_id join deferred)
- Before insert: if existing `pending` has identical `suggested_weights` (sorted JSON) → `proposals_created=0`, no insert
- Else: `supersedeAllPending()` then `insertPending(...)`
- Compare suggested weights vs current: if max abs delta ≥ `proposal_min_delta` → insert proposal `pending` with payload:

```json
{
  "window_days": 14,
  "rankings": [{"slug":"v2","score":0.71,"sessions":120,"leads":8,"engagement":0.6,"conversion":0.066}],
  "current_weights": {"v1":0.7,"v2":0.3},
  "suggested_weights": {"v1":0.4,"v2":0.6},
  "reason": "material_delta"
}
```

- Suggested weights: proportional to score among arms with enough sessions; then **normalize to sum 1.0**; floor exploration `min_explore_weight` for active manifesto arms still below sample; arms below min_sessions keep current weight (then re-normalize remaining mass)
- Kill suggestion (`weight→0`) only if sessions≥N and score is lowest and materially below second place
- **Never** call `upsert` weights from this use case

- [ ] **Step 1: Unit tests with fake aggregations**

```php
test('compute crea proposal pending cuando delta material', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug'=>'v1','sessions'=>100,'avg_scroll'=>40,'avg_duration_ms'=>20000,'leads'=>2,'top_exit_section'=>'pricing','sections_seen_avg'=>3],
        ['variant_slug'=>'v2','sessions'=>100,'avg_scroll'=>80,'avg_duration_ms'=>60000,'leads'=>10,'top_exit_section'=>'faq','sections_seen_avg'=>6],
    ]);
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new ComputeVariantScoresUseCase($metrics, $weights, $props, new LandingVariantRegistry($cfg), $exp);
    $out = $uc->ejecutar();
    assert_true($out['proposals_created'] >= 1, 'crea proposal');
    assert_same('pending', $props->rows[0]['status']);
    assert_same(0.5, $weights->get('v1'), 'no muta pesos');
});

test('compute no propone kill bajo minimo de sesiones', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug'=>'v1','sessions'=>10,'avg_scroll'=>10,'avg_duration_ms'=>1000,'leads'=>0,'top_exit_section'=>'hero','sections_seen_avg'=>1],
        ['variant_slug'=>'v2','sessions'=>100,'avg_scroll'=>80,'avg_duration_ms'=>60000,'leads'=>10,'top_exit_section'=>'faq','sections_seen_avg'=>6],
    ]);
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new ComputeVariantScoresUseCase($metrics, $weights, $props, new LandingVariantRegistry($cfg), $exp);
    $out = $uc->ejecutar();
    if ($out['proposals_created'] > 0) {
        $sug = $props->rows[0]['payload']['suggested_weights'];
        assert_true((float) $sug['v1'] > 0.0, 'no kill v1 por sample bajo');
    }
});

test('compute supersede pending previa en segundo run con pesos distintos', function (): void {
    $metrics = new FakeAggregateMetrics([
        ['variant_slug'=>'v1','sessions'=>100,'avg_scroll'=>40,'avg_duration_ms'=>20000,'leads'=>2,'top_exit_section'=>'pricing','sections_seen_avg'=>3],
        ['variant_slug'=>'v2','sessions'=>100,'avg_scroll'=>80,'avg_duration_ms'=>60000,'leads'=>12,'top_exit_section'=>'faq','sections_seen_avg'=>6],
    ]);
    $weights = new FakeWeights(['v1'=>0.9,'v2'=>0.1]);
    $props = new FakeProposals();
    $cfg = require ROOT_PATH.'/config/marketing/landing_variants.php';
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new ComputeVariantScoresUseCase($metrics, $weights, $props, new LandingVariantRegistry($cfg), $exp);
    $uc->ejecutar();
    $weights->upsert('v1', 0.85);
    $weights->upsert('v2', 0.15);
    $uc->ejecutar();
    $pending = array_values(array_filter($props->rows, static fn ($r) => $r['status'] === 'pending'));
    assert_same(1, count($pending), 'solo una pending');
});
```

- [ ] **Step 2: Implement use case + CLI** mirroring `scripts/expire-api-demos.php` bootstrap.

CLI output lines: `proposals_created=N`, per-slug score dump.

Document cron example in script header:

```
0 6 * * * cd /path/to/app && php scripts/compute-landing-variant-scores.php
```

- [ ] **Step 3: Confirm `proposal_min_delta` and `min_explore_weight` in config** (Task 1).

- [ ] **Step 4: Run tests + commit (suggested)**

```bash
git add app/Application/Marketing/ComputeVariantScoresUseCase.php scripts/compute-landing-variant-scores.php app/Domain/Marketing/Contracts/VariantProposalRepositoryInterface.php app/Infrastructure/Marketing/PdoVariantProposalRepository.php tests/Marketing/ComputeVariantScoresUseCaseTest.php
git commit -m "feat(marketing): compute hybrid scores and pending weight proposals"
```

---

### Task 9: Ops UI Accept / Reject

**Files:**
- Create: `app/Application/Marketing/AcceptVariantProposalUseCase.php`
- Create: `app/Application/Marketing/RejectVariantProposalUseCase.php`
- Create: `app/Presentation/Controllers/Admin/MarketingExperimentsController.php`
- Create: `app/Presentation/Views/admin/marketing/experiments.php`
- Modify: `routes/marketing_admin.php`
- Modify: `config/container.php`
- Test: `tests/Marketing/AcceptVariantProposalUseCaseTest.php`
- Test: `tests/Marketing/MarketingExperimentsControllerContractTest.php`

**Interfaces:**
- Routes (RBAC `marketing.experimentos` + CSRF on POST):
  - `GET /admin/marketing/experimentos` → dashboard
  - `POST /admin/marketing/experimentos/accept` (`proposal_id`)
  - `POST /admin/marketing/experimentos/reject` (`proposal_id`)
- Accept (Anti-deuda §E + §S):
  1. Load proposal; require `status === 'pending'` else throw domain exception
  2. Compare `payload.current_weights` vs `weights->all()` (abs delta per slug ≤ 1e-4); on mismatch → reject apply, return stale error (controller flash + redirect, HTTP 409 semantics)
  3. Normalize `suggested_weights` so sum === 1.0 (or reject if sum ≤ 0)
  4. **Transaction:** `UPDATE … WHERE id=? AND status='pending'` (rowCount must be 1) → upsert weights → commit. On zero rows → rollback + stale/resolved error. Never leave accepted without weights or weights without accepted.
- Reject: `UPDATE … WHERE id=? AND status='pending'` only; no weight mutation; no-op/error if already resolved
- Dashboard: metrics from `aggregateForScore` live + pending list with current vs suggested
- Dashboard hint (copy): “No aceptes con sample &lt; min_sessions o tráfico anómalo (bots)” — Anti-deuda §Y
- UI must show warning if stale (current_weights drift) before Accept when possible (recompute display from live weights)

- [ ] **Step 1: Accept use-case tests**

```php
test('accept aplica suggested_weights y cierra proposal', function (): void {
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1'=>0.2,'v2'=>0.8],
        'current_weights' => ['v1'=>0.5,'v2'=>0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 42);
    assert_same(0.2, $weights->get('v1'));
    assert_same(0.8, $weights->get('v2'));
    assert_same('accepted', $props->findById($id)['status']);
});

test('accept rechaza propuesta stale si pesos drift', function (): void {
    $weights = new FakeWeights(['v1'=>0.9,'v2'=>0.1]);
    $props = new FakeProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1'=>0.2,'v2'=>0.8],
        'current_weights' => ['v1'=>0.5,'v2'=>0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $threw = false;
    try {
        $uc->ejecutar($id, 42);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'stale');
    assert_same(0.9, $weights->get('v1'), 'no muta');
    assert_same('pending', $props->findById($id)['status']);
});

test('accept normaliza pesos a suma 1', function (): void {
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1'=>1.0,'v2'=>1.0],
        'current_weights' => ['v1'=>0.5,'v2'=>0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 1);
    $sum = (float) $weights->get('v1') + (float) $weights->get('v2');
    assert_true(abs($sum - 1.0) < 1e-6, 'normalizado');
});

test('accept es no-op/fail si proposal ya no pending (doble submit)', function (): void {
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1'=>0.2,'v2'=>0.8],
        'current_weights' => ['v1'=>0.5,'v2'=>0.5],
    ]);
    $uc = new AcceptVariantProposalUseCase($props, $weights);
    $uc->ejecutar($id, 1);
    $threw = false;
    try {
        $uc->ejecutar($id, 2);
    } catch (Throwable $e) {
        $threw = true;
    }
    assert_true($threw, 'segunda accept falla');
    assert_same(0.2, $weights->get('v1'), 'pesos inalterados en 2do intento');
});

test('reject no cambia pesos', function (): void {
    $weights = new FakeWeights(['v1'=>0.5,'v2'=>0.5]);
    $props = new FakeProposals();
    $id = $props->insertPending([
        'suggested_weights' => ['v1'=>0.1,'v2'=>0.9],
        'current_weights' => ['v1'=>0.5,'v2'=>0.5],
    ]);
    $uc = new RejectVariantProposalUseCase($props);
    $uc->ejecutar($id, 7);
    assert_same(0.5, $weights->get('v1'));
    assert_same('rejected', $props->findById($id)['status']);
});
```

- [ ] **Step 2: Implement controller + view**

View: Bootstrap admin layout like `authorize_orden.php`. Metrics table + pending proposals with CSRF forms.

Contract test:

```php
test('MarketingExperimentsController exige CSRF en accept/reject', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/app/Presentation/Controllers/Admin/MarketingExperimentsController.php');
    assert_true(str_contains($src, 'verifyCsrf'), 'csrf controller');
});
test('routes marketing_admin registra experimentos con CSRF y RBAC', function (): void {
    $src = (string) file_get_contents(ROOT_PATH.'/routes/marketing_admin.php');
    assert_true(str_contains($src, '/marketing/experimentos'), 'ruta');
    assert_true(str_contains($src, 'marketing.experimentos'), 'rbac');
    assert_true(str_contains($src, 'CsrfMiddleware'), 'csrf middleware dual');
});
```

**UI pattern:** mirror `authorize_orden.php` (Bootstrap + `ViewHelper::csrfField()` + flash messages). Show live weights vs proposal `current_weights` drift warning before Accept.
- [ ] **Step 3: Run tests + commit (suggested)**

```bash
git add app/Application/Marketing/AcceptVariantProposalUseCase.php app/Application/Marketing/RejectVariantProposalUseCase.php app/Presentation/Controllers/Admin/MarketingExperimentsController.php app/Presentation/Views/admin/marketing/experiments.php routes/marketing_admin.php config/container.php tests/Marketing/AcceptVariantProposalUseCaseTest.php tests/Marketing/MarketingExperimentsControllerContractTest.php
git commit -m "feat(marketing): ops UI to accept or reject variant weight proposals"
```

---

### Task 10: Retention purge CLI (anti-growth debt)

**Files:**
- Create: `app/Application/Marketing/PurgeLandingMetricsUseCase.php`
- Create: `scripts/purge-landing-metrics.php`
- Modify: `LandingMetricsRepositoryInterface` → `purgeOlderThan(DateTimeImmutable $cutoff): array{sessions:int,events:int}`
- Test: `tests/Marketing/PurgeLandingMetricsUseCaseTest.php`

**Interfaces:**
- Default cutoff = now − `retention_days` (config, default 90)
- Delete events where `created_at < cutoff`, then sessions where `last_seen_at < cutoff` (events first to avoid orphan queries)
- Never deletes weights/proposals
- CLI prints `purged_sessions=N purged_events=M`
- Cron example (weekly):

```
0 4 * * 0 cd /path/to/app && php scripts/purge-landing-metrics.php
```

- [ ] **Step 1: Failing test**

```php
test('purge borra solo filas antiguas', function (): void {
    $repo = new FakeMetricsRepo();
    $repo->seedOldAndNew();
    $exp = require ROOT_PATH.'/config/marketing/landing_experiments.php';
    $uc = new PurgeLandingMetricsUseCase($repo, $exp);
    $out = $uc->ejecutar();
    assert_true($out['events'] >= 1, 'borra events viejos');
    assert_true($repo->countNewEvents() >= 1, 'conserva recientes');
});
```

- [ ] **Step 2: Implement + commit (suggested)**

```bash
git add app/Application/Marketing/PurgeLandingMetricsUseCase.php scripts/purge-landing-metrics.php app/Domain/Marketing/Contracts/LandingMetricsRepositoryInterface.php app/Infrastructure/Marketing/PdoLandingMetricsRepository.php tests/Marketing/PurgeLandingMetricsUseCaseTest.php
git commit -m "feat(marketing): purge old landing metrics for retention"
```

---

### Task 11: Docs, CTA data-attrs, smoke checklist, deprecate productive flag

**Files:**
- Modify: docs that still say `LANDING_VARIANT` selects traffic (one-line successor pointer on `2026-07-14-landing-v2-dark-redesign-design.md`)
- Modify: partial CTAs — `data-lb-cta="demo|pricing|nav"` on hero CTA, nav Solicitar demo
- Modify: design spec footer “Implemented via plan …” when done
- Patch design spec §Asignación sticky / Preview: preview **does not** write `lb_var`; sets short-lived `lb_preview`; collect trusts cookie not body — do this in the same docs pass
- Note privacy: cookies funcionales first-party; CMP fuera de alcance (§I)
- Cron examples already in scripts; optionally add one-liner under `docs/DEPLOY.md` **only if** that file already documents marketing crons — otherwise keep cron notes in script headers only (avoid orphan docs)
- Test: `php tests/run.php Marketing` green
- Manual smoke (human, local):
  1. Apply migration (idempotent re-run safe for columns/permisos); confirm `marketing.experimentos` visible for `administrador`
  2. `GET /` → Set-Cookie `lb_vid`, `lb_var`; **no** `lb_preview`
  3. View-source SEO title matches assigned variant manifesto
  4. Network: `/marketing/collect` on load (form-urlencoded)
  5. `?variant=v1` → preview; **no** Set-Cookie `lb_var`; **yes** `lb_preview`; events `is_preview=1`; next `/` without query keeps prior sticky and clears `lb_preview`
  6. Spoof POST collect `is_preview=0` with `lb_preview` still set → stored preview=1
  7. Run score CLI → at most one pending → Accept (stale path: change weights manually first → Accept fails cleanly)
  8. Lead POST stores `landing_variant`; preview lead does **not**
  9. Run purge CLI against empty/new DB → `purged_*=0`
  10. Confirm v1 page fires `section_view` (Network) — proves `data-section` hard gate
  11. Confirm repeated heartbeats do **not** explode `dom_mkt_landing_events` row count (session `duration_ms` advances)
  12. Spoof collect `variant_slug` ≠ `lb_var` → stored slug matches cookie
  13. `php tests/run.php Marketing` includes `LandingV2ViewTest` green after view fallback

- [ ] **Step 1: Add `data-lb-cta` + align design-spec preview / privacy note**

- [ ] **Step 2: Full Marketing suite**

Run: `php tests/run.php Marketing`  
Expected: all PASS.

- [ ] **Step 3: Suggested commit**

```bash
git add docs/superpowers/specs/2026-07-15-landing-experiments-metrics-design.md docs/superpowers/specs/2026-07-14-landing-v2-dark-redesign-design.md app/Presentation/Views/publico/partials
git commit -m "docs(marketing): finalize landing experiments metrics wiring"
```

---

## Self-Review

**1. Spec coverage**

| Spec requirement | Task |
|------------------|------|
| N variants via code manifests | 1 |
| Sticky cookies 30d + weighted assign | 4 |
| Assigner replaces binary prod selector | 5 (+1,4) |
| `?variant=` / `?landing=` preview excluded | 4,6,7,8 (+ Anti-deuda §B/K/N) |
| Sections + copy overrides + SEO | 5 |
| First-party collect beacon | 6 |
| Sessions/events tables | 2,3 |
| Hybrid score + pending proposals | 8 |
| Human Accept/Reject mutates weights | 9 |
| Lead `landing_variant` | 7 |
| `LANDING_VARIANT` seed hint only | 1,4 |
| Fallback all weights 0 → v2 | 4 |
| Ops metrics dashboard | 9 |
| Retention / unbounded table growth | 10 |
| No merge to main | Global Constraints |
| Tests listed in spec | 4–9, 10 |
| Onion side-effect / Domain I/O guardrails | Anti-deuda §A/J/O + Tasks 3–4 |
| Collect hardening (slug/UUID/cookie/sys_kv) | 6 + §C/L |
| Proposal spam + stale Accept | 8–9 |
| Shared section map (no dual views) | 5 + §G/P |
| `is_preview` non-spoofable | 4,6 + §K |
| `status=paused` reassignment | 4 |
| v1 engagement markers | 5 + §M |
| Single `lead_submit` source | 6–7 + §N |
| Rewrite env-locking selection test | 5 |
| View BC for LandingV2ViewTest | 5 + §P |
| Heartbeat session-only (no event flood) | 6 + §Q |
| `variant_slug` cookie preference | 6 + §R |
| Accept atomic / transactional | 9 + §S |
| `meta` allowlist | 6 + §T |
| Register orphan copy_seo migration | 2 + §U |
| UUID v4 strict | 6 + §V |
| seedMissing no overwrite | 3–4 + §W |
| HTTPS without Request::isSecure | 4 + §X |
| Bot bias deferred | §I/Y + Task 9 UI hint |

**2. Placeholder scan:** Removed `...` test stubs; collect form-encoded deliberate; preview cookie rule + `lb_preview` explicit; Accept stale/normalize/atomic covered; `sys_kv` protocol specified; AssignInput avoids Kernel Request in Application; heartbeat/meta/variant spoof/view BC locked.

**3. Type consistency:** Cookie names `lb_vid`/`lb_var`/`lb_preview`; proposal statuses `pending|accepted|rejected|superseded`; client event types exclude `lead_submit`; section catalog ids stable; `CookieSpec` applied only in Presentation; registry constructed from injected config array; rate limiter interface in Domain, PDO impl in Infrastructure; collect `handle(..., ?cookieVariant)` signature matches §R.

## Execution Handoff

Plan reforzado (Anti-deuda §§A–Y + Tasks 1–11, contrastado con código actual — incl. `LandingV2ViewTest`, `Request` sin `isSecure`, `sys_kv`, migración copy_seo huérfana) en `docs/superpowers/plans/2026-07-15-landing-experiments-metrics.md`. Two execution options:

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
