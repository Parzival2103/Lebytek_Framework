# Plan de implementación — Handler de uploads compartido, avatares y perfil

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Infraestructura de archivos reusable (`FileUploadService` + ledger `core_archivos`), avatares de usuario con historial sobre ella, vistas de perfil propio y gestión de avatar en la edición admin, y migración del CRUD Engine al handler compartido.

**Architecture:** Se sigue la arquitectura MVC+Onion del framework: tabla `core_archivos` + entidad `Archivo` + interfaz de repositorio en Domain; `FileUploadService`/`ImageProcessor`/DTOs en Application; `ArchivoRepository` PDO en Infrastructure; controllers/vistas/partials en Presentation. El CRUD Engine delega su `handleUpload` en el servicio compartido sin cambiar su contrato de salida.

**Tech Stack:** PHP 8.1+, PDO/MySQL, GD (opcional, con degradación), Bootstrap 5, JS vanilla (`fetch` + `ConfirmModal` global), harness de tests propio (`php tests/run.php`, estilo microtest).

**Spec de referencia:** `docs/superpowers/specs/2026-06-11-uploads-avatares-perfil-design.md`

---

## Convenciones de este plan

- Es un plan **descriptivo**: indica qué hace cada pieza y con qué contrato; no dicta el cuerpo del código. Los bloques de código incluidos son **reglas/contratos** (SQL, rutas, formas JSON, firmas de API) que deben respetarse literalmente.
- Tests con el harness existente: archivos `*Test.php` con funciones `test()/assert_*` de `tests/lib/microtest.php`, ejecutados con `php tests/run.php <filtro>`. No usan BD: las semánticas de persistencia se prueban con repos falsos en `tests/fixtures/` (mismo patrón que `tests/fixtures/constraint_repos.php`).
- Mensajes de error de validación de uploads: **idénticos** a los actuales (los emite `UploadValidator`, que no se toca, y los dos mensajes de `CrudDataService::handleUpload` sobre directorio/guardado se conservan textualmente en el servicio nuevo).
- Commit al final de cada tarea (mensajes sugeridos en cada una).
- Verificación sintáctica de cada archivo PHP nuevo/modificado: `php -l <archivo>`.

---

## Fase A — Infraestructura de archivos compartida

### Task 1: Tabla `core_archivos` (schema + migración incremental)

**Files:**
- Modify: `database/schema/schema.sql` (agregar la tabla junto a las demás `core_*`)
- Create: `database/migrations_legacy/incrementales-2026-06/20260611120000_core_archivos.sql`

- [ ] **Step 1:** Agregar a `database/schema/schema.sql` la definición exacta de la tabla (regla, va literal en ambos archivos):

```sql
CREATE TABLE IF NOT EXISTS `core_archivos` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entidad_tipo`   VARCHAR(80)      NOT NULL,
  `entidad_id`     INT UNSIGNED     DEFAULT NULL,
  `coleccion`      VARCHAR(60)      NOT NULL DEFAULT 'default',
  `ruta`           VARCHAR(500)     NOT NULL,
  `thumbnail_ruta` VARCHAR(500)     DEFAULT NULL,
  `nombre_original` VARCHAR(255)    DEFAULT NULL,
  `mime`           VARCHAR(120)     DEFAULT NULL,
  `extension`      VARCHAR(20)      DEFAULT NULL,
  `tamano_bytes`   BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `disco`          VARCHAR(20)      NOT NULL DEFAULT 'public',
  `es_actual`      TINYINT(1)       NOT NULL DEFAULT 0,
  `creado_por`     INT UNSIGNED     DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_archivos_entidad` (`entidad_tipo`, `entidad_id`, `coleccion`),
  INDEX `idx_archivos_actual`  (`entidad_tipo`, `entidad_id`, `coleccion`, `es_actual`),
  INDEX `idx_archivos_deleted` (`deleted_at`),
  CONSTRAINT `fk_archivos_creado_por`
      FOREIGN KEY (`creado_por`) REFERENCES `auth_usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2:** Crear la migración incremental `20260611120000_core_archivos.sql` con el mismo `CREATE TABLE IF NOT EXISTS`, siguiendo el formato de cabecera/comentarios de los archivos hermanos en `database/migrations_legacy/incrementales-2026-06/` (p. ej. `20260607120000_crud_engine_demo_showcase.sql`).
- [ ] **Step 3:** Aplicar la migración en la BD local y verificar con `SHOW CREATE TABLE core_archivos` que índices y FK quedaron creados.
- [ ] **Step 4:** Commit: `feat(archivos): tabla core_archivos (ledger central de uploads)`

### Task 2: Entidad `Archivo` e interfaz `ArchivoRepositoryInterface` (Domain)

**Files:**
- Create: `app/Domain/Entities/Archivo.php`
- Create: `app/Domain/Interfaces/ArchivoRepositoryInterface.php`
- Test: `tests/Archivos/ArchivoEntityTest.php`

