# Spec — Handler de uploads compartido, avatares de usuario y edición de perfil

- **Fecha:** 2026-06-11
- **Estado:** Aprobado (diseño)
- **Specs derivados (fuera de alcance aquí):**
  1. Recuperación de contraseña por correo (`/auth/recuperar`) — *spec separado*.
  2. Límite de intentos de login (throttling) — *spec separado*.

---

## 1. Objetivo y contexto

El módulo de usuarios permite editar usuarios pero no gestionar su foto de perfil, y un usuario no tiene vistas para editar sus propios datos. Además, la lógica de subir/validar archivos vive embebida en `CrudDataService::handleUpload`, sin posibilidad de reutilizarse.

Este spec entrega tres bloques cohesivos:

1. **Infraestructura de archivos compartida** — un handler de uploads reusable por toda la app, con un ledger central (`core_archivos`) que registra cada archivo creado.
2. **Avatares de usuario** con historial (subir, cambiar a una previa, borrar la actual) encima de esa infraestructura.
3. **Vistas de edición**: perfil propio (`/admin/perfil`) y gestión de imagen desde la edición admin de usuarios.

El CRUD Engine se **migra** para usar el handler compartido (eliminando la duplicación), con tests de regresión al final.

### Estado actual relevante (verificado en el código)

- `UploadValidator` (`app/Application/Services/UploadValidator.php`) ya hace validación pura (error PHP, tamaño, extensión, coherencia MIME). **Se reutiliza, no se reescribe.**
- La lógica de mover archivos está en `CrudDataService::handleUpload` (detecta MIME con `finfo`, genera nombre seguro, crea dir, `move_uploaded_file`).
- `Usuario` (`app/Domain/Entities/Usuario.php`) ya tiene campo `avatar` (un solo `VARCHAR(500)`); `auth_usuarios.avatar` existe en el esquema.
- `ConfirmModal` global (`public/assets/js/app.js`) expone `ConfirmModal.show(opts): Promise<bool>` y atributos `data-confirm` — es el confirm estilizado a usar.
- No existe `public/uploads/`, ni infraestructura de correo, ni throttling de login.

---

## 2. Decisiones de diseño (cerradas en brainstorming)

| # | Decisión | Elección |
|---|----------|----------|
| 1 | Alcance del handler | Servicio compartido nuevo **+ migrar CRUD Engine** a él (opción B). Tests de regresión al final. |
| 2 | Almacenamiento del historial | Tabla **genérica polimórfica `core_archivos`** usable por todos los módulos (no tabla específica de avatar). |
| 3 | Compresión/thumbnails | Redimensionar/comprimir la **imagen principal**; API de thumbnails definida pero implementada en **fase 2** (opción C). Requiere GD; sin GD, guarda original. |
| 4 | Perfil propio | Ruta `/admin/perfil`; acceso para **cualquier usuario autenticado** (sin permiso RBAC especial). |
| 5 | Semántica de la X | Borra (soft-delete) el archivo actual y deja el avatar **vacío** (opción A). Cambiar a una previa es trabajo de la galería. |
| 6 | Interacción avatar | **AJAX** sin recargas. |
| 7 | Contraseña en perfil | Botón "Cambiar contraseña" → `/auth/recuperar` (deshabilitado si no hay correo). El flujo de recuperación es spec separado. |
| 8 | Set-password admin | La edición admin de usuarios **mantiene** el set-password directo actual (no cambia). |
| 9 | `entidad_id` en CRUD | Si el id de la fila no existe al subir (creación), se registra `entidad_id = NULL`; reconciliación por ruta queda para el futuro módulo de depuración. |

---

## 3. Arquitectura por capas

**Bloque 1 — Infraestructura de archivos (compartida)**
- `database` — tabla `core_archivos` (schema + migración incremental).
- `Domain/Entities/Archivo.php`
- `Domain/Interfaces/ArchivoRepositoryInterface.php`
- `Infrastructure/Repositories/ArchivoRepository.php`
- `Application/Services/FileUploadService.php` — el handler compartido.
- `Application/Services/ImageProcessor.php` — resize/compress con GD (degradación elegante sin GD).
- DTOs: `Application/DTO/Files/FileUploadConfig.php`, `Application/DTO/Files/ImageOptions.php` (con `ThumbnailOptions` reservado para fase 2).

**Bloque 2 — Avatares de usuario**
- `Application/UseCases/Avatares/{SubirAvatar,FijarAvatarActual,EliminarAvatar,ListarAvatares}UseCase.php`
- `Domain/Policies/AvatarPolicy.php` (o helper en el controller base): regla de autorización única.
- `auth_usuarios.avatar` = cache denormalizado de la ruta actual; la fuente de verdad del historial es `core_archivos`.

