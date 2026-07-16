# Landing experiments — métricas first-party + multi-variante (semi-auto)

**Fecha:** 2026-07-15  
**Rama:** `feature/backoffice-api-integration`  
**Repo:** Lebytek_Framework  
**Sucesor de:** `2026-07-14-landing-v2-dark-redesign-design.md` (el flag binario `LANDING_VARIANT` deja de ser el selector de producción)

## Objetivo

Permitir **N variantes** de la landing pública (empezando por las shells actuales **v1** y **v2**), asignar una distinta a cada visitante nuevo, **medir** scroll / tiempo / sección de abandono / CTAs / leads, calcular un **score híbrido**, y proponer subidas/bajadas de peso que un humano **confirma** (promote / kill) antes de cambiar el tráfico.

Ops ve métricas y acepta/rechaza propuestas. **Devs** definen variantes en código (manifiestos). Más adelante se generarán variantes adicionales desde un catálogo de secciones bien tipado; el contrato del manifiesto debe anticipar esa migración (posible persistencia CMS sin rediseñar el assigner).

## Decisiones acordadas

| # | Decisión |
|---|----------|
| 1 | **Score híbrido:** engagement (scroll, tiempo, profundidad) como señal temprana + conversión (leads/sesiones) como señal fuerte. |
| 2 | **Qué varía:** copy + layout ligero (mostrar/ocultar y reordenar secciones) sobre shells existentes; no variantes “completas” independientes en v1 del producto. |
| 3 | **Quién edita:** devs (código / seeds / manifiesto); ops solo métricas y promote/kill. |
| 4 | **Pesos:** semi-automático — el sistema propone; humano Accept/Reject antes de mutar tráfico. |
| 5 | **Telemetría:** solo first-party (BD + beacon). Sin GA/Meta en esta fase. |
| 6 | **Selector de prod:** el **asignador de experimentos** sustituye el flag binario. v1 y v2 entran como primeras armas; luego más desde plantillas de secciones. |
| 7 | **SEO:** por variante `title`, `meta description`, H1/copy de hero. URL canónica única `/`. Sin cambios de sitemap/robots. |
| 8 | **Sticky:** cookie `lb_var` TTL **30 días**; revisit → misma variante si sigue activa. |

## Fuera de alcance (esta versión)

- Edición de copy/orden desde CRUD admin (queda en code manifests).
- Bandit fully automatic (sin confirmación humana).
- Pixels / GA / Meta.
- Multi-URL por variante (`/v/slug`) o indexación separada.
- Generador visual de plantillas (sí el **contrato** de secciones que lo habilita después).
- Compras como señal del score (se puede añadir luego; leads bastan en v1).
- Merge a `main` (sigue la política de feature branch).

## Arquitectura — capas

| Capa | Responsabilidad | Ubicación |
|------|-----------------|-----------|
| **Shell** | Documento, fuentes, CSS/JS del tema | `layout.php` / `layout_v2.php` (+ futuros) |
| **Estructura** | Catálogo y orden/visibilidad de secciones | Manifiesto: `sections[{id, enabled}]` |
| **Contenido** | Copy, CTAs, SEO | CMS `dom_mkt_bloques` + `copy_overrides` / `seo` del manifiesto; paquetes compartidos desde `dom_mkt_paquetes` salvo override explícito |
| **Comportamiento** | Reveal, FAQ, billing + **collector** | Assets por shell + `landing_metrics.js` compartido |
| **Asignación** | Sticky weighted choice | `LandingExperimentService` / assigner |
| **Agregación** | Score + proposals | CLI/cron + tablas proposal/weights |
| **Ops UI** | Ranking, Accept/Reject | Admin marketing (dashboard o CRUD ligero) |

```text
GET /
  → assign sticky variant (cookie lb_vid + lb_var, TTL 30d)
  → load manifest + runtime weights
  → merge bloques + overrides + section order
  → render shell + partials
  → page emits metrics via POST /marketing/collect
```

Preview: `?variant=<slug>` fuerza render **sin** pisar cookie sticky `lb_var`, setea cookie corta HttpOnly `lb_preview=<slug>`, y **excluye** esa sesión/eventos del ranking/score (`is_preview` se deriva de `lb_preview` en el collector — el body no es fuente de verdad). Asignación no-preview borra `lb_preview`.

Compat flag legacy: en producción el assigner es la única fuente de verdad. `?landing=v1|v2` se trata como alias de `?variant=v1|v2`. `LANDING_VARIANT` deja de seleccionar tráfico; si existe en `.env`, solo se usa como **hint de seed** de `weight_default` en el bootstrap inicial (documentado en el plan), no en cada request.

