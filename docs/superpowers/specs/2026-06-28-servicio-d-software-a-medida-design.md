# Servicio D — "Software a la medida" (tooling del esqueleto) — Design Spec

> **Estado:** diseño aprobado, pendiente de plan de implementación (writing-plans).
> **Contexto:** el cuarto de los 4 servicios de dominio (A–D) descritos en
> `docs/superpowers/specs/2026-06-27-separacion-framework-v1-dominio-design.md` §7.
> Servicio **D = Software a la medida**: el **modelo de entrega** del servicio a la
> medida. No es un vertical `dom_*`; es **developer-experience / scaffolding** sobre
> el artefacto **`lebytek/skeleton`** que produce la separación v1.0. Convierte el
> esqueleto en una **plantilla provisionable**: `composer create-project
> lebytek/skeleton mi-proyecto` deja un proyecto nuevo, personalizado y listo para
> arrancar, que consume `lebytek/framework` por VCS privado, con demos OFF.
> Cada proyecto cerrado = una **instancia nueva del esqueleto**.

---

## 0. ⚠️ Prerrequisito de verificación antes de planear (LEER PRIMERO)

**Este spec NO se ejecuta todavía.** D **depende por completo** de que la
**Separación Framework v1.0 / Dominio**
(`docs/superpowers/plans/2026-06-27-separacion-framework-v1-dominio.md`) haya
**terminado y esté verde**, porque D opera sobre los artefactos que esa separación
crea. En concreto, antes de generar el plan de implementación de D es obligatorio
re-verificar contra el estado real:

1. **Existe el artefacto `lebytek/skeleton`.** La separación extrae el esqueleto
   ejecutable (entry point, `config/`, `routes/`, `.env.example`, bootstrap del
   proyecto) a su propio repo/artefacto (separación §5.2). D **no crea** el
   esqueleto: lo **convierte en provisionable**. Confirmar dónde vive (repo
   `lebytek/skeleton`) y que **arranca y pasa el arnés** (`php tests/run.php`).
2. **Existe el paquete `lebytek/framework`** publicado y taggeado (p. ej. v1.0.0) en
   GitHub **privado**, consumible por Composer vía repositorio VCS (separación §8.1).
3. **Demos OFF por defecto.** Confirmar que los demos viajaron al esqueleto y quedan
   **OFF** en `config/vertical.php` (separación criterio 8). El post-script de D
   refuerza este estado, no lo inventa.
4. **Mecanismo de auth a repos privados.** Confirmar cómo el VPS/dev se autentica a
   GitHub para Composer (deploy key SSH o token), porque `create-project` lo necesita
   (§D / §4).
5. **Datos "verificados (2026-06-28)".** Re-confirmar rutas y nombres: el terreno se
   mueve (paths de `config/vertical.php`, `scripts/`, `.env.example`,
   `php tests/run.php`).

> Regla operativa: **no escribir el plan de implementación de D hasta que la
> separación esté terminada y verde**, el esqueleto arranque, y `lebytek/framework`
> esté publicado por VCS privado. Entonces re-leer las piezas listadas y ajustar.

---

## 1. Objetivo y alcance

Hacer **repetible y a prueba de errores** el arranque de un nuevo proyecto "a la
medida". El servicio se vende como desarrollo de software a medida; técnicamente,
cada proyecto cerrado nace como una **instancia limpia del esqueleto** que ya trae el
framework, RBAC, dashboard, CRUD Engine, etc. D entrega el **camino de provisión**:

```
composer create-project lebytek/skeleton  →  proyecto personalizado y arrancable
   (consume lebytek/framework por VCS privado, demos OFF, .env y APP_KEY listos)
```

### Alcance MVP (este spec)
- **`composer.json` del esqueleto** preparado como plantilla provisionable
  (`type: project`, `repositories` VCS al framework privado, hook `post-create`).
- **Script de personalización post-create** (`scripts/post_create_project.php`):
  nombre de proyecto, `.env` + `APP_KEY`, demos OFF, reinicio de git, próximos pasos.
- **Comando `doctor`** (`scripts/doctor.php`): valida prerrequisitos (PHP, Composer,
  git, **auth al VCS privado**, resolubilidad de `lebytek/framework`, extensiones)
  **antes** de provisionar y como diagnóstico tras instalar.
