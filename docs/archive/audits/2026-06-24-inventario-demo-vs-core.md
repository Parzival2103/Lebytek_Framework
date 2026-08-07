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
