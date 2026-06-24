# Spec — Enriquecimiento del vertical-kit (referencia de patrones) + clasificación y limpieza de demos

**Fecha:** 2026-06-24
**Tipo:** diseño (spec). No es implementación.
**Repositorio:** Lebytek Framework (`contraste` / VPS auto-pull desde `main`).
**Audiencia:** Claude Code / Cursor que luego ejecutarán el plan derivado.
**Origen del contexto:** `docs/audits/2026-06-23-auditoria-salud-framework-input-brainstorm.md`, `vertical-kit/BRIEF-IA.md`, `vertical-kit/README.md`.

---

## 1. Objetivo y alcance

El **vertical-kit** (`vertical-kit/`) es el paquete que recibe una IA para generar un vertical
de negocio sobre el framework. Hoy solo enseña el **camino declarativo**: CRUD JSON
(`config/cruds/demo_*.json`), handlers de hook, configs de calendario/reportes y una
plantilla PDF. **No tiene referencia de cómo se construye un dominio real (Onion)** cuando el
CRUD Engine no basta, ni de **cómo se añade un conector de infraestructura externo**.

Este spec persigue dos cosas:

1. **Enriquecer el kit** para que la IA *vea* cómo se crean dominios y handlers reales y pueda
   **planear una configuración del framework** basándose en patrones existentes — usando los
   módulos nuevos (`marketing`, `integrations`) como **ejemplos documentados**.
2. **Limpieza y acomodo del framework**: establecer una **frontera clara entre core y material
   demo/ejemplo**, mediante un inventario clasificado + convención de nombres + test de frontera,
   y resolver los pocos archivos rotos/legacy.

### 1.1 Entregables (tres, un solo spec, independiente)

1. **`contexto/` enriquecido**: dos docs de referencia nuevos de los módulos `marketing` e
   `integrations` (read-only; la IA los **lee** para planear, **no los clona**), más actualización
   de `BRIEF-IA.md` para enrutar la decisión declarativo vs. dominio Onion vs. conector.
2. **Inventario + convención + test**: documento que etiqueta cada archivo del framework activo
   (core / demo / huérfano / legacy), una convención de nombres documentada y un test que verifique
   la frontera. **No se mueve ni renombra nada masivamente.**
3. **Limpieza puntual**: borrar el CRUD huérfano `config/cruds/clientes.json` y documentar el
   retiro del legacy `nuevo_modulo/`.

### 1.2 Decisiones tomadas en brainstorming

- **Forma del kit:** los módulos nuevos entran como **referencia documentada** en `contexto/`.
  `plantillas/` (lo clonable) **permanece intacto**.
- **Demos:** **clasificar + convención, sin mover**. Solo se corrigen los rotos/huérfanos.
- **Rotos/legacy:** **eliminar rotos + archivar legacy**.
- **Relación con el audit de salud:** **spec enfocado e independiente**. Los temas del audit
  amplio quedan como no-goals.

### 1.3 No-goals (los aborda el audit de salud 2026-06-23, NO este spec)

- RBAC, `rbac_route_permissions.php`, integridad de rutas.
- Gate de rutas opcionales por toggle (`vertical.modules.*`).
- Auto-registro de módulos (rutas/DI/providers desde manifiesto).
- Unificar `config/settings_sections.php` / `config/dashboard.php` con el container.
- Poblar `menu`/`providers` en manifiestos.
- Mover físicamente demos a cuarentena o purgarlos del core.
- Renombrado masivo de archivos.

---

## 2. Enriquecer `contexto/` — referencia de patrones

`vertical-kit/contexto/docs/modules/` hoy contiene: `calendario`, `menu`, `uso-de-modulo-dominio`.
Se añaden **dos documentos de referencia** que apuntan al **código vivo** (rutas `app/...` y, donde
ayude, `archivo:línea`), explicando el patrón sin pedir que se clone.

### 2.1 `contexto/docs/modules/patron-dominio-onion.md`

Enseña **cómo se arma un dominio real** cuando el recurso no encaja en el CRUD Engine. Estructura
del doc:

- **Cuándo usar este patrón** (vs. el declarativo): hay lógica que el motor no cubre (use cases,
  contratos de extensión, providers).