- [ ] **Step 1:** Escribir el test (estilo microtest) que cubra: construcción de `Archivo` desde fila con `Archivo::desdeFila(array)`; getters de todos los campos; `marcarComoActual()` devuelve un **clon** con `esActual=true` sin mutar el original; `marcarBorrado()` devuelve un clon con `deletedAt` poblado; `toArray()` (si se incluye) refleja los campos. Ejecutar `php tests/run.php Archivos` y verificar que **falla** (clase inexistente).
- [ ] **Step 2:** Implementar `Archivo`: entidad pura inmutable (sin SQL/HTTP), mismo estilo que `app/Domain/Entities/Usuario.php` (constructor con promoted properties + getters con nombre del campo). Campos = columnas de `core_archivos` (camelCase: `entidadTipo`, `entidadId`, `coleccion`, `ruta`, `thumbnailRuta`, `nombreOriginal`, `mime`, `extension`, `tamanoBytes`, `disco`, `esActual`, `creadoPor`, `createdAt`, `deletedAt`, `id`). Factory estático `desdeFila(array $row): self`.
- [ ] **Step 3:** Implementar `ArchivoRepositoryInterface` con exactamente estos métodos (regla de contrato):
  - `guardar(Archivo $archivo): int` — inserta y devuelve id.
  - `buscarPorId(int $id): ?Archivo`
  - `listarPorEntidad(string $tipo, int $id, string $coleccion): array` — solo no borrados (`deleted_at IS NULL`), más reciente primero.
  - `buscarActual(string $tipo, int $id, string $coleccion): ?Archivo` — el de `es_actual=1` no borrado.
  - `marcarActual(int $archivoId, string $tipo, int $entidadId, string $coleccion): void` — transaccional: pone `es_actual=0` a toda la colección y `=1` al elegido.
  - `softDelete(int $archivoId): void` — setea `deleted_at=NOW()` y `es_actual=0`.
- [ ] **Step 4:** `php tests/run.php Archivos` → PASS. `php -l` en ambos archivos.
- [ ] **Step 5:** Commit: `feat(archivos): entidad Archivo e interfaz de repositorio`

### Task 3: Repo falso de tests + `ArchivoRepository` (Infrastructure)

**Files:**
- Create: `tests/fixtures/archivo_repos.php` (FakeArchivoRepository en memoria)
- Create: `app/Infrastructure/Repositories/ArchivoRepository.php`
- Test: `tests/Archivos/ArchivoRepositoryContractTest.php`

- [ ] **Step 1:** Crear `FakeArchivoRepository` (implementa `ArchivoRepositoryInterface`, array en memoria, ids autoincrementales) siguiendo el patrón de `tests/fixtures/constraint_repos.php`. Debe replicar fielmente las semánticas del contrato (es la pieza que usarán todos los tests de use cases).
- [ ] **Step 2:** Escribir `ArchivoRepositoryContractTest` contra el fake: tras `guardar` x3 y `marcarActual(id2,…)`, **exactamente uno** tiene `esActual=true` y es id2; `softDelete(id2)` lo excluye de `listarPorEntidad` y `buscarActual` devuelve null si era el actual; `listarPorEntidad` no devuelve borrados; `buscarPorId` devuelve también borrados (lo necesita la validación de pertenencia). Ejecutar y verificar FAIL→implementar fake→PASS.
- [ ] **Step 3:** Implementar `ArchivoRepository` real sobre `BaseRepository` (`protected string $table = 'core_archivos'`), con prepared statements y usando `beginTransaction()/commit()/rollback()` del base en `marcarActual`. Mismo orden/semántica que el contrato del fake. (No hay test de integración con BD: se verifica con `php -l` y el smoke manual de la Fase F.)
- [ ] **Step 4:** `php -l app/Infrastructure/Repositories/ArchivoRepository.php` → OK.
- [ ] **Step 5:** Commit: `feat(archivos): ArchivoRepository PDO + fake de tests con contrato`

### Task 4: DTOs `FileUploadConfig`, `ImageOptions`, `ThumbnailOptions`

**Files:**
- Create: `app/Application/DTO/Files/FileUploadConfig.php`
- Create: `app/Application/DTO/Files/ImageOptions.php`
- Create: `app/Application/DTO/Files/ThumbnailOptions.php` (reservado fase 2: solo propiedades, nadie lo consume aún)
- Test: `tests/Archivos/FileUploadConfigTest.php`

- [ ] **Step 1:** Escribir el test: construcción de `FileUploadConfig` con todos los argumentos nombrados y lectura de cada propiedad; defaults (`coleccion='default'`, `disco='public'`, `imagen=null`, `esActual=false`, `creadoPor=null`, `entidadId=null`, `allowedExtensions=null`); construcción de `ImageOptions` y sus propiedades. Verificar FAIL.
- [ ] **Step 2:** Implementar los tres DTOs como clases `final` `readonly` (inmutables, solo constructor con promoted properties). Contrato de campos (regla, del spec §5):

