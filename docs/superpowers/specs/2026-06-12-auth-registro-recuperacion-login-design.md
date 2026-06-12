# Spec 2 — Registro público, recuperación de contraseña y vistas de auth

**Fecha:** 2026-06-12
**Estado:** Aprobado (brainstorming con usuario)
**Specs relacionados:** `2026-06-11-uploads-avatares-perfil-design.md` (dejó diferido este flujo: el botón "Cambiar contraseña" de `/admin/perfil` apunta a recuperación, hoy 404)

---

## §1 Contexto y problema

La plataforma solo permite autenticarse (`/login`) con usuarios creados por un admin. No existe:

1. Registro público de usuarios.
2. Recuperación de contraseña por correo (`/admin/perfil` ya enlaza un botón "Cambiar contraseña" que da 404).
3. Infraestructura de correo (el `.env.example` reserva variables `MAIL_*`, pero no hay mailer ni librería instalada).

Este spec agrega los tres flujos como módulo de plataforma, sin tocar la lógica del login existente.

## §2 Decisiones (validadas con el usuario)

| # | Decisión | Resolución |
|---|---|---|
| 1 | Modelo de registro | Público **con verificación de correo obligatoria**. Activable por config; el framework lo trae **apagado** por defecto. |
| 2 | Rol del registrado | Configurable (`registro.rol_default`); seed nuevo de rol **"Usuario"** (slug `usuario`) con permiso `dashboard.ver`. |
| 3 | Mailer | **PHPMailer vía composer**, envuelto en `MailerInterface` propia. Driver `log` adicional para desarrollo. |
| 4 | Alcance del login | Se conserva tal cual funciona; se agregan enlaces a registro/recuperación y se extrae un **shell visual común** para las tres vistas (sin cambiar su apariencia). |
| 5 | Tokens | **Tabla única `auth_tokens`** multi-propósito (recuperación + verificación), token hasheado, un solo uso, TTL por tipo. |
| 6 | Fase 2 de archivos (thumbnails/depuración) | **No entra aquí**: queda registrada como pendiente para un spec propio (ver §10). |

## §3 Configuración

- **`config/auth.php`** (nuevo):
  - `registro.habilitado` ← env `REGISTRO_HABILITADO` (default `false`).
  - `registro.rol_default` (slug, default `'usuario'`).
  - `tokens.recuperacion_ttl_min` (default `60`), `tokens.verificacion_ttl_min` (default `1440`).
  - `tokens.max_por_hora` (default `3`).
- **`config/mail.php`** (nuevo): lee `MAIL_DRIVER` (`smtp`|`log`), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (ya reservadas en `.env.example`).
- **Seed:** rol `usuario` ("Usuario") con `dashboard.ver`, en los seeds estándar.
- **composer:** agregar `phpmailer/phpmailer`.

## §4 Datos

### Tabla `auth_tokens` (schema baseline + migración incremental)