**Bloque 3 — Vistas**
- `Presentation/Controllers/Admin/PerfilController.php` (perfil propio).
- Ampliación de `Presentation/Controllers/Admin/UsuariosController.php` (endpoints de avatar para otros usuarios).
- `Presentation/Views/admin/perfil/index.php`
- `Presentation/Views/partials/avatar_manager.php` (compartido) + `public/assets/js/avatar-manager.js`
- Modificación de `Presentation/Views/admin/usuarios/editar.php` (insertar `avatar_manager`).
- Enlace "Mi perfil" en `Presentation/Views/partials/topbar.php`.

**Migración CRUD**
- `Application/Services/CrudDataService.php::handleUpload` delega en `FileUploadService`.

**Registro DI** en `config/container.php`: `FileUploadService`, `ImageProcessor`, `ArchivoRepository`.

---

## 4. Tabla `core_archivos`

Va en `database/schema/schema.sql` y como migración incremental (siguiendo el patrón de `database/migrations_legacy/incrementales-*`; archivo exacto se fija en el plan).

```sql
CREATE TABLE IF NOT EXISTS `core_archivos` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `entidad_tipo`   VARCHAR(80)      NOT NULL,           -- 'usuario', 'crud:demo_clientes', ...
  `entidad_id`     INT UNSIGNED     DEFAULT NULL,       -- id del dueño (nullable)
  `coleccion`      VARCHAR(60)      NOT NULL DEFAULT 'default', -- 'avatar', 'adjunto', ...
  `ruta`           VARCHAR(500)     NOT NULL,           -- relativa a public/ (o a storage privado)
  `thumbnail_ruta` VARCHAR(500)     DEFAULT NULL,       -- fase 2; nullable
  `nombre_original`VARCHAR(255)     DEFAULT NULL,
  `mime`           VARCHAR(120)     DEFAULT NULL,
  `extension`      VARCHAR(20)      DEFAULT NULL,
  `tamano_bytes`   BIGINT UNSIGNED  NOT NULL DEFAULT 0,
  `disco`          VARCHAR(20)      NOT NULL DEFAULT 'public', -- 'public' | 'private'
  `es_actual`      TINYINT(1)       NOT NULL DEFAULT 0,  -- puntero de colección (avatar activo)
  `creado_por`     INT UNSIGNED     DEFAULT NULL,        -- usuario que subió
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME         DEFAULT NULL,        -- soft-delete para depuración futura
  PRIMARY KEY (`id`),
  INDEX `idx_archivos_entidad` (`entidad_tipo`, `entidad_id`, `coleccion`),
  INDEX `idx_archivos_actual`  (`entidad_tipo`, `entidad_id`, `coleccion`, `es_actual`),
  INDEX `idx_archivos_deleted` (`deleted_at`),
  CONSTRAINT `fk_archivos_creado_por`
      FOREIGN KEY (`creado_por`) REFERENCES `auth_usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

El historial de avatares de un usuario = filas `(entidad_tipo='usuario', entidad_id=<id>, coleccion='avatar')` no borradas, con una marcada `es_actual=1`.

### Capa de dominio de archivos

- **`Archivo`** (entidad pura, sin SQL/HTTP): getters para todos los campos; factory `desdeFila(array)`; métodos inmutables `marcarComoActual()` / `marcarBorrado()` que devuelven clones (mismo estilo que `Usuario`).
- **`ArchivoRepositoryInterface`**:
  - `guardar(Archivo): int`
  - `buscarPorId(int): ?Archivo`
  - `listarPorEntidad(string $tipo, int $id, string $coleccion): Archivo[]` (excluye `deleted_at`)
  - `buscarActual(string $tipo, int $id, string $coleccion): ?Archivo`
  - `marcarActual(int $archivoId, string $tipo, int $entidadId, string $coleccion): void` (transacción: `es_actual=0` a los demás de la colección, `=1` al elegido)
  - `softDelete(int $archivoId): void`
- **`ArchivoRepository`** (Infrastructure) implementa sobre `BaseRepository` (PDO).

---

## 5. `FileUploadService` (handler compartido)

### Config por uso — `FileUploadConfig` (DTO inmutable)