```text
FileUploadConfig(
    entidadTipo: string,            // 'usuario' | 'crud:<key>' | ...
    entidadId: ?int,                // nullable (decisión 9)
    coleccion: string = 'default',
    disco: string = 'public',       // 'public' | 'private'
    directorio: string,             // relativo a public/ (p.ej. 'uploads/avatars')
    allowedExtensions: ?array = null,
    maxBytes: int,
    imagen: ?ImageOptions = null,
    esActual: bool = false,
    creadoPor: ?int = null
)
ImageOptions(maxWidth: int, maxHeight: int, calidad: int, thumbnail: ?ThumbnailOptions = null)
ThumbnailOptions(width: int, height: int)   // definido, sin consumidor (fase 2)
```

- [ ] **Step 3:** `php tests/run.php Archivos` → PASS.
- [ ] **Step 4:** Commit: `feat(archivos): DTOs FileUploadConfig/ImageOptions (ThumbnailOptions reservado)`

### Task 5: `ImageProcessor` (GD con degradación elegante)

**Files:**
- Create: `app/Application/Services/ImageProcessor.php`
- Test: `tests/Archivos/ImageProcessorTest.php`

- [ ] **Step 1:** Escribir el test: si `!extension_loaded('gd')`, el test se marca PASS con aviso (skip suave, no hay skip nativo en microtest). Con GD: generar en un dir temporal un PNG de p. ej. 200×100 (con las propias funciones GD), llamar a `redimensionar` con `ImageOptions(maxWidth:50, maxHeight:50, calidad:82)` y verificar con `getimagesize` que el resultado conserva proporción (50×25) y sigue siendo PNG; un archivo con extensión no-imagen (p. ej. `.txt`) pasa sin modificarse; una imagen ya menor al límite no se agranda. Verificar FAIL.
- [ ] **Step 2:** Implementar `ImageProcessor` con el contrato `redimensionar(string $rutaAbsoluta, ImageOptions $opts): void`:
  - Solo procesa JPG/PNG/WEBP (decide por MIME real del archivo); otros formatos: return sin tocar.
  - Si GD no está disponible: return sin tocar y deja aviso en log (canal de log existente del framework, mismo mecanismo que use Bootstrap/Logging en Infrastructure).
  - Redimensiona manteniendo proporción a caja `maxWidth×maxHeight` (nunca agranda) y recomprime con `calidad` (en PNG mapear calidad a nivel de compresión). Preserva transparencia de PNG/WEBP.
  - `thumbnail` de `ImageOptions` se ignora en esta fase (fase 2).
- [ ] **Step 3:** `php tests/run.php Archivos` → PASS.
- [ ] **Step 4:** Commit: `feat(archivos): ImageProcessor (resize/compress GD, degradación sin GD)`

### Task 6: `FileUploadService` (handler compartido)

**Files:**
- Create: `app/Application/Services/FileUploadService.php`
- Test: `tests/Archivos/FileUploadServiceTest.php`

- [ ] **Step 1:** Escribir el test usando archivos reales en un dir temporal como `tmp_name` (estructura `$_FILES` simulada) y el `FakeArchivoRepository`:
  - Caso feliz: valida, mueve el archivo al destino (`PUBLIC_PATH` apuntado a un dir temporal o `directorio` apuntando a subcarpeta temporal), inserta fila en el ledger con todos los metadatos (`entidadTipo`, `coleccion`, `mime`, `extension`, `tamano_bytes`, `creadoPor`), devuelve `Archivo` cuyo `ruta()` empieza con `/` + directorio.
  - Con `esActual:true`: el archivo previo de la colección queda `esActual=false` y el nuevo `true`.
  - Extensión no permitida → `ValidationException` con el mensaje actual (`'Extensión de archivo no permitida para …'`).
  - Archivo sobre `maxBytes` → `ValidationException` con el mensaje actual de tamaño.
  - Nombre original con caracteres raros → el nombre final cumple patrón `safeName_YmdHis_hex.ext`.
  Verificar FAIL.
- [ ] **Step 2:** Implementar `FileUploadService` con dependencias por constructor (`UploadValidator` se construye internamente con `maxBytes` del config de cada llamada, `ImageProcessor` y `ArchivoRepositoryInterface` inyectados) y la firma única (regla):

