# Diseño — Marketing: rediseño visual del front público + demo WhatsApp

- **Fecha:** 2026-06-23
- **Módulo:** Marketing y Contenido Público (segunda pasada — estilo visual + poblar demo)
- **Estado de partida:** cimientos implementados y funcionando (`modules.marketing=true`).
- **Alcance:** front público (layout + landing) y datos demo. El admin (CRUDs/Ajustes) usa el tema existente, fuera de alcance.

## Contexto previo (ya hecho, no reimplementar)

- Módulo desacoplable estilo reportes/calendario. Manifiesto en `config/modules/marketing.php`, toggle en `config/vertical.php`, rutas en `routes/marketing.php` (incluidas condicionalmente desde `routes/web.php`).
- Front público: `app/Presentation/Views/publico/{layout,landing,portal}.php`. Controladores en `app/Presentation/Controllers/Publico/` (`LandingController`, `LeadController`, `PortalClienteController`).
- Landing renderiza: bloque `hero` (`dom_mkt_bloques`) + paquetes con features (`dom_mkt_paquetes`) + formulario de captación de leads (POST `/lead` con CSRF → `dom_mkt_leads`).
- CRUDs admin: `config/cruds/mkt_{leads,paquetes,bloques,plantillas,secuencias}.json`.
- Datos demo actuales (genéricos) en `database/schema/modules/marketing.sql` (1 paquete, 1 hero, 1 plantilla).
- Tests en `tests/Marketing/*.php`, ejecutados con `php tests/run.php Marketing`.
- Referencia **solo de contenido/estilo**: `nuevo_modulo/` (vertical WhatsApp SaaS, git-ignorada, NO es parte del framework).

## Hechos técnicos verificados

- `RenderLandingUseCase::ejecutar($pagina)` devuelve `{ bloques, paquetes }`.
- `PdoMarketingContentRepository::bloquesPorPagina()` devuelve **todos** los bloques activos de la página indexados por `clave` (JSON decodificado, `ORDER BY orden`). → Añadir claves nuevas (`trust`, `testimonios`, `footer`) fluye sin tocar la capa de datos.
- `dom_mkt_paquetes` ya tiene `precio_mensual`, `precio_anual`, `features` (JSON), `destacado`, `badge`, `orden`, `activo`.
- `dom_mkt_bloques` guarda `contenido` como JSON libre por `(pagina, clave)`.
- El layout público actual **no** inyecta variables de tema; usa Bootstrap CDN plano.
- Tema brandeable: `LebytekUiConfig::resolve(ConfiguracionService::all())` devuelve `primaryColor`, `primaryHover`, `primaryActive`, `primarySubtle`, `primaryRgb`, `lebytekCssVariables`, etc. El partial `app/Presentation/Views/partials/styles/lebytek_theme_vars.php` ya emite `--app-primary` y overrides de Bootstrap a partir de esas claves (acepta `includeNavChrome`).

## Decisiones de diseño (confirmadas)

1. **Secciones:** set de conversión completo — Hero + Trust bar + Pricing (toggle mensual/anual) + Testimonios + Formulario + Footer. Todo data-driven por bloques.
2. **Color/marca:** el front lee el color primario del **tema de la app** (rebrandea por instalación) + hero oscuro neutro. Sin color hardcodeado de WhatsApp en el front.
3. **Datos demo:** archivo seed **aparte**, idempotente por fila. `marketing.sql` conserva su seed genérico mínimo.
4. **Hero:** genérico con **slot de media configurable** (imagen/ilustración opcional definida en el bloque). Sin JS de dominio.

## 1. Principio de desacople

Ningún archivo del núcleo referencia clases de Marketing. Nada del dominio WhatsApp entra como código — el sabor WhatsApp vive solo como **datos** en el seed demo.

**Archivos del framework (genéricos):**
- `app/Presentation/Views/publico/layout.php` (M) — tema, fuentes, footer, links de assets.
- `app/Presentation/Views/publico/landing.php` (M) — orquesta partials, data-driven.
- `app/Presentation/Views/publico/partials/_hero.php`, `_trust.php`, `_pricing.php`, `_testimonios.php`, `_lead_form.php`, `_footer.php` (A).
- `public/assets/publico/landing.css`, `landing.js`, e ilustración SVG genérica del hero (A).
- `app/Presentation/Controllers/Publico/LandingController.php` (M) — resuelve y pasa el tema.
- `tests/Marketing/*` (A/M).

**Demo (claramente demo, idempotente):**
- `database/schema/modules/marketing_demo.sql` (A).

## 2. Integración con el tema existente

`LandingController` resuelve `LebytekUiConfig::resolve(ConfiguracionService::all())` y pasa `primaryColor / primaryRgb / primaryHover / primaryActive / primarySubtle` (y `lebytekCssVariables`) al layout. El layout público incluye el partial existente `partials/styles/lebytek_theme_vars.php` con `includeNavChrome = false`.

Resultado: CTAs, card de plan destacado, checks y acentos usan el color primario de la instalación; el hero mantiene un fondo oscuro neutro donde el acento resalta.

## 3. Sistema visual

- Capa de tokens CSS propia del front público que consume `--app-primary` para acentos + escala neutra propia + fondo hero oscuro con gradiente/glow suave.
- Tipografías: **Plus Jakarta Sans** (títulos) + **Inter** (texto) vía Google Fonts.
- Sombras suaves, radio ~1rem, espaciado generoso. Mobile-first, Bootstrap 5 + CSS propio.
- En implementación se invoca **/ui-ux-pro-max** para fijar paleta neutra exacta, pairing tipográfico y detalle de componentes (skill de implementación; va tras aprobar el diseño).