- **Capas, con el módulo `marketing` como ejemplo:**
  - `Domain` — contratos e invariantes: `app/Domain/Marketing/Contracts/*` (p. ej.
    `LeadCaptureHandlerInterface`, `LandingContentProviderInterface`, `ProvisionAdapterInterface`)
    y value objects: `app/Domain/Marketing/ValueObjects/*` (`Lead`, `LeadDraft`, `MagicLinkToken`…).
  - `Application` — orquestación: `app/Application/Marketing/CapturarLeadUseCase.php`,
    `RenderLandingUseCase.php`.
  - `Infrastructure` — implementaciones: providers de contenido/paquetes/settings
    (`app/Infrastructure/Marketing/**`), pipeline de handlers de captura
    (`LeadCapture/PersistLeadHandler.php`, `AutoresponderHandler.php`, `NotifyInternalHandler.php`),
    repos (`app/Infrastructure/Repositories/PdoMarketingContentRepository.php`).
  - **Cableado:** bindings condicionales por toggle en `config/container.php` y rutas en
    `routes/marketing.php`; manifiesto `config/modules/marketing.php` con `permisos` poblados.
- **Relación con el contrato oficial:** enlazar a `docs/modules/uso-de-modulo-dominio.md` (ya en
  `contexto/`) como checklist formal; este doc es el **ejemplo trabajado** de ese checklist.

### 2.2 `contexto/docs/modules/patron-conector-integracion.md`

Enseña **cómo se añade un conector/canal externo desacoplado**, con el módulo `integrations` como
ejemplo:

- Connector HTTP genérico: `app/Infrastructure/Integrations/Http/HttpApiConnector.php`.
- Canales: `app/Infrastructure/Integrations/Channels/{EmailChannel,GreenApiWhatsappChannel}.php`.
- Partner connector: `app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php`.
- Cliente de cuenta: `app/Infrastructure/Integrations/GreenApi/GreenApiAccountClient.php`.
- Repos de cuenta/log: `app/Infrastructure/Integrations/Repositories/*`.
- Settings provider: `app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php`.
- Manifiesto: `config/modules/integrations.php` (`permisos` = `integrations.ver|enviar|configurar`,
  `bootstrap_sql` = `database/schema/modules/integrations.sql`).
- **Nota de desacople:** el dominio concreto (p. ej. WhatsApp) vive como datos/demo, no como
  lógica acoplada al core (coherente con el no-goal del audit de salud §8).

### 2.3 Actualizar `vertical-kit/BRIEF-IA.md`

- En §1–§2: añadir un **árbol de decisión** corto — *declarativo* (CRUD Engine: SQL + JSON
  (+ handlers)) → *dominio Onion* (use cases/contratos/providers) → *conector* (API/canal externo),
  enlazando a los dos docs nuevos.
- En la tabla final "Fuentes de verdad en `contexto/`": añadir filas para
  `patron-dominio-onion.md` y `patron-conector-integracion.md`.

> Estos docs son **referencia** (`contexto/`). No se añade nada a `plantillas/`.

---

## 3. Inventario + convención de demos (sin mover)

### 3.1 Documento de inventario

Crear `docs/audits/2026-06-24-inventario-demo-vs-core.md` que clasifique cada archivo relevante en
una de cuatro categorías:

| Categoría | Significado | Ejemplos (no exhaustivo) |
|---|---|---|
| `core` | Plataforma; no tocar | kernel, `auth_*`, ajustes, plantillas PDF de producto (`ContratoTemplate`, `PresupuestoTemplate`, `TablaEstadisticaTemplate`, `TicketCompraTemplate`) |
| `demo` | Ejemplo didáctico que vive en el repo | `config/cruds/demo_*.json`, `config/calendars/demo_citas.json`, `config/reportes/*.json` (incl. `clientes.json` → `demo_clientes`), `app/Application/Crud/Handlers/Demo*.php`, `EnviarWhatsappDemoHandler.php`, `app/Application/Pdf/Templates/DemoReporteTemplate.php`, `database/schema/modules/crud-engine.sql`, `database/schema/modules/marketing_demo.sql` |
| `huerfano` | Roto: apunta a algo inexistente | `config/cruds/clientes.json` (tabla `dom_clientes` no existe en `database/schema/`) |
| `legacy` | Fuera de arquitectura | `nuevo_modulo/` (app PHP plana en la raíz, **no trackeada por git**) |

