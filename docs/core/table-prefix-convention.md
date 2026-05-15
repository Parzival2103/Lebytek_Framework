# Convención de prefijos en base de datos

El archivo [`database/schema/schema.sql`](../../database/schema/schema.sql) define una instalación **desde cero** solo con tablas **plataforma**. Los scripts que renombraban bases legacy están archivados en [`database/migrations_legacy/`](../../database/migrations_legacy/) (solo referencia).

## Plataforma (compartida por cualquier vertical)

| Prefijo | Rol |
|---------|-----|
| `auth_` | Identidad y RBAC: usuarios, roles, permisos y tablas de unión. |
| `cfg_` | Configuración de instancia y catálogos auxiliares genéricos. |
| `log_` | Auditoría; tabla canónica `log_bitacora`. |
| `core_` | Extensión del núcleo (p. ej. registro de módulos); hoy stubs mínimos. |
| `int_` | Integraciones (p. ej. webhooks). |
| `rep_` | Definiciones de métricas / reporting. |
| `tmp_` | Colas o trabajos temporales (puede usar JSON; requiere motor reciente). |
| `sys_` | Almacén clave-valor u otros metadatos de sistema. |

## Dominio de negocio

| Prefijo | Rol |
|---------|-----|
| `dom_*` | Tablas que modelan el negocio de cada producto vertical. **No están** en `schema.sql` del framework; se crean junto al módulo según **[uso-de-modulo-dominio.md](../modules/uso-de-modulo-dominio.md)**. |

**Regla:** lo que es política transversal de la aplicación vive en `auth_` / `cfg_` / `log_` / `core_` / `int_` / `rep_` / `tmp_` / `sys_`. Las reglas de un negocio concreto van en tablas nuevas (convención `dom_*` o la que acuerde el proyecto).

Ver también [core-schema-and-modules.md](./core-schema-and-modules.md) y [example-domain-imprenta.md](../legacy/example-domain-imprenta.md) (anexo histórico).
