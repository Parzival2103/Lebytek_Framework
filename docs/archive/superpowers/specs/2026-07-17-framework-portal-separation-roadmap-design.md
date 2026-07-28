# Design: Roadmap ejecutable para separar Framework y Portal

**Fecha:** 2026-07-17  
**Repo de origen:** `Lebytek_Framework`  
**Estado:** diseño aprobado; planes `2026-07-17-fps-00` … `2026-07-17-fps-08` generados en rama `docs/framework-portal-separation-plans` — **pendientes de ejecución** en `consolidation/framework-portal-separation` (orden `00 → 08`)  
**Plan anterior:** `docs/superpowers/plans/2026-07-15-framework-portal-separation.md`

## Propósito

Reemplazar la ejecución monolítica del plan de separación por una secuencia de planes pequeños. Cada plan debe caber en una sola sesión de `subagent-driven-development`, terminar con una entrega verificable y no depender de memoria conversacional de ejecuciones anteriores.

El objetivo final permanece igual:

- `Lebytek_Framework` será el paquete Composer reutilizable `lebytek/framework`.
- `Lebytek_Portal` será una aplicación consumidora independiente.
- Otros clientes partirán de `skeleton/`, no del Portal.
- El Framework llegará a las aplicaciones únicamente mediante Composer.

## Estado real de Git

`main` no es actualmente un Framework puro. El commit `2c71d3f` fusionó el PR #5 de `feature/backoffice-api-integration` y llevó a `main` tanto mejoras de plataforma como código del Portal.

El delta posterior exclusivo de `feature/backoffice-api-integration` frente a `main` contiene aproximadamente 47 commits. Mezcla mejoras genéricas, especialmente Payments, con landing, membresías y otras funciones de negocio.

Por ello:

1. La consolidación comienza en una rama nueva creada desde `main`.
2. No se fusiona `feature/backoffice-api-integration` hacia `main`.
3. Las mejoras de plataforma se trasladan selectivamente por paths y comportamiento, no mediante cherry-picks completos.
4. El negocio del Portal se copiará posteriormente desde un SHA congelado de la feature.

## Decisiones aprobadas

### Estrategia

Se adopta una separación por dependencias y entregables verificables. El plan anterior conserva valor como fuente detallada, pero no debe ejecutarse completo.

### Payments

El Framework incluye infraestructura genérica de pagos:

- contratos y value objects;
- registry y factory de gateways;
- conexión genérica a Stripe mediante `StripeGateway` configurable;
- event log e idempotencia genéricos;
- schema y configuración de plataforma;
- módulo apagado por defecto.

El Portal conserva checkout, órdenes, membresías, activación de planes y traducción de eventos Stripe a reglas de negocio.

### Marketing

Marketing permanece completo en `Lebytek_Portal`.

Aunque se construye sobre CRUD Engine, hoy contiene CRM y reglas reales del producto: leads, membresías, landing, campañas, churn, autorespuestas y conexión con LebytekApi. No se crea `lebytek/module-marketing` con un solo consumidor.

El Framework conserva CRUD Engine genérico. Una extracción futura de Marketing requerirá un segundo consumidor y un diseño propio.

### Seguridad operativa

Queda fuera de estos planes, salvo orden explícita posterior:

- merge de `feature/backoffice-api-integration` hacia `main`;
- publicación o merge de ramas;
- creación de repositorios remotos;
- deploy, SSH, DNS o migraciones de producción;
- edición de `vendor/`.

## Roadmap de planes independientes

### Plan 00 — Inventario y rama de consolidación

**Entrega:** rama segura desde `main` y manifiesto de frontera/delta, sin cambios runtime.

Responsabilidades:

- registrar SHAs de `main`, feature y merge-base;
- crear la rama de consolidación;
- clasificar los paths del delta como plataforma, Portal, mixtos o descartados;
- enumerar paths permitidos y prohibidos;
- documentar que `main` ya contiene código Portal.

### Plan 01 — Payments y Stripe genéricos

**Entrega:** módulo Payments reutilizable, apagado por defecto y sin dependencia de Marketing.

Responsabilidades:

- trasladar contratos, value objects, registry, factory y repositorio de eventos;
- integrar `StripeGateway` genérico y configurable;
- incorporar schema, configuración, variables de entorno de ejemplo y tests;
- excluir cualquier archivo `app/**`, `mkt_*`, checkout o membresía.

Gate principal: `php tests/run.php Payments`, con `0 failed`.

### Plan 02 — Estabilización aislada de plataforma

**Entrega:** fixes independientes consolidados y baseline de instalación caracterizado.

Responsabilidades:

- corregir la caché de instancia de `ConfiguracionService`;
- ejecutar tests Kernel/Auth/install afectados;
- inventariar seeds y migraciones legacy;
- no archivar ni eliminar datos hasta tener evidencia greenfield en el Plan 03.

### Plan 03 — PackagePaths, SQL e Installer

**Entrega:** resolución correcta de archivos cuando el Framework está instalado bajo `vendor/lebytek/framework`.

Un máximo de cuatro tasks SDD cubrirá:

1. `PackagePaths` y sus pruebas.
2. `migrate.php` y `seed.php`.
3. `install.php`, `Installer` y `bootstrap_sql`.
4. Instalación greenfield y decisión basada en evidencia sobre seeds/migraciones legacy.

Invariantes:

- `ROOT_PATH` apunta al consumidor;
- `PackagePaths::root()` apunta al paquete;
- SQL de plataforma se resuelve primero desde el paquete;
- SQL de negocio vive en el consumidor;
- el Portal no duplica `schema.sql` de plataforma.