El documento debe listar, por cada archivo demo, **a qué demo pertenece** y si tiene dependencia con
código core (idealmente: ninguna).

### 3.2 Convención de nombres (documentada, sin renombrado masivo)

- Prefijo **`demo_`** para configs/SQL/handlers/templates de ejemplo: `config/cruds/demo_*.json`,
  `database/schema/modules/crud-engine.sql` (tablas `dom_demo_*`), `Demo*Handler.php`,
  `Demo*Template.php`, `*DemoHandler.php`.
- Las plantillas PDF de producto se confirman como `core` (no llevan prefijo demo).
- La convención se documenta en el inventario y se referencia desde `BRIEF-IA.md` §3 (reglas de
  nombrado). **No** se renombran archivos existentes en esta entrega salvo el huérfano que se borra.

### 3.3 Test de frontera

Añadir una suite a **`tests/run.php`** (harness principal del proyecto) que verifique:

1. **Sin CRUDs huérfanos:** todo `config/cruds/*.json` debe declarar una `table` que exista en
   algún `database/schema/**.sql` activo. (Atrapa huérfanos presentes y futuros.)
2. *(Opcional, si es barato)* que ningún archivo `demo_*` sea importado/requerido por código de
   `core` (los demos son hojas, no dependencias).

Criterio: la suite pasa en verde tras la limpieza de §4.

---

## 4. Limpieza puntual

- **Borrar** `config/cruds/clientes.json` — huérfano confirmado (`table: dom_clientes`, sin tabla en
  schema).
- **Conservar** `config/reportes/clientes.json` — **no** es huérfano (su `resource` es
  `demo_clientes`, que sí existe). Se reclasifica como `demo` en el inventario.
- **`nuevo_modulo/`** — no está trackeado por git (limpieza de workspace, no de repo). El spec
  recomienda **eliminarlo localmente** o moverlo fuera del árbol activo (p. ej. `docs/legacy/` o
  repo aparte). Queda documentado en el inventario como `legacy` a retirar; lo ejecuta el operador.
- Tras borrar el huérfano, el **test de frontera (§3.3) debe pasar** como criterio de cierre.

---

## 5. Criterios de éxito y verificación

- [ ] Existen `vertical-kit/contexto/docs/modules/patron-dominio-onion.md` y
      `patron-conector-integracion.md`, enlazados desde `BRIEF-IA.md` (árbol de decisión + tabla de
      fuentes de verdad).
- [ ] `docs/audits/2026-06-24-inventario-demo-vs-core.md` clasifica cada archivo relevante en
      core/demo/huérfano/legacy y documenta la convención `demo_`.
- [ ] `config/cruds/clientes.json` eliminado; ningún `config/cruds/*.json` apunta a tabla
      inexistente.
- [ ] Test de frontera integrado en `tests/run.php` y en verde (`php tests/run.php`).
- [ ] `nuevo_modulo/` documentado como legacy a retirar.
- [ ] El spec **no** modifica RBAC, toggles, `config/container.php`, rutas ni manifiestos (respeta
      los no-goals de §1.3).

| Check | Comando / artefacto |
|---|---|
| Suite completa (incl. frontera) | `php tests/run.php` |
| Sin CRUD huérfanos | suite de frontera nueva |
| Docs de referencia presentes | `vertical-kit/contexto/docs/modules/patron-*.md` |
| Inventario presente | `docs/audits/2026-06-24-inventario-demo-vs-core.md` |

---

## 6. Self-review

- **Placeholders:** sin TBD; las dos sub-decisiones abiertas (ubicación del inventario → `docs/audits/`;
  test → `tests/run.php`) quedaron resueltas con los defaults del repo.
- **Consistencia interna:** §2 (referencia, no clonar) coherente con la decisión de brainstorming;
  §3–§4 coherentes con "clasificar sin mover" + "borrar rotos / archivar legacy".
- **Alcance:** enfocado; los temas de modularidad/RBAC están explícitamente fuera (§1.3).
- **Ambigüedad:** `clientes.json` (CRUD) huérfano a borrar vs. `reportes/clientes.json` válido a
  conservar — diferenciados explícitamente en §4.
