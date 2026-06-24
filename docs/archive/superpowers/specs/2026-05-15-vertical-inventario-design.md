# Vertical Inventario — Design Spec

**Fecha:** 2026-05-15
**Estado:** Aprobado por usuario (todas las secciones)
**Objetivo doble:**
1. Construir el primer vertical de negocio real sobre Lebytek Framework.
2. Capturar fricción de desarrollo para alimentar el spec del CLI de scaffolding.

---

## 1. Contexto y motivación

Lebytek Framework está en etapa temprana. El esqueleto (Onion 5 capas, RBAC, CRUD Engine, Dashboard) ya está, pero NO ha sido validado con un vertical real. El checklist actual para añadir un vertical tiene 9 pasos manuales (`docs/modules/uso-de-modulo-dominio.md`), lo que sugiere alta fricción.

Construir un vertical real con disciplina de captura de fricción produce dos outputs valiosos:
- Un módulo funcional de inventario para uso interno.
- Un friction log que será el INPUT directo del siguiente spec: el CLI de scaffolding.

**Scope:** Framework de uso INTERNO del equipo. No se busca compatibilidad pública, no hay usuarios terceros.

---

## 2. Scope del vertical

### Incluido (MVP mínimo)
- **Categorías:** clasificación simple de productos (CRUD vía CRUD Engine).
- **Productos:** catálogo con código único, relación con categoría (CRUD vía CRUD Engine).
- **Movimientos:** ledger inmutable de entradas/salidas con validación de stock no negativo (hand-crafted).
- **Stock actual:** vista de consulta calculada on-demand por agregación SQL.
- **RBAC granular:** permisos por entidad y por acción.
- **Tests** del UseCase crítico (`RegistrarMovimientoUseCase`).
- **Friction log** mantenido durante todo el build.

### Excluido (no MVP)
- Bodegas múltiples / transferencias entre bodegas.
- Lotes, series, fechas de caducidad.
- Reservas de stock (movimientos pendientes).
- Workflow borrador → asentado para movimientos.
- Edición/borrado de movimientos (el ledger es inmutable; corrección = movimiento contrario, sin UI).
- Alertas de stock bajo, reportes complejos.
- Tests de Categorías/Productos (CRUD genérico, bajo ROI).
- Tests E2E.
- CI/CD setup.

---

## 3. Schema

Prefijo: `dom_inv_*` (sigue convención `dom_*` para módulos de dominio).

