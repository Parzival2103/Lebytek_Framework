# Despliegue, versionado y madurez — Plan de implementación (documental)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publicar la guía operativa de despliegue/versionado del spec aprobado como un documento durable en `docs/core/`, hacerla descubrible desde el índice y los docs relacionados, y marcar la narrativa operativa del spec archivado 2026-06-08 como superada.

**Architecture:** Trabajo 100% documental. El spec `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md` (ya commiteado, SHA `351c82a`) es la **fuente de contenido** y queda como registro de diseño. El plan deriva de él la **guía operativa viva** `docs/core/despliegue-y-versionado.md` (siguiendo la convención del repo de separar "Especificación" de "Guía operativa", ver `docs/README.md`), añade banners de referencia cruzada en los docs relacionados, indexa la guía en `docs/README.md` y pone un banner de "superado" en el spec archivado. **No se toca código ni configuración**; los hallazgos D1–D4 del spec quedan como deuda, sin resolver.

**Tech Stack:** Markdown. Verificación con `Grep` (enlaces presentes) y el test de integridad existente `php tests/run.php Install` (debe seguir verde: prueba de que no se tocó código/manifiestos).

**Convención de commits:** cada tarea cierra con un commit propio. Mensajes en español, prefijo `docs(deploy):`. Terminar cada mensaje con la línea `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

**Fuente de contenido (referencia constante):** todas las secciones que se copian "verbatim" provienen del spec aprobado `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md`. El engineer debe abrir ese archivo y copiar el contenido exacto de las secciones indicadas; el contenido ya fue revisado y aprobado, por lo que se copia sin reescribir.

---

## Estructura de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `docs/core/despliegue-y-versionado.md` | **Crear** | Guía operativa viva: modelo de 4 tiers, inventario maestro, 6 playbooks, versionado, checklists, deuda. Punto de entrada del operador. |
| `docs/core/instalacion-y-versionado.md` | Modificar | Añadir banner que apunta a la guía nueva como puerta de entrada operativa. |
| `docs/core/despliegue_hosting.md` | Modificar | Añadir banner que apunta a la guía nueva. |
| `docs/core/seguridad_secretos_deploy.md` | Modificar | Añadir banner que apunta a la guía nueva. |
| `docs/core/vertical-onboarding.md` | Modificar | Añadir banner que apunta a la guía nueva. |
| `docs/README.md` | Modificar | Indexar la guía nueva (tabla `docs/core/` + bullet "Cómo leer"). |
| `docs/archive/superpowers/specs/2026-06-08-instalacion-estandarizacion-versionado-design.md` | Modificar | Banner: narrativa operativa superada por la guía nueva (modelo de datos/motor siguen vigentes). |
| `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md` | Modificar | Banner: la versión publicada/viva vive en `docs/core/despliegue-y-versionado.md`. |

---

## Task 1: Crear la guía operativa viva en `docs/core/`

**Files:**
- Create: `docs/core/despliegue-y-versionado.md`
- Source (read-only): `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md`

La guía se ensambla en este orden exacto. Las secciones marcadas **[verbatim §X]** se copian palabra por palabra desde la sección §X del spec fuente. Las marcadas **[nuevo]** usan el texto provisto aquí.

- [ ] **Step 1: Crear el archivo con encabezado e introducción [nuevo]**

Crea `docs/core/despliegue-y-versionado.md` empezando con exactamente este contenido:

```markdown
# Despliegue y versionado — Guía operativa

> **Puerta de entrada única de despliegue.** Responde, sin leer código: qué editas para levantar un cliente nuevo, qué corres tras un `git pull`, dónde pones logo/nombre/módulos, y qué no debes tocar porque es core. El **diseño y la justificación** de esta guía viven en el spec [`../superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md`](../superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md); este documento es la versión viva que mantenemos.

- **Audiencia:** desarrollador que despliega (principal); cliente técnico en hosting compartido (secundaria).
- **Motor de instalación (cómo funciona por dentro):** [`instalacion-y-versionado.md`](instalacion-y-versionado.md).
- **Regla:** este documento **no** redefine secretos, hosting Apache ni onboarding de dominio; delega en sus docs dueños (ver "Relación con otros documentos" al final).
```

- [ ] **Step 2: Añadir el modelo de 4 tiers [verbatim §4]**

Copia íntegra la sección **§4 "Modelo conceptual: 4 tiers"** del spec fuente (incluyendo la tabla de tiers, §4.1 Regla de oro, §4.2 la excepción de `config/vertical.php`, y §4.3 T2≠T4). Pégala bajo el encabezado anterior, renombrando el título de `## 4. Modelo conceptual: 4 tiers` a `## Modelo conceptual: 4 tiers` (quita el número, esta guía no usa numeración de spec).

