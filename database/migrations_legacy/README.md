# Migraciones SQL legacy (referencia histórica)

Los archivos `002`–`008` (y `001` si existía en el historial) aplicaban cambios sobre **bases de datos ya existentes** del proyecto anterior (imprenta) antes de consolidar solo el framework base.

Las **instalaciones nuevas** deben usar únicamente:

- [`../schema/schema.sql`](../schema/schema.sql) — esquema plataforma (sin dominio empotrado).
- Para **eliminar** tablas `dom_*` en una base antigua: [`../schema/drop_legacy_domain_tables.sql`](../schema/drop_legacy_domain_tables.sql).

No ejecutar estos scripts sobre una base ya alineada al esquema actual sin revisar su contenido.