### `dom_inv_categorias`
```sql
CREATE TABLE dom_inv_categorias (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre      VARCHAR(120) NOT NULL,
  slug        VARCHAR(140) NOT NULL UNIQUE,
  descripcion TEXT NULL,
  activa      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categorias_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `dom_inv_productos`
```sql
CREATE TABLE dom_inv_productos (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  categoria_id  BIGINT UNSIGNED NOT NULL,
  codigo        VARCHAR(60) NOT NULL UNIQUE,
  nombre        VARCHAR(180) NOT NULL,
  descripcion   TEXT NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (categoria_id) REFERENCES dom_inv_categorias(id) ON DELETE RESTRICT,
  INDEX idx_productos_categoria (categoria_id),
  INDEX idx_productos_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `dom_inv_movimientos`
```sql
CREATE TABLE dom_inv_movimientos (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  producto_id BIGINT UNSIGNED NOT NULL,
  tipo        ENUM('entrada','salida') NOT NULL,
  cantidad    INT UNSIGNED NOT NULL,
  motivo      VARCHAR(255) NULL,
  usuario_id  BIGINT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES dom_inv_productos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_mov_usuario  FOREIGN KEY (usuario_id)  REFERENCES auth_usuarios(id)     ON DELETE RESTRICT,
  CONSTRAINT chk_mov_cantidad CHECK (cantidad > 0),
  INDEX idx_mov_producto (producto_id),
  INDEX idx_mov_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Decisión clave: stock NO se almacena
El stock actual NO es columna de `dom_inv_productos`. Se calcula on-demand:

```sql
SELECT COALESCE(SUM(CASE WHEN tipo='entrada' THEN cantidad ELSE -cantidad END), 0) AS stock
FROM dom_inv_movimientos WHERE producto_id = ?
```

**Razones:**
- Elimina race conditions de doble update (la fuente de verdad es una sola).
- Auditable por naturaleza (el ledger es la historia).
- Si performance se vuelve problema, se cachea — no es problema del MVP.
- Ejercita queries de agregación en Repository (estrés correcto al framework).

### Schema delivery
Como el framework NO tiene sistema de migraciones, el schema se añade a `database/schema/schema.sql` siguiendo el patrón existente. **Esta limitación va al friction log** como un dolor de alta severidad.

---

## 4. Distribución por capas

### Domain (`app/Domain/`)
```
Entities/Inventario/
  Categoria.php
  Producto.php
  Movimiento.php
Interfaces/Inventario/
  CategoriaRepositoryInterface.php
  ProductoRepositoryInterface.php
  MovimientoRepositoryInterface.php
Exceptions/Inventario/
  StockInsuficienteException.php   # extiende ValidationException
```

Sin ValueObjects nuevos (reutiliza `Slug` existente para categorías).

### Application (`app/Application/`)
```
DTO/Inventario/
  CrearCategoriaDTO.php, ActualizarCategoriaDTO.php
  CrearProductoDTO.php,  ActualizarProductoDTO.php
  RegistrarMovimientoDTO.php
Validators/Inventario/
  CrearProductoValidator.php          # código único, categoría existe y activa
  RegistrarMovimientoValidator.php    # cantidad>0, producto activo, usuario válido
UseCases/Inventario/
  RegistrarMovimientoUseCase.php      # único hand-crafted
  ConsultarStockUseCase.php           # wrapper trivial sobre repo
```

**Hipótesis a validar:** Categorías y Productos NO necesitan UseCases — el CRUD Engine los maneja completos. Si descubrimos que sí los necesitan, eso es dato crítico al friction log.

### Infrastructure (`app/Infrastructure/`)
```
Repositories/Inventario/
  CategoriaRepository.php    # PDO, implementa CategoriaRepositoryInterface
  ProductoRepository.php     # PDO
  MovimientoRepository.php   # PDO + método calcularStock(int $productoId): int
```

### Presentation (`app/Presentation/`)
```
Controllers/Admin/Inventario/
  MovimientosController.php  # index (listar), crear (form), registrar (POST)
  StockController.php        # index (tabla productos + stock calculado)

Views/admin/inventario/
  movimientos/index.php
  movimientos/registrar.php
  stock/index.php
```

Categorías y Productos NO tienen Controllers propios — los atiende `CrudController` genérico vía:
```
config/cruds/inventario_categorias.json
config/cruds/inventario_productos.json
```

### Config & Routes
```
config/cruds/inventario_categorias.json   # CRUD Engine config
config/cruds/inventario_productos.json
config/vertical.php                        # añadir 'inventario' => true
config/container.php                       # bindings nuevos (3 repos + 2 usecases)
routes/web.php                             # grupo /admin/inventario
```

---

## 5. Reglas de negocio de Movimientos

### `RegistrarMovimientoUseCase::execute(RegistrarMovimientoDTO $dto): int`

```
1. VALIDAR (RegistrarMovimientoValidator)
   - producto_id existe y está activo
   - tipo ∈ {entrada, salida}
   - cantidad > 0 (entero positivo)
   - usuario_id existe (inyectado por controller desde sesión)
   - motivo: opcional, max 255 chars

2. INICIAR TRANSACCIÓN PDO

3. SI tipo == 'salida':
     stock_actual = MovimientoRepository::calcularStock(producto_id)
     SI (stock_actual - cantidad) < 0:
         ROLLBACK
         throw StockInsuficienteException(producto_id, stock_actual, cantidad)

4. INSERTAR movimiento

5. CALCULAR stock_resultante = calcularStock(producto_id)

6. COMMIT

7. RETORNAR stock_resultante  // controller lo muestra al usuario
```

### Reglas explícitas
- **Inmutable:** un movimiento NO se edita ni se borra. Corrección = movimiento contrario con `motivo: "corrección de mov #N"`. UI de corrección NO está en MVP.
- **Stock no negativo:** validado al registrar salidas. Excepción tipada (`StockInsuficienteException`) que el controller convierte en error de form.
- **Race conditions:** transacción + cálculo dentro de la misma transacción es suficiente para MVP en MySQL con isolation level por default (REPEATABLE READ). NO añadimos locking explícito. Si surge contención real → friction log.
- **Productos inactivos:** no se permiten movimientos.

### `ConsultarStockUseCase`
Wrapper trivial sobre `MovimientoRepository::calcularStock`. Existe como UseCase para mantener Presentation desacoplado de Infrastructure. Reutilizable desde `StockController` y desde futuros widgets de dashboard.

---

## 6. RBAC, Menú y Vertical config

### Permisos nuevos (seed SQL)
```sql
INSERT INTO auth_permisos (slug, descripcion, modulo) VALUES
  ('inventario.ver',                   'Acceder al módulo Inventario',           'inventario'),
  ('inventario.categorias.gestionar',  'Crear/editar/eliminar categorías',       'inventario'),
  ('inventario.productos.gestionar',   'Crear/editar/eliminar productos',        'inventario'),
  ('inventario.movimientos.ver',       'Consultar historial de movimientos',     'inventario'),
  ('inventario.movimientos.registrar', 'Registrar movimientos de entrada/salida','inventario'),
  ('inventario.stock.ver',             'Consultar stock actual de productos',    'inventario');
```

Asignación inicial: los 6 permisos al rol `admin`.

### Entradas de menú (`core_menu_items`)
```
Inventario (padre)
  slug: inventario, permiso: inventario.ver, icon: bx-package
├── Categorías        (slug: inventario.categorias,        permiso: inventario.categorias.gestionar)
├── Productos         (slug: inventario.productos,         permiso: inventario.productos.gestionar)
├── Movimientos       (slug: inventario.movimientos,       permiso: inventario.movimientos.ver)
├── Registrar mov.    (slug: inventario.movimientos.registrar, permiso: inventario.movimientos.registrar)
└── Stock actual      (slug: inventario.stock,             permiso: inventario.stock.ver)
```

### Rutas
```php
// routes/web.php
$router->group(['prefix' => '/admin/inventario', 'middleware' => ['auth', 'rbac:inventario.ver']], function($r) {
    // Categorías y Productos: CrudController genérico vía rutas auto-resueltas

    // Movimientos (custom)
    $r->get ('/movimientos',           [MovimientosController::class, 'index'],     ['rbac:inventario.movimientos.ver']);
    $r->get ('/movimientos/registrar', [MovimientosController::class, 'crear'],     ['rbac:inventario.movimientos.registrar']);
    $r->post('/movimientos/registrar', [MovimientosController::class, 'registrar'], ['rbac:inventario.movimientos.registrar']);

    // Stock (custom)
    $r->get ('/stock',                 [StockController::class, 'index'],           ['rbac:inventario.stock.ver']);
});
```

### Toggle del vertical
```php
// config/vertical.php
'modules' => [
    'dashboard'      => true,
    'administracion' => true,
    'inventario'     => true,   // ← nuevo
],
```

### Container bindings
```php
// config/container.php — añadir:
$container->singleton(CategoriaRepositoryInterface::class,  fn() => new CategoriaRepository());
$container->singleton(ProductoRepositoryInterface::class,   fn() => new ProductoRepository());
$container->singleton(MovimientoRepositoryInterface::class, fn() => new MovimientoRepository());

$container->singleton(RegistrarMovimientoUseCase::class, fn(Container $c) => new RegistrarMovimientoUseCase(
    $c->get(MovimientoRepositoryInterface::class),
    $c->get(ProductoRepositoryInterface::class),
    new RegistrarMovimientoValidator(),
));

$container->singleton(ConsultarStockUseCase::class, fn(Container $c) => new ConsultarStockUseCase(
    $c->get(MovimientoRepositoryInterface::class)
));
```

**Hipótesis a validar:** este patrón repetitivo es precisamente lo que debe automatizar el CLI. Friction log lo registra.

---

## 7. Estrategia de Tests

### Bootstrap del test suite
El framework declara `./vendor/bin/phpunit` en `CLAUDE.md` pero NO tiene `phpunit.xml` ni carpeta `tests/`. Como parte de este vertical, bootstrappeamos el mínimo necesario:

```
tests/
├── bootstrap.php                                           # autoloader + env de test
├── Unit/
│   └── Application/Inventario/
│       ├── RegistrarMovimientoUseCaseTest.php
│       └── RegistrarMovimientoValidatorTest.php
└── Integration/
    └── Infrastructure/Inventario/
        └── MovimientoRepositoryStockTest.php               # toca DB real

phpunit.xml                                                 # config mínima
.env.testing                                                # DB de tests separada
```

### DB para tests de integración
**SQLite in-memory.** Más rápido, aislado, no requiere setup. Si encontramos queries MySQL-específicos en el repo, las divergencias van al friction log (señal de que el repo necesita abstracción de dialect o que tests deben usar MySQL real).

Si SQLite resulta no-viable por incompatibilidades graves, fallback a MySQL real con `BEGIN/ROLLBACK` por test.

### Tests que SÍ se escriben

**`RegistrarMovimientoUseCaseTest`** (3-5 tests):
1. Entrada exitosa → stock aumenta por la cantidad.
2. Salida exitosa con stock suficiente → stock disminuye.
3. Salida con stock insuficiente → lanza `StockInsuficienteException`, NO inserta movimiento.
4. Movimiento sobre producto inactivo → falla en validador.
5. Cantidad ≤ 0 → falla en validador.

**`MovimientoRepositoryStockTest`** (2 tests):
1. Producto sin movimientos → `calcularStock` retorna 0.
2. Mezcla de entradas y salidas → retorna suma neta correcta.

**`RegistrarMovimientoValidatorTest`** (2-3 tests de casos límite).

### Tests que NO se escriben
- CRUD Engine para Categorías/Productos (es config; testear aquí sería testear el framework, no este vertical — y el framework hoy no tiene tests, ese es problema aparte).
- Controllers de Movimientos (overkill para MVP, valdría integration test pero no ahora).
- Views.
- Repositorios de Categoría/Producto.
- Tests E2E.

---

## 8. Mecánica del Friction Log

### Archivo
`docs/superpowers/friction-log-inventario.md`

### Estructura de entrada
```markdown
### YYYY-MM-DD HH:MM — [Título corto del dolor]
- **Entidad:** Categorías | Productos | Movimientos | Cross-cutting
- **Capa:** Domain | Application | Infrastructure | Presentation | Config | Routes | Schema | Tests
- **Tipo:** Repetitivo | Olvidable | Boilerplate | Acoplamiento | Documentación faltante | Bug del framework
- **Qué pasó:** [1-3 frases concretas]
- **Archivos tocados:** [paths exactos]
- **Propuesta de automatización:** [qué debería hacer el CLI]
- **Severidad:** Baja | Media | Alta
```

### Cuándo se actualiza (durante el build, no al final)
- Edito el mismo tipo de archivo por segunda vez → boilerplate detectado.
- Tengo que abrir 3+ archivos para añadir UN concepto → acoplamiento.
- Olvido un paso del checklist de 9 pasos → memoria humana falla, CLI debe automatizar.
- Tengo que mirar docs/código existente para recordar cómo se hace → descubribilidad pobre.
- Una convención no está documentada pero se asume → riesgo de fragmentación.

### Resumen ejecutivo (al final del vertical)
Después de las 3 entidades:
1. **Top 5 dolores** ordenados por (frecuencia × severidad).
2. **Mapa de áreas del framework cubiertas/no cubiertas.**
3. **Boilerplate cuantificado:** archivos creados, líneas de config repetitiva, líneas de bindings.
4. **Recomendaciones priorizadas para el CLI:** comandos sugeridos y archivos a generar por cada uno.

Ese resumen es el INPUT directo del próximo spec (CLI de scaffolding).

### Responsable del log
Claude (yo). Se actualiza antes de cerrar cada cambio. Usuario revisa cuando quiera; no es su responsabilidad mantenerlo.

---

## 9. Orden de ejecución

Enfoque A — Lineal disciplinado:

1. **Setup** — schema, vertical config, RBAC seeds, menú, container bindings vacíos.
2. **Categorías** (CRUD Engine puro) — entidad más simple, ejercita CRUD Engine en su caso base.
3. **Productos** (CRUD Engine con relación) — añade FK, validación de unicidad de código.
4. **Movimientos** (hand-crafted) — UseCase, Validator, Controller, Vistas custom, tests.
5. **Stock actual** (vista de consulta) — StockController + view, reutiliza ConsultarStockUseCase.
6. **Friction log: resumen ejecutivo** — sintetizar aprendizajes para alimentar spec de CLI.

Cada paso es un checkpoint válido para commitear y/o paralelizar con agentes en Cursor.

---

## 10. Definition of Done

El vertical está listo cuando:
- [ ] Schema aplicado en `database/schema/schema.sql`, tablas creadas en DB.
- [ ] RBAC seeds aplicados, 6 permisos existen en `auth_permisos`.
- [ ] Menú visible en sidebar para usuario admin con los 5 ítems hijos.
- [ ] CRUD Engine funcional para Categorías (crear, listar, editar, eliminar).
- [ ] CRUD Engine funcional para Productos (crear, listar, editar, eliminar; validación de código único; selector de categoría).
- [ ] Registro de movimientos funciona end-to-end (form → controller → usecase → DB).
- [ ] Validación de stock no negativo bloquea salidas inválidas con mensaje claro.
- [ ] Listado de movimientos muestra historial filtrable por producto.
- [ ] Vista de stock actual muestra todos los productos con su stock calculado.
- [ ] Tests de `RegistrarMovimientoUseCase` pasan.
- [ ] Test de `calcularStock` pasa.
- [ ] `phpunit.xml` configurado, `./vendor/bin/phpunit` ejecuta sin error.
- [ ] Friction log tiene al menos 10 entradas y un resumen ejecutivo.
- [ ] Toggle `'inventario' => false` en `config/vertical.php` oculta el menú correctamente (verifica desacoplamiento).

---

## 11. Riesgos conocidos

| Riesgo | Mitigación |
|---|---|
| CRUD Engine no soporta el selector de categoría en form de Producto | Documentar en friction log; añadir UseCase de Producto si necesario |
| Schema sin sistema de migraciones complica rollback | Aceptado para MVP; va al friction log como dolor de alta severidad |
| SQLite vs MySQL: dialectos divergentes en agregación | Si ocurre, switch a MySQL real para tests de integración; documentar |
| El usuario decide pausar y retomar en sesión nueva | Spec + plan deben ser auto-suficientes para que cualquier agente recoja el trabajo |
| Bindings de container crecen mucho con el vertical | Esperado y deseado: es input del CLI |

---

## 12. No-goals explícitos

Este spec NO cubre:
- El CLI de scaffolding (es el siguiente spec, alimentado por el friction log de este).
- Sistema de migraciones (decisión separada, posiblemente trigger del CLI).
- Reorganización del `config/container.php` actual (no tocamos los bindings existentes).
- Mejoras al CRUD Engine descubiertas durante el build (se documentan en friction log, se atacan después).
- Multi-tenant, audit trail extendido, soft-deletes.
