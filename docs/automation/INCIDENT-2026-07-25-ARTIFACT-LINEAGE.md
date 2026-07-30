# Incidente 2026-07-25 — contaminación de lineage en automations

## Decisión

No ejecutar los planes generados el 2026-07-24 ni el 2026-07-25. Los PRs
#27, #28, #29 y #30 quedan superseded y deben cerrarse sin merge. El pipeline
de auditoría → spec → plan se reinicia después de publicar y copiar los prompts
canónicos corregidos en Cursor Automations.

## Evidencia

- `origin/automation/audit-spec-2026-07-25` no desciende de Framework `main`.
- Su historial contiene `feature/backoffice-api-integration` y su diff contra
  `main` abarca 241 archivos.
- PR #30, aunque apunta a `main`, hereda aproximadamente 240 archivos de
  Marketing y aplicación legacy; no es un PR docs-only.
- El plan detectó que su spec era feature-derived, pero continuó generando el
  artefacto. Detectar el error sin detenerse no satisface el preflight.
- PR #29 sí parte de `main`, pero mezcla el reporte con cambios de
  `.env.example` y conserva premisas operativas obsoletas. No debe reutilizarse
  como fuente del nuevo pipeline.

## Estado operativo canónico

- Framework/package: `Parzival2103/Lebytek_Framework`, rama `main`.
- Aplicación desplegable: `Parzival2103/Lebytek_Portal`, rama `main`.
- API WhatsApp: `Parzival2103/WhatsApiLebytek`, rama `main`.
- `feature/backoffice-api-integration` es historia de migración, no base de
  auditorías, specs, planes, implementación ni deploy actual.
- Portal consume `lebytek/framework` mediante Composer y el `composer.lock`
  versionado. Las rutas y copias del instalador de Portal son propiedad de
  Portal y no cambian por modificar el harness o `skeleton/`.

## Problemas técnicos encontrados en el plan descartado

Estos hallazgos se conservan como entrada para una auditoría futura, no como
autorización para implementar el plan:

1. Eliminar `LEBYTEK_API_*` del ejemplo Framework sin resolver consumidores
   existentes dejaría configuración activa sin documentar.
2. La vista CSRF propuesta usaría `e()` antes de cargar `steps.php`, causando
   un error fatal en el flujo 419.
3. `confirm_prod` solo se validaba en HTML y no en servidor.
4. Reinsertar `INSTALL_TOKEN` en URLs aumenta exposición en historial, logs y
   cabeceras Referer. Tras validarlo debe usarse sesión y una URL limpia.
5. El instalador consulta `install.lock` antes de autenticar y puede mostrar su
   resumen públicamente.
6. `/public/install/` es incorrecto cuando el document root es `public/`; la
   URL pública es `/install/`.
7. `w-sm-auto` no es una utilidad estándar de Bootstrap 5.3, los enlaces
   `/docs/core/*.md` no son rutas públicas y los assets Bootstrap asumidos por
   el plan no están garantizados.
8. Añadir rutas al harness y a `skeleton/` no actualiza las rutas de un Portal
   existente. Cualquier efecto en producción requiere ownership Portal.
9. Las pruebas de strings propuestas no validaban render, despacho HTTP,
   autenticación ni controles server-side.

## Causa raíz

Los archivos canónicos del repositorio y las instrucciones pegadas en Cursor
Automations no estaban sincronizados. La etapa spec heredó una rama legacy y la
etapa plan leyó esa spec contaminada. Los prompts anteriores permitían
continuar después de reconocer el problema y mezclaban correcciones triviales
con el artefacto de auditoría.

## Corrección del flujo

Cada etapa es artifact-only y se ejecuta desde una automation configurada para
`Parzival2103/Lebytek_Framework`, branch `main`:

1. **Audit:** un único reporte bajo `docs/audits/`; sin cambios de código,
   configuración, env examples, scripts, assets, specs ni planes.
2. **Spec:** un único archivo bajo `docs/superpowers/specs/`; lee el audit por
   GitHub API/diff y nunca hereda su branch.
3. **Plan:** un único archivo bajo `docs/superpowers/plans/`; lee la spec por
   GitHub API/diff y nunca hereda su branch.

En las tres etapas:

- `origin/main` debe ser ancestro de `HEAD`.
- Ningún commit exclusivo de `feature/backoffice-api-integration`
  (`origin/main..origin/feature/backoffice-api-integration`) debe ser ancestro
  de `HEAD` ni de una fuente. Esto bloquea ramas que heredaron cualquier tramo
  del historial legacy aunque su diff final parezca limpio.
- La fuente debe apuntar a `main`, descender de `origin/main`, no estar en
  conflicto (`MERGEABLE`, no `UNKNOWN`) y tener un diff permitido para su
  etapa.
- Cualquier preflight fallido termina la corrida sin crear un artefacto.
- Reconocer una anomalía y continuar está prohibido.
- Un gate con cero tests ejecutados no es verde.
- El working tree debe estar limpio al iniciar; antes del commit debe contener
  solo el artefacto esperado y después del commit debe volver a estar limpio.

## Acciones manuales requeridas

Modificar estos archivos no actualiza automations ya creadas:

1. Publicar los prompts canónicos de `docs/automation/` en Framework `main`.
2. Abrir Cursor Agents Window → Automations.
3. En cada una de las tres automations, seleccionar repositorio
   `Parzival2103/Lebytek_Framework` y branch `main`.
4. Reemplazar íntegramente el prompt pegado por el bloque `## Prompt` del
   archivo canónico correspondiente.
5. Deshabilitar las automations anteriores o recrearlas. No reutilizar branches
   ni memoria de ejecución que instruya partir de la feature legacy.

## Gate para la siguiente corrida

El flujo solo se considera corregido cuando:

- el audit PR apunta a `main`, desciende de `main` y contiene un reporte;
- el spec PR apunta a `main`, desciende de `main` y contiene una spec;
- el plan PR apunta a `main`, desciende de `main` y contiene un plan;
- ninguna de las tres ramas incluye código/configuración legacy;
- los artefactos usan Portal `main` como autoridad de aplicación/deploy y
  Framework `main` como autoridad del package.

## Addendum M7 — audit cerrado sin merge (2026-07-29 / 2026-07-30)

### Evidencia

- PR #48 `docs(audit): auditoría técnica diaria 2026-07-29` — `state=CLOSED`, `mergedAt=null`, `closedAt=2026-07-29T23:41:33Z`.
- Comentario owner: «Cerrado: continúa en #50» — viola Enfoque B (cross-PR sin merge).
- `docs/audits/2026-07-29-auditoria-tecnica-diaria.md` **ausente** en `main`.
- Último reporte mergeado: `docs/audits/2026-07-27-auditoria-tecnica-diaria.md` (PR #37).

### Procedimiento de recuperación

1. **Reporte huérfano (opcional):** PR docs-only desde `origin/automation/audit-2026-07-29` cherry-pick del archivo audit → `main`.
2. **Día en curso:** mergear PR audit abierto (#51 para 2026-07-30) con `gh pr merge <n> --squash` **antes** de cerrar.
3. **Prevención:** prompts F2–F3 + tests `AuditArtifactFreshnessTest` / `AutomationPromptInvariantTest`.
4. **Post-merge:** sincronizar prompts en Cursor UI (O2); verificar `php tests/run.php Docs` verde.

### Causa raíz M7

AUTOMATION-03 instruía «Cierra el PR draft» sin exigir merge — regresión de proceso, no de código producto.
