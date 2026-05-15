# Módulos especializados de plataforma

**Versión:** 0.1  
**Contexto:** complementa [arquitectura.md](./arquitectura.md), [modulo-crud-engine.md](../modules/crud/modulo-crud-engine.md) y [auditoria_alineacion_modulos_v0.1.md](../audits/auditoria_alineacion_modulos_v0.1.md).

---

## 1. Propósito

Definir qué son los **módulos especializados del core** frente al **CRUD Engine**, cuándo usar cada uno y las reglas mínimas que deben cumplir. Evita que autenticación, autorización, configuración global o navegación se implementen con el motor genérico de tablas `dom_*`.

---

## 2. Qué es un módulo especializado del core

Es una funcionalidad de **plataforma** que:

- expone rutas y vistas propias (no `/admin/crud/{resource}`),
- coordina casos de uso y/o repositorios sobre tablas con prefijos de plataforma (`auth_*`, `cfg_*`, `core_*`, …),
- aplica reglas de seguridad, validación o flujo que **no** deben depender solo de un JSON declarativo,
- integra RBAC, CSRF, LEBYTEK UI y el layout admin estándar.

Se implementa siguiendo la arquitectura **MVC + Onion** del proyecto: Presentation delgada, Application orquestando, Domain con reglas/contratos, Infrastructure con persistencia.

---

## 3. Cuándo NO usar CRUD Engine

No usar el CRUD Engine cuando:

- la tabla es **`auth_*`**, **`cfg_*`**, **`core_*`** o **`log_*`** (el validador del motor **rechaza** esas tablas; ver [modulo-crud-engine.md](../modules/crud/modulo-crud-engine.md) §6),
- intervienen **credenciales**, **sesión**, **tokens**, **superficie RBAC crítica** o **configuración que altera todo el sistema**,
- el flujo no es un CRUD simple sobre una entidad principal (p. ej. dashboard por proveedores, menú jerárquico, login).

En esos casos corresponde un **módulo especializado** o, en dominio de negocio, un módulo `dom_*` con lógica propia fuera del motor si el JSON no es suficiente.

---

## 4. Cuándo sí usar CRUD Engine

Usar el CRUD Engine cuando:

- existe una tabla **`dom_*`** (u otra permitida por la política `security` del JSON) con el esquema base esperado por el motor,
- el flujo es **listar / crear / ver / editar / borrado lógico** acotado a la definición del recurso,
- los campos y reglas pueden declararse en JSON, complementándose con **handlers** registrados en `config/crud_handlers.php` si hace falta lógica acotada.

Procedimiento: [uso-crud-engine.md](../modules/crud/uso-crud-engine.md).

---

## 5. Módulos especializados actuales

| Módulo | Rutas típicas | Tablas / notas |
|--------|----------------|----------------|
| **Usuarios** | `/admin/administracion/usuarios` | `auth_usuarios`, relación roles; hashing, DTOs, use cases |
| **Roles** | `/admin/administracion/roles` | `auth_roles`, `auth_roles_permisos`; matriz de permisos |
| **Permisos** | `/admin/administracion/permisos` | `auth_permisos`; slugs usados por menú y CRUD |
| **Ajustes** | `/admin/ajustes` | `cfg_configuraciones`; tema, layout LEBYTEK, etc. |
| **Dashboard** | `/admin/dashboard` | Sin tabla obligatoria; [modulo-dashboard.md](../modules/modulo-dashboard.md) |
| **Menú** | (sin pantalla única obligatoria) | `core_menu_items`; catálogo vía BD/seeds — [modulo-menu.md](../modules/modulo-menu.md) |
| **Login** | `/login`, `/` | Autenticación; fuera del shell admin autenticado |

---

## 6. Reglas obligatorias para módulos especializados

1. **Arquitectura Onion:** sin lógica de negocio pesada en controladores ni en vistas; coordinación en Application; reglas en Domain cuando aplique ([arquitectura.md](./arquitectura.md)).
2. **RBAC:** rutas protegidas con `RbacMiddleware` (o equivalente explícito) usando slugs existentes en `auth_permisos`; alinear cuando sea posible `permiso_slug` en `core_menu_items` con el permiso de la ruta.
3. **CSRF:** en todos los `POST`/`PUT`/`DELETE` que muten estado (p. ej. `CsrfMiddleware` en rutas).
4. **LEBYTEK UI:** vistas alineadas al contrato [ui_ux.md](./ui_ux.md) y a la implementación [ui_ux_implementacion_v0.1.md](./ui_ux_implementacion_v0.1.md) (`.ct-page`, cards, tablas responsive).
5. **Sin SQL en vistas:** consultas y comandos solo en repositorios / servicios de infraestructura o aplicación.
6. **Excepciones documentadas:** si un comportamiento se desvía del estándar, registrar motivo y responsable (plantilla §7).

---

## 7. Plantilla para documentar nuevas excepciones

Copiar y completar al introducir un módulo o desviación:

```markdown
### Excepción: [Nombre breve]

- **Módulo / rutas:**
- **Motivo:** seguridad | negocio | efecto global | integración externa | otro
- **Por qué no aplica CRUD Engine (si aplica):**
- **Permisos RBAC:** slugs, middleware, ítems de menú relacionados
- **Archivos / capas principales:**
- **Fecha y responsable:**
```

---

## Referencias

- [table-prefix-convention.md](./table-prefix-convention.md)
- [core-schema-and-modules.md](./core-schema-and-modules.md)
- [auth_rbac_seguridad_v0.1.md](./auth_rbac_seguridad_v0.1.md)
- Corrección aplicada: [correccion_alineacion_modulos_v0.1.md](../audits/correccion_alineacion_modulos_v0.1.md)