```php
new FileUploadConfig(
    entidadTipo:   'usuario',          // o 'crud:demo_clientes'
    entidadId:     5,                  // nullable
    coleccion:     'avatar',           // 'default' por defecto
    disco:         'public',           // 'public' | 'private'
    directorio:    'uploads/avatars',  // relativo a public/ (o a storage privado)
    allowedExtensions: ['jpg','jpeg','png','webp'],
    maxBytes:      2 * 1024 * 1024,    // tope de ESTE formulario
    imagen:        new ImageOptions(   // null si no se procesa imagen
        maxWidth: 1024, maxHeight: 1024, calidad: 82,
        thumbnail: null                // fase 2: ThumbnailOptions(...)
    ),
    esActual:      true,               // marca este archivo como actual en su colección
    creadoPor:     $sessionUserId      // nullable
);
```

Cada formulario arma su propio `FileUploadConfig`: tipos aceptados, tamaño máximo, disco y compresión salen de ahí.

### API

```php
FileUploadService::handle(array $file, FileUploadConfig $cfg): Archivo
```

Flujo:
1. Detecta MIME real con `finfo` (como hoy en `CrudDataService`).
2. Valida con `UploadValidator` (error PHP, tamaño, extensión, coherencia MIME). Reutiliza el servicio existente.
3. Si `cfg->imagen !== null` y GD disponible → `ImageProcessor` redimensiona a `maxWidth/maxHeight` (proporción mantenida) y recomprime a `calidad`. Sin GD → guarda original y registra aviso en log.
4. Genera nombre seguro (`safeName_YmdHis_rand.ext`, igual que hoy), crea el directorio destino, mueve el archivo (`move_uploaded_file`, o `rename` en pruebas).
5. Si `esActual` → marca los demás de la colección como no-actuales y este como actual.
6. Inserta la fila en `core_archivos` y devuelve el `Archivo`.

Errores → `ValidationException` con los **mismos mensajes** que el flujo CRUD actual.

### `ImageProcessor`

Servicio aislado: `redimensionar(string $rutaAbsoluta, ImageOptions $opts): void` con GD (`imagecreatefrom*` / `image{jpeg,png,webp}`). Solo procesa JPG/PNG/WEBP; otros formatos pasan sin tocar.

### Migración del CRUD Engine

`CrudDataService::handleUpload` deja de mover/validar a mano:

```php
$archivo = $this->fileUploadService->handle($file, new FileUploadConfig(
    entidadTipo: 'crud:' . $definition->key(),
    entidadId:   $rowId,            // NULL si aún no existe (creación)
    coleccion:   $field->name(),
    disco:       'public',
    directorio:  $definition->uploadsPath(),
    allowedExtensions: $field->validation()['allowed_extensions'] ?? null,
    maxBytes:    ((int) Config::get('security.max_upload_mb', 10)) * 1024 * 1024,
    imagen:      null,             // el CRUD genérico no fuerza procesamiento de imagen
    esActual:    false,
    creadoPor:   $userId
));
return $archivo->ruta();           // misma forma de ruta que hoy; la columna del recurso no cambia de contrato
```

Cada upload del CRUD queda registrado en `core_archivos` (tracking). El contrato de salida (ruta relativa) es idéntico al actual.

---

## 6. Avatares — casos de uso, endpoints y autorización

### Casos de uso (`Application/UseCases/Avatares/`)

- **`SubirAvatarUseCase`** `(usuarioId, $file, actorId)`: arma `FileUploadConfig(coleccion:'avatar', esActual:true, imagen: 1024px/q82)`, llama a `FileUploadService`, actualiza `auth_usuarios.avatar` = ruta nueva. Devuelve el `Archivo`.
- **`FijarAvatarActualUseCase`** `(usuarioId, archivoId, actorId)`: valida pertenencia a `(usuario, usuarioId, 'avatar')` y no borrado; `marcarActual`; actualiza `auth_usuarios.avatar`.
- **`EliminarAvatarUseCase`** `(usuarioId, archivoId, actorId)`: valida pertenencia; `softDelete`; si era el actual → `auth_usuarios.avatar = NULL`. No borra el archivo físico (lo hará el futuro módulo de depuración leyendo `deleted_at`).
- **`ListarAvataresUseCase`** `(usuarioId)`: devuelve historial vigente (no borrados) para la galería.

### Autorización

Regla única (en `AvatarPolicy` o helper del controller base): un actor puede operar sobre el avatar del usuario `X` si **`actorId === X`** (su propio perfil) **o** tiene permiso **`usuarios.gestionar`** (admin sobre otro).

### Endpoints (rutas nuevas en `routes/web.php`; sesión + CSRF)

