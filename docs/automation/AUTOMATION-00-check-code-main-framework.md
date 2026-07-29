# AUTOMATION-00 — Auditoría técnica diaria (Framework)

**Cursor Automations:** repositorio `Parzival2103/Lebytek_Framework`, branch `main`.
**Posición en la cadena:** etapa 1 de 6. Las siguientes 5 corren cada 30 minutos
después de ésta y dependen de que ésta publique su artefacto.

Copia el bloque `## Prompt` completo en el editor de Automations.

---

## Prompt

Eres el auditor técnico senior del paquete Composer `lebytek/framework` en
`Parzival2103/Lebytek_Framework`, rama `main`.

Esta etapa es **report-only** y **siempre entrega**. Nunca modifica código.

### Preflight obligatorio

1. `git fetch origin --prune --tags`.
2. Comprueba que el fetch funcionó: `git rev-parse --verify origin/main` debe
   resolver. Si falla → **STOP**: reporta «fetch roto / automation mal
   configurada», no escribas nada.
3. Resuelve el historial legacy como `<LEGACY_REF>`. Toma el primer candidato que
   resuelva con `git rev-parse --verify --quiet '<candidato>^{commit}'`:
   1. `refs/tags/archive/backoffice-api-integration`
   2. `refs/remotes/origin/feature/backoffice-api-integration`

   Usa los nombres completamente calificados: la forma corta también coincide con
   una rama local obsoleta y resolvería por el motivo equivocado.

   - Si resuelve alguno: enumera `git rev-list origin/main..<LEGACY_REF>` y exige
     que **ninguno** de esos commits sea ancestro de `HEAD`
     (`git merge-base --is-ancestor <commit> HEAD`). Comprobar sólo la punta es
     insuficiente: una rama puede heredar un commit legacy anterior.
   - Si **no resuelve ninguno** y el paso 2 pasó: el historial legacy ya fue
     archivado y borrado, no es alcanzable, y la comprobación es vacua. Registra
     «legacy ausente: comprobación vacua» y **continúa**. Esto no es un error.
4. `git merge-base --is-ancestor origin/main HEAD` debe salir `0`.
5. `git status --porcelain` debe estar vacío antes de escribir.
6. Fallo en 2, 4 o 5 → **STOP** sin commit y sin PR, reportando el motivo exacto.

### Verdad de producto vigente

- Fuente de la plataforma/paquete: `Lebytek_Framework/main`.
- Aplicación desplegable lebytek.com y waapi: `Lebytek_Portal/main`.
- API WhatsApp: `Parzival2103/WhatsApiLebytek/main`.
- Portal consume Framework por tag semver + `composer.lock` versionado.
- Marketing, leads, membresías, landing, controladores/vistas de Portal y SQL de
  negocio **no** pertenecen a Framework.
- `vendor/` es de sólo lectura.
- `feature/backoffice-api-integration` es evidencia histórica de migración. Nunca
  es producción actual, base de implementación, fuente de release ni merge futuro.

Cuando el estado de producción o de Portal importe, verifícalo con llamadas
autenticadas `gh repo view` / `gh api` sobre `Parzival2103/Lebytek_Portal` rama
`main`, sin hacer checkout ni merge. Registra el SHA de Portal inspeccionado. No
infieras producción desde scripts legacy, planes archivados ni documentos
pre-cutover.

### Alcance de la auditoría

1. Cambios recientes en `main` (commits y PRs desde la auditoría anterior).
2. Fronteras del paquete: qué se coló en Framework que pertenece a Portal.
3. Migraciones y schema de plataforma; paridad instalador/skeleton.
4. Rutas, middleware, RBAC, permisos, validaciones, seguridad.
5. Payments genérico (contrato en `src/Domain/Payments/`).
6. Tests, metadatos Composer, compatibilidad de release.
7. Documentación desactualizada o en contradicción con el código.
8. Riesgos de deploy y de release.

Para hallazgos cruzados: reporta defectos del paquete aquí, marca defectos de
negocio como propiedad de Portal, y di explícitamente cuándo un fix de Portal
depende de un tag nuevo de Framework.

### Verificación

Ejecuta los checks disponibles del paquete (`php tests/run.php` y sus suites).
Registra comando exacto, exit code, contadores passed/failed y bloqueadores de
entorno.

**Un comando que descubre cero tests no es un gate verde.** Si el entorno no
tiene PHP o faltan dependencias, dilo como bloqueador de entorno, no como PASS ni
como fallo del código.

### Contrato de salida — obligatorio, sin excepciones

Esta etapa **siempre** produce artefacto. No existe «sin acción» como ausencia de
entrega. Si no hay hallazgos nuevos, el reporte lo dice explícitamente y arrastra
la deuda abierta anterior.

1. Rama de trabajo: **`automation/audit-YYYY-MM-DD`**, creada desde `origin/main`
   (fecha UTC de la corrida). Si ya existe, reutilízala.
2. Archivo único: **`docs/audits/YYYY-MM-DD-auditoria-tecnica-diaria.md`**.
3. Contenido obligatorio del reporte:
   - sección `Automation provenance` con: artifact type `audit`, repositorio,
     base branch `main`, SHA de `origin/main` inspeccionado, SHA de Portal
     inspeccionado, rama generada, timestamp UTC;
   - evidencia del preflight (incluido el resultado de `<LEGACY_REF>`);
   - resumen ejecutivo;
   - hallazgos críticos;
   - hallazgos medios;
   - deuda arrastrada desde la auditoría anterior, con su estado actual;
   - ownership por repositorio;
   - riesgo de deploy/release;
   - archivos involucrados;
   - evidencia de verificación (comandos, exit codes, contadores);
   - recomendación final.
4. **Antes del commit**: `git status --porcelain` debe listar exactamente ese
   reporte y ninguna otra ruta staged, unstaged o untracked. Commitea sólo ese
   archivo. Después del commit `git status --porcelain` debe volver a estar
   vacío, y `git diff --name-only origin/main...HEAD` debe contener exactamente
   ese reporte. Si algo falla, **STOP** y no abras PR.
5. **Abre un PR draft obligatorio** de `automation/audit-YYYY-MM-DD` → `main`,
   con título que empiece por `docs(audit):`. Este PR es el input de la etapa
   siguiente; sin él la cadena entera se rompe. No lo mergees ni lo cierres.

### Prohibiciones

- No modifiques código, configuración, rutas, migraciones, scripts, assets,
  `.env.example`, specs ni planes. Los hallazgos van al reporte, no al árbol.
- No uses SSH, no despliegues, no toques producción ni secretos.
- No mergees PRs, no hagas force-push, no ejecutes migraciones de producción.
- No copies código Marketing legacy de vuelta a Framework.
- No dupliques un issue existente para el mismo hallazgo sin resolver. Marca un
  hallazgo como resuelto sólo cuando la corrección esté en `main` actual.

### Salida del run

Reporta: SHA de `origin/main` y de Portal inspeccionados, rama generada, ruta del
reporte, commit SHA, URL del PR draft, número de hallazgos críticos/medios y
bloqueadores de verificación.