```php
FileUploadService::handle(array $file, FileUploadConfig $cfg): Archivo
```

  Flujo (espejo del actual `CrudDataService::handleUpload`, líneas 623-679, que es la referencia de comportamiento):
  1. Detectar MIME real con `finfo` (mismo guard de disponibilidad).
  2. Validar con `UploadValidator::assertValid` (error PHP, tamaño según `cfg->maxBytes`, extensiones, coherencia MIME). **No se toca `UploadValidator`.**
  3. Generar nombre seguro idéntico al actual (`preg_replace` sobre el filename + `_YmdHis_` + `bin2hex(random_bytes(4))` + extensión).
  4. Resolver dir destino: `PUBLIC_PATH . '/' . trim(directorio,'/')` cuando `disco='public'`; crear con `mkdir 0775` recursivo; conservar el mensaje `'No fue posible crear el directorio de uploads.'`.
  5. Mover: `move_uploaded_file`; si falla y el origen no es un upload real (`!is_uploaded_file`), usar `rename` (camino de tests); conservar el mensaje `'No fue posible guardar el archivo subido.'`.
  6. Si `cfg->imagen !== null`: invocar `ImageProcessor::redimensionar` sobre el archivo ya movido (recalcular `tamano_bytes` después).
  7. Insertar fila en `core_archivos` vía repositorio; si `cfg->esActual`, llamar a `marcarActual` (solo posible cuando `entidadId !== null`; con `entidadId NULL` se inserta con `es_actual=0`).
  8. Devolver el `Archivo` persistido.
  Errores → siempre `ValidationException` (mismos mensajes que hoy).
- [ ] **Step 3:** `php tests/run.php Archivos` → PASS.
- [ ] **Step 4:** Commit: `feat(archivos): FileUploadService, handler de uploads compartido con ledger`

### Task 7: Registro DI de la infraestructura de archivos

**Files:**
- Modify: `config/container.php`
- Modify: `.gitignore`

- [ ] **Step 1:** Registrar como singletons, siguiendo el estilo existente (imports arriba, closures con `Container $c`): `ArchivoRepositoryInterface::class → ArchivoRepository`, `ImageProcessor::class`, `FileUploadService::class` (recibe `ImageProcessor` + `ArchivoRepositoryInterface`).
- [ ] **Step 2:** Agregar `public/uploads/` a `.gitignore` (el directorio se crea en runtime; no se versionan archivos subidos).
- [ ] **Step 3:** `php -l config/container.php` → OK; arrancar `php -S localhost:8000 -t public` y cargar `/login` para confirmar que el contenedor compila.
- [ ] **Step 4:** Commit: `chore(archivos): bindings DI de FileUploadService/ImageProcessor/ArchivoRepository`

---

## Fase B — Migración del CRUD Engine

### Task 8: `CrudDataService::handleUpload` delega en `FileUploadService`

**Files:**
- Modify: `app/Application/Services/CrudDataService.php` (método `handleUpload`, líneas 623-679, y constructor)
- Modify: `config/container.php` (binding de `CrudDataService`)
- Test: `tests/Crud/Upload/CrudUploadLedgerTest.php` (nuevo)
- Regression: `tests/Crud/**` completo

- [ ] **Step 1:** Revisar cómo los tests de contexto (`tests/Crud/Context/*.php`) construyen `CrudDataService`, para conocer qué constructores hay que actualizar al agregar la dependencia.
- [ ] **Step 2:** Escribir `CrudUploadLedgerTest`: con un `FileUploadService` armado sobre `FakeArchivoRepository` y un recurso CRUD de fixture con uploads habilitados, un alta con archivo deja: (a) el payload del campo con la **misma forma de ruta que hoy** (`/uploads-path/archivo.ext`), y (b) una fila en el ledger con `entidadTipo='crud:<key>'`, `coleccion=<nombre del campo>`, `entidadId=NULL` (decisión 9: en creación el id de fila aún no existe), `esActual=false`. Verificar FAIL.
- [ ] **Step 3:** Modificar `CrudDataService`:
  - Constructor: reemplazar el parámetro `UploadValidator` por `FileUploadService` (o sumarlo y eliminar el validator si ya nadie más lo usa dentro de la clase — revisar usos antes de borrar).
  - `handleUpload` conserva sus guards de entrada (uploads habilitados, campo sin archivo → null) y delega el resto en `FileUploadService::handle` con un `FileUploadConfig` construido según la regla del spec §5: `entidadTipo: 'crud:'.key`, `entidadId: id de la fila o NULL en creación`, `coleccion: nombre del campo`, `disco 'public'`, `directorio: uploadsPath()`, `allowedExtensions` del campo, `maxBytes` desde `Config::get('security.max_upload_mb', 10)`, `imagen: null`, `esActual: false`, `creadoPor: id del usuario de sesión si existe`. Devuelve `ruta()` del `Archivo` — el contrato externo (string ruta relativa) **no cambia**.
  - El label usado en mensajes de validación sigue siendo `field->label()` (los mensajes deben quedar idénticos).