## Manifiesto de variante (código)

Archivo canónico sugerido: `config/marketing/landing_variants.php` (o registry PHP cargado desde container).

Campos por entrada:

```text
slug: string                 # v1, v2, …
shell: v1|v2                 # layout/view pair
status: active|paused|archived
sections: [{ id, enabled }]  # id ∈ catalogo fijo
copy_overrides: { bloque_clave → patch parcial }
seo: { title, description }
weight_default: float        # bootstrap si aún no hay fila en BD
```

**Catálogo de secciones (v1 del contrato):**  
`hero`, `trust`, `features`, `pricing`, `testimonios`, `faq`, `lead_form`  
(ids alineados con `data-reveal-id` / parciales actuales).

**Seed inicial:** dos manifiestos `v1` y `v2` — shells distintas, mismas secciones enabled por defecto, SEO tomado del title/description actuales del controller (dejar de hardcodear en controller; pasar a manifiesto).

Añadir una variante nueva = clonar manifiesto + overrides + deploy. El manifiesto es el contrato estable cuando migréis a filas CMS.

## Persistencia (first-party)

| Tabla | Rol |
|-------|-----|
| `dom_mkt_variant_weights` | `slug`, `weight`, `updated_at` — pesos runtime; elegibilidad = manifiesto `status=active` **y** `weight > 0` |
| `dom_mkt_variant_proposals` | ranking/scores/pesos sugeridos, `status` (`pending\|accepted\|rejected\|superseded`), `created_at`, payload JSON |
| `dom_mkt_landing_sessions` | `visitor_id`, `variant_slug`, timestamps, `duration_ms`, `max_scroll_pct`, `exit_section` |
| `dom_mkt_landing_events` | eventos: `pageview`, `scroll_depth`, `section_view`, `heartbeat`, `exit`, `cta_click`, `lead_submit` (+ meta JSON mínima) |

**Atribución de conversión:** columnas en `dom_mkt_leads` (y, si aplica más tarde, órdenes): `landing_variant`, opcional `visitor_id`.

Migraciones bajo `database/migrations/` + mirror en `database/schema/modules/marketing.sql` según convención del módulo.

## Asignación sticky

1. Asegurar `lb_vid` (UUID anónimo, HttpOnly, SameSite=Lax, TTL ≥ 30d).
2. Si `lb_var` presente y la variante está `active` con `weight > 0` → reusar.
3. Si no → weighted random sobre armas elegibles; set `lb_var` **TTL 30 días**.
4. Reasignar si cookie inválida, variante pausada/archivada, o peso 0.
5. `?variant=` force **sin** escribir `lb_var` (solo QA de esa request; no cuenta score). El sticky previo permanece intacto. Se setea `lb_preview` (TTL corto); el collector usa esa cookie — no el flag del body — para `is_preview`.

No requiere login ni fingerprinting. Complejidad baja.

## Telemetría cliente → servidor

- Asset: `public/assets/publico/landing_metrics.js` incluido desde ambos layouts.
- Endpoint: `POST /marketing/collect` (ruta marketing, rate-limited vía `sys_kv` — no Session PHP, payload máximo, sin PII, CSRF-exempt + hardenings: UUID v4, allowlists, cookies preferidas sobre body).
- Eventos cliente:
  - `pageview` al load
  - `scroll_depth` buckets 25 / 50 / 75 / 100
  - `section_view` vía IntersectionObserver (`data-reveal-id` / `data-section` — **v1 debe tener `data-section`**)
  - `heartbeat` ~15s (tiempo activo) — **server:** actualiza sesión; no flood de filas `landing_events`
  - `exit` / `visibilitychange` → actualiza sesión (max_scroll, exit_section, duration)
  - `cta_click` (selector data-attribute)
- Lead: formulario incluye hidden `landing_variant`; cookie `lb_var` preferida server-side; **no atribuir** bajo `lb_preview`. Evento `lead_submit` **solo server-side** tras captura exitosa (el JS no lo emite).
- Transporte: `navigator.sendBeacon` / `fetch` keepalive; fallos silenciosos en cliente.
- Plan de implementación (guardrails Anti-deuda A–Y): `docs/superpowers/plans/2026-07-15-landing-experiments-metrics.md`.

## Score híbrido y propuestas

Ventana rolling configurable; **default 14 días**.

Conceptualmente:

```text
engagement = normalize(max_scroll, duration, sections_seen)
conversion  = leads / sessions   (sesiones con piso mínimo N, default N=50)
score = w_eng * engagement + w_conv * conversion
```

