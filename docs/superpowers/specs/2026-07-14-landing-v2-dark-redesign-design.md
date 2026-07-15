# Landing v2 — Rediseño oscuro (flag-based)

**Fecha:** 2026-07-14
**Rama:** feature/backoffice-api-integration
**Origen del diseño:** Claude Design `Lebytek Landing v2.dc.html`
(project `b667ba5f-062b-46d8-a9c5-f083417163c5`)

## Objetivo

Implementar un nuevo landing (tema oscuro, tipografías Syne + Space Grotesk)
importado desde Claude Design, **sin borrar ni modificar el landing actual**.
Ambas variantes coexisten; se elige en runtime mediante un flag. Esto permite
probar el nuevo landing un tiempo con reversión inmediata (rollback) al actual.
En un futuro (fuera de este alcance) el flag será alimentado por un pipeline de
métricas; por ahora es un simple valor de configuración.

## Decisiones acordadas

1. **Rollback por flag de configuración** — no por copia de archivos ni solo por
   rama git. Las dos variantes conviven y se alternan sin tocar archivos.
2. **Datos reales** — precios/planes desde `$paquetes` con links `/comprar` y
   `comprasHabilitadas`; FAQ/features/testimonios/hero/trust desde `$bloques`.
3. **Formulario de demo real** — se conserva el estilo del diseño pero se cablea
   a `POST /lead` con CSRF y los campos reales del backend, añadiendo `email`.

## Naturaleza del archivo importado

El `.dc.html` usa el DSL de Claude Design (`<x-dc>`, `DCLogic`, `sc-for`,
`sc-if`, bindings `{{ }}`, `IntersectionObserver`, estado tipo React). **No es
HTML de arrastrar y soltar**: su lógica y estilos inline se portan a la
estructura PHP/`ViewHelper` del repo y a JS vanilla. El contenido semántico
(secciones, copy, animaciones) se preserva; la implementación se adapta.

## Arquitectura

### Selección de variante (flag + override)

- `.env.example`: añadir `LANDING_VARIANT=v1` (default).
- `config/app.php`: exponer el valor (p.ej. `'landing_variant' => env('LANDING_VARIANT', 'v1')`),
  siguiendo el patrón de acceso a env/config existente en el repo.
- `LandingController::index`: resolver la variante efectiva.
  - Base: valor de config `landing_variant`.
  - Override de preview por query: `?landing=v2` (o `v1`), replicando el patrón
    de `?compras=` ya usado en el controlador. Solo afecta el render.
  - Si la variante es `v2` → renderizar vista `publico/landing_v2` con layout
    `publico/layout_v2`. En cualquier otro caso → vista/layout actuales
    (`publico/landing` + `publico/layout`), **sin cambios**.
  - El **mismo view-model** (`$bloques`, `$paquetes`, `comprasHabilitadas`,
    variables de UI, `empresaNombre`, `empresaLogo`, `pageTitle`,
    `metaDescription`) se pasa a ambas variantes.
- **Reversión:** `LANDING_VARIANT=v1` (o dejar de pasar `?landing=v2`). El
  landing actual nunca se sobrescribe.

### Archivos nuevos (todos aditivos)

- `app/Presentation/Views/publico/layout_v2.php`
  Shell oscuro: fuentes Syne + Space Grotesk, CSS autocontenido (sin dependencia
  de Bootstrap), nav sticky tipo glass y footer con borde superior verde. Marca
  desde `empresaNombre`/`empresaLogo`. Mantiene `pageTitle`/`metaDescription`
  del controlador; `theme-color` acorde al tema oscuro (`#05070F`). Enlaza
  `landing_v2.css` y `landing_v2.js`.
- `app/Presentation/Views/publico/landing_v2.php`
  Orquestador que hace `echo ViewHelper::render(...)` de los parciales v2 en el
  orden del diseño: hero, trust, features, pricing, testimonios, faq, lead_form.
