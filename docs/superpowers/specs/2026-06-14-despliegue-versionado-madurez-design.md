# Despliegue, versionado y madurez — Design

> Documento de diseño/gobierno para formalizar cómo se **instala, versiona y despliega** cada instancia del framework Lebytek en su etapa de madurez. Es un documento **operativo y descriptivo**: documenta el sistema tal como existe hoy, fija reglas de gobierno (modelo de capas, semver) y no toca código ni configuración. Las inconsistencias detectadas se listan como hallazgos en la sección de deuda documental.

- **Fecha:** 2026-06-14
- **Estado:** Diseño aprobado (pendiente de plan de implementación documental)
- **Naturaleza:** Descriptivo + reglas de gobierno. Sin cambios de código/config en este spec.
- **Posicionamiento:** Documento paraguas / puerta de entrada única de despliegue. Complementa (no absorbe) los docs existentes y delega en ellos los temas profundos.
- **Audiencia:** Desarrollador que despliega (principal); cliente técnico en hosting compartido (secundaria).

---

## 1. Resumen y problema

El framework entró en etapa de madurez: ya hay módulos (CRUD Engine, calendario, pdf-kit, reportes, auth), un instalador con manifiestos y tracking de versiones, una página de estado y un test de integridad de estandarización. El motor de instalación ya está implementado (ver spec archivado 2026-06-08).

El problema **ya no es construir el motor**, sino que **falta una guía operativa unificada** que responda, sin leer código:

- ¿Qué archivos edito para levantar un cliente nuevo con solo dashboard + CRUD demo?
- ¿Qué corro en el VPS después de un `git pull`?
- ¿Dónde pongo logo, nombre de empresa y módulos activos?
- ¿Qué **no** debo tocar porque es core del framework?

Hoy esas respuestas están dispersas entre cuatro documentos (`instalacion-y-versionado.md`, `despliegue_hosting.md`, `seguridad_secretos_deploy.md`, `vertical-onboarding.md`) y el spec archivado. No hay un **mapa único** que separe el core inmutable de la configuración por instancia y por vertical, ni una **regla escrita** de versionado.

**Objetivo:** entregar la puerta de entrada única de despliegue: un modelo conceptual de capas, un inventario maestro de superficies de configuración, seis playbooks por tipo de despliegue, reglas de versionado coherentes y un índice que diga qué doc es dueño de cada tema.

---

## 2. Estado actual (qué ya existe en el repo)

Estas piezas **ya están implementadas** y este spec las documenta y referencia; no las reimplementa.

### 2.1 Motor de instalación y versionado

- **Manifiestos de módulo** en `config/modules/*.php`: declaran `clave`, `nombre`, `version`, `obligatorio`, `requiere` (dependencias), y los archivos `.sql`/cruds que el módulo **posee**. Algunos declaran además `bootstrap_sql` (ruta a `database/schema/modules/<m>.sql`) y, en el caso de calendario, `calendars`.
- **Tablas de versionado** `cfg_migraciones` (archivo + checksum sha256 + módulo dueño) y `cfg_modulos` (clave + versión instalada + activo).
- **Versión de plataforma** en `config/app.php` → `version` (hoy `1.0.0`).
- **Motor** en la capa Application: `Installer` (con `requisitosCheck`, `plan`, `aplicar`, `baseline`), `ModuleRegistry`, `DependencyResolver`, `DeploymentStatus`; contratos en Domain; repositorios PDO en Infrastructure.

### 2.2 Superficies de instalación (misma lógica compartida)

| Superficie | Cómo |
|---|---|
| Wizard web | `https://tu-host/install/` (lock + token en producción) |
| CLI instalar | `php scripts/install.php [--modules=core,crud-engine] [--dry-run] [--baseline]` |
| CLI estado | `php scripts/status.php` |
| Página admin | `/admin/sistema/estado` (permiso `sistema.ver`) |

### 2.3 Módulos declarados hoy

`core` (obligatorio), `dashboard`, `crud-engine`, `calendario`, `pdf-kit`, `reportes`. Toda migración/seed pertenece a **exactamente un** manifiesto; el test `tests/Install/EstandarizacionIntegridadTest.php` falla si hay archivos huérfanos o con doble dueño.

