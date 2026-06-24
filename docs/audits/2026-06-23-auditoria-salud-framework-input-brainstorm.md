# Auditoría de salud del framework — Input para brainstorming (spec de corrección)

**Tipo:** auditoría consolidada + brief para diseño (no implementación).  
**Fecha:** 2026-06-23.  
**Repositorio:** Lebytek Framework (`contraste` / `lebytek.com` VPS auto-pull desde `main`).  
**Audiencia primaria:** Claude Code (y cualquier agente) usando la skill **brainstorming** → spec en `docs/superpowers/specs/` → plan en `docs/superpowers/plans/`.  
**Alcance:** núcleo de plataforma, configuración, modularidad, RBAC, CRUD Engine, módulos opcionales (incl. marketing reciente). **No** audita lógica de negocio de verticales concretas.

**Nota operativa:** Cursor y Claude Code trabajan en el mismo repositorio. Cualquier spec/plan debe evitar duplicar trabajo en curso (marketing front público, ajustes extensibles, seeds demo) y debe listar archivos “zona caliente” para coordinación.

---

## 0. Cómo usar este documento (brainstorming)

1. **Explorar contexto** — este archivo + `docs/core/arquitectura.md` + `config/vertical.php` + `docs/audits/reporte-mapa-proyecto.md`.
2. **Objetivo del spec de corrección:** framework **sano**, **sin hardcode innecesario**, **configuración ejercitada** (toggles, manifiestos, registros declarativos), **una fuente de verdad** por concepto (RBAC, menú, rutas, DI).
3. **Preguntas guía para brainstorming** (una por turno en la skill):
   - ¿Qué debe ser **configurable por deploy** (`vertical.php`) vs **por instancia** (`cfg_configuraciones`) vs **código**?
   - ¿Qué módulos son **obligatorios del core** y cuáles **plug-in** con auto-registro?
   - ¿Qué hardcodes son **aceptables** (seguridad, invariantes) vs **deuda**?
   - ¿Criterio de “framework sano” para cerrar el spec? (tests, `rbac_integrity_report`, toggles, sin URLs rotas en prod).
4. **Criterios de éxito propuestos para el spec:**
   - Toggles en `vertical.php` **gaten** menú + rutas + bindings DI de forma uniforme.
   - Manifiestos `config/modules/*.php` **alimentan** Installer / integridad (permisos, menú, rutas, providers) sin editar `web.php`/`container.php` por cada módulo nuevo.
   - `config/settings_sections.php` y `config/dashboard.php` **usados** con el mismo patrón (sin registro duplicado solo en `container.php`).
   - Cero recursos CRUD JSON apuntando a tablas inexistentes en schema activo.
   - `config/rbac_route_permissions.php` y `scripts/rbac_integrity_report.php` **completos** respecto a rutas reales.
   - Tests de “default de producto” alineados con `vertical.php` del repo framework (o fixture de test aislado).
5. **Salida esperada del brainstorming:** `docs/superpowers/specs/2026-06-XX-framework-salud-correccion-design.md` con fases, no-goals y checklist verificable.

---

## 1. Revisión de auditorías previas (`docs/audits/`)

| Documento | Fecha / corte | Tema | Veredicto | ¿Cerrado? |
|-----------|---------------|------|-----------|-----------|
| `auditoria_crud_engine_v0.1.md` | 2026-04-28 | CRUD Engine vs arquitectura | ~68–78% cumplimiento; handlers, `security.mode`, menú padre CRUD | **Parcial** — mucho corregido en `modules/crud/history/`; informe archivado |
| `auditoria_alineacion_modulos_v0.1.md` | 2026-05-02 | LEBYTEK UI, RBAC rutas, CRUD vs especializados | RBAC rutas débil en dashboard/ajustes; redirects usuarios | **Parcial** — ver corrección |
| `correccion_alineacion_modulos_v0.1.md` | 2026-05-02 | RBAC granular rutas + redirects usuarios | Implementado en `routes/web.php`, seeds, migración menú | **Mayoría cerrada** — pendientes `permisos.gestionar`, `ajustes.ver`, KPIs dashboard |
| `correccion_auth_rbac_v0.1.md` | 2026-05-02 | Validación permisos, matriz roles, integridad RBAC | Slug rules, filtro IDs rol, `rbac_integrity_report.php` | **Mayoría cerrada** — slugs legacy en BD, `permisos.gestionar` |
| `auditoria_documentacion.md` | 2026-04-30 | Coherencia `/docs` | Duplicación docs ↔ `.cursor/rules`; CRUD spec actualizada | **Mantenimiento continuo** |
| `informe-capacidades-framework-examen-dominio-ficticio.md` | 2026-06-08 | Calendario, dashboard por rol, scope CRUD, agregaciones | Calendario **antes No**; ahora módulo existe; scope **parcial** | **Parcial** — calendario implementado; scope owner no universal |
| `reporte-mapa-proyecto.md` | 2026-06-23 | Mapa técnico completo | Rutas/DI centralizados; toggles incompletos; marketing “planificado” | **Parcial** — marketing ya en código; hallazgos 1–10 siguen vigentes |