- **Documentación** ("Crear un proyecto a la medida") en el README del esqueleto.
- **Prueba/CI de provisión** que verifica el resultado de instanciar.

### Fuera de alcance (otros specs / iteraciones)
- **Embudo de leads `a_la_medida` y su CRM** → son del **Servicio A**
  (`servicio_interes='a_la_medida'`). D arranca **después** de cerrar el proyecto;
  no toca la conversión comercial ni vincula `lead_id`.
- **Generación de módulos de dominio** (`dom_*`): D entrega el cascarón; construir lo
  a medida sigue la guía existente `docs/modules/uso-de-modulo-dominio.md` (§6, "día 2").
- Multi-tenancy, plantillas de proyecto por tipo de cliente, generadores de código
  (scaffolders de entidades/CRUD), CI/CD por proyecto, pagos/facturación.
- Publicar el esqueleto o el framework en Packagist (siguen **privados** vía VCS).

### Regla de separación respetada
D vive en el **esqueleto** (`lebytek/skeleton`), no en el paquete `lebytek/framework`
ni en el producto Repo 2. **No añade tablas `dom_*` ni rutas de runtime.** El paquete
framework jamás referencia el tooling de D; el tooling sí asume el contrato público
del esqueleto (config togglable, `.env.example`, arnés de tests).

---

## 2. Estado verificado del punto de partida (2026-06-28)

> Sujeto al §0: re-verificar tras la separación.

- **El repo actual** ya migró a autoload Composer con
  `Lebytek\Framework\ → src/` y `App\ → app/` (`composer.json` raíz). La separación
  lo parte en **tres artefactos** (separación §1, §5): paquete / esqueleto / producto.
- El esqueleto (artefacto 2) **aún se está extrayendo** por el plan de separación;
  hereda de este repo: `public/` (entry point), `config/`, `routes/`, `.env.example`,
  `scripts/` (incl. `seed.php`), arnés `tests/run.php`, y demos **OFF**.
- **Convención de comandos:** ya existe el patrón de scripts CLI sueltos en
  `scripts/` corribles con `php scripts/<x>.php` (p. ej. `seed.php`). D añade
  `post_create_project.php` y `doctor.php` siguiendo ese patrón.
- **Toggle de módulos:** `config/vertical.php` controla qué módulos están encendidos;
  los demos quedan OFF ahí. El post-script de D **reescribe/forza** ese archivo a
  "demos OFF" en la instancia nueva.
- **Cifrado:** el framework cifra tokens de instancias (Green API) con una clave de
  app; el `.env`/`APP_KEY` debe existir y ser único por instancia. El post-script lo
  genera (no se reutiliza la clave del esqueleto).

---

## 3. El esqueleto provisionable (`composer.json` de `lebytek/skeleton`)

El esqueleto se declara como **plantilla de proyecto Composer**:

```jsonc
{
  "name": "lebytek/skeleton",
  "description": "Esqueleto de aplicación Lebytek (plantilla de proyecto a la medida)",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": ">=8.1",
    "lebytek/framework": "^1.0"
  },
  "autoload": { "psr-4": { "App\\": "app/" } },
  "repositories": [
    { "type": "vcs", "url": "git@github.com:Parzival2103/Lebytek_Framework.git" }
  ],
  "scripts": {
    "post-create-project-cmd": "php scripts/post_create_project.php",
    "doctor": "php scripts/doctor.php"
  },
  "config": { "sort-packages": true }
}
```

Notas:
- `repositories` VCS apunta al **repo privado** del framework (separación §8.1). La URL
  exacta se confirma en el plan (puede ser SSH o HTTPS+token según auth del entorno).
- `lebytek/framework` se consume por **constraint de versión** (tag), no por `dev-main`,
  para reproducibilidad por proyecto.
- `post-create-project-cmd` dispara la personalización **una sola vez** al instanciar.
- El script `doctor` queda como comando Composer (`composer doctor`) **y** corrible
  directo (`php scripts/doctor.php`) para usarlo **antes** de `create-project`.

---

## 4. Comando `doctor` (`scripts/doctor.php`)