### 2.4 Evolución desde el spec archivado

El spec 2026-06-08 definió el modelo de datos (`cfg_migraciones`, `cfg_modulos`) y el motor. Desde entonces el repo evolucionó: apareció la clave `bootstrap_sql` (que el spec no contemplaba) y se sumaron los módulos `pdf-kit` y `reportes`. El presente documento toma esa realidad como punto de partida.

---

## 3. Alcance / fuera de alcance

### Dentro de alcance

- Modelo conceptual de **4 tiers** que clasifica cada superficie de configuración.
- **Inventario maestro** de superficies (fuente única de atributos).
- **Seis playbooks** por tipo de despliegue que referencian el inventario.
- **Reglas de versionado** (semver independiente por plataforma y por módulo, disparadores de nivel, procedimiento ante checksum modificado).
- **Checklists pre/post deploy** por entorno (local, VPS auto-pull, hosting compartido Apache).
- **Índice de relación** con los docs existentes (reemplaza/complementa/obsoleta).

### Fuera de alcance (YAGNI)

- Multi-tenant.
- Rollback automático de migraciones.
- Generador web de `.env`.
- Cambios de código o configuración (este spec no renombra claves ni edita archivos; los hallazgos quedan documentados como deuda).
- Reabsorber el contenido de los docs existentes (se complementan, no se duplican).

---

## 4. Modelo conceptual: 4 tiers

Cada superficie de configuración pertenece a **exactamente un** tier, definido por *quién la posee* y *dónde vive*.

| Tier | Qué es | Vive en | ¿Se commitea? | Ejemplos |
|---|---|---|---|---|
| **T1 · Core inmutable** | El framework. El operador no lo edita por cliente. | Repo versionado | Sí | `app/`, `database/schema/schema.sql`, `config/modules/*.php`, `config/container.php`, `scripts/`, `routes/` |
| **T2 · Módulo opcional de plataforma** | Código que viene en el repo pero se **activa por instancia**. | Repo (código) + BD (estado en `cfg_modulos`) | Sí el código; su *toggle* es T3 | `crud-engine`, `calendario`, `pdf-kit`, `reportes` y sus `bootstrap_sql` |
| **T3 · Instancia / cliente** | Lo que distingue un cliente de otro **sin tocar producto**. | Servidor (`.env`) + BD (`cfg_configuraciones`) + un punto del repo (`vertical.php`) | `.env` **nunca**; `vertical.php` sí | `.env`, `config/vertical.php`, branding (logo/colores/nombre), cuenta admin |
| **T4 · Vertical de negocio** | El producto concreto (`dom_*`). **No** vive en el repo base. | Repo/rama del vertical + BD | En su propio repo/rama, no en la base | tablas `dom_*`, sus cruds JSON, entidades, casos de uso, rutas y permisos/menú propios |

### 4.1 Regla de oro

> **Editar T1** = cambias el framework para *todos* los clientes.
> **Editar T2** = activas o desactivas una capacidad ya construida.
> **Editar T3** = personalizas *un* cliente.
> **Editar T4** = construyes el producto de negocio.

Un operador cuyo trabajo es **levantar clientes** vive en **T3** y nunca toca T1. Un desarrollador de producto trabaja en **T4** (y, ocasionalmente, evoluciona T1/T2 como mantenedor del framework).

### 4.2 La excepción de `config/vertical.php`

`config/vertical.php` es físicamente un archivo del repo (parecería T1) pero **semánticamente es T3**: su contenido cambia por instancia. El spec lo declara como **el único archivo del repo versionado que es legítimamente por-instancia**. Por eso editarlo **no** cuenta como "tocar el core", aunque sí se commitea por instancia. Esta dualidad es deliberada y se documenta para que nadie la confunda con deuda.

### 4.3 Diferencia clave: T2 ≠ T4

Activar `reportes` (T2) es marcar un toggle y correr el instalador sobre código que **ya existe en el repo**. Crear un `dom_inventario` (T4) es **escribir producto nuevo** que no viaja en la base. Confundirlos lleva a buscar en el repo base código de negocio que nunca estuvo ahí.

