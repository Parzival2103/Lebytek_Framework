# Semillas SQL (orden estable)

Scripts idempotentes (`INSERT IGNORE` / `INSERT … SELECT … WHERE NOT EXISTS`) para poblar datos base después de aplicar [`database/schema/schema.sql`](../schema/schema.sql).

Ejecución ordenada mediante **`php scripts/seed.php`** (lista `database/seeds/*.sql` en orden lexigráfico) o cliente MySQL por archivo.

Orden típico del núcleo (prefijo numérico):

| Archivo | Contenido |
|---------|-----------|
| `010_auth_permisos.sql` | Permisos de plataforma |
| `015_core_menu_items.sql` | Menú admin (`core_menu_items`) |
| `020_auth_roles.sql` | Roles base |
| `025_auth_roles_permisos.sql` | Asignaciones rol ↔ permiso |
| `030_auth_usuario_admin.sql` | Usuario inicial `admin@sistema.local` (contraseña por defecto en el propio archivo; cambiar en producción) |
| `035_cfg_configuraciones.sql` | Filas por defecto de `cfg_configuraciones` |

Para nuevos módulos de dominio, añadir archivos con prefijo posterior (p. ej. `040_mi_modulo.sql`) siguiendo [uso-de-modulo-dominio.md](../../docs/modules/uso-de-modulo-dominio.md).
