Las migraciones SQL incrementales **post-baseline** se colocan aquí (convención `YYYYMMDDHHMMSS_descripcion.sql`).

El bootstrap greenfield (plataforma + datos iniciales) vive en [`../schema/schema.sql`](../schema/schema.sql).  
Los módulos opcionales pueden tener bootstrap en [`../schema/modules/`](../schema/modules/).

Los scripts históricos consolidados en junio 2026 están en [`../migrations_legacy/incrementales-2026-06/`](../migrations_legacy/incrementales-2026-06/).  
Los seeds antiguos están en [`../seeds_legacy/baseline-2026-06/`](../seeds_legacy/baseline-2026-06/).

## Reglas para el instalador (`SqlFileRunner`)

1. **Una sentencia por línea** — no agrupar `PREPARE …; EXECUTE …; DEALLOCATE …` en la misma línea.
2. **Datos de ejemplo** — `INSERT … SELECT … WHERE NOT EXISTS` o `INSERT IGNORE`.
3. **Consultas de verificación** — no incluir `SELECT` al final del archivo.
4. **DDL** — `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`.
5. **Manifiesto** — cada archivo debe declararse en exactamente un `config/modules/*.php`.