---

## 5. Flujos de despliegue

Los seis tipos de despliegue se apoyan en el **mismo motor** ya existente. Diagrama de alto nivel:

```
                         ┌─────────────────────────────┐
                         │   Motor de instalación       │
                         │   (Installer + ModuleRegistry│
                         │    + DependencyResolver +     │
                         │    DeploymentStatus)          │
                         └──────────────┬──────────────┘
            ┌───────────────┬───────────┼───────────┬───────────────┐
            ▼               ▼           ▼           ▼               ▼
        Wizard web      CLI install   CLI status  Página         (lectura)
        public/install/ install.php   status.php  /admin/sistema/estado

  Tipo de despliegue          Toca tier(s)     Pasa por el motor
  ─────────────────────────────────────────────────────────────────
  1. Greenfield               T1·T2·T3         Sí (schema + plan/aplicar)
  2. Update de plataforma      T1·T2           Sí (solo pendientes)
  3. Alta de módulo opcional   T2·T3           Sí (--modules)
  4. Onboarding vertical dom_* T4              Según el módulo (ver §11)
  5. Solo branding/empresa     T3              No (panel Ajustes / BD)
  6. Solo secretos/entorno     T3              No (.env en servidor)
```

Los flujos 5 y 6 **no pasan por el instalador**: son cambios puros de instancia (BD de configuración o `.env`). Esto es central para responder "¿qué corro?": la respuesta a veces es *nada del motor*.

---

## 6. Inventario maestro + playbooks (núcleo del documento)

### 6.1 Inventario maestro de superficies

Una fila por superficie lógica (se agrupan archivos homogéneos, p. ej. `config/cruds/*.json`, en una sola fila). Es la **fuente única** de atributos; los playbooks la referencian por nombre y no repiten estos datos.

Leyenda de columnas:
- **Tier:** T1–T4 según §4.
- **Dueño:** dónde reside la verdad (Repo / Servidor / BD).
- **¿`install.php`?:** si el motor de instalación la consume o ejecuta.
- **¿BD?:** si su aplicación modifica la base de datos.
- **¿Reinicio?:** si requiere recargar el proceso/caché para tomar efecto.
- **¿Commit?:** si debe versionarse en git.

| Superficie | Tier | Dueño | ¿`install.php`? | ¿BD? | ¿Reinicio? | ¿Commit? | Propósito |
|---|---|---|---|---|---|---|---|
| `.env` | T3 | Servidor | No (lo lee) | No | No¹ | **Nunca** | Secretos y entorno (DB, APP_KEY, mail, flags) |
| `config/vertical.php` | T3 (excepción §4.2) | Repo | No | No | No | Sí | Toggle de módulos activos + labels de menú |
| `cfg_configuraciones` (panel Ajustes) | T3 | BD | No | — | No | No | Branding: nombre, logo, colores, posición de menú |
| Cuenta admin inicial | T3 | BD | Sí (greenfield) | Sí | No | No | Primer acceso; reemplaza el seed por defecto |
| `config/app.php` | T1 | Repo | No (lo lee) | No | Sí² | Sí | Versión de plataforma, nombre, zona, asset version |
| `config/container.php` | T1 | Repo | No (lo consume) | No | Sí² | Sí | Bindings DI del framework |
| `config/modules/*.php` | T1 | Repo | Sí (lo consume) | Indirecto | No | Sí | Manifiesto: versión, deps, archivos dueños |
| `database/schema/schema.sql` | T1 | Repo | Sí (base) | **Sí** | No | Sí | DDL de plataforma + datos iniciales |
| `database/schema/modules/<m>.sql` | T2 | Repo | **Sí** (`bootstrap_sql`) | **Sí** | No | Sí | DDL + datos de un módulo opcional |
| `database/migrations/*.sql` | T1/T2 | Repo | **Sí** | **Sí** | No | Sí | Cambios incrementales con tracking por checksum |
| `database/seeds/*.sql` | T1/T2 | Repo | **Sí** | **Sí** | No | Sí | Datos sembrables con tracking |
| `config/cruds/*.json` | T2/T4 | Repo | No (runtime los carga) | No | No | Sí | Definición declarativa de recursos CRUD |
| `config/dashboard.php` | T1/T2 | Repo | No | No | No | Sí | Lista de providers del dashboard |
| `routes/web.php`, `routes/api.php` | T1/T4 | Repo | No | No | No | Sí | Rutas del framework y de verticales |
| `core_menu_items` | T1/T2/T4 | BD | Sí (seeds) | **Sí** | No | No (datos) | Menú admin dinámico filtrado por RBAC |
| Permisos / roles (`auth_*`) | T1/T2/T4 | BD | Sí (seeds) | **Sí** | No | No (datos) | RBAC; slugs `modulo.accion` |
| `cfg_migraciones` / `cfg_modulos` | T1 | BD | Sí (las gestiona) | **Sí** | No | No (datos) | Tracking de qué se aplicó y qué versión hay |
| tablas `dom_*` y código de vertical | T4 | Repo vertical + BD | Según el módulo | **Sí** | No | En repo vertical | Dominio de negocio del cliente |
| `storage/install.lock` | T3 | Servidor | Sí (lo escribe) | No | No | **Nunca** | Marca de instalación completada |

