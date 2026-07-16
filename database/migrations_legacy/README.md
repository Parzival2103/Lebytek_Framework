# Migraciones SQL legacy (referencia histórica)

## incrementales-2026-06/

Scripts incrementales del instalador **consolidados en junio 2026** en:

- [`../schema/schema.sql`](../schema/schema.sql) — plataforma + datos iniciales (RBAC, menú, admin de prueba).
- [`../schema/modules/crud-engine.sql`](../schema/modules/crud-engine.sql) — showcase demo CRUD Engine.

No forman parte del flujo del instalador en instalaciones nuevas.

## Scripts 002–008 (dominio anterior)

Aplicaban cambios sobre bases del proyecto anterior (imprenta) antes de consolidar solo el framework base.

Las **instalaciones nuevas** deben usar:

- [`../schema/schema.sql`](../schema/schema.sql)
- Para eliminar tablas `dom_*` en una base antigua: [`../schema/drop_legacy_domain_tables.sql`](../schema/drop_legacy_domain_tables.sql)

`20260428132600_drop_crud_engine_demo_resources.sql` — limpieza manual de pruebas demo; no usar en instalador.

No ejecutar estos scripts sobre una base ya alineada al esquema actual sin revisar su contenido.