- [ ] **Step 3: Añadir flujos de despliegue [verbatim §5]**

Copia la sección **§5 "Flujos de despliegue"** completa (incluido el diagrama ASCII dentro de su bloque ```). Título: `## Flujos de despliegue`.

- [ ] **Step 4: Añadir inventario maestro + playbooks [verbatim §6]**

Copia la sección **§6 completa** ("Inventario maestro + playbooks"), incluyendo §6.1 (tabla maestra + leyenda + notas al pie ¹ ²) y §6.2 (los 6 playbooks completos). Título: `## Inventario maestro + playbooks`. Mantén los sub-encabezados de cada playbook tal cual.

- [ ] **Step 5: Añadir versionado [verbatim §7]**

Copia la sección **§7 "Versionado y actualización"** completa (§7.1 a §7.5). Título: `## Versionado y actualización`.

- [ ] **Step 6: Añadir checklist por entorno [verbatim §9]**

Copia la sección **§9 "Checklist pre/post deploy por entorno"** completa (la tabla de 3 entornos + el párrafo de post-deploy). Título: `## Checklist pre/post deploy por entorno`.

- [ ] **Step 7: Añadir seguridad (resumen) [verbatim §8]**

Copia la sección **§8 "Seguridad y secretos"** completa. Título: `## Seguridad y secretos (resumen)`.

- [ ] **Step 8: Añadir deuda conocida [verbatim §10]**

Copia la sección **§10 "Riesgos, decisiones abiertas y deuda documental"** completa (tabla D1–D4 + decisiones abiertas). Título: `## Deuda conocida y decisiones abiertas`.

- [ ] **Step 9: Añadir "Relación con otros documentos" [verbatim §11]**

Copia la sección **§11 "Relación con docs existentes"** completa (la tabla reemplaza/complementa/obsoleta + el párrafo "Qué reemplaza"). Título: `## Relación con otros documentos`. **No** se copian las secciones §1, §2, §3 ni §12 del spec (son material de diseño, no de guía viva).

- [ ] **Step 10: Verificar estructura del archivo**

Run: `grep -n "^## " docs/core/despliegue-y-versionado.md`
Expected (8 encabezados de nivel 2, en este orden):
```
Modelo conceptual: 4 tiers
Flujos de despliegue
Inventario maestro + playbooks
Versionado y actualización
Checklist pre/post deploy por entorno
Seguridad y secretos (resumen)
Deuda conocida y decisiones abiertas
Relación con otros documentos
```

- [ ] **Step 11: Verificar que no quedaron referencias internas rotas a numeración de spec**

Run: `grep -nE "§[0-9]|sección [0-9]|\(§" docs/core/despliegue-y-versionado.md`
Expected: sin resultados (la guía viva no referencia números de sección del spec). Si aparece alguno dentro del texto copiado (p. ej. "ver §7.4" dentro de §6.2 o §10), reemplázalo por el título de la sección correspondiente (p. ej. "ver Versionado y actualización"). Repite el grep hasta que no devuelva nada.

- [ ] **Step 12: Commit**

```bash
git add docs/core/despliegue-y-versionado.md
git commit -m "docs(deploy): guia operativa viva de despliegue y versionado

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Banners de referencia cruzada en los 4 docs relacionados

Cada banner es una sola línea de blockquote insertada **justo después** del encabezado H1 (y de cualquier banner existente), con una línea en blanco antes y después.

**Files:**
- Modify: `docs/core/instalacion-y-versionado.md`
- Modify: `docs/core/despliegue_hosting.md`
- Modify: `docs/core/seguridad_secretos_deploy.md`
- Modify: `docs/core/vertical-onboarding.md`

- [ ] **Step 1: `instalacion-y-versionado.md`**

El archivo empieza con:
```
# Instalación y versionado

Cada despliegue es autodescriptivo y versionado mediante:
```
Inserta, entre el H1 y el párrafo "Cada despliegue…", esta línea (dejando líneas en blanco a ambos lados):
```
> **Guía operativa de despliegue:** para el mapa de qué tocar por tipo de despliegue (greenfield, update, branding, etc.), ver [`despliegue-y-versionado.md`](despliegue-y-versionado.md). Este documento describe el **motor** por dentro.
```

- [ ] **Step 2: `despliegue_hosting.md`**

El archivo ya tiene un banner tras el H1 (`> **Instalación y versionado:** …`). Inserta **inmediatamente después** de ese banner existente (antes del párrafo "La aplicación debe exponerse…") esta línea:
```
> **Guía operativa de despliegue:** el playbook completo por tipo de despliegue está en [`despliegue-y-versionado.md`](despliegue-y-versionado.md); aquí solo el detalle de Apache / document root.
```

- [ ] **Step 3: `seguridad_secretos_deploy.md`**

El archivo empieza con:
```
# Seguridad de secretos y checklist de despliegue (VPS)

**Regla:** En el repositorio solo vive `.env.example`. ...
```
Inserta, entre el H1 y el párrafo `**Regla:**`, esta línea:
```
> **Guía operativa de despliegue:** este documento es la fuente autoritativa de secretos y rotación; el contexto de despliegue completo está en [`despliegue-y-versionado.md`](despliegue-y-versionado.md).
```

- [ ] **Step 4: `vertical-onboarding.md`**

El archivo empieza con:
```
# Checklist: nuevo proyecto desde esta base

Guía para clonar el repo y levantar una instancia ...
```
Inserta, entre el H1 y el párrafo "Guía para clonar…", esta línea:
```
> **Guía operativa de despliegue:** para el modelo de capas (core / módulo opcional / instancia / vertical) y los playbooks por tipo de despliegue, ver [`despliegue-y-versionado.md`](despliegue-y-versionado.md).
```

- [ ] **Step 5: Verificar los 4 banners**

Run: `grep -l "despliegue-y-versionado.md" docs/core/instalacion-y-versionado.md docs/core/despliegue_hosting.md docs/core/seguridad_secretos_deploy.md docs/core/vertical-onboarding.md`
Expected: los 4 archivos listados.

- [ ] **Step 6: Commit**

```bash
git add docs/core/instalacion-y-versionado.md docs/core/despliegue_hosting.md docs/core/seguridad_secretos_deploy.md docs/core/vertical-onboarding.md
git commit -m "docs(deploy): enlaces cruzados hacia la guia operativa desde docs relacionados

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Indexar la guía en `docs/README.md`

**Files:**
- Modify: `docs/README.md`

- [ ] **Step 1: Añadir bullet en "Cómo leer esta documentación"**

En la lista numerada de la sección "## Cómo leer esta documentación", el ítem 4 actual es:
```
4. **Nueva instancia o vertical:** [`core/vertical-onboarding.md`](core/vertical-onboarding.md).
```
Inserta **antes** de ese ítem un nuevo bullet (y renumera los siguientes para mantener el orden 1..N):
```
4. **Desplegar / actualizar una instancia:** [`core/despliegue-y-versionado.md`](core/despliegue-y-versionado.md) — mapa de qué tocar por tipo de despliegue.
```
Tras insertarlo, el que era ítem 4 ("Nueva instancia o vertical") pasa a 5, y "Nuevo módulo de negocio" pasa a 6.

- [ ] **Step 2: Añadir fila en la tabla `docs/core/`**

En la tabla bajo "## `docs/core/`", la fila actual de hosting es:
```
| [`despliegue_hosting.md`](core/despliegue_hosting.md) | Exposición vía `public/` |
```
Inserta **inmediatamente antes** de esa fila una nueva:
```
| [`despliegue-y-versionado.md`](core/despliegue-y-versionado.md) | **Guía operativa**: 4 tiers, inventario de superficies, playbooks por tipo de despliegue, versionado |
```

- [ ] **Step 3: Verificar**

Run: `grep -n "despliegue-y-versionado.md" docs/README.md`
Expected: 2 líneas (el bullet y la fila de tabla).

- [ ] **Step 4: Commit**

```bash
git add docs/README.md
git commit -m "docs(deploy): indexar la guia operativa de despliegue en el README de docs

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: Banners de "superado" / "publicado" en los dos specs

**Files:**
- Modify: `docs/archive/superpowers/specs/2026-06-08-instalacion-estandarizacion-versionado-design.md`
- Modify: `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md`

- [ ] **Step 1: Banner en el spec archivado 2026-06-08**

El archivo empieza con:
```
# Instalación, estandarización y versionado — Design

> Fase siguiente del framework Lebytek. Convierte cada despliegue ...
```
Inserta, entre el H1 y el blockquote "> Fase siguiente…", esta línea (con líneas en blanco a ambos lados):
```
> ⚠️ **Narrativa operativa superada.** El **modelo de datos (`cfg_migraciones`/`cfg_modulos`) y el motor descritos aquí siguen vigentes**, pero la guía operativa de despliegue la reemplaza [`docs/core/despliegue-y-versionado.md`](../../../core/despliegue-y-versionado.md). No usar este spec como guía de despliegue.
```
Nota: verifica la profundidad de la ruta relativa. Desde `docs/archive/superpowers/specs/` hasta `docs/core/` son tres niveles arriba: `../../../core/despliegue-y-versionado.md`.

- [ ] **Step 2: Verificar la ruta relativa del banner archivado**

Run: `ls docs/archive/superpowers/specs/../../../core/despliegue-y-versionado.md`
Expected: la ruta resuelve a `docs/core/despliegue-y-versionado.md` (sin error "No such file"). Si da error, corrige el número de `../` en el banner hasta que resuelva.

- [ ] **Step 3: Banner "publicado" en el spec nuevo 2026-06-14**

En `docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md`, la lista de metadatos del encabezado termina con:
```
- **Audiencia:** Desarrollador que despliega (principal); cliente técnico en hosting compartido (secundaria).
```
Añade **justo debajo** de esa línea, como nuevo ítem de la misma lista:
```
- **Versión publicada (viva):** [`../../core/despliegue-y-versionado.md`](../../core/despliegue-y-versionado.md). Este spec es el registro de diseño; la guía que se mantiene vive en `docs/core/`.
```

- [ ] **Step 4: Verificar la ruta relativa del banner del spec nuevo**

Run: `ls docs/superpowers/specs/../../core/despliegue-y-versionado.md`
Expected: resuelve a `docs/core/despliegue-y-versionado.md` sin error. Corrige los `../` si falla.

- [ ] **Step 5: Commit**

```bash
git add "docs/archive/superpowers/specs/2026-06-08-instalacion-estandarizacion-versionado-design.md" "docs/superpowers/specs/2026-06-14-despliegue-versionado-madurez-design.md"
git commit -m "docs(deploy): marcar spec 2026-06-08 como superado y enlazar guia publicada

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Verificación global (sin regresiones de código)

Prueba de que el trabajo fue puramente documental y los enlaces son coherentes.

**Files:** ninguno (solo lectura/verificación).

- [ ] **Step 1: El test de integridad de instalación sigue verde**

Run: `php tests/run.php Install`
Expected: todo verde. Como no se tocó ningún manifiesto ni `.sql`, `EstandarizacionIntegridadTest` debe pasar igual que antes. (Si el harness completo es `php tests/run.php`, úsalo; el objetivo es confirmar que no hubo cambios de código.)

- [ ] **Step 2: Confirmar que solo cambiaron archivos `.md` bajo `docs/`**

Run: `git diff --name-only 351c82a..HEAD`
Expected: solo rutas que terminan en `.md` dentro de `docs/`. Ninguna ruta bajo `app/`, `config/`, `database/`, `scripts/` o `routes/`. Si aparece algo fuera de `docs/`, revertir ese cambio (este plan es documental).

- [ ] **Step 3: Todos los enlaces nuevos resuelven a archivos existentes**

Run: `grep -rn "despliegue-y-versionado.md" docs/`
Expected: aparecen las referencias creadas (guía + 4 banners + README ×2 + 2 specs). Verifica manualmente que cada enlace relativo apunte a un archivo que existe (`docs/core/despliegue-y-versionado.md`).

- [ ] **Step 4: Commit final (si hubo correcciones en este task)**

Si los pasos anteriores forzaron alguna corrección, commitéala:
```bash
git add -A docs/
git commit -m "docs(deploy): correcciones de verificacion final de la guia de despliegue

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```
Si no hubo correcciones, omite este commit.

---

## Notas de cierre

- **Push:** según `CLAUDE.md`, los pushes a `main` van directos (el VPS hace auto-pull). Empuja solo cuando el usuario lo pida explícitamente; este plan no incluye `git push`.
- **Fuera de alcance (recordatorio):** los hallazgos D1 (`pdf_kit` vs `pdf-kit`), D3 (re-sellado de checksum) y D4 (semántica de `vertical.modules`) **no** se resuelven aquí; quedan documentados como deuda en la guía y el spec. Resolverlos requiere su propio spec/plan porque tocan código/config.
```