> ¹ **`.env`:** cambiarlo no reinicia el proceso en PHP por-request, pero si hay OPcache/persistencia conviene recargar para garantizar lectura fresca; ver checklist por entorno (§9).
> ² **`config/app.php` / `container.php`:** "Reinicio = Sí²" significa que en entornos con OPcache o servidor persistente conviene recargar para que el cambio tome efecto; con `php -S` local basta reiniciar el servidor.

### 6.2 Playbooks por tipo de despliegue

Cada playbook indica **objetivo → superficies que toca (por nombre del inventario) → pasos → comando(s) → riesgo principal → verificación**, y cierra con la **pregunta de éxito** que responde.

#### Playbook 1 — Instalación nueva (greenfield)

- **Objetivo:** levantar un cliente nuevo con base de datos propia.
- **Superficies:** `.env`, `database/schema/schema.sql`, `config/modules/*.php`, `database/schema/modules/<m>.sql`, `config/vertical.php`, cuenta admin, `cfg_configuraciones`, `storage/install.lock`.
- **Pasos:** crear BD vacía (utf8mb4) → copiar `.env.example` a `.env` y definir `DB_*`/`APP_URL` → ejecutar el instalador (wizard web o CLI) seleccionando los módulos deseados → crear cuenta admin → ajustar branding en el panel Ajustes → confirmar `vertical.php` con los módulos activos.
- **Comando:** `php scripts/install.php --modules=core,dashboard,crud-engine` (o el wizard `public/install/`).
- **Riesgo principal:** document root mal apuntado (debe ser `public/`); credenciales por defecto sin cambiar.
- **Verificación:** `php scripts/status.php` muestra plataforma + módulos; `/login` carga con estilos.
- **Responde:** *"¿Qué archivos edito para levantar un cliente nuevo con solo dashboard + CRUD demo?"* → `.env` (T3) y la selección `--modules=core,dashboard,crud-engine`; nada de T1.

#### Playbook 2 — Actualización de plataforma (mismo cliente, nuevo release)

- **Objetivo:** traer un nuevo release sin perder datos.
- **Superficies:** código T1/T2 (vía `git pull`), `database/migrations/*.sql`, `database/schema/modules/<m>.sql`, `cfg_migraciones`/`cfg_modulos`.
- **Pasos:** `git pull` (o auto-pull en VPS) → correr el instalador, que aplica **solo lo pendiente** por checksum y actualiza versiones de módulo → revisar estado.
- **Comando:** `php scripts/install.php` seguido de `php scripts/status.php`.
- **Riesgo principal:** checksum modificado tras aplicar (drift) → ver §7.4; no re-ejecutar a ciegas.
- **Verificación:** `status.php` sin migraciones pendientes ni checksums modificados inesperados.
- **Responde:** *"¿Qué corro en el VPS después de un `git pull`?"* → `install.php` (aplica pendientes) y `status.php` (confirma).