**Lectura transversal:** las correcciones de mayo 2026 mejoraron **frontera HTTP RBAC** y **auth/RBAC datos**, pero **modularidad declarativa** (rutas, DI, manifiestos, toggles) y **configuración no ejercitada** siguen siendo el eje principal de deuda.

---

## 2. Estado actual del framework (snapshot 2026-06-23)

### 2.1 Lo que está sano y bien ejercitado

| Área | Evidencia | Comentario |
|------|-----------|------------|
| Capas Onion | `app/Presentation` → `Application` → `Domain` → `Infrastructure` | Documentado y respetado en módulos nuevos (marketing, reportes, pdf-kit) |
| Auth + sesión | `AuthMiddleware`, `LoginRateLimitService`, tokens correo | Config `auth.php`, `session.php` usados en Kernel |
| RBAC rutas core admin | `routes/web.php` — `dashboard.ver`, `administracion.ver`, `usuarios.gestionar`, `roles.gestionar` | Comentario explícito sobre `permisos.gestionar` ausente |
| CRUD Engine demo | `config/cruds/demo_*.json`, handlers, permisos en migraciones | Patrón referencia operativo |
| Menú dinámico + vertical | `core_menu_items` + `VerticalProfile::filterMenuByModules` | Toggle por módulo en menú |
| Marketing desacoplado | `routes/marketing.php` condicional; bindings en `container.php` bajo toggle | Patrón **referencia** para otros módulos (aún manual en container) |
| Ajustes extensible (parcial) | `SettingsSectionProviderInterface`, `SettingsSectionRegistry`, partial `_provider_section` en `partials/admin/ajustes/` | Providers marketing en container; **bug prod** si partial no desplegado |
| Tests harness | `php tests/run.php` — suites Auth, Crud, Marketing, Pdf, Reporte | ~137+ tests; PHPUnit secundario |
| Integridad RBAC CLI | `scripts/rbac_integrity_report.php` + `RbacIntegrityReportService` | Existe pero lista de rutas **incompleta** |

### 2.2 Toggles `config/vertical.php` (estado repo local)

```php
'dashboard' => true, 'administracion' => true, 'calendario' => true,
'pdf_kit' => true, 'reportes' => true, 'marketing' => true
```

**Conflicto de producto vs tests:** `tests/Marketing/ManifestTest.php` exige `marketing => false` por defecto; el repo tiene `true`. El spec debe definir **default del framework base** vs **default del clone Contraste/VPS**.

### 2.3 Módulos con manifiesto (`config/modules/`)

| Manifiesto | `bootstrap_sql` | `permisos` en manifiesto | `menu` en manifiesto | Rutas gated por toggle |
|------------|-----------------|--------------------------|----------------------|------------------------|
| `core.php` | — | — | — | N/A |
| `dashboard.php` | — | — | — | No |
| `crud-engine.php` | sí | — | — | No (demo siempre registrado) |
| `calendario.php` | sí | **vacío** | **vacío** | **No** (`/admin/calendario/*` siempre en `web.php`) |
| `pdf-kit.php` | sí | **vacío** | **vacío** | **Parcial** (solo controller; rutas siempre) |
| `reportes.php` | sí | **vacío** | **vacío** | **Parcial** (controller; rutas siempre) |
| `marketing.php` | sí | **poblado** | **vacío** | **Sí** (`routes/marketing.php`) |

**Installer (`ModuleRegistry`, `scripts/install.php`)** lee manifiestos pero **no** auto-registra rutas ni container bindings ni menú desde campos `menu`/`providers` vacíos.