- [ ] **Step 4:** Actualizar el binding de `CrudDataService` en `config/container.php` (inyectar `FileUploadService` desde el contenedor) y cualquier construcción directa en tests de contexto.
- [ ] **Step 5:** Correr regresión completa: `php tests/run.php Crud` → todos PASS, y `php tests/run.php` (suite completa) → PASS.
- [ ] **Step 6:** Commit: `refactor(crud): handleUpload delega en FileUploadService (ledger core_archivos)`

---

## Fase C — Avatares: política y casos de uso

### Task 9: `AvatarPolicy` (regla única de autorización)

**Files:**
- Create: `app/Domain/Policies/AvatarPolicy.php`
- Test: `tests/Avatares/AvatarPolicyTest.php`

- [ ] **Step 1:** Escribir el test: la política expone un único método de decisión puro (sin sesión ni estado global; recibe `actorId`, `usuarioObjetivoId` y un booleano/callable «tiene usuarios.gestionar», al estilo de `RbacPolicy` que ya recibe sus datos por constructor). Casos: actor == objetivo → permitido aunque no tenga permiso; actor != objetivo con `usuarios.gestionar` → permitido; actor != objetivo sin permiso → denegado. Verificar FAIL.
- [ ] **Step 2:** Implementar `AvatarPolicy` en Domain (pura, sin dependencias externas — regla del spec §6: `actorId === usuarioId` **o** permiso `usuarios.gestionar`). Quien la consume (controllers) resuelve el booleano del permiso vía `RbacService::puede('usuarios.gestionar')` y el `actorId` vía sesión.
- [ ] **Step 3:** `php tests/run.php Avatares` → PASS.
- [ ] **Step 4:** Commit: `feat(avatares): AvatarPolicy (dueño o usuarios.gestionar)`

### Task 10: Método `actualizarAvatar` en el repositorio de usuarios

**Files:**
- Modify: `app/Domain/Interfaces/UsuarioRepositoryInterface.php`
- Modify: `app/Infrastructure/Repositories/UsuarioRepository.php`
- Modify: fixture de usuarios fake si existe en `tests/fixtures/` (revisar; si no existe, crearlo mínimo para los tests de la Task 11)

- [ ] **Step 1:** Agregar al contrato `actualizarAvatar(int $usuarioId, ?string $ruta): void` — actualiza **solo** la columna `auth_usuarios.avatar` (cache denormalizado; evita reescribir password/roles con el `update()` completo).
- [ ] **Step 2:** Implementarlo en `UsuarioRepository` con un UPDATE acotado a `avatar` + `updated_at`.
- [ ] **Step 3:** `php -l` en ambos archivos; correr la suite completa (`php tests/run.php`) para confirmar que nada que implemente la interfaz quedó roto (buscar otras implementaciones de `UsuarioRepositoryInterface` antes, p. ej. fakes en tests).
- [ ] **Step 4:** Commit: `feat(usuarios): actualizarAvatar en repositorio (cache denormalizado)`

### Task 11: Casos de uso de avatares

**Files:**
- Create: `app/Application/UseCases/Avatares/SubirAvatarUseCase.php`
- Create: `app/Application/UseCases/Avatares/FijarAvatarActualUseCase.php`
- Create: `app/Application/UseCases/Avatares/EliminarAvatarUseCase.php`
- Create: `app/Application/UseCases/Avatares/ListarAvataresUseCase.php`
- Create: `tests/fixtures/avatar_fakes.php` (fake de `UsuarioRepositoryInterface` mínimo si no existe, espía de `FileUploadService` si hace falta)
- Test: `tests/Avatares/AvatarUseCasesTest.php`

Convenciones comunes (reglas del spec §6): constantes compartidas `entidad_tipo='usuario'`, `coleccion='avatar'`, directorio `uploads/avatars`, imagen `ImageOptions(1024, 1024, 82)`. La autorización NO vive aquí (la aplican los controllers con `AvatarPolicy`); los use cases validan **pertenencia e integridad**.

- [ ] **Step 1:** Escribir los tests (con `FakeArchivoRepository` + fakes de usuario/upload):
  - `SubirAvatarUseCase(usuarioId, $file, actorId)`: invoca el handler con config de avatar (`esActual:true`, `creadoPor:actorId`, `entidadId:usuarioId`), y tras subir actualiza `auth_usuarios.avatar` con la ruta nueva; devuelve el `Archivo`.
  - `FijarAvatarActualUseCase(usuarioId, archivoId, actorId)`: si el archivo no existe, no pertenece a `(usuario, usuarioId, 'avatar')` o está borrado → `ValidationException`; si es válido → `marcarActual` + actualiza cache `avatar`.
  - `EliminarAvatarUseCase(usuarioId, archivoId, actorId)`: valida pertenencia igual; `softDelete`; si el borrado **era el actual** → `actualizarAvatar(usuarioId, null)`; si no era el actual, el cache no cambia. No toca el archivo físico.
  - `ListarAvataresUseCase(usuarioId)`: devuelve historial vigente (no borrados, más reciente primero) con el actual marcado.
  Verificar FAIL.