#### Playbook 3 — Alta de módulo opcional (T2)

- **Objetivo:** activar una capacidad ya presente en el repo (p. ej. `reportes`).
- **Superficies:** `config/vertical.php`, `config/modules/<m>.php` (lectura), `database/schema/modules/<m>.sql`, `core_menu_items`/permisos del módulo.
- **Pasos:** activar la clave del módulo en `vertical.php` → correr el instalador acotado a ese módulo (resuelve dependencias: `reportes` requiere `core`, `crud-engine`, `pdf-kit`) → verificar menú/permisos.
- **Comando:** `php scripts/install.php --modules=reportes`.
- **Riesgo principal:** olvidar una dependencia (la resuelve el `DependencyResolver`); inconsistencia de clave entre `vertical.php` y el manifiesto (ver §10).
- **Verificación:** `status.php` lista el módulo como instalado y activo.
- **Responde:** *"¿Dónde activo un módulo que ya viene en el framework?"* → `vertical.php` + `install.php --modules=...`.

#### Playbook 4 — Onboarding de vertical de negocio (T4, `dom_*`)

- **Objetivo:** construir el producto concreto del cliente.
- **Superficies:** tablas `dom_*`, código de vertical, `config/cruds/*.json` propios, `routes/web.php`, permisos/menú propios, clave en `vertical.php`.
- **Pasos:** seguir el checklist oficial de módulo de dominio (no se duplica aquí).
- **Comando:** según el módulo; encuadrado en T4.
- **Riesgo principal:** intentar tratar un `dom_*` como módulo opcional del repo base.
- **Verificación:** el vertical aparece en menú filtrado por RBAC; sus rutas responden.
- **Responde:** *"¿Cómo agrego un dominio de negocio nuevo?"* → remite a `vertical-onboarding.md` y `uso-de-modulo-dominio.md`.

#### Playbook 5 — Solo branding / empresa (sin tocar código)

- **Objetivo:** cambiar identidad visual del cliente.
- **Superficies:** `cfg_configuraciones` (panel Ajustes) **únicamente**.
- **Pasos:** entrar al panel Ajustes y cambiar nombre, logo, colores y posición de menú.
- **Comando:** ninguno; **no** pasa por `install.php`.
- **Riesgo principal:** ninguno relevante a nivel despliegue.
- **Verificación:** la marca nueva se ve tras refrescar.
- **Responde:** *"¿Dónde pongo logo y nombre de empresa?"* → panel Ajustes (BD `cfg_configuraciones`); cero código.

#### Playbook 6 — Solo secretos / entorno (`.env`)

- **Objetivo:** cambiar credenciales o flags de entorno.
- **Superficies:** `.env` **únicamente**.
- **Pasos:** editar `.env` en el servidor según las reglas de seguridad → recargar si hay OPcache/proceso persistente.
- **Comando:** ninguno del motor.
- **Riesgo principal:** commitear `.env` (jamás); secretos filtrados → rotación obligatoria.
- **Verificación:** la app conecta/usa el nuevo valor; `git ls-files .env` no devuelve nada.
- **Responde:** *"¿Qué no debo tocar porque es core?"* → todo T1; los secretos viven en `.env` (T3, nunca en repo).

---

## 7. Versionado y actualización

### 7.1 Dos números independientes con semver

- **Versión de plataforma** (`config/app.php` → `version`): versiona el **core (T1)** y el repo como *release agregado*. Sube cuando cambia el core o se publica un release del conjunto.
- **Versión por módulo** (manifiesto → `version`, reflejada en `cfg_modulos`): sube **solo** cuando cambia ese módulo. Independiente de la de plataforma.

Activar o actualizar un módulo **no** obliga a subir la versión de plataforma, y un release de plataforma **no** renumera los módulos.

### 7.2 Disparadores semver (regla escrita)

Aplica por igual a la versión de plataforma y a la de cada módulo, según qué cambió en su propio ámbito:

| Cambio | Nivel | Ejemplo |
|---|---|---|
| Cambio incompatible de datos/estructura existente | **MAJOR** | renombrar o eliminar columna en uso; nueva semántica de un campo; quitar permiso |
| Adición que no rompe lo previo | **MINOR** | nueva tabla de un módulo, nuevo crud, permiso o menú nuevo |
| Fix sin tocar BD ni contrato | **PATCH** | corrección de vista, bugfix lógico interno |

