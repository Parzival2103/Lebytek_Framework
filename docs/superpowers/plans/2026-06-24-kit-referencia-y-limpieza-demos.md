# Kit de referencia + limpieza de demos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enriquecer el `vertical-kit` con documentación de referencia de los módulos nuevos (marketing/integrations), clasificar los archivos demo vs core con un test de frontera, y borrar el CRUD huérfano.

**Architecture:** Cambios puramente de documentación + un test de validación + un borrado. No se toca código de producción (controllers, container, rutas, manifiestos). El test de frontera vive en el harness `tests/run.php` (microtest, sin PHPUnit).

**Tech Stack:** PHP 8.1+, harness propio `tests/microtest.php` (`test()`, `assert_true()`, `assert_same()`), Markdown.

## Global Constraints

- **No-goals (NO tocar en este plan):** RBAC, `config/rbac_route_permissions.php`, gate de rutas por toggle, auto-registro de módulos, `config/settings_sections.php`, `config/dashboard.php`, `config/container.php`, `routes/*`, manifiestos `config/modules/*.php`.
- **Los docs nuevos van en `vertical-kit/contexto/`** (referencia, read-only). `vertical-kit/plantillas/` **no se modifica**.
- **No renombrado masivo:** la convención `demo_` se documenta; no se renombran archivos existentes salvo el huérfano que se borra.
- **Tests:** harness `php tests/run.php`; API `test(string $name, callable $fn)`, `assert_true(bool, string)`, `assert_same(mixed $expected, mixed $actual, string)`. Constante de raíz: `ROOT_PATH`. Los archivos de test terminan en `Test.php`.
- **CRUD JSON:** la tabla de cada recurso vive en `$cfg['resource']['table']`.
- **Spec de referencia:** `docs/superpowers/specs/2026-06-24-kit-referencia-y-limpieza-demos-design.md`.

---

### Task 1: Test de frontera CRUD + borrado del huérfano `clientes.json`

Garantiza que todo `config/cruds/*.json` apunte a una tabla que exista en `database/schema/`, y elimina el recurso roto `clientes.json` (tabla `dom_clientes` inexistente).

**Files:**
- Create: `tests/Crud/CrudConfigBoundaryTest.php`
- Delete: `config/cruds/clientes.json`

**Interfaces:**
- Consumes: `ROOT_PATH` (definido en `tests/bootstrap.php`); funciones `test()`, `assert_true()` de `tests/lib/microtest.php`.
- Produces: nada que consuman otras tareas (test hoja).

- [ ] **Step 1: Write the failing test**

Create `tests/Crud/CrudConfigBoundaryTest.php`:

```php
<?php
// tests/Crud/CrudConfigBoundaryTest.php
declare(strict_types=1);

test('todo config/cruds/*.json apunta a una tabla existente en database/schema', function (): void {
    // 1) Concatenar todo el SQL de schema activo (base + módulos).
    $schemaFiles = array_merge(
        glob(ROOT_PATH . '/database/schema/*.sql') ?: [],
        glob(ROOT_PATH . '/database/schema/modules/*.sql') ?: []
    );
    assert_true(count($schemaFiles) > 0, 'debe haber archivos de schema');

    $schema = '';
    foreach ($schemaFiles as $file) {
        $schema .= "\n" . (string) file_get_contents($file);
    }

    // 2) Cada CRUD JSON debe declarar resource.table y existir un CREATE TABLE para ella.
    $cruds = glob(ROOT_PATH . '/config/cruds/*.json') ?: [];
    assert_true(count($cruds) > 0, 'debe haber configs CRUD');

    foreach ($cruds as $path) {
        $name = basename($path);
        $cfg = json_decode((string) file_get_contents($path), true);
        assert_true(is_array($cfg), "{$name} es JSON válido");

        $table = $cfg['resource']['table'] ?? null;
        assert_true(is_string($table) && $table !== '', "{$name} declara resource.table");

        $pattern = '/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table, '/') . '`?/i';
        assert_true(
            preg_match($pattern, $schema) === 1,
            "{$name}: tabla '{$table}' debe tener CREATE TABLE en database/schema"
        );
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php CrudConfigBoundary`
Expected: FAIL con mensaje `clientes.json: tabla 'dom_clientes' debe tener CREATE TABLE en database/schema` (porque `dom_clientes` no existe en el schema).

- [ ] **Step 3: Delete the orphan CRUD config**

Run: `git rm config/cruds/clientes.json`

(Nota: `config/reportes/clientes.json` **NO** se toca — apunta a `demo_clientes`, que sí existe.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php CrudConfigBoundary`
Expected: PASS (`1 passed, 0 failed`).

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php tests/run.php`
Expected: el resumen final no muestra fallos nuevos atribuibles a estos cambios.

- [ ] **Step 6: Commit**

```bash
git add tests/Crud/CrudConfigBoundaryTest.php
git commit -m "test(crud): frontera config/cruds vs schema; borra clientes.json huerfano

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Documento de inventario demo vs core