---

## 3. Inventario de hardcoding (deuda vs aceptable)

### 3.1 Hardcoding aceptable (no mover sin motivo)

| Ubicación | Qué está fijo | Por qué |
|-----------|---------------|---------|
| Tablas `auth_*`, `cfg_*`, `core_*`, `log_*` fuera del CRUD Engine | Política de seguridad del motor | Documentado en `modulo-crud-engine.md` |
| `AjustesController::validatedLebytekUiSettings()` | Enumeraciones UI (`fluid`/`boxed`, densidades, radius) | Validación de invariantes de tema |
| `UsuariosController::USUARIOS_BASE` | Path `/admin/administracion/usuarios` | Constante de ruta canónica (mejor que string disperso) |
| Seeds SQL permisos/menú base | Datos iniciales de plataforma | Normal en framework; migraciones idempotentes para drift |

### 3.2 Hardcoding / centralización problemática (candidatos de spec)

| # | Hallazgo | Evidencia | Impacto |
|---|----------|-----------|---------|
| H1 | **Lista fija de campos core en Ajustes** | `AjustesController::guardar()` — `empresa_nombre`, `menu_layout`, `primary_color`, … | Nuevos campos de plataforma requieren editar controller; providers solo cubren módulos |
| H2 | **Bindings DI de módulos en `container.php`** | Bloques `if (vertical.modules.marketing)` ~líneas 533–587; reportes/pdf similares | Cada módulo toca archivo global compartido (conflictos multi-agente) |
| H3 | **Rutas opcionales en `routes/web.php`** | Calendario, pdf-kit, reportes sin `vertical.modules.*` | Deploy “sin calendario” sigue exponiendo URLs |
| H4 | **Dashboard providers solo en `config/dashboard.php`** | Marketing no aporta dashboard; calendario sí vía clase fija | Manifiesto `providers` ignorado |
| H5 | **Settings providers marketing solo en container** | `config/settings_sections.php` → `providers: []` | Patrón declarativo **no ejercitado**; duplicación con container |
| H6 | **CRUD `clientes.json` huérfano** | Tabla `dom_clientes` no en `database/schema/` activo | Recurso roto si se activa en menú |
| H7 | **URLs y copy en vistas públicas marketing** | Nav layout: “Paquetes”, “Demo”, `/login` fijos en `publico/layout.php` | Aceptable para demo; bloques `footer`/`hero` son data-driven — **mezcla** |
| H8 | **`config/menu.php` obsoleto vacío** | Comentario “no cargar”; referencias históricas | Ruido; riesgo de confusión |
| H9 | **`vertical.labels.menu` nunca usado en seeds** | Solo `VerticalProfile::menuLabel()` | Feature de personalización **sin datos ni docs de uso** |
| H10 | **`nuevo_modulo/` en raíz** | App PHP plana, README propio, sin Onion | Confunde onboarding y auditorías (mapa 2026-06-23) |

### 3.3 Incidente producción reciente (hardcode de rutas + despliegue)

- Error: `partials/admin/ajustes/_provider_section.php` no encontrado en VPS.
- Causa: `ViewHelper::partial()` **siempre** resuelve bajo `Views/partials/`; el archivo estuvo en `Views/admin/ajustes/` (corregido localmente).
- **Lección para spec:** convención de partials + checklist de despliegue + test que renderice ajustes con `settingsSections` no vacío.

---

## 4. Configuración y capacidades disponibles pero no ejercitadas (o incompletas)

