# Brief: generar un vertical de negocio sobre Lebytek Framework

> **Para la IA que recibe este kit.** No conoces el código del framework. Este
> documento + la carpeta `contexto/` te dan todo lo necesario. La carpeta
> `plantillas/` contiene un vertical demo **completo y funcional** que debes
> clonar y adaptar. **No inventes estructura nueva: imita la demo.**

---

## 1. Qué es un "vertical" aquí

Lebytek es un framework de plataforma (auth, RBAC, menú admin, ajustes, shell)
**sin lógica de negocio**. Un **vertical** (tier T4) es el producto concreto de
un cliente (una clínica, un taller, un almacén…). Vive en tablas con prefijo
**`dom_*`** y se engancha a la plataforma vía permisos, menú y un toggle.

La plataforma incluye un **CRUD Engine**: un motor genérico que renderiza un
recurso de datos (listado, alta, edición, borrado, filtros) **a partir de un
archivo JSON declarativo**, sin escribir controladores ni vistas.

> **Regla de oro de eficiencia:** si el recurso encaja en el CRUD Engine,
> el vertical es **solo SQL + JSON (+ handlers opcionales)**. NO escribas capas
> Domain/Application/Infrastructure/Presentation salvo que haya lógica que el
> motor no cubra. La demo es 100% declarativa: úsala como vara de medir.

### Árbol de decisión (qué construir)

1. **¿El recurso encaja en el CRUD Engine?** → camino **declarativo**: SQL + JSON
   (+ handlers opcionales). Es el caso más común. NO escribas capas Onion.
2. **¿Hay lógica que el motor no cubre** (orquestación, contratos de extensión,
   providers intercambiables)? → **dominio Onion**. Ver
   `contexto/docs/modules/patron-dominio-onion.md` (ejemplo: marketing).
3. **¿Necesitas enviar mensajes / consumir una API / webhooks externos?** →
   **conector de integración**. Ver
   `contexto/docs/modules/patron-conector-integracion.md` (ejemplo: integrations).

---

## 2. Lo que debes producir por cada vertical

Salida mínima (caso declarativo, el más común):

| # | Archivo a generar | Clónalo de (en `plantillas/`) |
|---|---|---|
| 1 | `database/schema/modules/<vertical>.sql` | `database/schema/modules/crud-engine.sql` |
| 2 | `config/cruds/<recurso>.json` (uno por entidad) | `config/cruds/demo_*.json` |
| 3 | `config/modules/<vertical>.php` (manifiesto) | `config/modules/crud-engine.php` |
| 4 | Entrada en `config/vertical.php` → `modules` | ver §5 |

Salida ampliada (solo si aplica):

| Caso | Archivo extra | Plantilla |
|---|---|---|
| Hay columnas de fecha y quieres vista calendario | `config/calendars/<recurso>.json` | `config/calendars/demo_citas.json` |
| Quieres reportes PDF del recurso | `config/reportes/<recurso>.json` | `config/reportes/*.json` |
| Hay reglas que el motor no cubre (toggles, guards, validaciones) | `app/Application/Crud/Handlers/<X>.php` + registro en `config/crud_handlers.php` | `app/Application/Crud/Handlers/Demo*.php` |
| Plantilla PDF a medida | `app/Application/Pdf/Templates/<X>Template.php` | `app/Application/Pdf/Templates/DemoReporteTemplate.php` |
| Lógica de dominio real (no CRUD) | capas Onion completas | ver `contexto/docs/modules/uso-de-modulo-dominio.md` §8–13 |

---

## 3. Reglas no negociables (leer antes de generar SQL)

1. **Prefijo `dom_*`** en TODAS las tablas del vertical. Nunca uses
   `auth_*`, `cfg_*`, `core_*`, `log_*` (son de la plataforma).
   Ref: `contexto/docs/core/table-prefix-convention.md`.
2. **El SQL debe ser idempotente.** Tablas con `CREATE TABLE IF NOT EXISTS`;
   permisos con `INSERT IGNORE`; menú y seeds con `INSERT ... WHERE NOT EXISTS`
   o `INSERT IGNORE`. La demo `crud-engine.sql` es el patrón exacto a copiar.
3. **No edites `schema.sql`.** El schema base (`contexto/database/schema/schema.sql`)
   es solo referencia: te muestra las tablas que YA existen (`auth_permisos`,
   `core_menu_items`, `auth_usuarios`, etc.) a las que enganchas FKs/permisos/menú.