Perfil propio (`PerfilController`), actor = usuario de sesión:
```
GET    /admin/perfil                      → vista de perfil
PUT    /admin/perfil                      → actualizar nombre/apellido/email
POST   /admin/perfil/avatar               → subir (multipart)   → JSON
POST   /admin/perfil/avatar/{id}/actual   → fijar actual        → JSON
DELETE /admin/perfil/avatar/{id}          → borrar              → JSON
GET    /admin/perfil/avatares             → listar historial    → JSON
```

Admin sobre otro usuario (`UsuariosController`), requiere `usuarios.gestionar`:
```
POST   /admin/administracion/usuarios/{id}/avatar              → JSON
POST   /admin/administracion/usuarios/{id}/avatar/{aid}/actual → JSON
DELETE /admin/administracion/usuarios/{id}/avatar/{aid}        → JSON
GET    /admin/administracion/usuarios/{id}/avatares           → JSON
```

Ambos controllers delegan en los mismos casos de uso (pasando `usuarioId` objetivo y `actorId`).

### Respuesta JSON estándar

```json
{ "ok": true,
  "actual": { "id": 12, "ruta": "/uploads/avatars/..." },
  "historial": [
    { "id": 12, "ruta": "...", "esActual": true },
    { "id": 9,  "ruta": "...", "esActual": false }
  ] }
```
Errores → `{ "ok": false, "error": "mensaje" }` con código HTTP apropiado.

---

## 7. Vistas y UI

### Partial compartido `partials/avatar_manager.php` + `avatar-manager.js`

Parametrizado por `usuarioId` objetivo y la URL base de endpoints (perfil propio vs. admin). Layout de dos contenedores:

- **Contenedor principal (izquierda):**
  - Sin foto → recuadro vacío con icono + "Sin foto de perfil".
  - Con foto → imagen actual + **burbuja X** (arriba-derecha) → `ConfirmModal.show({variant:'danger'})`; al confirmar, `DELETE` al endpoint y la UI vuelve a estado vacío.
- **Contenedor secundario (derecha):** zona "Cambiar o agregar foto" con input file → `POST` multipart → la nueva pasa a actual.
- **Galería de historial (abajo):** miniaturas previas (no borradas); clic → `POST .../actual` → se vuelve la actual (resaltada).

`avatar-manager.js`: módulo acotado; hace `fetch` a los endpoints JSON, repinta principal + galería con la respuesta, reutiliza `ConfirmModal`. Sin recargas.

### Vista `/admin/perfil` (`perfil/index.php`)

Formulario con nombre, apellido, correo (PUT a `/admin/perfil`) + `avatar_manager` (endpoints de perfil) + botón "Cambiar contraseña" → `/auth/recuperar` (`disabled`/oculto si `email` vacío). Enlace "Mi perfil" desde el menú de usuario en `topbar.php`.

### Vista admin de edición (`usuarios/editar.php`)

Se inserta el mismo `avatar_manager` (endpoints `/admin/administracion/usuarios/{id}/avatar...`). El campo contraseña inline del admin **se mantiene como está hoy** (set-password directo).

---

## 8. Plan de pruebas (al final)

- **Unit** — `FileUploadConfig`/`ImageOptions` (construcción); `ImageProcessor` (resize con imagen de prueba; skip si no hay GD); `AvatarPolicy` (propio vs `usuarios.gestionar`); casos de uso de avatar (mock de `ArchivoRepository` + `FileUploadService`).
- **`FileUploadService`** — con `$_FILES`/`tmp` simulados (`tmpfile`): valida, mueve, inserta en `core_archivos`, marca actual; rutas de error conservan mensajes previos.
- **Regresión CRUD** — correr `tests/Crud/...` tras migrar `handleUpload`; + test que confirme que un upload del CRUD deja fila en `core_archivos` y devuelve la misma forma de ruta.
- **`ArchivoRepository`** — `marcarActual` deja exactamente uno con `es_actual=1`; `softDelete` setea `deleted_at` y lo excluye del historial.

Verificación final: `./vendor/bin/phpunit` + `php -l` en archivos nuevos.

---

## 9. Fuera de alcance (specs siguientes)

- **Spec 2 — Recuperación de contraseña por correo:** Mailer/transport, tabla de tokens con vencimiento, vistas `/auth/recuperar` (solicitar) y restablecer, envío y expiración. Regla: el botón del perfil no se puede usar sin correo registrado.
- **Spec 3 — Throttling de login:** store de intentos (IP/email), bloqueo temporal, integración en `LoginUseCase`/`AuthService`.
- **Fase 2 de este spec:** generación real de thumbnails (`ThumbnailOptions`) y, eventualmente, módulo de depuración de archivos viejos (consumiendo `core_archivos.deleted_at` y huérfanos).