Clasifica cada archivo relevante en core / demo / huérfano / legacy y documenta la convención `demo_` y el retiro de `nuevo_modulo/`.

**Files:**
- Create: `docs/audits/2026-06-24-inventario-demo-vs-core.md`

**Interfaces:**
- Consumes: nada.
- Produces: documento referenciado por el spec; no consumido por código.

- [ ] **Step 1: Create the inventory document**

Create `docs/audits/2026-06-24-inventario-demo-vs-core.md`:

```markdown
# Inventario demo vs core — frontera del framework

**Fecha:** 2026-06-24
**Spec:** docs/superpowers/specs/2026-06-24-kit-referencia-y-limpieza-demos-design.md
**Propósito:** frontera clara entre código de plataforma (`core`), material de
ejemplo (`demo`), recursos rotos (`huerfano`) y restos fuera de arquitectura
(`legacy`). No se mueve ni renombra nada salvo el huérfano borrado.

## Convención de nombres

- Prefijo **`demo_`** para configs / SQL / handlers / templates de ejemplo:
  `config/cruds/demo_*.json`, tablas `dom_demo_*` en
  `database/schema/modules/crud-engine.sql`, `Demo*Handler.php`, `Demo*Template.php`,
  `*DemoHandler.php`.
- Las plantillas PDF de producto **no** llevan prefijo demo y se consideran `core`.
- Un test de frontera (`tests/Crud/CrudConfigBoundaryTest.php`) verifica que ningún
  `config/cruds/*.json` apunte a una tabla inexistente.

## Clasificación

### core (plataforma — no tocar)
- `app/Kernel/**`, `app/Domain/**` (no-demo), `auth_*`, `cfg_*`, `core_*`, `log_*`.
- Plantillas PDF de producto: `app/Application/Pdf/Templates/ContratoTemplate.php`,
  `PresupuestoTemplate.php`, `TablaEstadisticaTemplate.php`, `TicketCompraTemplate.php`.

### demo (ejemplo didáctico que vive en el repo)
- `config/cruds/demo_categorias.json`, `demo_citas.json`, `demo_clientes.json`,
  `demo_pedidos.json`, `demo_productos.json`.
- `config/calendars/demo_citas.json`.
- `config/reportes/citas.json`, `clientes.json`, `pedidos.json`, `productos.json`
  (apuntan a recursos `demo_*`).
- `app/Application/Crud/Handlers/DemoClienteContactoValidator.php`,
  `DemoPedidoPagarGuard.php`, `DemoPedidoTotalValidator.php`,
  `DemoProductoStateGuard.php`, `DemoProductoToggleStatusHandler.php`,
  `EnviarWhatsappDemoHandler.php`.
- `app/Application/Pdf/Templates/DemoReporteTemplate.php`.
- `database/schema/modules/crud-engine.sql` (tablas `dom_demo_*`),
  `database/schema/modules/marketing_demo.sql`.

### huerfano (roto — resuelto en este spec)
- `config/cruds/clientes.json` → tabla `dom_clientes` inexistente. **BORRADO** (Task 1).

### legacy (fuera de arquitectura — retirar)
- `nuevo_modulo/` — app PHP plana en la raíz, **no trackeada por git**. Acción
  recomendada para el operador: eliminar localmente o mover fuera del árbol activo
  (p. ej. `docs/legacy/`). No genera commit (no está en git).

## Dependencias de los demos

Los archivos `demo` son **hojas**: no deben ser `require`/`use` desde código `core`.
El registro de handlers/templates demo ocurre vía config declarativa
(`config/crud_handlers.php`, configs de reportes/PDF), no por dependencia directa.
```

- [ ] **Step 2: Verify the document exists and is non-empty**

Run: `php -r "echo is_file('docs/audits/2026-06-24-inventario-demo-vs-core.md') && filesize('docs/audits/2026-06-24-inventario-demo-vs-core.md') > 0 ? 'OK' : 'MISSING';"`
Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add docs/audits/2026-06-24-inventario-demo-vs-core.md
git commit -m "docs(audit): inventario demo vs core + convencion de nombres

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Doc de referencia — patrón dominio Onion (módulo marketing)

Añade al `contexto/` del kit la referencia de cómo se construye un dominio real cuando el CRUD Engine no basta, usando `marketing` como ejemplo trabajado.

**Files:**
- Create: `vertical-kit/contexto/docs/modules/patron-dominio-onion.md`

**Interfaces:**
- Consumes: nada.
- Produces: doc enlazado desde `BRIEF-IA.md` en Task 5.

- [ ] **Step 1: Create the reference document**

Create `vertical-kit/contexto/docs/modules/patron-dominio-onion.md`:

```markdown
# Patrón: dominio Onion (cuando el CRUD Engine no basta)

> **Referencia, no plantilla.** Esto NO se clona. Lee este patrón para entender
> cómo el framework implementa un dominio real con lógica propia, y planea tu
> vertical imitando la estructura. El contrato formal está en
> `docs/modules/uso-de-modulo-dominio.md`; este documento es el ejemplo trabajado
> sobre el módulo `marketing`.

## Cuándo usar este patrón

- El recurso **encaja en el CRUD Engine** → quédate en SQL + JSON (+ handlers). NO
  uses este patrón.
- Hay **lógica que el motor no cubre**: orquestación multi-paso, contratos de
  extensión, providers intercambiables, integraciones → entonces sí, capas Onion.

## Capas (ejemplo: módulo marketing)

### Domain — contratos e invariantes (sin dependencias externas)
- Contratos: `app/Domain/Marketing/Contracts/` — p. ej.
  `LeadCaptureHandlerInterface`, `LandingContentProviderInterface`,
  `CommercialPackageSourceInterface`, `LeadRepositoryInterface`,
  `ProvisionAdapterInterface`.
- Value objects: `app/Domain/Marketing/ValueObjects/` — `Lead`, `LeadDraft`,
  `LeadResult`, `MagicLinkToken`, `Provision`, `ProvisionResult`.

### Application — orquestación (use cases)
- `app/Application/Marketing/CapturarLeadUseCase.php` — pipeline de captura.
- `app/Application/Marketing/RenderLandingUseCase.php` — armado de la landing.

### Infrastructure — implementaciones concretas
- Providers de contenido/paquetes: `app/Infrastructure/Marketing/CrudLandingContentProvider.php`,
  `CrudCommercialPackageSource.php`.
- Pipeline de handlers de captura:
  `app/Infrastructure/Marketing/LeadCapture/PersistLeadHandler.php`,
  `AutoresponderHandler.php`, `NotifyInternalHandler.php`.
- Settings providers: `app/Infrastructure/Marketing/Settings/*SettingsProvider.php`.
- Repositorio PDO: `app/Infrastructure/Repositories/PdoMarketingContentRepository.php`.

### Cableado (plataforma)
- Bindings condicionales por toggle en `config/container.php` (bloque
  `if vertical.modules.marketing`).
- Rutas en `routes/marketing.php` (incluidas condicionalmente).
- Manifiesto `config/modules/marketing.php` con `permisos` poblados y `bootstrap_sql`.

## Regla de dependencias (Onion)

`Presentation → Application → Domain ← Infrastructure`. El Domain no importa nada
de fuera; Infrastructure implementa las interfaces del Domain. Detalle:
`docs/core/arquitectura.md`.
```

- [ ] **Step 2: Verify the document exists**

Run: `php -r "echo is_file('vertical-kit/contexto/docs/modules/patron-dominio-onion.md') ? 'OK' : 'MISSING';"`
Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add vertical-kit/contexto/docs/modules/patron-dominio-onion.md
git commit -m "docs(kit): referencia patron dominio Onion (ejemplo marketing)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Doc de referencia — patrón conector de integración (módulo integrations)

Añade al `contexto/` la referencia de cómo se añade un conector/canal externo desacoplado, usando `integrations` como ejemplo.

**Files:**
- Create: `vertical-kit/contexto/docs/modules/patron-conector-integracion.md`

**Interfaces:**
- Consumes: nada.
- Produces: doc enlazado desde `BRIEF-IA.md` en Task 5.

- [ ] **Step 1: Create the reference document**

Create `vertical-kit/contexto/docs/modules/patron-conector-integracion.md`:

```markdown
# Patrón: conector de integración (API / canal externo)

> **Referencia, no plantilla.** No se clona. Lee este patrón para entender cómo el
> framework añade integraciones externas desacopladas, y planea la tuya imitando la
> estructura. Ejemplo trabajado: módulo `integrations` (Green API WhatsApp).

## Cuándo usar este patrón

- Necesitas **enviar mensajes / consumir una API externa / recibir webhooks** desde
  un vertical, sin acoplar el dominio concreto al core.

## Piezas (ejemplo: módulo integrations)

- Connector HTTP genérico:
  `app/Infrastructure/Integrations/Http/HttpApiConnector.php`.
- Canales (estrategia por proveedor):
  `app/Infrastructure/Integrations/Channels/EmailChannel.php`,
  `GreenApiWhatsappChannel.php`.
- Partner connector (alta/gestión de cuentas del proveedor):
  `app/Infrastructure/Integrations/Partner/GreenApiPartnerConnector.php`.
- Cliente de cuenta:
  `app/Infrastructure/Integrations/GreenApi/GreenApiAccountClient.php`.
- Repositorios de cuenta y log:
  `app/Infrastructure/Integrations/Repositories/IntegrationAccountRepository.php`,
  `IntegrationLogRepository.php`.
- Settings provider (configuración por instancia):
  `app/Infrastructure/Integrations/Settings/IntegrationsWhatsappSettingsProvider.php`.

## Cableado (plataforma)

- Manifiesto `config/modules/integrations.php`: `permisos`
  (`integrations.ver`, `integrations.enviar`, `integrations.configurar`),
  `bootstrap_sql` = `database/schema/modules/integrations.sql`.

## Desacople (regla clave)

El **dominio concreto** (p. ej. WhatsApp para un vertical) vive como **datos / demo
/ seeds**, NO como lógica acoplada al core. El conector expone canales genéricos; el
vertical configura cuál usa. Coherente con el contrato de `docs/modules/uso-de-modulo-dominio.md`.
```

- [ ] **Step 2: Verify the document exists**

Run: `php -r "echo is_file('vertical-kit/contexto/docs/modules/patron-conector-integracion.md') ? 'OK' : 'MISSING';"`
Expected: `OK`

- [ ] **Step 3: Commit**

```bash
git add vertical-kit/contexto/docs/modules/patron-conector-integracion.md
git commit -m "docs(kit): referencia patron conector de integracion (ejemplo integrations)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Enrutar los docs nuevos desde `BRIEF-IA.md`

Añade el árbol de decisión (declarativo → dominio Onion → conector) y las filas de los dos docs nuevos en la tabla de fuentes de verdad.

**Files:**
- Modify: `vertical-kit/BRIEF-IA.md`

**Interfaces:**
- Consumes: docs creados en Task 3 y Task 4.
- Produces: nada.

- [ ] **Step 1: Add the decision tree after section §1**

En `vertical-kit/BRIEF-IA.md`, justo después del bloque de la "Regla de oro de eficiencia" (final de §1, antes del separador `---` que precede a §2), insertar:

```markdown
### Árbol de decisión (qué construir)

1. **¿El recurso encaja en el CRUD Engine?** → camino **declarativo**: SQL + JSON
   (+ handlers opcionales). Es el caso más común. NO escribas capas Onion.
2. **¿Hay lógica que el motor no cubre** (orquestación, contratos de extensión,
   providers intercambiables)? → **dominio Onion**. Ver
   `contexto/docs/modules/patron-dominio-onion.md` (ejemplo: marketing).
3. **¿Necesitas enviar mensajes / consumir una API / webhooks externos?** →
   **conector de integración**. Ver
   `contexto/docs/modules/patron-conector-integracion.md` (ejemplo: integrations).
```

- [ ] **Step 2: Add rows to the "Fuentes de verdad" table**

En la tabla final "Fuentes de verdad en `contexto/`" de `BRIEF-IA.md`, añadir estas dos filas (antes de la fila "Resumen de arquitectura | CLAUDE.md"):

```markdown
| Patrón de dominio Onion (lógica real) | `docs/modules/patron-dominio-onion.md` |
| Patrón de conector de integración | `docs/modules/patron-conector-integracion.md` |
```

- [ ] **Step 3: Verify the links resolve to real files**

Run:
```bash
php -r '$b=file_get_contents("vertical-kit/BRIEF-IA.md");
$ok = str_contains($b,"patron-dominio-onion.md") && str_contains($b,"patron-conector-integracion.md")
  && is_file("vertical-kit/contexto/docs/modules/patron-dominio-onion.md")
  && is_file("vertical-kit/contexto/docs/modules/patron-conector-integracion.md");
echo $ok ? "OK" : "BROKEN";'
```
Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add vertical-kit/BRIEF-IA.md
git commit -m "docs(kit): arbol de decision + enlaces a patrones de referencia en BRIEF-IA

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Verificación final (cierre del plan)

- [ ] `php tests/run.php` → sin fallos nuevos; suite `CrudConfigBoundary` en verde.
- [ ] `config/cruds/clientes.json` ya no existe; `config/reportes/clientes.json` se conserva.
- [ ] Existen `vertical-kit/contexto/docs/modules/patron-dominio-onion.md` y `patron-conector-integracion.md`.
- [ ] `docs/audits/2026-06-24-inventario-demo-vs-core.md` clasifica core/demo/huérfano/legacy.
- [ ] `BRIEF-IA.md` enlaza ambos docs y tiene el árbol de decisión.
- [ ] No se modificó RBAC, toggles, container, rutas ni manifiestos.
```