### 7.3 Detección de "actualización disponible"

La página `/admin/sistema/estado` (y `status.php`) comparan la **versión declarada** en el manifiesto contra la **versión instalada** en `cfg_modulos`. Si difieren, marcan "actualización disponible". Esto ya está implementado; el spec solo fija *cuándo* el desarrollador debe mover cada número.

### 7.4 Procedimiento ante checksum modificado

El motor calcula un sha256 de cada `.sql` aplicado y lo guarda en `cfg_migraciones`. Si un archivo ya aplicado cambia de checksum, el estado lo marca como **"modificado tras aplicar"** y **no** lo re-ejecuta (evita corromper datos). Reglas operativas:

1. **Nunca** editar un `.sql` ya aplicado en producción. Para cambiar algo aplicado, se crea una **migración nueva** que aplica el delta.
2. Si el cambio ya está reflejado manualmente en la BD y el `.sql` se editó por error, la alerta indica **drift**: documentarlo. (Re-sellar el checksum es una *decisión abierta* — §10 — porque hoy no hay comando para ello.)
3. La alerta de `status.php` es señal de **drift**, no un error que se "arregla" re-corriendo el archivo.

### 7.5 Baseline para deploys legacy

Un despliegue que existía antes del tracking adopta el histórico con `php scripts/install.php --baseline`: marca las migraciones/seeds presentes como aplicadas (sin re-ejecutarlas) y registra los módulos detectados. Se corre **una sola vez** por deploy legacy.

---

## 8. Seguridad y secretos (resumen; fuente: `seguridad_secretos_deploy.md`)

Reglas resumidas (la fuente autoritativa es `docs/core/seguridad_secretos_deploy.md`; aquí solo el extracto operativo):

- En el repo solo vive `.env.example`. **`.env` jamás se versiona** (T3, dueño = servidor).
- El VPS hace auto-pull de `main`: cualquier secreto commiteado se considera comprometido y exige rotación (`DB_PASSWORD`, `APP_KEY`).
- Checklist mínimo pre-producción: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, `APP_KEY` único por entorno, `DB_PASSWORD` rotado respecto a desarrollo, `MAX_UPLOAD_MB` acorde al servidor, y `git ls-files .env` vacío.
- El wizard web exige `INSTALL_TOKEN` cuando `APP_ENV=production` y se bloquea con `storage/install.lock` tras instalar.

---

## 9. Checklist pre/post deploy por entorno

| Ítem | Local (`php -S … -t public`) | VPS Linux (auto-pull) | Hosting compartido Apache |
|---|---|---|---|
| Document root en `public/` | N/A (se pasa `-t public`) | Sí | **Crítico** (ver `despliegue_hosting.md`) |
| `.env` presente y correcto | Sí (`APP_ENV=local`) | Sí (`production`, secretos rotados) | Sí (`production`) |
| Tras `git pull` correr `install.php` | Manual | **Sí** (parte del flujo de release) | Sí, vía wizard o CLI si hay acceso |
| Verificar con `status.php` | Opcional | **Sí** | Opcional (o página `/admin/sistema/estado`) |
| `APP_DEBUG` | `true` ok | `false` | `false` |
| `SESSION_SECURE` | `false` ok (sin HTTPS) | `true` | `true` |
| Recargar OPcache/proceso tras cambio de `config/*` o `.env` | Reiniciar `php -S` | Recargar PHP-FPM/servicio | Según panel del hosting |
| `install.lock` presente tras instalar | Opcional | Sí | Sí |
| Lista de módulos coincide con `vertical.php` | Sí | Sí | Sí |

**Post-deploy en los tres entornos:** abrir `/login` (carga con estilos), iniciar sesión, abrir `/admin/dashboard`, y revisar `status.php` o `/admin/sistema/estado` sin pendientes ni drift inesperado.

---

## 10. Riesgos, decisiones abiertas y deuda documental

