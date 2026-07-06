# Servicio A — Sitio comercial Lebytek (3 servicios + embudo) — Design Spec

> **Estado:** diseño aprobado, pendiente de plan de implementación (writing-plans).
> **Contexto:** uno de los 4 servicios de dominio (A–D) descritos en
> `docs/superpowers/specs/2026-06-27-separacion-framework-v1-dominio-design.md` §7.
> Servicio **A = Sitio comercial Lebytek**: el sitio público real de la empresa que
> presenta los 3 servicios vendibles (B "API sola", C "salones/citas",
> D "software a la medida") y un **embudo de leads segmentado por servicio**.
> Se construye **sobre el módulo `marketing` ya existente** (no lo reimplementa).

---

## 0. ⚠️ Prerrequisito de verificación antes de planear (LEER PRIMERO)

**Este spec NO se ejecuta todavía.** Al momento de escribirlo, el plan de
**Separación Framework v1.0 / Dominio** (`docs/superpowers/plans/2026-06-27-separacion-framework-v1-dominio.md`)
está **en curso en segundo plano** (rename masivo de namespaces `App\` →
`Lebytek\Framework\`, partición de `container.php`, movimiento de archivos
app-level al esqueleto, creación de Repo 2). Iba por ~task 5 y **aún no termina
ni está verificado**.

**Antes de generar el plan de implementación de este Servicio A es obligatorio
re-verificar contra el código real**, porque el terreno se está moviendo:

1. **Namespaces.** Confirmar el namespace final de cada pieza que este spec toca.
   Hoy el módulo marketing es **dominio** (`App\…`) y consume framework
   (`Lebytek\Framework\…`), pero la separación puede haber movido archivos,
   renombrado clases o cambiado el destino (paquete vs esqueleto vs Repo 2).
   Verificar especialmente: `LandingController`, `RenderLandingUseCase`,
   `CommercialPackageSourceInterface`, `LeadDraft`, `LeadController`,
   `CapturarLeadUseCase`, middlewares (`CsrfMiddleware`), `LebytekUiConfig`,
   `ConfiguracionService`.
2. **Ubicación del módulo marketing.** Tras la separación, Marketing vive en
   **Repo 2 (`lebytek/producto`, namespace `App\`)** — confirmar que ya migró y
   que los paths (`routes/marketing.php`, `config/cruds/mkt_*.json`,
   `database/schema/modules/marketing.sql`, vistas `publico/`) existen en el repo
   destino donde se implementará A.
3. **Mecanismo de registro.** La separación promueve manifiestos + ServiceProvider.
   Verificar si las rutas/bindings de marketing ya pasan por un provider o siguen
   en el `routes/web.php` / `container.php` condicional al toggle. El plan de A debe
   enganchar al mecanismo **que exista en ese momento**.
4. **Firmas y datos confirmados aquí.** Todo lo "verificado (2026-06-28)" en este
   spec debe re-confirmarse: las firmas de método y columnas pueden haber cambiado.

> Regla operativa: **no escribir el plan de implementación de A hasta que la
> separación esté terminada y verde** (arnés `php tests/run.php` pasando en el repo
> destino). Entonces re-leer las piezas listadas arriba y ajustar el plan a la
> realidad del código.

---

## 1. Objetivo y alcance

Construir el **sitio comercial de Lebytek** sobre la infraestructura genérica del
módulo `marketing` (landing data-driven, captación de leads, CRUDs de contenido,
tema brandeable, portal cliente). A diferencia del demo actual del módulo —que
presenta **un solo producto** (API WhatsApp)— el Servicio A presenta **tres
ofertas** y enruta cada prospecto al servicio correcto.

### Estructura aprobada: hub + página por servicio
- **Home-hub** (`GET /`): hero de empresa + grid con los **3 servicios** (resumen
  + CTA). Cada tarjeta **enlaza a la página dedicada del servicio** (no despliega
  pricing inline).
- **Página por servicio**:
  - `GET /servicios/api` → producto "API sola" (servicio B)
  - `GET /servicios/salones` → vertical salones/citas (servicio C)
  - `GET /servicios/a-la-medida` → software a la medida (servicio D)
  - Cada una con su hero, features, **pricing filtrado a su servicio**, FAQ,
    formulario de captación (con interés pre-fijado) y footer.

### Alcance MVP (este spec)
- Routing hub + 3 páginas de servicio sobre el render existente.
- Filtrado de paquetes por servicio (`dom_mkt_paquetes.servicio`).
- Embudo de leads **segmentado** (`dom_mkt_leads.servicio_interes`) + segmentación
  en el CRM admin.
- Seed de contenido demo idempotente con copy real de los 3 servicios.

### Fuera de alcance (iteraciones / otros specs)
- **Conversión lead → cliente** de cada servicio (cada uno en su propio spec; el
  `lead_id` soft-ref ya está previsto, p. ej. `dom_apiwa_clientes.lead_id`).
- Pagos / checkout / facturación.
- CMS extendido (blog, páginas arbitrarias más allá de las 4 conocidas),
  multi-idioma, A/B testing, analítica avanzada.
- Portal cliente / aprovisionamiento (capacidad genérica ya existente; su uso por
  servicio va en el spec del servicio).

### Regla de separación respetada
Ningún archivo del **núcleo/framework** referencia clases de A: A es **dominio**
(`App\…`) que consume las interfaces y helpers del framework. El "sabor" comercial
de Lebytek vive como **datos** (seed) y **contenido editable** (CRUD), no como
código del framework. Sin FKs duras cruzando hacia tablas del núcleo.

---

## 2. Estado verificado del punto de partida (2026-06-28)

> Sujeto al §0: re-verificar tras la separación.

- **Módulo marketing existe y funciona** (`modules.marketing=true`): manifiesto
  `config/modules/marketing.php`, toggle en `config/vertical.php`, rutas en
  `routes/marketing.php` (incluidas condicionalmente desde `routes/web.php`).
- `routes/marketing.php` registra hoy **solo** 3 rutas públicas:
  `GET /` (`LandingController::index`), `POST /lead` (`LeadController::capturar`,
  con `CsrfMiddleware`), `GET /portal` (`PortalClienteController::entrar`).
  **No hay routing por servicio** — A lo añade.
- `LandingController::index` fija la página a `'home'` a mano y resuelve el tema
  vía `LebytekUiConfig::resolve(ConfiguracionService::all())`.
- `RenderLandingUseCase::ejecutar(string $pagina = 'home')` **ya acepta** el
  parámetro de página y devuelve `{ bloques, paquetes }`, pero
  `CommercialPackageSourceInterface::listarPaquetes()` **no recibe filtro**
  (devuelve todos los paquetes activos) — A añade el filtro por servicio.
- `dom_mkt_paquetes` tiene `nombre, precio_mensual, precio_anual, features (JSON),
  destacado, badge, orden, activo` — **sin** columna de servicio.
- `dom_mkt_leads` (CRUD `config/cruds/mkt_leads.json`) tiene `nombre, email,
  telefono, estado, mensaje, created_at` — **sin** `servicio_interes`.
- `LeadDraft` VO: `nombre, email, telefono?, mensaje?, utm[]` — **sin** interés.
- `CapturarLeadUseCase` ejecuta una **cadena de `LeadCaptureHandlerInterface`**
  (captura → notificación → autoresponder → …), abortable.
- `dom_mkt_bloques` indexa contenido por `(pagina, clave)`, JSON libre; el
  repositorio devuelve todos los bloques activos de la página por `clave`.
- `dom_mkt_paginas` está previsto en el modelo de datos del módulo (CMS opcional).
- Partials públicos existentes reusables: `publico/partials/_hero.php`,
  `_trust.php`, `_pricing.php`, `_testimonios.php`, `_lead_form.php`, `_footer.php`;
  layout `publico/layout.php` ya consciente del tema; assets
  `public/assets/publico/landing.{css,js}`.

---

## 3. Modelo de datos (aditivo, idempotente, `dom_mkt_*`)

Todo es **aditivo** sobre tablas existentes + filas de contenido. Bootstrap en un
seed demo nuevo idempotente (`database/schema/modules/marketing_servicios_demo.sql`),
re-ejecutable. `marketing.sql` (bootstrap del módulo) **no se toca** salvo las dos
columnas nuevas, que se añaden con `IF NOT EXISTS` / patrón de migración del repo.

### 3.1 `dom_mkt_paquetes` — nueva columna `servicio`
| Columna | Tipo | Notas |
|---|---|---|
| `servicio` | VARCHAR(20) NULL | `api` \| `salones` \| `a_la_medida`. NULL = no asignado (no se muestra en páginas de servicio) |

- Índice `KEY(servicio, activo, orden)` para el listado por página.
- El CRUD `mkt_paquetes` añade el campo `servicio` (select) en el form y como
  filtro en el listado.

### 3.2 `dom_mkt_leads` — nueva columna `servicio_interes`
| Columna | Tipo | Notas |
|---|---|---|
| `servicio_interes` | VARCHAR(20) NULL | `api` \| `salones` \| `a_la_medida`. Origen del prospecto; clave de segmentación del CRM |

- El CRUD `mkt_leads` añade `servicio_interes` como **columna de listado**, **filtro**
  y campo en el form/detalle.
- Es referencia **blanda**: la conversión a cliente de cada servicio (que enlaza por
  `lead_id`) vive en el spec de ese servicio.

### 3.3 `dom_mkt_paginas` — registrar las 4 páginas
Filas (idempotentes por `slug`): `home`, `servicio_api`, `servicio_salones`,
`servicio_a_la_medida` (slug, título, layout público, publicada=1). Sirven como
catálogo de páginas válidas y para metadatos (título/SEO básico por página).

### 3.4 `dom_mkt_bloques` — contenido por `(pagina, clave)`
Sin cambios de esquema. Se **siembran** bloques por página de servicio. Convención
de `pagina`: `home`, `servicio_api`, `servicio_salones`, `servicio_a_la_medida`.
Claves por página (todas opcionales, render condicional al existir el bloque):

| `clave` | Uso |
|---|---|
| `hero` | título, subtítulo, badge, CTA(s), `media` (slot opcional) |
| `servicios` | **solo en `home`**: lista `{slug, titulo, resumen, icono, cta_texto}` para el grid-hub |
| `features` | lista de capacidades del servicio |
| `pricing_intro` | encabezado de la sección de precios (los precios vienen de `dom_mkt_paquetes` filtrados) |
| `faq` | lista `{pregunta, respuesta}` |
| `trust` / `testimonios` / `footer` | reutilizan los partials existentes |

> Todas las claves son editables desde el CRUD `mkt_bloques` ya existente (editor
> JSON genérico). **No se crea ningún CRUD nuevo.**

---

## 4. Routing y render (cambios en el módulo `App\…`, no en el framework)

### 4.1 Rutas (`routes/marketing.php`)
Añadir, dentro del bloque condicional al toggle:
```php
// Páginas de servicio del sitio comercial.
$router->get('/servicios/{slug}', [LandingController::class, 'servicio']);
```
`GET /` (home-hub) y `POST /lead` siguen igual. El `{slug}` se valida en el
controlador contra el set conocido.

### 4.2 `LandingController`
- `index()` (home): añade al view-model los **bloques de la home** incluido el
  bloque `servicios` (grid-hub). Sigue resolviendo el tema vía `LebytekUiConfig`.
- **Nuevo** `servicio(Request $request): Response`:
  1. Toma `slug` de la ruta; lo mapea a servicio canónico
     (`api|salones|a-la-medida` → `api|salones|a_la_medida`) y a página
     (`servicio_{servicio}`). Slug desconocido → **404** (response 404 del framework).
  2. `renderLanding->ejecutar("servicio_{servicio}", $servicio)`.
  3. Renderiza `publico/landing` (mismo layout/tema) con el `servicioActual` para
     pre-fijar el formulario.

> El set canónico de servicios vive en un único lugar del dominio (p. ej.
> constante/whitelist en el controlador o un pequeño VO `ServicioComercial`), para
> que routing, validación de lead y filtrado de paquetes compartan la misma fuente.

### 4.3 `RenderLandingUseCase`
- Firma: `ejecutar(string $pagina = 'home', ?string $servicio = null): array`.
- Pasa `$servicio` al package source: `listarPaquetes($servicio)`.

### 4.4 `CommercialPackageSourceInterface` + repo default
- Firma: `listarPaquetes(?string $servicio = null): array`.
- Default (CRUD/PDO): `WHERE activo = 1 AND (:servicio IS NULL OR servicio = :servicio) ORDER BY orden`.
- En `home` se llama sin servicio (no se renderiza pricing en la home; el grid solo
  enlaza). En páginas de servicio se filtra por su valor.

---

## 5. Embudo de leads segmentado

### 5.1 `LeadDraft` VO
Añadir `?string $servicioInteres` (último parámetro, opcional, para no romper
construcciones existentes) + getter `servicioInteres(): ?string`.

### 5.2 Formulario público (`_lead_form.php`)
- En **páginas de servicio**: campo oculto `servicio_interes` con el valor de la
  página (pre-fijado, no editable por el usuario).
- En la **home** (si se incluye un form general): `<select>` con las 3 opciones.
- Mismo `POST /lead` + `CsrfMiddleware` (sin cambios de seguridad).

### 5.3 `LeadController::capturar`
- Lee `servicio_interes` del request; lo **valida** contra la whitelist (valor
  inválido → se descarta a `null`, no rompe la captura); lo pasa al `LeadDraft`.

### 5.4 Persistencia
El handler de persistencia (`LeadRepositoryInterface` / `Pdo…`) guarda
`servicio_interes`. El resto de la cadena (notificación interna, autoresponder)
**no cambia** en este spec — la diferenciación por servicio del autoresponder/
secuencia queda para el subsistema de email marketing (otro spec).

### 5.5 CRM admin (`config/cruds/mkt_leads.json`)
- Columna `servicio_interes` en el listado (con badge por valor).
- **Filtro** por `servicio_interes` (segmentar el embudo por oferta).
- Campo en form/detalle.

---

## 6. Vistas y assets (públicos, genéricos)

- **Reusan** los partials existentes: `_hero`, `_pricing`, `_lead_form`, `_footer`,
  `_trust`, `_testimonios`.
- **Nuevo** partial `publico/partials/_servicios_grid.php`: renderiza el bloque
  `servicios` de la home como tarjetas; cada tarjeta enlaza a `/servicios/{slug}`.
- `landing.php` orquesta los partials data-driven (cada uno solo si su bloque/datos
  existen), exactamente como hoy; se le añade el grid-hub cuando hay bloque
  `servicios`.
- Tema brandeable ya resuelto (`LebytekUiConfig` → variables CSS de
  `partials/styles/lebytek_theme_vars.php`). Sin color hardcodeado por servicio.
- `landing.js`/`landing.css` existentes: el toggle mensual/anual del pricing y el
  scroll-reveal se reutilizan tal cual en las páginas de servicio.

---

## 7. Contenido / datos demo

Seed nuevo `database/schema/modules/marketing_servicios_demo.sql`, **idempotente
por fila** (`INSERT … WHERE NOT EXISTS` / `UPDATE` guardado), **demo-autoritativo**:

- **Páginas:** alta de `home`, `servicio_api`, `servicio_salones`,
  `servicio_a_la_medida` en `dom_mkt_paginas`.
- **Home-hub:** bloque `hero` de empresa + bloque `servicios` con las 3 tarjetas
  (slug, título, resumen, CTA).
- **Por página de servicio:** `hero`, `features`, `faq` con copy real de Lebytek.
- **Paquetes:** asignar `servicio` a los paquetes. Para **API** se reusan/etiquetan
  los planes ya definidos en el demo del servicio B (Básico/Pro/Empresa →
  `servicio='api'`). **Salones** y **a la medida**: planes propios o "cotización"
  (CTA contactar) según copy — el pricing es opcional por bloque/paquete.
- El demo single-product anterior (paquetes sin `servicio`) se **desactiva por fila**
  (`UPDATE … SET activo=0` / o se etiqueta `servicio='api'`), **no se borra**.

> Si el contenido comercial fino (copy final, precios definitivos de C y D) no está
> listo, el seed usa placeholders claros y el resto se edita desde los CRUD. El sitio
> funciona con las 4 páginas aunque alguna sección esté vacía (render condicional).

---

## 8. Registro, toggle y arranque

- **Sin módulo nuevo.** A vive dentro del módulo `marketing` existente (mismas
  rutas condicionales, mismo manifiesto, mismo toggle `modules.marketing`).
- El seed `marketing_servicios_demo.sql` se referencia como datos demo del módulo
  (idempotente, encendible/aplicable junto al bootstrap del módulo), consistente con
  el patrón `marketing_demo.sql`.
- **Compat hacia atrás:** con marketing activo pero **sin** el seed de servicios, el
  sitio se comporta como hoy (home single-product). Las columnas nuevas son NULL-
  tolerantes; las firmas con parámetro opcional no rompen llamadas existentes.

---

## 9. Estrategia de pruebas (`php tests/run.php Marketing`)

Extiende el arnés existente del módulo:

1. **Routing/render de servicio:** `GET /servicios/api` (y los otros 2) renderiza el
   hero + pricing **filtrado a su servicio**; un slug desconocido (`/servicios/x`)
   → **404**.
2. **Filtrado de paquetes:** `listarPaquetes('api')` devuelve solo paquetes
   `servicio='api'`; `listarPaquetes(null)` (home) no impone filtro; un paquete con
   `servicio` distinto **no** aparece en otra página.
3. **Home-hub:** la home lista los **3 servicios** del bloque `servicios` y cada
   tarjeta enlaza a `/servicios/{slug}` correcto.
4. **Lead segmentado:** `POST /lead` desde una página de servicio persiste
   `servicio_interes` correcto; valor inválido se guarda como `null` sin romper la
   captura; el form mantiene CSRF (sin regresión).
5. **CRM:** el listado de `mkt_leads` expone columna + filtro por `servicio_interes`.
6. **Tema:** el layout público inyecta las variables de tema (color primario en el
   HTML) en las páginas de servicio igual que en la home.
7. **Desacople (regresión):** ningún archivo del núcleo/framework referencia el
   namespace del dominio marketing; el toggle off deja el sitio inerte.

---

## 10. Entregables y secuencia

> Precondición global: la separación v1.0 terminada y verde (§0).

1. **Datos** — columnas `servicio` (paquetes) y `servicio_interes` (leads) +
   `dom_mkt_paginas` de las 4 páginas; ajustes a `mkt_paquetes.json` /
   `mkt_leads.json`.
2. **Render** — `RenderLandingUseCase` + `CommercialPackageSourceInterface` con
   filtro por servicio; whitelist canónica de servicios.
3. **Routing/controlador** — `GET /servicios/{slug}` + `LandingController::servicio`
   con 404 para slug desconocido.
4. **Embudo** — `LeadDraft.servicioInteres` + `LeadController` + persistencia +
   `_lead_form` (hidden/select).
5. **Vistas** — `_servicios_grid.php` + orquestación en `landing.php`.
6. **Demo** — `marketing_servicios_demo.sql` idempotente con copy de los 3 servicios.
7. **Pruebas** — arnés verde con los 7 grupos de casos.

---

## 11. Decisiones registradas
- **Estructura** = hub + página por servicio (`/servicios/{slug}`), no landing única.
- **Home-hub** = tarjetas que **enlazan** a la página dedicada (sin pricing inline).
- **Enrutamiento de leads** = columna `servicio_interes` en `dom_mkt_leads` +
  segmentación en el CRM; conversión a cliente diferida a cada servicio.
- **Paquetes por servicio** = columna `servicio` en `dom_mkt_paquetes`; cada página
  filtra los suyos; reusa el partial `_pricing` y el CRUD `mkt_paquetes`.
- **Sin módulo nuevo** = todo aditivo sobre el módulo `marketing` existente.
- **Cambios retro-compatibles** = columnas NULL-tolerantes + parámetros opcionales.
- **Spec agnóstico al timing de la separación**, con prerrequisito explícito de
  re-verificación de namespaces y piezas en implementación (§0).
