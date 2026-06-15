# Documentación del proyecto

Índice de la carpeta [`docs/`](.). La normativa está repartida por **tema**; prioriza estos archivos antes que copias paralelas no enlazadas.

## Cómo leer esta documentación

1. **Arquitectura y estructura:** [`core/arquitectura.md`](core/arquitectura.md) y [`core/estructura_proyecto.md`](core/estructura_proyecto.md).
2. **Nombres y API:** [`core/convenciones_nombres.md`](core/convenciones_nombres.md), [`core/reglas_api.md`](core/reglas_api.md).
3. **IA / consistencia de cambios:** [`core/reglas_ia.md`](core/reglas_ia.md) (legible por humanos); reglas ejecutables en el IDE: [`.cursor/rules`](../.cursor/rules).
4. **Desplegar / actualizar una instancia:** [`core/despliegue-y-versionado.md`](core/despliegue-y-versionado.md) — mapa de qué tocar por tipo de despliegue.
5. **Nueva instancia o vertical:** [`core/vertical-onboarding.md`](core/vertical-onboarding.md).
6. **Nuevo módulo de negocio (`dom_*`):** [`modules/uso-de-modulo-dominio.md`](modules/uso-de-modulo-dominio.md).

## `docs/core/`

| Documento | Contenido |
|-----------|-----------|
| [`arquitectura.md`](core/arquitectura.md) | MVC + Onion, capas, flujo, reglas de dependencia |
| [`estructura_proyecto.md`](core/estructura_proyecto.md) | Árbol oficial de carpetas |
| [`convenciones_nombres.md`](core/convenciones_nombres.md) | BD, PHP, archivos, API, JSON |
| [`reglas_ia.md`](core/reglas_ia.md) | Reglas para desarrollo asistido por IA |
| [`reglas_api.md`](core/reglas_api.md) | Convenciones de API |
| [`diccionario_dominio.md`](core/diccionario_dominio.md) | Lenguaje del negocio |
| [`despliegue-y-versionado.md`](core/despliegue-y-versionado.md) | **Guía operativa**: 4 tiers, inventario de superficies, playbooks por tipo de despliegue, versionado |
| [`despliegue_hosting.md`](core/despliegue_hosting.md) | Exposición vía `public/` |
| [`table-prefix-convention.md`](core/table-prefix-convention.md) | Prefijos de tablas |
| [`core-schema-and-modules.md`](core/core-schema-and-modules.md) | Plataforma vs dominio |
| [`schema-code-map.md`](core/schema-code-map.md) | Tablas ↔ código |
| [`vertical-onboarding.md`](core/vertical-onboarding.md) | Checklist de instancia |
| [`modulos_especializados_plataforma.md`](core/modulos_especializados_plataforma.md) | Módulos core vs CRUD Engine, reglas y plantilla de excepciones |
| [`auth_rbac_seguridad_v0.1.md`](core/auth_rbac_seguridad_v0.1.md) | Auth/RBAC: tablas, slugs, rutas, menú, SQL injection, checklist |

## `docs/modules/`

| Documento | Contenido |
|-----------|-----------|
| [`uso-de-modulo-dominio.md`](modules/uso-de-modulo-dominio.md) | Checklist para un módulo nuevo |
| [`modulo-menu.md`](modules/modulo-menu.md) | Menú admin (`core_menu_items`) |
| [`modulo-dashboard.md`](modules/modulo-dashboard.md) | Extensión del dashboard |

### CRUD Engine (`docs/modules/crud/`)

| Documento | Rol |
|-----------|-----|
| [`modulo-crud-engine.md`](modules/crud/modulo-crud-engine.md) | **Especificación** (contrato JSON, seguridad, listados, handlers) |
| [`uso-crud-engine.md`](modules/crud/uso-crud-engine.md) | **Guía operativa** (pasos mínimos para un recurso) |
| [`history/correccion_crud_engine_v0.1.md`](modules/crud/history/correccion_crud_engine_v0.1.md) | Cambios tras auditoría CRUD |
| [`history/refinamiento_crud_engine_v0.1.md`](modules/crud/history/refinamiento_crud_engine_v0.1.md) | Validación, agregaciones, UI |

## `docs/audits/`

| Documento | Contenido |
|-----------|-----------|
| [`auditoria_crud_engine_v0.1.md`](audits/auditoria_crud_engine_v0.1.md) | Auditoría técnica CRUD + plataforma (2026-04-28); parcialmente superada por `history/` y spec actual |
| [`auditoria_documentacion.md`](audits/auditoria_documentacion.md) | Esta reorganización y coherencia documental |
| [`auditoria_alineacion_modulos_v0.1.md`](audits/auditoria_alineacion_modulos_v0.1.md) | Alineación CRUD / LEBYTEK / RBAC por sección |
| [`correccion_alineacion_modulos_v0.1.md`](audits/correccion_alineacion_modulos_v0.1.md) | RBAC en rutas, redirects usuarios, menú granular (2026-05-02) |
| [`correccion_auth_rbac_v0.1.md`](audits/correccion_auth_rbac_v0.1.md) | Endurecimiento RBAC, slugs, formulario roles, informe integridad (2026-05-02) |

## `docs/legacy/`

| Documento | Nota |
|-----------|------|
| [`example-domain-imprenta.md`](legacy/example-domain-imprenta.md) | Anexo histórico; no es procedimiento vigente |