Pesos en config: default `w_eng=0.35`, `w_conv=0.65`.  
Piso mínimo de sesiones por variante antes de proponer kill o peso 0 (`N` arriba).

CLI/cron:

1. Agrega métricas por slug.
2. Si hay cambio material vs pesos actuales → inserta `dom_mkt_variant_proposals` `pending`.
3. **No** escribe weights automáticamente.

Al añadir una variante nueva con peso exploratorio, el proposal puede sugerir **rebajar a 0** las peores (con piso de exploración opcional en config).

## Ops UI (semi-auto)

Pantalla admin marketing (permiso tipo `marketing.gestionar` o dedicado `marketing.experimentos`):

- Tabla por variante: sesiones, scroll medio, tiempo medio, exit_section top, leads, tasa, score.
- Lista de propuestas pending con diff de pesos.
- Acciones: **Accept** (aplica `dom_mkt_variant_weights`, cierra proposal) / **Reject**.

Sin editor de copy en esta fase.

## SEO

- Una URL pública: `/` (canonical única).
- Por variante: `<title>`, `<meta name="description">`, H1/hero desde override o bloque.
- Sin cambios a sitemap/robots.
- Crawlers ven la variante que el assigner les asigne (limitación aceptada del A/B same-URL); no se implementa cloaking.

## Errores y degradación

| Caso | Comportamiento |
|------|----------------|
| Collect falla | Cliente ignora; no afecta UX |
| Sin filas weights | Usar `weight_default` del manifiesto / `seed_weight_defaults()` |
| Cookie variante desconocida | Reasignar |
| Todas peso 0 | Fallback fijo a slug `v2` si está `active` en manifiesto; si no, primera `active` por orden del archivo |
| Preview | Excluido del score (`lb_preview` → `is_preview=1`; body no cuenta) |
| Heartbeat | Actualiza sesión; **no** inserta fila event por tick (anti-crecimiento) |
| Spoof body slug/vid/preview | Preferir cookies HttpOnly; UUID v4 strict; meta allowlist |
| Accept concurrente / stale | UPDATE atómico `WHERE status=pending` + snapshot pesos; transacción |

## Testing

- Assigner: sticky, weights, reassignment on paused, `?variant=` **sin** escribir `lb_var`, set `lb_preview`.
- Merge: section order/hide, SEO fields, copy override shallow merge.
- Collect: valid/invalid payloads, rate limit, cookie preference (vid/var/preview), heartbeat sin event flood, reject `lead_submit` client.
- Proposal accept/reject mutates weights only on accept; doble accept falla; stale snapshot.
- Lead persists `landing_variant` (no bajo `lb_preview`).
- Views: `LandingV2ViewTest` sigue verde con fallback si falta `$sectionsHtml`.
- Smoke: `/` sets cookies; metrics script presente; admin proposal flow; purge CLI.

## Archivos / módulos tocados (orientativo)

- `LandingController`, nuevo Application service assigner + collector use case
- `config/marketing/landing_variants.php`, `config/app.php` / env para pesos score y TTL
- `routes/marketing.php` + controller collect; `routes/marketing_admin.php` + ops UI
- Migraciones + repos PDO Marketing
- `landing_metrics.js` + include en layouts v1/v2
- Lead form / `LeadController` / repository (columna variant)
- Tests bajo `tests/Marketing/`
- Deprecar uso productivo de solo `LANDING_VARIANT` documentando migración

## Criterios de éxito

1. Visitante nuevo puede recibir v1 o v2 según pesos; revisit ≤30d ve la misma.
2. Dashboard muestra métricas reales de scroll/tiempo/abandono/leads por variante.
3. Proposal → Accept cambia distribución de tráfico sin deploy de código.
4. SEO title/description distintos por variante en el HTML servido.
5. Marketing suite verde; sin merge a `main` requerido.

## Relación con landing v2

La coexistencia flag-based de v2 permanece como **implementación de shells**. Este diseño **eleva** v1/v2 a armas del experimento y añade pipeline de métricas que el spec v2 dejó explícitamente fuera de alcance.

## Privacidad y cookies (§I)

Las cookies `lb_vid`, `lb_var` y `lb_preview` son **funcionales first-party** del experimento (asignación sticky, preview QA y telemetría agregada). No almacenan PII en el cliente. Un banner de consentimiento / CMP legal queda **fuera de alcance** de esta versión; si el negocio lo exige más adelante, no sustituye por sí solo la necesidad de aviso de privacidad en sitio.

---

**Implemented via plan:** `docs/superpowers/plans/2026-07-15-landing-experiments-metrics.md` (Tasks 1–11, 2026-07-15).
