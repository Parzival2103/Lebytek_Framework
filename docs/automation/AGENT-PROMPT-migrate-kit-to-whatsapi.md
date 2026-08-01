# AGENT PROMPT — Migrar automation-kit → WhatsApiLebytek

**Uso:** abre este archivo en **Cursor → Agents** desde un **Workspace multi-repo**
que tenga checkout de:

| Repo | Path típico en el workspace |
|------|-----------------------------|
| `Parzival2103/Lebytek_Framework` | p. ej. `Lebytek_Framework/` |
| `Parzival2103/WhatsApiLebytek` | p. ej. `WhatsApiLebytek/` |

Copia el bloque `## Prompt` completo como instrucción del Agent (o @-menciona
este archivo). El Agent debe tener **Git write + `gh`** en ambos repos.

---

## Prompt

Eres un agente de migración multi-repo. Tu trabajo es **único y completo**:

1. Llevar el portable automation kit desde `Lebytek_Framework` a
   `WhatsApiLebytek` (API / `api.lebytek.com`).
2. Abrir PR en el API, pasar tests, y **mergear** a `main` del API.
3. **Borrar** el kit (y el rastro) de Framework: archivos, test, pointer del
   README, cerrar/borrar el PR de Framework y la rama remota.

No dejes el kit en ambos sitios. El destino canónico es **solo** WhatsApiLebytek.

### Contexto de origen (Framework)

En `Lebytek_Framework` busca (prioridad):

1. Rama `cursor/generic-automation-kit-1ef5` / PR
   https://github.com/Parzival2103/Lebytek_Framework/pull/65
2. Si ya está mergeado en `main`, usa `main`.
3. Contenido a migrar:

```
docs/automation-kit/README.md
docs/automation-kit/REPO-PROFILE.example.md
docs/automation-kit/INSTALL-WhatsApiLebytek.md
docs/automation-kit/profiles/WhatsApiLebytek.md
docs/automation-kit/AUTOMATION-00-daily-audit.md
docs/automation-kit/AUTOMATION-01-daily-spec.md
docs/automation-kit/AUTOMATION-02-audit-tech-debt.md
docs/automation-kit/AUTOMATION-03-audit-ux.md
docs/automation-kit/AUTOMATION-04-plan-writer.md
docs/automation-kit/AUTOMATION-05-wha-notify.md
docs/automation-kit/AUTOMATION-06-plan-readiness-gate.md
docs/automation-kit/AUTOMATION-07-plan-executor.md
docs/automation-kit/AUTOMATION-08-plan-closure.md
tests/Docs/AutomationKitPortableTest.php   # microtest Framework — adaptar a Pest en el API
```

También hay un pointer en `docs/automation/README.md` (bloque «Portable kit»)
que **debe eliminarse** en la limpieza de Framework.

Este propio archivo (`docs/automation/AGENT-PROMPT-migrate-kit-to-whatsapi.md`)
se borra al final de la limpieza de Framework, después del merge exitoso en el API.

### Fase A — Instalar en WhatsApiLebytek

Trabaja en el checkout del API. Base: `origin/main`.

1. `git fetch origin --prune` y crea rama:
   `cursor/automation-kit-portable-1ef5` (o el prefijo de rama que use el repo;
   si exige otro patrón, respétalo y anótalo).
2. Layout destino (fusiona con lo existente; **no** destruyas
   `docs/automation/CONTEXT.md`, `AGENTS.md`, ni reportes históricos):

```bash
# Desde la raíz del API
mkdir -p docs/automation \
         docs/audits \
         docs/superpowers/specs \
         docs/superpowers/plans \
         docs/automation-reports \
         docs/archive/superpowers/plans \
         docs/automation/profiles

# Copiar prompts + docs del kit Framework → docs/automation/
# (lee los ficheros del checkout Framework y escríbelos aquí)
cp <FW>/docs/automation-kit/AUTOMATION-0*.md docs/automation/
cp <FW>/docs/automation-kit/README.md docs/automation/KIT-README.md
cp <FW>/docs/automation-kit/REPO-PROFILE.example.md docs/automation/
cp <FW>/docs/automation-kit/INSTALL-WhatsApiLebytek.md docs/automation/
cp <FW>/docs/automation-kit/profiles/WhatsApiLebytek.md docs/automation/profiles/
cp <FW>/docs/automation-kit/profiles/WhatsApiLebytek.md docs/automation/REPO-PROFILE.md
```

3. **README del API** (`docs/automation/README.md`): reescribe el roadmap para
   las **nueve** etapas 00–08 del kit (ya no el roadmap viejo de 5 etapas ni
   links rotos a prompts Framework renombrados). Enlaza `REPO-PROFILE.md`,
   `KIT-README.md` y `CONTEXT.md`. Mantén el principio human-in-the-loop.
4. **Obsoleto:** si existe `docs/automation/AUTOMATION-01-daily-audit.md` (audit
   SaaS viejo), renómbralo a
   `docs/automation/archive/AUTOMATION-01-daily-audit.LEGACY.md` (crea
   `archive/`) o bórralo con nota en el PR — no dejes dos cadenas concurrentes.