- [ ] **Step 2:** Implementar los cuatro use cases (clases `final`, dependencias por constructor: `FileUploadService`, `ArchivoRepositoryInterface`, `UsuarioRepositoryInterface` según necesite cada uno; mismo estilo que `app/Application/UseCases/Usuarios/*`).
- [ ] **Step 3:** `php tests/run.php Avatares` → PASS.
- [ ] **Step 4:** Commit: `feat(avatares): casos de uso subir/fijar/eliminar/listar con historial`

---

## Fase D — Endpoints y perfil propio

### Task 12: `ActualizarPerfilUseCase` (datos propios, sin roles/activo/password)

**Files:**
- Create: `app/Application/UseCases/Perfil/ActualizarPerfilUseCase.php`
- Test: `tests/Avatares/ActualizarPerfilUseCaseTest.php`

- [ ] **Step 1:** Escribir el test: actualiza solo nombre/apellido/email del propio usuario; valida con las mismas reglas que `CrearUsuarioValidator::validateUpdate` (sin password); email duplicado de otro usuario → `ValidationException`; **no** modifica password, activo, roles ni avatar (verificar contra el fake que el update preserva esos campos). Verificar FAIL.
- [ ] **Step 2:** Implementarlo siguiendo el patrón de `ActualizarUsuarioUseCase` (`app/Application/UseCases/Usuarios/ActualizarUsuarioUseCase.php`) pero sin sincronizar roles y conservando `activo` y `passwordHash` actuales. No reutilizar `ActualizarUsuarioUseCase` directamente: exige `rolIds`/`activo` y resincroniza roles, lo cual es incorrecto para el perfil propio.
- [ ] **Step 3:** `php tests/run.php Avatares` → PASS (suite completa también).
- [ ] **Step 4:** Commit: `feat(perfil): ActualizarPerfilUseCase (nombre/apellido/email propios)`

### Task 13: `PerfilController` + endpoints de avatar en `UsuariosController` + rutas

**Files:**
- Create: `app/Presentation/Controllers/Admin/PerfilController.php`
- Modify: `app/Presentation/Controllers/Admin/UsuariosController.php`
- Modify: `routes/web.php`
- Modify: `config/container.php` (bindings de ambos controllers)

- [ ] **Step 1:** Implementar `PerfilController` (extiende `AdminBaseController`). El **actor y el objetivo** salen siempre de la sesión (`currentUser()['id']`); nunca del request. Acciones:
  - `index` — vista `admin/perfil/index` con datos del usuario (vía `UsuarioRepositoryInterface`) y el historial de avatares.
  - `actualizar` (PUT) — `verifyCsrf` + `ActualizarPerfilUseCase`; al cambiar el email/nombre, refrescar los datos de `auth_user` en sesión para que la topbar refleje el cambio; redirect con flash (patrón de `UsuariosController::actualizar`).
  - `subirAvatar`, `fijarAvatar`, `eliminarAvatar`, `listarAvatares` — endpoints JSON: `verifyCsrf` en los mutadores, delegan en los use cases de la Task 11 con `usuarioId = actorId`, responden con `$this->json(...)`.
  Manejo de errores JSON: `ValidationException` → HTTP 422, no encontrado → 404, todo con la forma de error estándar (abajo).
- [ ] **Step 2:** Ampliar `UsuariosController` con cuatro métodos homólogos (`subirAvatar`, `fijarAvatar`, `eliminarAvatar`, `listarAvatares`) que toman `usuarioId` del parámetro de ruta `{id}` y el actor de sesión. Antes de delegar, aplican `AvatarPolicy` (con `RbacService::puede('usuarios.gestionar')`); denegado → 403 JSON. Reutilizan **los mismos use cases**.
- [ ] **Step 3:** Registrar las rutas en `routes/web.php` (regla del spec §6, dentro del grupo `/admin` con `AuthMiddleware`; las de perfil **sin** RBAC extra — cualquier autenticado; las de usuarios dentro del grupo `/administracion` con `$rbacUsuarios`; CSRF en todos los mutadores):

```text
GET    /admin/perfil                       → PerfilController@index
PUT    /admin/perfil                       → PerfilController@actualizar          [CSRF]
POST   /admin/perfil/avatar                → PerfilController@subirAvatar         [CSRF]
POST   /admin/perfil/avatar/{id}/actual    → PerfilController@fijarAvatar         [CSRF]
DELETE /admin/perfil/avatar/{id}           → PerfilController@eliminarAvatar      [CSRF]
GET    /admin/perfil/avatares              → PerfilController@listarAvatares

POST   /admin/administracion/usuarios/{id}/avatar               → UsuariosController@subirAvatar    [RBAC+CSRF]
POST   /admin/administracion/usuarios/{id}/avatar/{aid}/actual  → UsuariosController@fijarAvatar    [RBAC+CSRF]
DELETE /admin/administracion/usuarios/{id}/avatar/{aid}         → UsuariosController@eliminarAvatar [RBAC+CSRF]
GET    /admin/administracion/usuarios/{id}/avatares             → UsuariosController@listarAvatares [RBAC]
```