Gates: filtros `PackagePaths` y `PlatformSqlResolve`, ambos con `0 failed`.

### Plan 04 — Skeleton mínimo

**Entrega:** aplicación consumidora standalone sin código de Lebytek Portal.

Un máximo de cuatro tasks SDD cubrirá:

1. limpieza de código, configuración y rutas de Marketing;
2. limpieza de SQL, seeds y migraciones duplicadas;
3. lista canónica de assets de plataforma;
4. bootstrap y prueba del skeleton fuera del monorepo.

El skeleton incluye CRUD Engine y Payments apagado. Excluye Marketing, `mkt_*`, `assets/publico`, landing, leads, membresías y LebytekApi.

Gate: `php tests/run.php SkeletonPurity`, más smoke desde una copia temporal standalone.

### Plan 05 — Crear `Lebytek_Portal` local

**Entrega:** proyecto Composer sibling con el negocio completo del Portal.

Responsabilidades:

- congelar el SHA fuente de `feature/backoffice-api-integration`;
- crear `Lebytek_Portal` sin modificar la feature;
- copiar solo paths de propiedad Portal;
- configurar un path repository local hacia Framework;
- ejecutar baseline de Marketing y verificar el manifiesto de ownership.

No se incorporan ramas derivadas ni worktrees. El Portal no contiene `src/` ni schema de plataforma duplicado.

### Plan 06 — Corte de frontera local

**Entrega:** Framework y Portal funcionan como árboles independientes.

Un máximo de cuatro tasks SDD cubrirá:

1. retirar `App\\` del autoload del paquete;
2. retirar Marketing y demás negocio de la raíz Framework;
3. reducir la raíz Framework a paquete más harness de tests;
4. ejecutar pruebas cruzadas Framework/Portal.

Gates Framework:

- `PackageAutoloadBoundary`;
- `Kernel`;
- suite completa de plataforma.

Gates Portal:

- `composer validate`;
- suite `Marketing`.

No se reintroduce Marketing al paquete para resolver fallos de integración. Se corrigen paths, bindings o contratos en su dueño real.

### Plan 07 — Documentación y reglas permanentes

**Entrega:** documentación y guardrails que impiden reconstruir el monorepo accidentalmente.

Incluye:

- arquitectura consumidor/paquete;
- ownership de SQL;
- sincronización de assets;
- guía de tenants;
- README de Framework y Portal;
- actualización de reglas Cursor y `CLAUDE.md`.

Debe declarar que Framework no es un sitio desplegable, Portal es un consumidor, Marketing pertenece al Portal y Payments genérico pertenece al Framework.

### Plan 08 — Preparación de publicación y cutover

**Entrega:** checklist de revisión humana y runbook, sin operaciones externas.

Incluye:

- validación de manifiestos y `composer.lock`;
- propuesta de remotes y creación futura del repo Portal;
- runbook VPS, rollback y smoke tests.

No ejecuta `gh repo create`, push, merge, deploy, SSH, DNS ni migraciones de producción. Cualquier operación posterior requiere orden explícita y un plan operativo separado.

## Dependencias y orden

Orden obligatorio:

`00 → 01 → 02 → 03 → 04 → 05 → 06 → 07 → 08`

El Plan 02 puede implementarse en paralelo técnico con el 01, pero el roadmap lo mantiene secuencial para evitar que dos sesiones modifiquen la misma rama.

Ningún plan debe empezar si su predecesor no tiene:

- commits existentes en la rama prevista;
- reporte SDD completo;
- revisión de task limpia;
- tests indicados con `0 failed`, cuando el plan modifique comportamiento ejecutable;
- progreso registrado en `.superpowers/sdd/progress.md` durante su ejecución.

## Presupuesto por plan

Cada archivo de plan tendrá entre dos y cuatro tasks revisables. Una task debe tener su propio ciclo TDD y una entrega que un reviewer pueda aprobar o rechazar independientemente.

No se agruparán en una sola task:

- cambios de plataforma y negocio;
- escritura de package paths y carve del Portal;
- publicación remota y deploy;
- eliminación de seeds y correcciones de caché.

## Manejo de errores y recuperación

- Si un path aparece como mixto, se reimplementa la parte genérica; no se copia el archivo completo.
- Si una instalación greenfield necesita un seed propuesto para archivo, ese seed permanece activo.
- Si el Portal falla tras retirar `App\\` del paquete, se corrige el ownership; no se restaura el autoload dual.
- Si la feature cambia después de congelar el SHA, los nuevos commits quedan fuera y requieren un delta posterior.
- Si una revisión encuentra una contradicción con el diseño aprobado, la ejecución se detiene para decisión humana.

## Criterios finales de éxito

La separación técnica termina cuando:

1. Framework y Portal funcionan desde directorios independientes.
2. Portal consume `lebytek/framework` únicamente mediante Composer.
3. Framework no autoload-ea `App\\` ni contiene CRM.
4. Skeleton crea una aplicación sin Marketing.
5. Payments y Stripe genéricos funcionan sin membresías.
6. SQL de plataforma se resuelve desde el paquete y el negocio desde el consumidor.
7. La documentación refleja el nuevo modelo.
8. Todos los gates de los planes 00–07 terminan con `0 failed`.
9. No se ha ejecutado merge a `main` ni ninguna operación de producción.

## Relación con el plan anterior

El archivo `2026-07-15-framework-portal-separation.md` queda como referencia detallada y fuente histórica de D1–D11, con cabecera **SUPERSEDED** para ejecución. Los planes `2026-07-17-fps-00` … `fps-08` repiten requisitos completos y no dependen de que un subagente lea el documento monolítico.