4. **Columnas de auditoría estándar** en cada tabla `dom_*` (cópialas de la demo):
   `deleted TINYINT(1)`, `created_at`, `created_by`, `updated_at`, `updated_by`,
   `deleted_at`, `deleted_by`. El motor asume soft-delete por `deleted`.
5. **Permisos con slug `modulo.accion`** (`mi_vertical.ver`, `.crear`, `.editar`,
   `.eliminar`). Charset `utf8mb4` / `utf8mb4_unicode_ci`.
6. **Menú en `core_menu_items`**: una fila raíz por vertical + subítems con
   `parent_id`. Cada fila lleva `permiso_slug` y `vertical_module`.
   Ref: `contexto/docs/modules/modulo-menu.md`.

---

## 4. Anatomía de un `config/cruds/<recurso>.json`

Estudia `plantillas/config/cruds/demo_pedidos.json` (el más completo: tiene
relaciones, estados, hooks) y `demo_clientes.json` (el más simple). Cada JSON
declara, como mínimo: la tabla `dom_*`, los campos (tipo, label, validación),
las columnas del listado, los filtros, los permisos por acción y, opcionalmente,
`hooks.handler` (string de la whitelist en `config/crud_handlers.php`,
**nunca** un FQCN). No inventes claves: usa solo las que aparezcan en los demo.

---

## 5. Cableado final (manifiesto + toggle)

- **Manifiesto** `config/modules/<vertical>.php`: copia `crud-engine.php` y ajusta
  `key`, `version` (semver, empieza en `1.0.0`), `deps` (casi siempre
  `['core','crud-engine']`; añade `pdf-kit`/`reportes`/`calendario` si los usas)
  y `bootstrap_sql` apuntando a tu `database/schema/modules/<vertical>.sql`.
- **Toggle** en `config/vertical.php`:
  ```php
  'modules' => [
      '<vertical>' => true,
  ],
  ```
  La clave debe coincidir con el `slug` de la entrada raíz de menú.

---

## 6. Cómo se instala lo que generes (contexto, no acción tuya)

El operador correrá `php scripts/install.php --modules=<vertical>`, que aplica el
`bootstrap_sql`, registra la versión en `cfg_modulos` y resuelve dependencias.
Por eso el SQL debe ser idempotente y el manifiesto correcto. Detalle del modelo
de tiers y despliegue: `contexto/docs/core/despliegue-y-versionado.md` (Playbook 4).

---

## 7. Checklist de entrega (autovalida antes de responder)

- [ ] Todas las tablas usan prefijo `dom_*` y columnas de auditoría.
- [ ] SQL 100% idempotente (re-ejecutable sin error ni duplicados).
- [ ] Un `config/cruds/<recurso>.json` por entidad, sin claves inventadas.
- [ ] Permisos `modulo.accion` + filas de `core_menu_items` con `permiso_slug`.
- [ ] Manifiesto `config/modules/<vertical>.php` con `deps` y `bootstrap_sql`.
- [ ] Entrada en `config/vertical.php`.
- [ ] Si usaste handlers: clase + registro en `config/crud_handlers.php`.
- [ ] No tocaste `schema.sql` ni tablas de plataforma.

---

### Fuentes de verdad en `contexto/` (consulta cuando dudes)

| Tema | Archivo |
|---|---|
| Checklist oficial de módulo (el contrato) | `docs/modules/uso-de-modulo-dominio.md` |
| Modelo de tiers y despliegue | `docs/core/despliegue-y-versionado.md` |
| Pasos de instancia/BD | `docs/core/vertical-onboarding.md` |
| Prefijos de tablas | `docs/core/table-prefix-convention.md` |
| Naming PHP/DB/rutas | `docs/core/convenciones_nombres.md` |
| Regla de dependencias (Onion) | `docs/core/arquitectura.md` |
| Menú admin desde BD | `docs/modules/modulo-menu.md` |
| Calendario declarativo | `docs/modules/modulo-calendario.md` |
| Mapa tabla ↔ código | `docs/core/schema-code-map.md` |
| Patrón de dominio Onion (lógica real) | `docs/modules/patron-dominio-onion.md` |
| Patrón de conector de integración | `docs/modules/patron-conector-integracion.md` |
| Resumen de arquitectura | `CLAUDE.md` |
| Tablas que YA existen | `database/schema/schema.sql` |
| Cambios post-deploy (migraciones) | `database/migrations/README.md` |