- [ ] **Step 4:** Forma JSON estándar de todas las respuestas de avatar (regla del spec §6 — ambos controllers la comparten, idealmente vía un pequeño presenter/método común):

```json
{ "ok": true,
  "actual": { "id": 12, "ruta": "/uploads/avatars/..." },
  "historial": [ { "id": 12, "ruta": "...", "esActual": true } ] }
```
Errores: `{ "ok": false, "error": "mensaje" }` con código HTTP apropiado (403/404/422).

- [ ] **Step 5:** Registrar bindings en `config/container.php`: binding nuevo de `PerfilController` y ampliación del de `UsuariosController` (líneas ~253) con los use cases de avatar, `AvatarPolicy`/`RbacService` y `UsuarioRepositoryInterface`.
- [ ] **Step 6:** `php -l` en todos los modificados; smoke manual: `GET /admin/perfil` responde 200 autenticado y redirige a login sin sesión; `GET /admin/administracion/usuarios/1/avatares` exige `usuarios.gestionar`.
- [ ] **Step 7:** Commit: `feat(perfil): PerfilController y endpoints de avatar propios y admin`

---

## Fase E — UI

### Task 14: Partial `avatar_manager` + `avatar-manager.js`

**Files:**
- Create: `app/Presentation/Views/partials/avatar_manager.php`
- Create: `public/assets/js/avatar-manager.js`

- [ ] **Step 1:** Implementar el partial parametrizado por dos variables que el include define: `usuarioId` objetivo y `avatarBaseUrl` (p. ej. `/admin/perfil/avatar` o `/admin/administracion/usuarios/5/avatar`), más la ruta del avatar actual y el historial inicial (render server-side del estado inicial; JS solo repinta). Estructura (regla de UX del spec §7):
  - **Contenedor principal (izquierda):** sin foto → recuadro vacío con icono Bootstrap Icons + texto "Sin foto de perfil"; con foto → imagen actual + **burbuja X** arriba-derecha.
  - **Contenedor secundario (derecha):** zona "Cambiar o agregar foto" con input file (accept jpg/jpeg/png/webp).
  - **Galería (abajo):** miniaturas del historial no borrado; la actual resaltada.
  - El partial embebe el token CSRF (reutilizar el valor que genera `ViewHelper::csrfField()`) en un `data-*` del contenedor raíz, junto con `data-base-url`, para que el JS no dependa de variables globales.
- [ ] **Step 2:** Implementar `avatar-manager.js` como módulo acotado (IIFE u objeto, mismo estilo que `public/assets/js/calendar.js`/`crud-engine.js`): se inicializa sobre cada `[data-avatar-manager]` presente. Comportamiento (reglas):
  - Todo por `fetch` AJAX, **sin recargas**; CSRF se envía como header `X-CSRF-Token` (soportado por `BaseController::verifyCsrf`, `app/Kernel/BaseClasses/BaseController.php:46`).
  - Para `DELETE` usar el mismo mecanismo de override del resto de la app (POST + `_method`).
  - Subir: POST multipart → repinta principal+galería con la respuesta JSON estándar.
  - X de borrar: `ConfirmModal.show({variant:'danger', ...})` del modal global (`public/assets/js/app.js`); al confirmar → DELETE → la UI vuelve al estado vacío (o al nuevo actual si el backend lo informa).
  - Clic en miniatura → POST `.../{id}/actual` → la elegida pasa a actual (resaltado actualizado desde la respuesta, no desde el estado local).
  - Errores (`ok:false`) → mostrar el mensaje (alert/feedback inline consistente con el resto de la app).
- [ ] **Step 3:** Incluir `avatar-manager.js` en el layout o en las vistas que usan el partial (seguir el mecanismo con que las vistas existentes cargan sus JS específicos, p. ej. cómo calendario incluye `calendar.js`).
- [ ] **Step 4:** Commit: `feat(avatares): partial avatar_manager + avatar-manager.js (AJAX, ConfirmModal)`

### Task 15: Vista `/admin/perfil` + enlace en topbar

**Files:**
- Create: `app/Presentation/Views/admin/perfil/index.php`
- Modify: `app/Presentation/Views/partials/topbar.php` (dropdown de usuario, líneas 54-58)

