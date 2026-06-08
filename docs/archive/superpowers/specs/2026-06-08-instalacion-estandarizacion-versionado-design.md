# Instalación, estandarización y versionado — Design

> Fase siguiente del framework Lebytek. Convierte cada despliegue en algo **autodescriptivo, versionado y reproducible** mediante manifiestos de módulo, tracking de migraciones y un instalador guiado (wizard web + CLI), más una página admin de estado.

- **Fecha:** 2026-06-08
- **Estado:** Diseño aprobado (pendiente de plan de implementación)
- **Enfoque elegido:** A — Registro central + manifiestos PHP livianos (mínimo movimiento de archivos, compatible con despliegues actuales)

---

## 1. Problema y objetivo

El sistema ya tiene varios módulos y muchas carpetas/archivos. Es config-driven: módulos y flujos viven en `config/cruds/*.json`, `config/vertical.php`, `config/dashboard.php`, `config/crud_handlers.php`, `config/container.php`, más `core_menu_items`, permisos y seeds. El dolor: **ya no es posible recordar mentalmente qué está implementado/desplegado en cada instancia.**

Hoy existe `scripts/install.php` (idempotente: schema → migrations → seeds), `migrate.php` y `seed.php`, pero:

- No hay **tracking de migraciones** (se re-ejecuta todo confiando en SQL idempotente).
- No hay concepto de **versión** (ni de plataforma ni de módulo).
- No hay **manifiesto por módulo**: lo que un módulo necesita (migraciones, seeds, permisos, menú, config, handlers, cruds) está disperso.

**Objetivo:** dejar cada despliegue autodescriptivo, versionado y reproducible, con un instalador guiado que acompañe la instalación y una página de estado que responda "¿qué hay desplegado aquí y en qué versión?".

---

## 2. Alcance

### Dentro de alcance

- Manifiestos PHP por **módulo grande** (`core`, `dashboard`, `crud-engine`, verticales `dom_*`).
- Tablas de versionado: migraciones aplicadas + módulos instalados con versión; semver de plataforma.
- **Motor de instalación** en `Application` (puro, testeable), consumido por: wizard web bajo `public/`, CLI en `scripts/`, y página admin de estado.
- Flujos: **instalación nueva** y **actualización** (aplica solo lo pendiente, idempotente).
- **Selección de módulos** en el wizard: `core` obligatorio, resto opcional → escribe `config/vertical.php`.
- Seguridad del wizard: **lock file + token** (token exigido en producción); `.env` se asume existente (solo se valida conexión).

### Fuera de alcance (YAGNI)

- Rollback de migraciones (deshacer).
- Generación/edición de `.env` desde la web.
- Wizard de creación de verticales nuevos.
- Multi-tenant.
- UI de marca avanzada (se reutiliza el panel de Ajustes existente).

### Reglas de capa (Onion)

- Lógica en `Application` (`Installer`, `ModuleRegistry`, `ManifestValidator`, `DependencyResolver`, `DeploymentStatus`).
- Contratos en `Domain` (interfaces de repositorio + excepción).
- Acceso a BD/FS en `Infrastructure`.
- Wizard web y página de estado en `Presentation`. Nada de negocio en `Presentation`.

---

## 3. Decisiones tomadas (resumen del brainstorming)

| Tema | Decisión |
|------|----------|
| Entregable central | Ambos: instalador guiado (nuevos) + estado/versión (existentes) sobre base común de manifiestos + versionado |
| Interfaz | Wizard **web** como principal (cliente final), CLI para devs/CI, ambos sobre la misma lógica |
| Versionado | Completo: tracking de migraciones + semver de plataforma + versión por módulo |
| Unidad de módulo | Mixto: manifiestos para módulos grandes; los recursos CRUD de un vertical se declaran dentro del manifiesto del vertical |
| Formato manifiesto | **PHP** (`return [...]`), coherente con `config/*.php`; los recursos CRUD siguen en JSON |
| Seguridad wizard | Lock file + token (token exigido en `APP_ENV=production`) |
| Estado/doctor | Página admin "Sistema/Estado" protegida por RBAC (superficie principal); servicio reutilizable por CLI |
| Alcance del flujo | Instalación nueva + actualización idempotente (sin rollback) |
| Manejo de `.env` | Se asume existente; el wizard solo valida conexión y procede |
| Selección de módulos | `core` obligatorio; resto opcional y elegible; el wizard escribe `config/vertical.php` |

---

## 4. Modelo de datos (versionado)

Tres piezas, con prefijo `cfg_` (configuración de sistema), añadidas a `database/schema/schema.sql`.

### 4.1 `cfg_migraciones` — migraciones aplicadas

