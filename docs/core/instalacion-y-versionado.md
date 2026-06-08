# Instalación y versionado

Cada despliegue es autodescriptivo y versionado mediante:

- **Manifiestos de módulo** (`config/modules/*.php`): declaran versión, dependencias, y los archivos `.sql`/cruds que el módulo posee.
- **Tablas de versionado** (`cfg_migraciones`, `cfg_modulos`): qué se aplicó y qué versión de cada módulo está instalada.
- **Versión de plataforma**: `config/app.php` → `version`.

## Superficies (misma lógica)

| Superficie | Cómo |
|---|---|
| Wizard web | `https://tu-host/install/` (lock + token en producción) |
| CLI instalar | `php scripts/install.php [--modules=core,crud-engine] [--dry-run] [--baseline]` |
| CLI estado | `php scripts/status.php` |
| Página admin | `/admin/sistema/estado` (permiso `sistema.ver`) |

## Flujos

- **Instalación nueva:** schema base → migraciones/seeds pendientes → registro de versiones → admin → `install.lock`.
- **Actualización:** aplica solo lo pendiente por checksum; actualiza versiones de módulo.
- **Deploy legacy:** `php scripts/install.php --baseline` marca el histórico como aplicado sin re-ejecutar.

## Estandarización

Toda migración/seed pertenece a **exactamente un** manifiesto. El test
`tests/Install/EstandarizacionIntegridadTest.php` falla si hay archivos huérfanos o con doble dueño.

## Añadir un módulo nuevo

1. Crea `config/modules/<clave>.php` (ver contrato en `crud-engine.php`).
2. Lista sus migraciones/seeds y declara `requiere`.
3. Corre `php tests/run.php Install` (todo verde) y `php scripts/status.php`.