- [ ] **Step 1:** Implementar la vista de perfil siguiendo el estilo de `admin/usuarios/editar.php` (card Bootstrap, `ViewHelper::csrfField()`, `_method` PUT): formulario nombre/apellido/correo → `PUT /admin/perfil`; el partial `avatar_manager` con los endpoints de perfil; botón "Cambiar contraseña" como enlace a `/auth/recuperar`, **deshabilitado u oculto si el usuario no tiene correo** (regla, decisión 7; el flujo de recuperación es spec aparte — el enlace puede 404 mientras tanto y es aceptado).
- [ ] **Step 2:** Agregar en el dropdown del usuario en `topbar.php` el ítem "Mi perfil" (icono `bi-person`) apuntando a `/admin/perfil`, encima de "Ajustes".
- [ ] **Step 3:** Smoke manual: login → menú usuario → "Mi perfil" → editar nombre y guardar (flash + topbar actualizado); subir avatar, cambiarlo por uno previo desde la galería, borrarlo con la X (confirm estilizado, sin recarga en las tres operaciones).
- [ ] **Step 4:** Commit: `feat(perfil): vista /admin/perfil con avatar manager y enlace en topbar`

### Task 16: Avatar manager en la edición admin de usuarios

**Files:**
- Modify: `app/Presentation/Views/admin/usuarios/editar.php`

- [ ] **Step 1:** Insertar el partial `avatar_manager` en la vista (sección propia, fuera del `<form>` principal para no anidar formularios), parametrizado con `usuarioId` del usuario editado y base URL `/admin/administracion/usuarios/{id}/avatar`. El campo de contraseña inline existente **no se toca** (regla, decisión 8).
- [ ] **Step 2:** Smoke manual como admin: editar otro usuario, subir/cambiar/borrar su avatar sin recargar; verificar que un usuario sin `usuarios.gestionar` recibe 403 en esos endpoints.
- [ ] **Step 3:** Commit: `feat(usuarios): gestión de avatar en edición admin (avatar_manager compartido)`

---

## Fase F — Verificación final

### Task 17: Regresión completa y cierre

- [ ] **Step 1:** Suite completa: `php tests/run.php` → 0 fallos. Adicionalmente `./vendor/bin/phpunit` si la config del proyecto lo contempla (CLAUDE.md lo lista como comando estándar).
- [ ] **Step 2:** `php -l` sobre todos los archivos PHP nuevos (`Archivo`, `ArchivoRepositoryInterface`, `ArchivoRepository`, DTOs, `ImageProcessor`, `FileUploadService`, `AvatarPolicy`, use cases, `PerfilController`, vistas/partials nuevos).
- [ ] **Step 3:** Checklist funcional manual (servidor local + BD con la migración aplicada):
  - CRUD demo con campo file: subir archivo → se guarda igual que antes (misma ruta en la columna) **y** aparece fila en `core_archivos` con `entidad_tipo='crud:...'`.
  - Perfil propio: ciclo completo subir → fijar previa → borrar actual (avatar queda vacío, `auth_usuarios.avatar = NULL`, fila con `deleted_at`).
  - Admin sobre otro usuario: mismo ciclo; sin permiso → 403.
  - Sin GD (si es posible probar): subir avatar guarda el original y deja aviso en log.
- [ ] **Step 4:** Verificar `git status` limpio de archivos no intencionales (no debe entrar nada bajo `public/uploads/`).
- [ ] **Step 5:** Commit final si quedó algo suelto: `chore(archivos): cierre de verificación uploads/avatares/perfil`

---

## Cobertura spec → tareas (self-review)

| Spec | Tarea |
|---|---|
| §4 tabla `core_archivos` + migración | Task 1 |
| §4 entidad `Archivo` + interfaz + repo | Tasks 2, 3 |
| §5 DTOs config | Task 4 |
| §5 `ImageProcessor` (GD, degradación) | Task 5 |
| §5 `FileUploadService` (flujo 1-6, mensajes) | Task 6 |
| §3 registro DI | Tasks 7, 8, 13 |
| §5 migración CRUD + decisión 9 (`entidad_id NULL`) | Task 8 |
| §6 autorización (`AvatarPolicy`) | Task 9 |
| §6 cache `auth_usuarios.avatar` | Tasks 10, 11 |
| §6 cuatro use cases | Task 11 |
| §6 endpoints + JSON estándar | Task 13 |
| §7 partial + JS (AJAX, ConfirmModal, decisión 5: X = soft-delete y vacío) | Task 14 |
| §7 vista perfil + botón contraseña (decisión 7) + topbar | Tasks 12, 13, 15 |
| §7 edición admin (decisión 8: set-password intacto) | Task 16 |
| §8 plan de pruebas + regresión | Tests por tarea + Task 17 |
| Fase 2 (thumbnails reales, depuración) | Fuera de alcance: solo `ThumbnailOptions` definido (Task 4) y `deleted_at`/archivo físico intacto (Task 11) |