```sql
CREATE TABLE IF NOT EXISTS `cfg_migraciones` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo`        VARCHAR(64)  NOT NULL,          -- key del módulo dueño
  `archivo`       VARCHAR(255) NOT NULL,          -- nombre del .sql
  `checksum`      CHAR(64)     NOT NULL,          -- sha256 del contenido
  `aplicada_en`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cfg_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 `cfg_modulos` — módulos instalados/activos y su versión

```sql
CREATE TABLE IF NOT EXISTS `cfg_modulos` (
  `clave`          VARCHAR(64)  NOT NULL,         -- 'core', 'crud-engine', 'dom_inventario'
  `version`        VARCHAR(20)  NOT NULL,         -- semver instalado
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `instalado_en`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 Versión de plataforma

Constante en código: `config/app.php` → clave `version` (p. ej. `'1.0.0'`). Es la versión del framework como release. Las versiones por módulo viven en sus manifiestos y se reflejan en `cfg_modulos`.

La página de estado compara **versión declarada en manifiesto** vs **versión registrada en `cfg_modulos`** para detectar "actualización disponible".

### 4.4 Checksum

Si un `.sql` ya aplicado cambia de checksum, el estado lo marca como **"modificado tras aplicar"** (alerta), pero **no** lo re-ejecuta automáticamente (evita corromper datos).

### 4.5 Compatibilidad / baseline

En un despliegue que ya existía sin estas tablas, la primera corrida del instalador en modo actualización:
1. Crea `cfg_migraciones` / `cfg_modulos`.
2. Ejecuta `baseline()`: marca como aplicadas todas las migraciones presentes (sin re-ejecutar histórico).
3. Registra `core` + módulos detectados por sus archivos.

---

## 5. Manifiestos de módulo

Un archivo PHP por módulo grande en `config/modules/<clave>.php` (carpeta nueva en `config/`, documentada).

### 5.1 Contrato

```php
<?php
// config/modules/crud-engine.php
return [
    'clave'       => 'crud-engine',
    'nombre'      => 'CRUD Engine',
    'descripcion' => 'Motor CRUD genérico dirigido por configuración JSON + demo showcase.',
    'version'     => '1.0.0',
    'obligatorio' => false,            // 'core' => true
    'requiere'    => ['core'],         // dependencias por clave
    'migraciones' => [                 // archivos que ESTE módulo posee
        '20260428132500_crud_engine_demo_resources.sql',
        '20260607120000_crud_engine_demo_showcase.sql',
    ],
    'seeds'       => [],               // archivos en database/seeds que posee
    'cruds'       => [                 // recursos JSON que aporta (referencia/inventario)
        'demo_clientes', 'demo_productos', 'demo_categorias', 'demo_pedidos',
    ],
    'permisos'    => [],               // slugs que introduce (referencia/validación)
    'menu'        => [],               // slugs raíz de core_menu_items que aporta
    'providers'   => [],               // FQCN dashboard providers, si aplica
];
```

### 5.2 Decisiones de contrato

- **`core`** es manifiesto obligatorio que **reclama el `schema.sql` base + las migraciones/seeds actuales de plataforma** (auth, menú, ajustes). Todo lo que ya existe queda adoptado por un módulo; sin archivos huérfanos.
- Cada migración/seed pertenece a **exactamente un** módulo. El instalador valida que no haya archivos en `database/migrations|seeds/` sin dueño ni con doble dueño → **estandarización verificable**.
- **`requiere`** habilita orden topológico: no se instala `crud-engine` sin `core`; un vertical puede requerir `crud-engine`.
- `cruds`, `providers`, `permisos`, `menu` son **declarativos para inventario y validación** (la página de estado los lista). **No** cambian cómo se cargan en runtime (eso lo hacen `config/cruds`, `config/dashboard.php`, etc.). El manifiesto **referencia**, no reemplaza → evita doble fuente de verdad.

### 5.3 `ManifestValidator`

Revisa (errores acumulados, estilo `CrudConfigValidator`):
- Forma del manifiesto (claves requeridas, tipos).
- Dueño único de cada migración/seed (sin huérfanos ni duplicados).
- Dependencias resueltas (todas las claves de `requiere` existen).
- `cruds` listados existen como JSON en `config/cruds/`.

---

## 6. Capa Application (el motor)

### 6.1 Domain (contratos)

- `MigrationRepositoryInterface` — `aplicadas(): array`, `registrar(modulo, archivo, checksum): void`, `existeTabla(nombre): bool`.
- `ModuleStateRepositoryInterface` — `instalados(): array`, `registrar(clave, version, activo): void`.
- `Exceptions/InstallerException` — errores de instalación con contexto.

### 6.2 Infrastructure

- `MigrationRepository`, `ModuleStateRepository` (PDO sobre `cfg_migraciones` / `cfg_modulos`).
- `Install/SqlFileRunner` — lee y ejecuta un `.sql` (reutiliza el partido de sentencias de `seed.php`), calcula checksum sha256.

### 6.3 Application/Install

- `ModuleManifest` — VO inmutable de un manifiesto cargado.
- `ModuleRegistry` — carga `config/modules/*.php`, devuelve `ModuleManifest[]`.
- `ManifestValidator` — sección 5.3.
- `DependencyResolver` — orden topológico por `requiere` (detecta ciclos → `InstallerException`).
- `Installer` — orquesta:
  1. `requisitosCheck()` — PHP ≥ 8.1, ext PDO/pdo_mysql, `storage/` escribible, `.env` presente, conexión BD.
  2. `plan(modulosSeleccionados)` — calcula acciones (migraciones pendientes por checksum, seeds, registro de versión) **sin ejecutar** → permite preview / dry-run.
  3. `aplicar(plan)` — ejecuta; registra en `cfg_migraciones` / `cfg_modulos`. Transacción por módulo cuando el motor lo permita (ver riesgos DDL).
  4. `baseline()` — marca migraciones existentes como aplicadas en despliegues legacy.
- `DeploymentStatus` — arma el view-model de estado: versión plataforma, módulos (declarada vs instalada → "actualización disponible"), migraciones pendientes, checksums modificados, health checks. **Fuente única** para la página admin y el CLI.

### 6.4 Modo de operación

`Installer` detecta automáticamente **nueva** (no existe `cfg_modulos` o sin filas) vs **actualización** (ya hay estado). En ambos casos el resultado es el mismo plan idempotente; la diferencia es solo qué resulta "pendiente".

---

## 7. Wizard web (instalador guiado)

### 7.1 Ubicación

`public/install/` con front controller mínimo (`public/install/index.php`) que **no** arranca el router admin completo (puede correr antes de que BD/seeds existan). Reutiliza Autoloader + EnvLoader + Config + `Installer`.

### 7.2 Seguridad

- Si existe `storage/install.lock` → responde "Ya instalado" + resumen de solo lectura; no ejecuta nada.
- Si `APP_ENV=production` → exige `INSTALL_TOKEN` (de `.env`) para abrir el wizard, incluso en primera vez.
- Al completar con éxito → escribe `storage/install.lock` (versión, fecha, módulos elegidos).
- CSRF en cada POST; sin credenciales en logs.

### 7.3 Pasos del asistente

1. **Bienvenida + requisitos** — muestra `requisitosCheck()`. Bloquea avanzar si algo crítico falla.
2. **Conexión BD** — valida que `.env` conecte (no edita `.env`). Si falla, instruye corregir `.env` y reintentar.
3. **Selección de módulos** — `core` marcado y bloqueado; lista el resto desde manifiestos con descripción y versión; resuelve dependencias (marcar un vertical que requiere `crud-engine` lo marca también).
4. **Cuenta admin** — email + contraseña (reemplaza el seed por defecto; valida fuerza mínima).
5. **Revisión (preview/plan)** — muestra el plan: migraciones a aplicar, seeds, módulos a registrar. Botón "Instalar".
6. **Ejecución + resultado** — corre `aplicar(plan)`, escribe `config/vertical.php` con los módulos activos, crea el admin, escribe `install.lock`, muestra resumen con versión instalada y enlace a `/login`.

### 7.4 Escritura de `config/vertical.php`

Se genera desde plantilla con los `modules` activos según selección, manteniendo `labels` si ya existían.

### 7.5 Estética

Vista standalone limpia y moderna (no usa el layout admin); puede apoyarse en el CSS existente bajo `public/assets/`.

---

## 8. Página de estado y CLI

### 8.1 Página admin "Sistema / Estado" (Presentation)

- Ruta `GET /admin/sistema/estado`, protegida por RBAC con permiso nuevo `sistema.ver`.
- Entrada en `core_menu_items` (seed nuevo) + permiso en seeds; asignado al rol admin.
- Controlador delgado → `DeploymentStatus` → vista con tarjetas:
  - **Versión de plataforma**.
  - **Módulos** (clave, versión declarada vs instalada, activo, "actualización disponible").
  - **Migraciones pendientes**.
  - **Checksums modificados**.
  - **Health checks** (storage escribible, conexión, lock presente).
- Solo lectura (no ejecuta instalación desde el panel en esta fase).

### 8.2 CLI (devs/CI), misma lógica

- `scripts/install.php` se **reescribe** para usar el `Installer` (plan + aplicar con tracking real), conservando la firma `php scripts/install.php`. Flags: `--modules=core,crud-engine`, `--dry-run`, `--baseline`.
- `scripts/status.php` (nuevo) imprime el `DeploymentStatus` en texto.
- `migrate.php` y `seed.php` se mantienen pero se documentan como legacy/secundarios (o delegan al runner nuevo).

---

## 9. Compatibilidad con despliegues actuales

- Primera corrida en deploy existente: crea `cfg_migraciones` / `cfg_modulos`, ejecuta `baseline()` (marca migraciones presentes como aplicadas), registra `core` + módulos detectados. Sin re-ejecución destructiva.
- El demo del CRUD Engine pasa a ser módulo opcional `crud-engine`. Un deploy que no lo quiera podrá quedar sin esas tablas demo (sin romper si ya existen).

---

## 10. Testing (harness propio `tests/run.php`)

- `ManifestValidator`: dueño único, deps resueltas, ciclos, cruds inexistentes.
- `DependencyResolver`: orden topológico correcto; ciclo lanza error.
- `Installer::plan()` con repos en memoria (fakes de las interfaces): pendientes correctos en nueva vs actualización; checksum cambiado se reporta sin re-aplicar.
- `DeploymentStatus`: declarada vs instalada → flags correctos.
- Integridad de estandarización: todos los `.sql` reales tienen dueño en algún manifiesto.

---

## 11. Estructura de archivos

**Configuración / datos**
- `config/modules/{core,dashboard,crud-engine}.php` — manifiestos.
- `database/schema/schema.sql` — añadir `cfg_migraciones` y `cfg_modulos`.
- Seeds para permiso/menú `sistema.ver`.
- `config/app.php` — añadir `version`.

**Domain**
- `app/Domain/Interfaces/MigrationRepositoryInterface.php`
- `app/Domain/Interfaces/ModuleStateRepositoryInterface.php`
- `app/Domain/Exceptions/InstallerException.php`

**Infrastructure**
- `app/Infrastructure/Repositories/MigrationRepository.php`
- `app/Infrastructure/Repositories/ModuleStateRepository.php`
- `app/Infrastructure/Install/SqlFileRunner.php`

**Application**
- `app/Application/Install/ModuleManifest.php`
- `app/Application/Install/ModuleRegistry.php`
- `app/Application/Install/ManifestValidator.php`
- `app/Application/Install/DependencyResolver.php`
- `app/Application/Install/Installer.php`
- `app/Application/Install/DeploymentStatus.php`

**Presentation**
- `app/Presentation/Controllers/SistemaEstadoController.php`
- `app/Presentation/Views/admin/sistema/estado.php`
- `public/install/index.php` (+ vistas del wizard)

**Scripts**
- `scripts/status.php` (nuevo)
- `scripts/install.php` (reescritura sobre `Installer`)

**DI / rutas**
- `config/container.php` — bindings additivos.
- `routes/web.php` — ruta `/admin/sistema/estado`.

**Docs**
- `docs/core/instalacion-y-versionado.md` (nuevo).
- Actualizar `docs/core/vertical-onboarding.md` y `docs/core/despliegue_hosting.md`.

---

## 12. Riesgos y mitigación

| Riesgo | Mitigación |
|--------|------------|
| Ejecución multi-statement en hosting compartido | Reutilizar el runner ya probado de `seed.php` (partido de sentencias) |
| Transacciones DDL no atómicas en MySQL | Documentarlo; ordenar el plan para fallar temprano; el preview/plan reduce sorpresas |
| Wizard web expuesto en `public/` | Lock file + token (token exigido en producción); CSRF; resumen de solo lectura tras instalar |
| Doble fuente de verdad módulo vs runtime | Manifiestos solo **referencian** (inventario/validación); el runtime sigue cargando de `config/*` |
| Deploy legacy sin tablas de versionado | `baseline()` marca histórico como aplicado sin re-ejecutar |

---

## 13. Criterios de aceptación

1. Existen manifiestos para `core`, `dashboard`, `crud-engine`; toda migración/seed tiene dueño único (test verde).
2. `cfg_migraciones` y `cfg_modulos` se crean en instalación nueva; `baseline()` adopta deploys existentes sin re-ejecutar.
3. Wizard web completa una instalación nueva (requisitos → conexión → módulos → admin → preview → ejecución) y queda bloqueado por lock tras éxito; en producción exige token.
4. El wizard escribe `config/vertical.php` con los módulos seleccionados.
5. Modo actualización aplica solo migraciones pendientes y actualiza versiones de módulo.
6. La página `/admin/sistema/estado` (RBAC `sistema.ver`) muestra versión de plataforma, módulos (declarada vs instalada), migraciones pendientes, checksums modificados y health checks.
7. `scripts/install.php --dry-run` y `scripts/status.php` funcionan sobre la misma lógica.
8. Suite `php tests/run.php` termina en verde.