| Config / capacidad | Ubicación | Estado | Acción sugerida para spec |
|--------------------|-----------|--------|---------------------------|
| `config/settings_sections.php` | `providers: []` | **No usado** — marketing registrado en `container.php` | Unificar con `SettingsSectionRegistry` leyendo FQCNs + filtro vertical |
| `config/modules/*.php` → `menu`, `permisos` | Varios manifiestos | **Vacío o no aplicado por Installer** | Installer o script que sincronice menú/permisos desde manifiesto |
| `config/modules/*.php` → `providers` | marketing `[]` | **Ignorado** | Auto-registro dashboard/settings/pdf según manifiesto |
| `config/vertical.php` → `labels.menu` | `[]` | **Sin ejemplos ni seeds** | Documentar o eliminar si no es producto |
| `config/rbac_route_permissions.php` | 4 slugs solo | **Desactualizado** — faltan `sistema.ver`, `pdf_kit.ver`, `reportes.*`, `marketing.*` | Ampliar + test que compare con `routes/web.php` |
| `scripts/rbac_integrity_report.php` | CLI | Existe | Integrar en CI o `scripts/status.php` |
| `scripts/seed.php --marketing-demo` | Flag nuevo | **Ejercitado** en tests | Extender patrón `--<modulo>-demo` documentado |
| `scripts/add_color_configs.php` | One-off DB | **Ad hoc** — no en flujo install/seed | Migrar a seed/migración o borrar |
| `scripts/build-vertical-kit.sh` | Shell | Kit vacío (`vertical-kit/`) | Implementar o quitar carpeta |
| `CrudScopeResolver` + JSON `scope` | Solo `demo_clientes.json` | **Capacidad no ejercitada** en marketing (`mkt_leads` sin scope; test falla) | Definir política owner vs admin para leads |
| `list.aggregation` / `group_by` CRUD | Motor + docs | Parcial (pie solo en vista agrupada) | Documentar o completar UX |
| `routes/api.php` | Casi vacío | Solo ping | API fuera de alcance salvo decisión explícita |
| `ModuleManifest::menu` / `permisos` | Domain Install | Campos parseados, **no consumidos** en install flow completo | Wiring en Installer |
| PHPUnit (`phpunit.xml.dist`) | Composer dev | Secundario vs `tests/run.php` | Unificar estrategia CI en spec |
| `config/cruds/clientes.json` | CRUD config | **Huérfano** | Quitar o vertical kit con tabla |
| Permisos manifiesto marketing | `marketing.gestionar`, `marketing.leads`, … | En manifiesto y SQL; **CRUD usa `permission_prefix: marketing`** | Verificar alineación slug ↔ JSON ↔ seeds |
| `LebytekUiConfig::resolve()` | Tema público + admin | **Ejercitado** en landing marketing | Extender a todas las superficies públicas |

---

## 5. Brechas abiertas por área (priorizadas para el spec)

### P0 — Rompe producción o seguridad

1. **Convención partials + despliegue incompleto** (ajustes provider section).
2. **Recursos CRUD sin tabla** (`clientes.json`).
3. **Rutas admin opcionales sin gate vertical** (calendario accesible con toggle off).

### P1 — Framework “no sano” para verticales / multi-agente

4. **Auto-registro módulos** (rutas + DI + providers) — hoy cada módulo edita `web.php` + `container.php`.
5. **`settings_sections.php` y `dashboard.php` como única fuente** — eliminar registro duplicado en container.
6. **`rbac_route_permissions.php` completo** + test de drift rutas ↔ middleware.
7. **Default `vertical.php` vs tests** — política clara (framework template vs instancia).

### P2 — Consistencia y deuda media

8. **Ajustes:** campos core también declarativos (o `CoreSettingsProvider`).
9. **`mkt_leads` scope** — alinear con política de leads (owner vs `marketing.gestionar`).
10. **Manifiestos `menu`/`permisos`** poblados y consumidos por Installer.
11. **`nuevo_modulo/`** — mover a `docs/legacy/`, repo separado, o eliminar.
12. **Pendientes mayo 2026:** `permisos.gestionar`, `ajustes.ver`, filtrar enlaces dashboard por permiso destino.

### P3 — Mejora / limpieza

13. Eliminar o documentar `config/menu.php`, `vertical-kit/`, `add_color_configs.php`.
14. Unificar comando de tests CI.
15. Sincronizar `reporte-mapa-proyecto.md` (marketing ya no es “solo planificado”).

---

## 6. Temas para el spec de corrección (propuesta de fases)

El brainstorming puede adoptar **3 fases** (ajustar según esfuerzo):

### Fase A — Higiene y fronteras (bajo riesgo, alto valor)

- Checklist despliegue: partials, assets públicos, seeds demo.
- Corregir CRUD huérfanos y tests de config (`clientes`, `mkt_leads` scope).
- Completar `rbac_route_permissions.php` + test drift.
- Resolver default `vertical.php` + fixture de test.
- Documentar convención `ViewHelper::partial()` vs `ViewHelper::render()`.

### Fase B — Configuración ejercitada