Diagnóstico de prerrequisitos. Se usa en dos momentos: **antes** de provisionar (el
desarrollador lo corre sobre un clon del esqueleto o vía `composer doctor`) y **tras**
instalar, para confirmar que la instancia quedó sana. **No modifica nada**; solo
reporta y sale con código != 0 si algo crítico falla (apto para CI).

Chequeos (cada uno OK / ADVERTENCIA / FALLA):

| Chequeo | Criterio | Severidad |
|---|---|---|
| **PHP** | versión `>= 8.1` | crítico |
| **Extensiones PHP** | `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `json` presentes | crítico |
| **Composer** | binario disponible y versión 2.x | crítico |
| **git** | binario disponible | advertencia |
| **Auth VCS privado** | el repo `lebytek/framework` es **alcanzable** con la auth actual (deploy key/token) | crítico |
| **Resolubilidad del paquete** | `lebytek/framework` resuelve a una versión que cumple el constraint | crítico |
| **`.env`** | existe (tras instalar) y `APP_KEY` no vacío | crítico (post) |
| **DB** | si `.env` está configurado, conexión PDO de prueba | advertencia |
| **Extensión opcional** | `gd` (para PDFs/imágenes) presente | advertencia |

> El chequeo de **auth VCS** es la clave: sin él, `create-project` falla con un error
> de Composer opaco. `doctor` lo detecta **antes** y explica cómo configurar la auth
> (deploy key/token), enlazando la sección del README (§7).
> Salida final: resumen con conteo de OK/advertencias/fallas y, si hay fallas
> críticas, instrucciones accionables + exit code 1.

---

## 5. Script de personalización post-create (`scripts/post_create_project.php`)

Corre **una vez** tras `composer create-project` (vía `post-create-project-cmd`).
Soporta **interactivo** (pregunta) y **no-interactivo** (flags / variables de entorno,
para CI/automatización: `--name=`, `--namespace=`, `--no-interaction`). Es
**idempotente y seguro**: no pisa un `.env` existente y aborta limpio si falta auth.

Pasos:
1. **Nombre del proyecto** → escribe `APP_NAME` en `.env`. Namespace de dominio:
   **default `App\`** (no se renombra salvo `--namespace=` explícito; renombrar es
   opcional y reescribe `composer.json` + `app/`).
2. **`.env`**: copia `.env.example` → `.env` **solo si no existe** (si existe, avisa y
   no sobreescribe). **Genera `APP_KEY`** (clave de cifrado única de la instancia,
   alta entropía); nunca reutiliza la del esqueleto.
3. **Demos OFF**: fuerza `config/vertical.php` a dejar **todos los módulos demo/showcase
   en OFF** (idempotente: si ya están OFF, no cambia nada).
4. **Reinicio de git**: elimina el historial heredado del esqueleto y hace
   `git init` + commit inicial limpio ("Proyecto a la medida inicializado desde
   lebytek/skeleton"). Si no hay git, lo omite con advertencia.
5. **Próximos pasos**: imprime guía clara — configurar DB en `.env`,
   `php scripts/seed.php`, `php -S localhost:8000 -t public`, correr
   `composer doctor`, y enlace a la guía de dominio (§6).

> El post-script **no** instala dependencias (Composer ya lo hizo en `create-project`)
> ni toca el paquete framework. Solo personaliza la instancia.

---

## 6. "Día 2" — construir lo a la medida dentro de la instancia

D entrega el **cascarón listo**; el desarrollo a medida **no se reimplementa** aquí.
La instancia ya trae el camino estándar para añadir un vertical de dominio: el
README enlaza la guía existente **`docs/modules/uso-de-modulo-dominio.md`** (checklist:
tablas `dom_*` → entidades/interfaces → repos Infra → bindings → UseCases → Controller/
vistas → rutas → RBAC + menú → toggle en `vertical.php`). Servicios B y C son ejemplos
vivos de ese patrón y pueden citarse como referencia.

---

## 7. Documentación (README del esqueleto)

Sección **"Crear un proyecto a la medida"** con:
- **Auth VCS** primero: configurar deploy key SSH o token de Composer para alcanzar
  los repos privados (`lebytek/framework` y `lebytek/skeleton`). Es el paso que más
  falla; va al principio y `doctor` lo valida.
- Comando: `composer create-project lebytek/skeleton <proyecto>` (+ variante
  no-interactiva con flags).
- Qué hace el **post-script** (resumen de §5) y qué deja listo.
- **Día 1** (arrancar): DB, `seed.php`, servidor de dev, `composer doctor`.
- **Día 2** (construir): enlace a la guía de dominio (§6).
- Nota de **VPS**: el deploy del proyecto incluye `composer install` con la misma auth
  VCS (consistente con separación §8.1).

---

## 8. Estrategia de pruebas (`php tests/run.php`, sin red)

La prueba verifica el **resultado de provisionar**, no el canal de envío. Se ejecuta
sobre una **copia del esqueleto en un directorio temporal**, corriendo el post-script
en modo no-interactivo (sin requerir red ni el paquete real; el `require` del framework
se simula/omite en el fixture si hace falta):

1. **Post-create personaliza:** tras correr el post-script con `--name=Demo`:
   - existe `.env` y `APP_KEY` **no vacío**;
   - `config/vertical.php` tiene **todos los demos OFF**;
   - `composer.json` declara `require lebytek/framework` y `repositories` VCS;
   - `APP_NAME` = `Demo` en `.env`.
2. **Idempotencia/seguridad:** re-ejecutar el post-script **no** pisa el `.env`
   existente ni regenera `APP_KEY`; no rompe.
3. **`doctor` reporta y corta:** con un entorno donde falta un chequeo crítico
   (p. ej. extensión simulada ausente), `doctor` devuelve exit code != 0 y lista la
   falla; con entorno sano, exit 0.
4. **Arranque de la instancia (criterio heredado, separación §criterio 3):** una
   instancia con `composer install` + framework resuelto **arranca y pasa el arnés**
   (`php tests/run.php` verde). Este caso puede correrse en CI con auth VCS real
   (smoke), separado del arnés unitario sin red.
5. **Desacople:** ningún archivo del paquete `lebytek/framework` referencia el tooling
   de D; el tooling vive solo en el esqueleto.

---

## 9. Entregables y secuencia

> Precondición global: separación v1.0 terminada y verde, esqueleto arrancable,
> `lebytek/framework` publicado por VCS privado (§0).

1. **`composer.json` del esqueleto** — `type: project`, `repositories` VCS,
   `require lebytek/framework`, scripts `post-create-project-cmd` + `doctor`.
2. **`scripts/doctor.php`** — chequeos de §4, exit codes, salida accionable.
3. **`scripts/post_create_project.php`** — personalización de §5 (interactivo +
   flags), idempotente y seguro.
4. **`.env.example`** — placeholders + nota de auth VCS; sin secretos.
5. **README** — sección "Crear un proyecto a la medida" (§7) + enlace a la guía de
   dominio (§6).
6. **Pruebas/CI** — arnés con los grupos de casos de §8 (unitarios sin red + smoke de
   provisión con auth real en CI).

### Fuera de alcance (próximas iteraciones)
Generadores de código de dominio, plantillas de proyecto por tipo de cliente,
multi-tenancy, CI/CD por proyecto, vínculo automático lead→proyecto. Cada uno: su
propio spec → plan.

---

## 10. Decisiones registradas
- **D = tooling del esqueleto**, no un vertical `dom_*`: cero tablas/rutas de runtime;
  vive en `lebytek/skeleton`.
- **Provisión vía `composer create-project lebytek/skeleton` + post-script**
  (estándar de Composer; reusa la infra de paquete privado de la separación).
- **Comando `doctor`** valida prerrequisitos (incl. **auth VCS privado**) antes de
  provisionar y tras instalar; no modifica nada; exit code apto para CI.
- **Post-script idempotente y seguro**: genera `.env` + `APP_KEY` único, fuerza demos
  OFF, reinicia git, imprime próximos pasos; nunca pisa un `.env` existente.
- **Embudo de leads `a_la_medida` fuera de alcance** (es del Servicio A); D arranca
  tras cerrar el proyecto.
- **Construcción a medida reusa** `docs/modules/uso-de-modulo-dominio.md` (no se
  reimplementa la guía de dominio).
- **Repos privados** (framework + esqueleto): se mantienen vía VCS, no Packagist.
- **Spec dependiente de la separación**, con prerrequisito explícito (§0).