Hallazgos detectados al explorar el repo. Se documentan como deuda; **este spec no los resuelve** (naturaleza descriptiva + reglas).

| # | Hallazgo | Impacto | Seguimiento propuesto |
|---|---|---|---|
| D1 | Claves con guion bajo en `config/vertical.php` (`pdf_kit`, `reportes`) vs guion en manifiesto (`pdf-kit`). | Confusión al cruzar toggle ↔ manifiesto; potencial fallo silencioso si algo casa por clave exacta. | Decidir una convención única de clave y alinear; tarea de un plan posterior, no aquí. |
| D2 | Spec archivado 2026-06-08 desactualizado: no menciona `bootstrap_sql`, `pdf-kit` ni `reportes`. | Lectura engañosa si se toma como guía operativa vigente. | Marcado como histórico en §11; su modelo de datos sigue válido, su narrativa operativa la reemplaza este doc. |
| D3 | No existe comando para **re-sellar** un checksum tras un cambio legítimo. | El drift solo se puede documentar, no limpiar; alertas persistentes. | Evaluar un `--reseal`/equivalente en un spec futuro; hoy decisión abierta (§7.4). |
| D4 | `vertical.modules` mezcla slugs de menú raíz con claves de módulo (el comentario del archivo asume que cada clave es un slug de menú). | Modelo mental ambiguo sobre qué representa cada clave del toggle. | Aclarar la semántica de `vertical.modules` (¿toggle de módulo o de menú?) en un ajuste de doc/código posterior. |

**Decisiones abiertas (no bloqueantes):**
- Convención definitiva de claves de módulo (guion vs guion bajo) — D1.
- Mecanismo de re-sellado de checksum — D3.
- Semántica única de `vertical.modules` — D4.

---

## 11. Relación con docs existentes

Este documento es la **puerta de entrada única de despliegue**. No absorbe contenido: delega los temas profundos en sus dueños y los referencia.

| Documento | Relación | Qué aporta / queda |
|---|---|---|
| `docs/core/instalacion-y-versionado.md` | **Complementa** | Sigue siendo dueño de la mecánica del motor (manifiestos, superficies, baseline). Este spec añade el modelo de tiers y la guía operativa. |
| `docs/core/despliegue_hosting.md` | **Complementa** | Sigue siendo dueño del detalle Apache / document root. El playbook de hosting compartido enlaza aquí. |
| `docs/core/seguridad_secretos_deploy.md` | **Complementa** | Sigue siendo dueño de secretos y rotación. La §8 es solo extracto. |
| `docs/core/vertical-onboarding.md` | **Complementa** | Sigue siendo dueño del onboarding T4. El Playbook 4 delega aquí y en `uso-de-modulo-dominio.md`. |
| `docs/modules/uso-de-modulo-dominio.md` | **Complementa** | Checklist oficial de módulo de dominio (T4). |
| `docs/archive/superpowers/specs/2026-06-08-instalacion-estandarizacion-versionado-design.md` | **Obsoleta (parcial)** | Su **modelo de datos `cfg_*` y el motor siguen vigentes**. Su **narrativa operativa** queda superada por este documento. No usar como guía de despliegue. |

**Qué reemplaza este spec:** el rol de "guía de despliegue" que estaba disperso entre los docs anteriores y el spec archivado, ahora centralizado aquí como índice operativo único.

---

## 12. Criterios de aceptación del documento

1. Un operador responde, sin leer código, las cuatro preguntas de éxito (§1) usando los playbooks 1, 2, 5 y 6 y la regla de oro (§4.1).
2. El modelo de 4 tiers clasifica sin ambigüedad cada superficie del inventario (§6.1), incluida la excepción de `vertical.php` (§4.2).
3. La sección 6 tiene **un** inventario maestro como fuente única y seis playbooks que lo referencian sin repetir atributos.
4. La regla de versionado distingue plataforma vs módulo, con tabla de disparadores y procedimiento de checksum modificado (§7).
5. Los hallazgos D1–D4 quedan registrados como deuda, sin que el spec modifique código o config.
6. La §11 declara explícitamente, por documento, qué se complementa, qué se reemplaza y qué queda obsoleto.