```sql
CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED    NOT NULL,
  `tipo`       VARCHAR(30)     NOT NULL,              -- 'recuperacion' | 'verificacion'
  `token_hash` CHAR(64)        NOT NULL,              -- sha256 del token en claro
  `expira_en`  DATETIME        NOT NULL,
  `usado_en`   DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tokens_usuario_tipo` (`usuario_id`, `tipo`),
  INDEX `idx_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_tokens_usuario`
      FOREIGN KEY (`usuario_id`) REFERENCES `auth_usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `auth_usuarios`

- Columna nueva: `email_verificado_en` DATETIME NULL (migración incremental + baseline).
- El registro crea el usuario con `activo = 0`; la verificación pone `activo = 1` y `email_verificado_en = NOW()`.
- **`LoginUseCase` no se modifica**: ya rechaza usuarios inactivos, por lo que un registrado sin verificar no puede entrar. Los usuarios creados por admin no cambian (quedan con `email_verificado_en` NULL y operan igual que hoy). Trade-off aceptado: un admin puede activar manualmente a un registrado sin verificar.

### Domain

- Entidad `AuthToken` (inmutable, `desdeFila`, mismo estilo que `Archivo`).
- `AuthTokenRepositoryInterface`: `guardar`, `buscarVigentePorHash(string $hash, string $tipo): ?AuthToken`, `marcarUsado(int $id): void`, `invalidarDeUsuario(int $usuarioId, string $tipo): void`, `contarRecientes(int $usuarioId, string $tipo, int $minutos): int`.
- Implementación PDO `AuthTokenRepository` en Infrastructure.

## §5 Mailer

- **`MailerInterface`** en `app/Domain/Interfaces/` (patrón del framework): `enviar(MensajeCorreo $mensaje): void`. DTO `MensajeCorreo` (`destinatario`, `nombreDestinatario`, `asunto`, `html`) en `Application/DTO/Mail/`.
- **Drivers** en `app/Infrastructure/Mail/`:
  - `PhpMailerMailer` — SMTP real (host/puerto/credenciales/TLS desde `config/mail.php`).
  - `LogMailer` — escribe asunto/destinatario/cuerpo al log del framework (desarrollo y fallback).
  - Binding en `config/container.php` según `MAIL_DRIVER`.
- **Plantillas** PHP en `app/Presentation/Views/emails/` (`verificacion.php`, `recuperacion.php`): HTML simple autocontenido, renderizadas con el mecanismo de vistas existente. Reciben nombre del usuario y URL absoluta con el token (base desde `APP_URL`).
- Fallo de SMTP → se registra en log y el use case lanza `ValidationException` con mensaje genérico ("No fue posible enviar el correo. Intenta más tarde."); nunca expone detalles del transporte.

## §6 Casos de uso (Application)

Carpeta `app/Application/UseCases/Auth/` (junto a `LoginUseCase`/`LogoutUseCase`).

| Use case | Contrato |
|---|---|
| `RegistrarUsuarioUseCase` | Valida (reglas de `CrearUsuarioValidator`: nombre, apellido, email único, password + confirmación), exige `registro.habilitado`, crea usuario `activo=0` con rol default, genera token `verificacion`, envía correo. |
| `VerificarCorreoUseCase` | Recibe token en claro → hashea → busca vigente tipo `verificacion`; inválido/vencido/usado → `ValidationException`. Éxito: `usado_en=NOW()`, `activo=1`, `email_verificado_en=NOW()`. |
| `ReenviarVerificacionUseCase` | Para usuario no verificado: invalida tokens previos del tipo, genera uno nuevo y reenvía. Respuesta externa genérica (anti-enumeración) y throttle (§8). |
| `SolicitarRecuperacionUseCase` | Si el email existe y está activo: invalida tokens previos, genera token `recuperacion`, envía correo. **El resultado observable es idéntico exista o no el email.** Throttle (§8). |
| `RestablecerPasswordUseCase` | Consume token `recuperacion` vigente; valida password nuevo (mismas reglas que el validador actual); actualiza hash; marca token usado. |

Generación de token: `bin2hex(random_bytes(32))` en claro para la URL; solo `hash('sha256', $token)` se persiste.

## §7 Presentación: rutas, controllers y vistas

### Rutas (públicas, raíz — consistentes con `/login`; POSTs con `CsrfMiddleware`; con sesión activa redirigen a `/admin/dashboard`)

```text
GET  /registro                → RegistroController@mostrar       (404 si registro.habilitado=false)
POST /registro                → RegistroController@registrar     [CSRF]
GET  /registro/verificar      → RegistroController@verificar     (?token=...)
POST /registro/reenviar       → RegistroController@reenviar      [CSRF]