- `app/Presentation/Views/publico/partials/v2/`
  `_hero.php`, `_trust.php`, `_features.php`, `_pricing.php`, `_testimonios.php`,
  `_faq.php`, `_lead_form.php`. Markup oscuro nuevo que **lee las mismas claves**
  de `$bloques`/`$paquetes` que los parciales v1 equivalentes.

### Assets nuevos (no se tocan `landing.css` / `landing.js`)

- `public/assets/publico/landing_v2.css`
  Estilos inline del diseño portados a clases `lb-*`, tema oscuro, breakpoints
  `@media (max-width: 860px)` y `(max-width: 560px)`, keyframes `dotMove`,
  `hubPulse`, `floatY`, `drift`, y `@media (prefers-reduced-motion: reduce)`.
- `public/assets/publico/landing_v2.js`
  Port vanilla de la lógica React del diseño:
  - Scroll-reveal con `IntersectionObserver` (atributo `data-reveal-id` /
    `data-reveal`), un solo disparo por sección.
  - Acordeón FAQ de apertura única.
  - Toggle de facturación mensual/anual reutilizando el contrato
    `data-monthly`/`data-annual` y `data-compra-monthly`/`data-compra-annual`
    que el pricing v1 ya usa.
  - Respeta `prefers-reduced-motion`.

## Cableado de datos reales

### Pricing (`$paquetes`, `comprasHabilitadas`)

Campos por paquete: `nombre`, `precio_mensual`, `precio_anual`, `features`
(array o JSON string), `destacado` (→ featured), `badge`, `slug`.

- Precio con toggle mensual/anual usando `precio_anual`.
- Slugs comprables: `starter`, `business`. Botón "Comprar ya" →
  `/comprar/{slug}?ciclo=monthly|annual`, solo si `comprasHabilitadas` y precio
  numérico > 0.
- Enterprise / precio no numérico → sin precio numérico y CTA "Contactar".
- Formato de precio y reglas idénticas a `_pricing.php` v1 (reutilizar la misma
  lógica de formato/gating para evitar divergencia).

### Hero / trust / features / testimonios / FAQ (`$bloques`)

Cada parcial v2 lee las mismas subclaves que su equivalente v1
(`$bloques['hero']`, `['trust']`, `['features']`, `['testimonios']`,
`['faq']` con `titulo`/`lead`/`items[]` de `pregunta`/`respuesta`). El copy del
diseño se usa como fallback cuando el bloque venga vacío.

### Formulario de demo (`POST /lead`)

- `<form method="POST" action="/lead">` con `ViewHelper::csrfField()`.
- Campos reales del backend: `nombre`, `email` (**añadido**, requerido),
  `telefono`, `mensaje`.
- Campo opcional "Empresa": input visual que se **fusiona en `mensaje`** al
  enviar (vía JS), sin cambios en el backend.
- Reutiliza la muestra de flash (`success`/`error`) del `_lead_form.php` v1.

## Manejo de errores / bordes

- Si un bloque de `$bloques` viene vacío, el parcial v2 hace fallback al copy del
  diseño o se omite (igual criterio que v1, que hace `return` temprano cuando no
  hay datos).
- Si `$paquetes` está vacío, la sección de pricing no se renderiza (como v1).
- Variante desconocida en el flag/query → se trata como `v1` (fail-safe hacia el
  landing estable).

## Testing

- `php tests/run.php Marketing`.
- Añadir un smoke test: con un view-model de ejemplo, la vista `landing_v2` y sus
  parciales renderizan sin errores de PHP.
- Si el harness lo permite, test de que el flag/override selecciona la vista
  correcta (`v1` vs `v2`).
- Verificación manual: `/?landing=v2`.

## Fuera de alcance

- Pipeline de métricas que elija la variante automáticamente (futuro).
- Cambios en el backend de `/lead` o en la estructura de `$paquetes`/`$bloques`.
- Refactor de los parciales v1 existentes.
