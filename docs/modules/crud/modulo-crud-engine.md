# MÓDULO: CRUD ENGINE v0.1

**Lecturas relacionadas:** guía breve [`uso-crud-engine.md`](./uso-crud-engine.md); historia de endurecimiento [`history/correccion_crud_engine_v0.1.md`](./history/correccion_crud_engine_v0.1.md), [`history/refinamiento_crud_engine_v0.1.md`](./history/refinamiento_crud_engine_v0.1.md); auditoría puntual [`../../audits/auditoria_crud_engine_v0.1.md`](../../audits/auditoria_crud_engine_v0.1.md) (referencia temporal). Arquitectura global: [`../../core/arquitectura.md`](../../core/arquitectura.md).

---

## 1. PROPÓSITO

El CRUD Engine es un módulo del core que permite generar interfaces CRUD funcionales a partir de:

**Tabla física + definición JSON = CRUD funcional**

Su objetivo es:

- acelerar el desarrollo de módulos administrativos
- estandarizar formularios y tablas
- evitar creación de clases repetitivas
- mantener consistencia visual y estructural
- permitir extensibilidad mediante lógica externa

---

## 2. ALCANCE

El CRUD Engine es responsable de:

### ✔ Incluye
- Formularios dinámicos
- Tablas dinámicas
- Acciones CRUD estándar
- Validaciones simples
- Integración con permisos
- Integración con menú
- Borrado lógico
- Auditoría básica
- Soporte para uploads
- Extensión mediante handlers

### ❌ No incluye
- Creación automática de tablas
- Lógica de negocio compleja
- SQL dinámico libre
- Constructor visual de CRUDs
- Versionado de configuraciones

---

## 3. CONCEPTO PRINCIPAL

Cada CRUD se define mediante:

1. Una tabla física existente (`dom_*`)
2. Un archivo JSON de configuración

---

## 4. UBICACIÓN DE CONFIGURACIÓN

Los JSON deben ubicarse en:

```
/config/cruds/
```

Ejemplo:

```
/config/cruds/clientes.json
/config/cruds/productos.json
```

---

## 5. REGLAS DE TABLAS

Toda tabla que use el CRUD Engine debe contener:

```sql
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
status VARCHAR(30) NOT NULL DEFAULT 'activo',
deleted TINYINT(1) NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
created_by BIGINT UNSIGNED NULL,
updated_at DATETIME NULL,
updated_by BIGINT UNSIGNED NULL,
deleted_at DATETIME NULL,
deleted_by BIGINT UNSIGNED NULL
```

---

## 6. SEGURIDAD

### Tablas permitidas por defecto

- Prefijo `dom_*` sobre tablas de negocio.

### Tablas siempre bloqueadas

Sin excepción (aunque `allow_core_table` sea `true`):

- prefijos `auth_*`, `cfg_*`, `core_*`, `log_*`

### Bloque `security` en JSON

```json
"security": {
  "allow_core_table": false,
  "mode": "restricted"
}
```

- **`mode`:** `restricted` | `strict` (obligatorio si se declara `security`; el validador exige valores conocidos).
- **`restricted`:** solo tablas `dom_*` por defecto. Una tabla sin prefijo `dom_*` puede usarse únicamente si `allow_core_table` es **`true`** (excepción explícita; usar con cautela).
- **`strict`:** solo se permiten tablas con prefijo `dom_*`; `allow_core_table` **no** autoriza tablas no `dom_*`.

Las tablas con prefijos de plataforma listados arriba siguen rechazadas en ambos modos.

---

## 7. PERMISOS

Cada recurso debe usar:

```
{recurso}.ver
{recurso}.crear
{recurso}.editar
{recurso}.eliminar
```

Ejemplo:

```
clientes.ver
clientes.crear
clientes.editar
clientes.eliminar
```

---

## 8. RUTAS

El CRUD Engine usa rutas genéricas:

```
GET    /admin/crud/{resource}
GET    /admin/crud/{resource}/crear
POST   /admin/crud/{resource}
GET    /admin/crud/{resource}/{id}
GET    /admin/crud/{resource}/{id}/editar
POST   /admin/crud/{resource}/{id}
POST   /admin/crud/{resource}/{id}/eliminar
```

---

## 9. FORMULARIO DINÁMICO

Debe soportar:

- text
- textarea
- select
- checkbox
- hidden
- readonly
- required
- columnas Bootstrap (`col`)
- validaciones declarativas en JSON con saneo y tipo coherente (aplicadas en servidor vía `CrudFieldValidationService`; ver [`history/refinamiento_crud_engine_v0.1.md`](./history/refinamiento_crud_engine_v0.1.md))
- uploads

---

## 10. TABLA DINÁMICA

Debe soportar:

- columnas configurables (incl. `sortable`, `searchable`, `format` date/datetime/money, badges)
- búsqueda, orden acotado a columnas válidas, filtros
- acciones por fila
- paginación
- **Agrupación y sumario:** campo opcional **`list.group_by`** (columna existente, no protegida) y **`list.summaries`** (ver ejemplo en `config/cruds/demo_productos.json` y detalle en [`history/refinamiento_crud_engine_v0.1.md`](./history/refinamiento_crud_engine_v0.1.md))
- **`list.aggregation`** (opcional): límites de coste — `enabled`, `max_rows` (1–500000, por defecto 5000 cuando el recurso construye valores por defecto), `require_filter_above` (omite agregación si el conteo candidato supera el umbral y no hay búsqueda ni filtros de listado activos). Si se omite agregación, el listado continúa en modo tabla paginado (mensaje en UI y registro operativo).
- **`list.table_compact`** o **`list.table_sm`:** tabla compacta en UI

---

## 11. BORRADO LÓGICO

El delete debe:

- marcar `deleted = 1`
- registrar `deleted_at`
- registrar `deleted_by`
- no eliminar físicamente

---

## 12. BITÁCORA

Debe registrar eventos en:

```
log_bitacora
```

Eventos:

- crud.create
- crud.update
- crud.delete

---

## 13. JSON BASE

Estructura mínima:

```json
{
  "resource": {
    "key": "clientes",
    "title": "Clientes",
    "table": "dom_clientes",
    "primary_key": "id",
    "permission_prefix": "clientes"
  },
  "list": {
    "columns": [],
    "filters": [],
    "actions": ["show", "edit", "delete"]
  },
  "form": {
    "fields": []
  },
  "uploads": {
    "enabled": false,
    "public_path": "uploads/cruds"
  },
  "hooks": {
    "handler": null
  }
}
```

---

## 14. HANDLERS (LÓGICA EXTERNA)

No se admite **FQCN** en el JSON. `hooks.handler` es una **clave** (`[a-z0-9_-]{1,64}`) definida en [`config/crud_handlers.php`](../../../config/crud_handlers.php), que mapa clave → clase PHP. La clase debe existir, cargarse por autoload e implementar [`CrudHookHandlerInterface`](../../../app/Domain/Interfaces/CrudHookHandlerInterface.php). El runner invoca por nombre si existen:

- beforeStore / afterStore
- beforeUpdate / afterUpdate
- beforeDelete / afterDelete

Ejemplo JSON:

```json
"hooks": {
  "handler": "mi_recurso_post_guardado"
}
```

Ejemplo de registro (ilustrativo):

```php
'mi_recurso_post_guardado' => \App\Application\Crud\Handlers\MiHandler::class,
```

---

## 15. VALIDACIONES OBLIGATORIAS

Antes de ejecutar:

- JSON válido
- tabla existente
- columnas existentes
- tabla permitida
- permisos válidos
- campos no protegidos

Campos protegidos:

- id
- created_at
- created_by
- updated_at
- updated_by
- deleted
- deleted_at
- deleted_by

---

## 16. ARQUITECTURA DE CLASES

Ubicación real (bajo `app/`):

```
app/Application/Services/
    CrudResourceService
    CrudConfigLoader
    CrudConfigValidator
    CrudFormBuilder
    CrudTableBuilder
    CrudDataService
    CrudHookRunner
    CrudHandlerRegistry
    CrudFieldValidationService

app/Domain/Entities/
    CrudResourceDefinition
    CrudFieldDefinition

app/Domain/Interfaces/
    CrudHookHandlerInterface

app/Infrastructure/Repositories/
    GenericCrudRepository

app/Presentation/Controllers/Admin/
    CrudController
```

Handlers: `app/Application/Crud/Handlers/` (p. ej. extender `AbstractCrudHookHandler`) y registro explícito en `config/crud_handlers.php`.

---

## 17. VISTAS

```
app/Presentation/Views/admin/crud/
    index.php
    form.php
    show.php
```

Partials: `app/Presentation/Views/partials/crud/` (vacío de listado, alerta si se omiten agregaciones, etc.). Estilos específicos: `public/assets/css/crud-engine.css`.

---

## 18. PRINCIPIOS DE IMPLEMENTACIÓN

- no hardcodear campos
- no duplicar lógica
- usar configuración JSON
- usar prepared statements
- respetar arquitectura Onion
- mantener código desacoplado

---

## 19. DEFINICIÓN FINAL

El CRUD Engine es un módulo del core que permite generar interfaces CRUD funcionales para tablas existentes mediante configuración JSON, proporcionando formularios, tablas, acciones estándar, seguridad, auditoría y extensibilidad mediante handlers.
