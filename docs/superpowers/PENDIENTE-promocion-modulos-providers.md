# PENDIENTE — Promoción de módulos a "provider por módulo"

> Estado actual: el registro de bindings/rutas/menú/dashboard usa el **mínimo viable**
> (entrypoint único `Lebytek\Framework\Kernel\Container\FrameworkServiceProvider`).
> El modelo objetivo (spec 2026-06-27 §6) es un **ServiceProvider por módulo** que
> registre sus bindings, rutas, menú, dashboard contributions, settings sections y
> crud handlers vía su manifiesto (`config/modules/*.php`, campo `providers`).
>
> Este archivo LISTA lo pendiente. NO implementa la promoción (fuera de alcance del
> ciclo de separación). Cada módulo se promoverá en su propio ciclo.

## Módulos del framework pendientes de promover
- [ ] core
- [ ] crud-engine
- [ ] dashboard
- [ ] calendario
- [ ] pdf-kit
- [ ] reportes
- [ ] integrations

## Módulos de dominio (cuando existan, en Repo 2)
- [ ] marketing
- [ ] (futuro) dom_apiwa_* — producto "API sola"
- [ ] (futuro) dom_salon_* — vertical salones/citas

## Criterio de "promovido"
Un módulo está promovido cuando su manifiesto declara un `provider` que registra
TODO lo suyo (container, rutas, menú, dashboard, settings, crud handlers) y el
entrypoint único ya no contiene bindings de ese módulo.