## 4. Secciones de la landing (data-driven)

`landing.php` renderiza cada partial **solo si su bloque existe**:

| Sección | Fuente | Clave / origen |
|---|---|---|
| **Hero** | bloque `hero` | `titulo`, `subtitulo`, `badge`, `cta_texto`/`cta_url`, `cta2_texto`/`cta2_url`, `media` (`img`, `alt`) — slot opcional |
| **Trust bar** | bloque `trust` | lista `{valor, etiqueta}` |
| **Pricing** | `paquetes` (existente) | cards con `badge`/`destacado` + **toggle mensual/anual** (JS lee `data-monthly`/`data-annual`, usa `precio_anual`) |
| **Testimonios** | bloque `testimonios` | lista `{texto, autor, avatar?}` + estrellas |
| **Formulario** | existente | restyled como card; mismo POST `/lead` + CSRF (sin cambios de lógica) |
| **Footer** | bloque `footer` (opcional) | columnas/links; fallback a footer simple |

Las claves nuevas (`trust`, `testimonios`, `footer`) son editables desde el CRUD `mkt_bloques` ya existente (editor JSON genérico). **No se crea ningún CRUD nuevo.**

## 5. Flujo de datos

`LandingController.index`:
1. Resuelve tema (`LebytekUiConfig::resolve(ConfiguracionService::all())`).
2. `renderLanding->ejecutar('home')` → `bloques` (hero + trust + testimonios + footer) y `paquetes`.
3. Renderiza `publico/landing` con `publico/layout` ya consciente del tema.

La capa de datos no cambia.

## 6. Datos demo WhatsApp (`marketing_demo.sql`)

`marketing.sql` conserva su seed genérico mínimo. El seed demo es **opcional**, idempotente y **demo-autoritativo**:

- **Paquetes:** desactiva el placeholder genérico (`UPDATE dom_mkt_paquetes SET activo=0 WHERE nombre='Plan Demo'`) e inserta **Básico / Pro / Empresa**, cada uno `WHERE NOT EXISTS (SELECT 1 FROM dom_mkt_paquetes WHERE nombre = …)`. `features` como `JSON_ARRAY(...)`, `destacado=1` y `badge='Más popular'` en Pro, precios mensual/anual.
- **Hero:** `UPDATE` del bloque `(home, hero)` con copy WhatsApp + `media`; `INSERT … WHERE NOT EXISTS` si faltara.
- **Trust / Testimonios / Footer:** `INSERT … WHERE NOT EXISTS` por `(pagina, clave)`.
- **Plantilla autoresponder:** `UPDATE` del copy al contexto demo.

Re-ejecutable sin duplicar.

**Contenido (de `nuevo_modulo/src/content.php`, solo texto):**
- Hero: "Envía mensajes de WhatsApp desde tus programas" / "API simple y confiable para automatizar notificaciones, alertas y mensajes a tus clientes." / badge "API de WhatsApp" / CTA "Solicitar demo".
- Trust: `REST API` (Integración simple), `< 5 min` (Tiempo de setup), `24/7` (Entrega confiable).
- Planes:
  - Básico — $69/mes / $599/año — 5 000 mensajes/mes, 1 número de WhatsApp, Soporte por correo.
  - Pro — $99/mes / $899/año — 30 000 mensajes/mes, 1 número de WhatsApp, Soporte prioritario — destacado, badge "Más popular".
  - Empresa — A medida — Mensajes ilimitados, Múltiples números, Soporte dedicado.
- Testimonios: 3 reseñas (María G. / Carlos R. / Lucía M.) con texto de la referencia.

**Media del hero:** SVG **genérico** creado en `public/assets/publico/` (mock de mensajería/app estilizado, **sin marca WhatsApp**), referenciado por el bloque demo. Mantiene el front desacoplado.

## 7. JS (vanilla, sin dominio)

`public/assets/publico/landing.js`: toggle de precio mensual/anual, scroll reveal opcional, navbar que se solidifica al hacer scroll. Nada específico de WhatsApp.

## 8. Testing (`php tests/run.php Marketing`)

Extiende el harness existente:
- Landing renderiza trust/testimonios cuando hay bloques y **los omite limpiamente** cuando no.
- Pricing muestra 3 planes con features, destacado y toggle (atributos `data-*`).
- El layout público inyecta las variables de tema (color primario presente en el HTML).
- Form de lead sigue posteando con CSRF (sin regresión).
- Se mantiene el test de desacople: ningún archivo del núcleo referencia el namespace Marketing.

## Restricciones

- Mantener el desacople: ningún archivo del núcleo referencia clases de Marketing; nada del dominio WhatsApp entra al framework como código.
- Datos demo idempotentes (`IF NOT EXISTS` / `WHERE NOT EXISTS` / `UPDATE` guardado), prefijo `dom_mkt_*`.
- Plataforma: PHP 8.1+, MVC+Onion propio, Bootstrap 5, harness de tests propio (`php tests/run.php`).

## Fuera de alcance

- Rediseño del admin (CRUDs/Ajustes) — usa el tema existente.
- Cambios en la lógica de captación de leads, portal cliente, o autoresponder (solo restyle del form y copy demo).
- Cambios de schema (`dom_mkt_*` ya soporta todo lo necesario).