GET  /recuperar               → RecuperacionController@mostrar
POST /recuperar               → RecuperacionController@solicitar  [CSRF]
GET  /restablecer             → RecuperacionController@mostrarRestablecer (?token=...)
POST /restablecer             → RecuperacionController@restablecer [CSRF]
```

- Controllers nuevos y acotados: `RegistroController`, `RecuperacionController` (en `app/Presentation/Controllers/`, junto a `AuthController`). `AuthController` queda solo con login/logout.
- **Corrección:** el botón "Cambiar contraseña" de `admin/perfil/index.php` pasa de `/auth/recuperar` (404) a `/recuperar`.
- Bindings nuevos en `config/container.php`.

### Vistas y shell común

- Se extrae el shell visual del login (fondo, card centrada, branding/tema de `LebytekUiConfig`, flashes) a `partials/auth_card.php`; `auth/login.php` se refactoriza para usarlo **sin cambio visual** (misma apariencia píxel a píxel en lo razonable).
- Vistas nuevas sobre el shell:
  - `auth/registro.php` — nombre, apellido, email, password, confirmación.
  - `auth/registro_enviado.php` — "revisa tu correo" + botón reenviar (POST `/registro/reenviar`).
  - `auth/recuperar.php` — email.
  - `auth/recuperar_enviado.php` — mensaje genérico ("Si el correo existe, enviamos instrucciones").
  - `auth/restablecer.php` — password + confirmación (token en campo oculto).
- Login: enlaces "¿Olvidaste tu contraseña?" → `/recuperar` y "Crear cuenta" → `/registro` (este último solo si `registro.habilitado`).
- Errores/estados con el patrón actual del login: `Session::flash` + old input.

## §8 Seguridad

1. **Tokens:** 32 bytes aleatorios; solo el hash sha256 en BD; un solo uso; TTL por tipo; al emitir uno nuevo se invalidan los previos del mismo usuario+tipo.
2. **Anti-enumeración:** `/recuperar` y `/registro/reenviar` responden siempre el mismo mensaje, exista o no la cuenta; con email inexistente no se envía nada y no se persiste nada.
3. **Throttle:** máx. `tokens.max_por_hora` (3) emisiones por usuario+tipo por hora, contadas con `contarRecientes` sobre `auth_tokens` (sin infraestructura extra). Excedido → misma respuesta genérica, sin envío.
4. **Restablecer:** la página GET no revela validez del token hasta el POST y nunca expone el email asociado.
5. **Verificación:** al verificar NO se inicia sesión automáticamente; redirige a `/login` con flash de éxito (evita fijación de sesión).
6. CSRF en todos los POST (middleware existente); mensajes de validación vía `ValidationException` como en el resto del framework.

## §9 Plan de pruebas

Harness propio (`php tests/run.php`, microtest, sin BD), fakes en `tests/fixtures/`:

- `FakeAuthTokenRepository` (en memoria, replica el contrato) y `FakeMailer` (espía: acumula mensajes enviados).
- `tests/Auth/RegistroUseCaseTest.php`: usuario creado inactivo con rol default + token + 1 correo; email duplicado → `ValidationException`; registro deshabilitado → falla; password sin confirmación → falla.
- `tests/Auth/VerificacionUseCaseTest.php`: token vencido/usado/de otro tipo/inexistente → `ValidationException`; éxito activa y marca verificado; reenviar invalida los previos.
- `tests/Auth/RecuperacionUseCaseTest.php`: email existente → token + correo; email inexistente → **mismo resultado externo y cero correos**; 4ª solicitud en la hora → sin envío; restablecer actualiza el hash y consume el token; token consumido no se reutiliza.
- Contrato del fake vs repositorio real: test de contrato sobre el fake (patrón `ArchivoRepositoryContractTest`).
- Regresión: suite completa en verde; login sin cambios de comportamiento.
- Smoke manual: ciclo completo registro→correo (driver `log`)→verificar→login; recuperar→restablecer→login; botón del perfil llega a `/recuperar`.

## §10 Fuera de alcance (specs futuros)

- **Fase 2 de archivos** (pendiente heredado del spec de avatares): thumbnails reales (`ThumbnailOptions`) y depuración de archivos borrados/huérfanos → spec propio.
- Rate limiting del login y lockout de cuenta.
- 2FA / MFA.
- Cambio de email con re-verificación desde el perfil.
- Cola/async de correos (el envío es síncrono en esta fase).
