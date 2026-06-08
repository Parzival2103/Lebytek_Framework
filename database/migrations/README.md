Las migraciones SQL incrementales nuevas pueden colocarse aquí (convención `YYYYMMDDHHMMSS_descripcion.sql`). El bootstrap inicial del proyecto usa [`../schema/schema.sql`](../schema/schema.sql).

Los scripts históricos del dominio anterior están en [`../migrations_legacy/`](../migrations_legacy/).

## Reglas para el instalador (`SqlFileRunner`)

Las migraciones se ejecutan con `prepare()` + `execute()` (una sentencia por llamada). Para que sean **idempotentes** y compatibles con reintentos:

1. **Una sentencia por línea** — no agrupar `PREPARE …; EXECUTE …; DEALLOCATE …` en la misma línea. Preferir `ADD COLUMN IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS` (MariaDB 10.11+).
2. **Datos de ejemplo** — usar `INSERT … SELECT … WHERE NOT EXISTS` o `INSERT IGNORE` (con clave única), nunca `INSERT … VALUES` plano si puede re-ejecutarse.
3. **Permisos y menú** — `INSERT IGNORE` en `auth_permisos`, `auth_roles_permisos` y `core_menu_items`.
4. **Consultas de verificación** — no incluir `SELECT` al final del archivo para “revisar”; ejecutarlas manualmente en el cliente SQL si hace falta.
5. **DDL** — `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`.
6. **Manifiesto** — cada archivo debe declararse en exactamente un `config/modules/*.php` (ver `ManifestValidator`).

`20260428132600_drop_crud_engine_demo_resources.sql` está en [`../migrations_legacy/`](../migrations_legacy/) — limpieza manual de pruebas; **no** debe figurar en manifiestos de instalación.
