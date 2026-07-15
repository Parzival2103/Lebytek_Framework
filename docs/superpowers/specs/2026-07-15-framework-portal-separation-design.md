# Design: Separación Framework ↔ Portal (tenant Lebytek)

**Fecha:** 2026-07-15  
**Repo:** `Lebytek_Framework` (origen del trabajo) + futuro repo Portal  
**Estado:** borrador aprobado en brainstorming; pendiente revisión humana del archivo  
**Alcance:** decisión de arquitectura y cutover conceptual — **sin implementación en este documento**

## Problema

Hoy conviven en un solo repo (y mentalmente bajo el mismo nombre **Lebytek**):

1. **Framework** — plataforma PHP reutilizable (`src/`, `Lebytek\Framework\`, paquete Composer `lebytek/framework`).
2. **Portal / tenant empresa Lebytek** — back-office y sitio desplegado en lebytek.com / waapi (`app/`, marketing, leads, membresías, cliente HTTP de api.lebytek.com).

Eso facilitó arrancar el admin en un branch (`feature/backoffice-api-integration`), pero bloquea el objetivo real: **reutilizar el framework en apps de otros clientes/tenants** sin arrastrar el negocio de Lebytek.

## Objetivos

| Prioridad | Objetivo |
|-----------|----------|
| Primario | Reuso: otros proyectos-tenant consumen el framework como dependencia |
| Secundario | Claridad de naming: dejar de confundir plataforma vs portal empresa |
| Terciario | Deploy limpio: un checkout de app + Composer → `vendor/` |

## No-objetivos (fuera de alcance)

- Merge de `feature/backoffice-api-integration` → `main` sin orden explícita del usuario.
- Extraer ya el módulo marketing a un paquete aparte (YAGNI hasta el 2º consumidor).
- Multi-tenant en **un** deploy / una BD compartida. Este diseño es **multi-proyecto** (un repo/app por tenant).
- Cambiar DNS, VPS o producción en este documento.

## Naming (guía corta)

| Concepto | Nombre propuesto | Qué es |
|----------|------------------|--------|
| Plataforma | **Lebytek Framework** / paquete `lebytek/framework` | Código reutilizable en `src/` |
| Producto empresa | **Lebytek Portal** (repo sugerido `Lebytek_Portal`) | Tenant de la misma empresa: admin + marketing + integración api |
| Otros clientes | App tenant X (nombre del cliente) | Proyectos nuevos basados en skeleton + framework |
| Prefijo Composer | `lebytek/*` | Vendor namespace de la compañía (OK aunque el portal sea otro repo) |

Regla verbal: *“Lebytek Framework no es el portal; el portal es un consumidor del Framework.”*

## Decisión de enfoque

**Enfoque A — Dos repos + Composer (elegido).**

- Repo Framework: paquete limpio, tags semver, sin negocio del portal.
- Repo Portal: aplicación desplegable; `require` de `lebytek/framework`.
- Módulos opcionales (`lebytek/module-*`) más adelante, cuando un segundo tenant los necesite.

**Descartado:** “`git pull` de dos repos en la **misma** carpeta”. Git no gestiona bien dos árboles solapados (`.git` anidados, conflictos de paths, document root ambiguo). Composer sí resuelve el caso: la app posee el árbol desplegable; el framework entra en `vendor/lebytek/framework`.

**Alternativas no elegidas:**

| Enfoque | Idea | Por qué no (ahora) |
|---------|------|---------------------|
| B — Monorepo `packages/` + `apps/` | Un Git, path repos | Útil internamente, pero el objetivo es reuso externo claro y repos independientes |
| C — Solo limpiar `main` y aplazar el repo Portal | Menos riesgo corto | Retrasa el modelo que necesitan los tenants externos |

## Arquitectura objetivo

```text
┌─────────────────────────────────────────┐
│  Repo: Lebytek_Portal (deploy VPS)      │
│  app/  config/  routes/  public/        │
│  database/ (negocio)  tests/ (negocio)  │
│  composer.json → require framework      │
│           │                             │
│           ▼                             │
│  vendor/lebytek/framework/   ◄── Composer
└─────────────────────────────────────────┘
                    ▲
                    │ tags ^x.y / VCS privado
┌─────────────────────────────────────────┐
│  Repo: Lebytek_Framework                │
│  src/  (Lebytek\Framework\)             │
│  tests/ de plataforma                   │
│  docs/ del framework                    │
│  skeleton/ (plantilla para tenants)     │
└─────────────────────────────────────────┘
```

**Flujo HTTP (sin cambio de capas):**  
`public/index.php` (Portal) → Bootstrap / Kernel (Framework en `vendor/`) → Controllers / UseCases / Domain del Portal (`App\`).

**Frontera de código (lista conceptual):**

| Va al Framework | Va al Portal | Más adelante (módulo opcional) |
|-----------------|--------------|-------------------------------|
| Kernel, router, DI, sesión, seguridad | `app/` de marketing, leads, membresías | Extraer marketing si un 2º tenant lo pide |
| Auth / RBAC base, CRUD Engine, dashboard builders | `LebytekApiClient`, contratos api producto | Otros `lebytek/module-*` |
| Helpers/infra genéricos (mail/PDF wrappers genéricos) | `config/`, `routes/`, vistas de producto | |
| Schema/permisos de plataforma (`auth_*`, etc. según ya exista) | Migraciones/seeds `dom_*` de negocio | |
| `skeleton/` plantilla mínima | Deploy lebytek.com / waapi | |

El `skeleton/` existente ya declara `lebytek/framework` vía VCS: es la base oficial para **nuevos** tenants (no el portal Lebytek completo).

## Deploy VPS (modelo correcto)

Un sitio = **un** checkout del **Portal** + `composer install`.

```text
/home/.../lebytek.com/
  app/ config/ routes/ public/ database/ storage/ .env
  composer.json / composer.lock
  vendor/   ← incluye lebytek/framework + dompdf, phpmailer, etc.
```

- Document root: `public/` del Portal.
- `.env` y secretos solo en el Portal.
- Actualizar framework: bump de versión en `composer.json` / `composer update lebytek/framework` (o pin a tag), no un segundo `git pull` hermano solapado.

**Desarrollo local del mantenedor del framework:**

- Recomendado: `repositories` tipo `path` apuntando a un clone hermano de `Lebytek_Framework` para iterar sin publicar tag en cada cambio.
- Alternativa: VCS + branch/tag (`dev-main`, `^1.0`) cuando se quiera validar el mismo flujo que producción.

## Módulos opcionales (fase posterior)

Cuando un **segundo** tenant necesite marketing (u otro vertical):

1. Extraer a `lebytek/module-marketing` (u otro nombre).
2. El Portal Lebytek y el tenant X lo `require`n solo si aplica.
3. Hasta entonces, marketing permanece **dentro** del Portal (no ensucia el paquete framework).

## Plan de cutover (conceptual)

1. **Congelar frontera** con checklist de archivos/carpetas (Framework vs Portal).
2. **Framework limpio en su repo:** `main` publica solo el paquete; tags semver; CI de tests de plataforma.
3. **Crear `Lebytek_Portal`:** mover/copiar el árbol desplegable actual (origen práctico: tip de trabajo del portal, p. ej. historia de `feature/backoffice-api-integration` / árbol monorepo actual) + `composer.json` que `require` `lebytek/framework`.
4. **Ajustar autoload:** Portal autoload solo `App\\`; Framework solo `Lebytek\\Framework\\`. Dejar de autoload-ear ambos desde un único `composer.json` de deploy.
5. **Documentar migraciones:** quién aplica schema de plataforma vs negocio (scripts/orden).
6. **VPS:** apuntar el sitio al repo Portal; `composer install --no-dev`; smoke (login admin, landing, un camino de integración api). Mantener la política vigente: no merge a `main` del Framework sin orden explícita.
7. **Tenants nuevos:** partir de `skeleton/`, no del Portal Lebytek.

## Testing

| Capa | Dónde | Qué |
|------|-------|-----|
| Plataforma | Repo Framework | Kernel, router, CRUD, RBAC, regressions de `src/` |
| Negocio | Repo Portal | Marketing, leads, membresías, cliente api (mocks del framework si hace falta) |
| Smoke cutover | VPS / staging | Login, landing pública, un flujo integración |

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Autoload mezclado `App\\` + `Lebytek\\Framework\\` en un solo `composer.json` | Portal requiere paquete; no path-autoload de `src/` en prod |
| Migraciones mezcladas plataforma / negocio | Inventario + orden documentado; smoke post-migrate |
| Confusión de ramas VPS (`feature/backoffice-…` del monorepo) | Checklist de cutover: remote + composer.lock + smoke |
| Naming humano (“Lebytek”) | Guía de naming arriba + README en ambos repos |
| Sacudir marketing al Framework “por comodidad” | Rechazar en review; solo módulo opcional con 2º consumidor |

## Criterios de éxito

- Un developer de un tenant externo puede: clonar skeleton (o app vacía), `composer require lebytek/framework`, levantar admin básico **sin** código de marketing Lebytek.
- El portal Lebytek despliega desde **su** repo; el framework llega solo por Composer.
- El equipo verbaliza sin ambigüedad: Framework vs Portal vs Tenant X.

## Próximo paso tras aprobación de este archivo

Invocar el flujo **writing-plans** para un plan de implementación detallado (checklist de archivos, comandos, orden de PRs/repos, smoke). Sin código hasta ese plan y su ejecución acordada.