5. **Tests (Pest, no microtest Framework):** crea
   `tests/Feature/Docs/AutomationKitPortableTest.php` (o la convención Pest del
   repo) que verifique:
   - existen los 9 `docs/automation/AUTOMATION-0*.md` con bloque `## Prompt` y
     mención a `REPO-PROFILE`;
   - existe `docs/automation/REPO-PROFILE.md` con
     `Parzival2103/WhatsApiLebytek` y `composer test`;
   - existe `docs/automation/KIT-README.md`;
   - **ningún** prompt hardcodea la identidad
     «paquete Composer `lebytek/framework`» como auditor senior.
   No copies el archivo microtest de Framework tal cual; reescríbelo en Pest
   (`composer test` / `./vendor/bin/pest`).
6. Ajusta paths en `INSTALL-WhatsApiLebytek.md` y `KIT-README.md` para decir
   que el kit **vive en este repo** (`WhatsApiLebytek/docs/automation/`), no
   en Framework. Quita lenguaje del tipo «copia desde Framework» como flujo
   principal; deja una línea histórica si quieres.
7. Commit atómico en la rama del API, p. ej.:
   `docs(automation): install portable 9-stage Cursor automation kit`

### Fase B — PR + tests + merge en el API

1. Push: `git push -u origin <rama-api>`.
2. Abre PR → `main` con título tipo
   `docs(automation): portable 9-stage Cursor automation kit`.
   Body: origen (Framework PR #65 / kit), archivos, que reemplaza el daily-audit
   legacy, secrets WhatsApp 05/08, permisos gh merge 03/08.
3. Corre `composer test` (o al menos el archivo Pest nuevo). **Cero tests
   descubiertos ≠ verde.** Arregla fallos antes de merge.
4. Espera CI green si el repo lo exige.
5. Merge: `gh pr merge <n> --squash` (o la política del repo). Verifica
   `mergedAt`.
6. Borra la rama remota de feature del API si procede.

**STOP si el merge del API falla.** No toques Framework hasta que el kit esté
en `WhatsApiLebytek/main`.

### Fase C — Limpiar Framework (solo tras merge API OK)

En el checkout de `Lebytek_Framework`, desde `main` actualizado (o rama de
limpieza `cursor/remove-automation-kit-1ef5` → PR → merge):

1. Confirma con `gh api` / `git show` que el kit está en
   `WhatsApiLebytek@main` (al menos `docs/automation/REPO-PROFILE.md` y los 9
   prompts).
2. Elimina por completo:
   - `docs/automation-kit/` (todo el directorio)
   - `tests/Docs/AutomationKitPortableTest.php`
   - el bloque «Portable kit» añadido en `docs/automation/README.md`
   - este archivo `docs/automation/AGENT-PROMPT-migrate-kit-to-whatsapi.md`
3. Si PR Framework #65 sigue abierto: **ciérralo sin merge**
   (`gh pr close 65 --comment "Migrated to WhatsApiLebytek; kit removed from Framework."`)
   y borra la rama remota `cursor/generic-automation-kit-1ef5`.
4. Si #65 ya se mergeó a Framework `main`: abre PR de limpieza que borre los
   paths de arriba, pásalo, mergea, y documenta en el body el SHA/PR del API.
5. Verifica en Framework: `git grep -n automation-kit` no debe dejar referencias
   vivas (salvo menciones históricas irrelevantes en logs de PR).
6. Corre tests Docs relevantes en Framework si el harness está disponible
   (`php tests/run.php Docs/AutomationPromptInvariant` etc.) — no deben
   depender del kit borrado.

### Prohibiciones

- No dejes el kit canónico en Framework «por si acaso».
- No mergees `feature/backoffice-api-integration` en ningún repo.
- No despliegues VPS, no edites `.env` prod, no SSH.
- No inventes secretos WhatsApp.
- No borres `docs/automation/` de Framework (prompts 00–08 de producción del
  paquete); solo el **kit portable** y este prompt de migración.
- No edites `vendor/` en ningún sitio.

### Criterios de aceptación

- [ ] `WhatsApiLebytek/main` contiene los 9 prompts + `REPO-PROFILE.md` + tests Pest verdes mergeados.
- [ ] PR del API mergeado (`mergedAt` verificado).
- [ ] Framework ya no tiene `docs/automation-kit/` ni el test portable ni el pointer del README.
- [ ] PR Framework #65 cerrado sin merge **o** revertido/limpiado si ya estaba mergeado.
- [ ] Rama remota Framework `cursor/generic-automation-kit-1ef5` eliminada (si existía).
- [ ] Este prompt de migración eliminado de Framework tras el éxito.

### Salida del run

Reporta: SHAs/PR URLs del API (merge), evidencia de tests, acciones de limpieza
en Framework (PR close / cleanup PR merge), y confirmación de que el kit vive
solo en WhatsApiLebytek.