- `settings_sections.php` y `dashboard.php` leídos por container (con filtro `VerticalProfile`).
- Gate uniforme: helper `VerticalProfile::moduleEnabled` en grupo de rutas admin opcionales.
- `AjustesController`: core settings vía provider o config declarativa.

### Fase C — Modularidad plug-in (esfuerzo alto)

- Extender `ModuleManifest` + `Installer` para `permisos`, `menu`, `routes`, `container_bindings`, `settings_providers`, `dashboard_providers`.
- Patrón ya probado en marketing: `routes/<modulo>.php` + toggle.
- Reducir ediciones concurrentes en `container.php` / `web.php`.

---

## 7. Preguntas abiertas para brainstorming (una por sesión)

1. **Default del framework:** ¿`marketing` (y otros módulos demo) deben estar `false` en el template git y `true` solo en clones verticales?
2. **Leads scope:** ¿owner sobre `created_by`, solo admin con `marketing.gestionar`, o híbrido con `bypass_permission`?
3. **Installer vs seeds:** ¿manifiesto es fuente de verdad para permisos/menú o SQL seeds siguen siendo canónicos?
4. **API:** ¿incluir en “framework sano” o explícitamente out-of-scope?
5. **Multi-agente:** ¿rama/feature flags o ownership por directorio para Cursor vs Claude Code?
6. **`nuevo_modulo/`:** ¿archivar, integrar como vertical, o repo aparte?

---

## 8. No-goals propuestos (evitar scope creep en el spec)

- Reescribir CRUD Engine o migrar plataforma (`auth_*`, usuarios, roles) al motor genérico.
- Implementar dominio WhatsApp como código en el core (solo datos demo en seeds).
- Editor visual de dashboard o personalización por usuario en BD (fase posterior).
- Reemplazar `tests/run.php` por PHPUnit en un solo PR.

---

## 9. Verificación cuando el spec se implemente

| Check | Comando / artefacto |
|-------|---------------------|
| Suite principal | `php tests/run.php` (o subconjuntos Marketing, Auth, Crud) |
| Integridad RBAC | `php scripts/rbac_integrity_report.php` |
| Toggles | Con cada módulo en `false`, rutas del módulo → 404 o no registradas |
| Ajustes con marketing on | Render incluye secciones provider sin error de partial |
| Seed demo marketing | `php scripts/seed.php --marketing-demo` |
| Sin CRUD huérfanos | Test que valide `table` de cada `config/cruds/*.json` vs schema |
| VPS | Tras pull: verificar paths de partials y assets bajo `public/assets/publico/` |

---

## 10. Índice de evidencia (rutas clave)

| Tema | Archivos |
|------|----------|
| Vertical / toggles | `config/vertical.php`, `app/Kernel/Vertical/VerticalProfile.php` |
| Rutas | `routes/web.php`, `routes/marketing.php`, `routes/api.php` |
| DI | `config/container.php`, `config/settings_sections.php`, `config/dashboard.php` |
| Manifiestos | `config/modules/*.php`, `app/Application/Install/ModuleRegistry.php` |
| RBAC rutas | `config/rbac_route_permissions.php`, `app/Application/Services/RbacIntegrityReportService.php` |
| Ajustes | `app/Presentation/Controllers/Admin/AjustesController.php`, `app/Presentation/Views/partials/admin/ajustes/_provider_section.php` |
| CRUD configs | `config/cruds/*.json`, `tests/Marketing/CrudConfigsTest.php` |
| Marketing | `config/modules/marketing.php`, `database/schema/modules/marketing.sql`, `database/schema/modules/marketing_demo.sql` |
| Auditorías previas | `docs/audits/*.md` (este directorio) |
| Mapa proyecto | `docs/audits/reporte-mapa-proyecto.md` |

---

## 11. Self-review (autor de esta auditoría)

- Cobertura de auditorías previas: sí, §1 + hallazgos cruzados.
- Lista hardcoding: §3 con aceptable vs problemático.
- Config no usada: §4 tabla explícita.
- Orientado a brainstorming: §0, §6, §7, §8.
- Un solo archivo en `docs/audits/`: cumplido.
- Placeholders: sin TBD de implementación; preguntas abiertas son deliberadas para brainstorming.

**Siguiente paso recomendado:** invocar skill **brainstorming** con este archivo como contexto y producir `docs/superpowers/specs/2026-06-XX-framework-salud-correccion-design.md`.
