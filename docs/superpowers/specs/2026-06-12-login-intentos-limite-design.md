# Spec 3 — Límite de intentos de login (rate limiting)

**Fecha:** 2026-06-12  
**Estado:** Aprobado (brainstorming + plan 2026-06-12)  
**Specs relacionados:**
- `2026-06-11-uploads-avatares-perfil-design.md` (§9 — throttling diferido a este spec)
- `2026-06-12-auth-registro-recuperacion-login-design.md` (§10 — rate limiting explícitamente fuera de alcance allí)
- Roadmap seguridad M1 (`docs/archive/superpowers/plans/2026-06-09-roadmap-seguridad-y-continuidad.md`)

---

## §1 Contexto y problema

Hoy el login (`POST /login` → `LoginUseCase` → `AuthService::autenticar`) no limita intentos fallidos. Un atacante puede probar contraseñas indefinidamente contra cualquier email o desde una IP compartida (NAT, oficina).

El framework ya aplica un patrón de throttle en `AuthTokenService` (máx. 3 emisiones de token por usuario+tipo por hora vía `contarRecientes`). Este spec reutiliza esa idea para fallos de login, sin bloquear cuentas en BD ni introducir lockout permanente.

**Hallazgo de seguridad:** M1 — *Sin rate limiting en login → fuerza bruta*.

---

## §2 Decisiones (validadas con el usuario)

| # | Decisión | Resolución |
|---|---|---|
| 1 | Modelo de contadores | **Opción A — dual IP + email.** Contadores independientes por IP del cliente y por email normalizado. Bloqueo temporal si **cualquiera** supera el umbral en la ventana. |
| 2 | Lockout de cuenta | **No.** No se modifica `auth_usuarios` ni se desactiva la cuenta. Solo ventana temporal en memoria de intentos (tabla `auth_login_intentos`). |
| 3 | Alcance del endpoint | Solo `POST /login`. Registro, recuperación y verificación ya tienen throttle propio vía `AuthTokenService`. |
| 4 | Mensaje al usuario | **Genérico anti-enumeración:** mismo texto que credenciales inválidas (`Credenciales incorrectas.`) cuando el límite está activo. No revelar si el bloqueo es por IP o por email. |
| 5 | Cuenta inactiva | **Sin cambio** en esta fase: `AuthService` sigue mostrando el mensaje específico de cuenta desactivada (comportamiento actual). Unificarlo con mensaje genérico queda como mejora futura opcional. |
| 6 | Persistencia | Tabla nueva `auth_login_intentos` (append-only de fallos), no Redis ni archivos. Alineado al stack PHP+MySQL del framework. |
| 7 | Configuración | `config/auth.php` → sección `login` con defaults; override por `.env`. |

### Valores por defecto propuestos

| Parámetro | Default | Env |
|---|---|---|
| Máximo de fallos en ventana | `5` | `LOGIN_MAX_INTENTOS` |
| Ventana (minutos) | `15` | `LOGIN_VENTANA_MIN` |

*(Ajustables en implementación; los defaults siguen OWASP Cheat Sheet: pocos intentos, ventana corta.)*

---

## §3 Configuración

Extender **`config/auth.php`**:

```php
'login' => [
    'max_intentos'  => (int) EnvLoader::get('LOGIN_MAX_INTENTOS', 5),
    'ventana_min'   => (int) EnvLoader::get('LOGIN_VENTANA_MIN', 15),
],
```

Documentar en **`.env.example`**:

```
# Rate limiting login (fallos por IP o email en ventana)
LOGIN_MAX_INTENTOS=5
LOGIN_VENTANA_MIN=15
```

Binding en **`config/container.php`**: repositorio + `LoginRateLimitService` con valores desde `Config::get('auth.login.*')`.

---

## §4 Datos

### Tabla `auth_login_intentos`

Registra **solo intentos fallidos** (no éxitos). Cada fallo genera **dos filas**: una por dimensión `ip` y otra por `email`.

```sql
CREATE TABLE IF NOT EXISTS `auth_login_intentos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dimension`  VARCHAR(10)     NOT NULL,   -- 'ip' | 'email'
  `clave`      VARCHAR(255)    NOT NULL,   -- IP (trim, primer hop X-Forwarded-For) o email normalizado
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_login_intentos_busqueda` (`dimension`, `clave`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- **Migración incremental** en `database/migrations/` + actualización de `database/schema/schema.sql`.
- **Normalización de email:** `mb_strtolower(trim($email))` — misma cadena que usa el login tras `trim` en el controlador.
- **IP:** `Request::ip()` existente (primer valor de `X-Forwarded-For` si aplica). Documentar que detrás de proxy confiable el operador debe configurar headers correctamente.

### Retención

- Consultas usan solo filas con `created_at >= NOW() - ventana`.
- Tras login **exitoso**, borrar filas recientes de esa IP y ese email (reset explícito).
- Job opcional de higiene: `DELETE` de filas más viejas que `2 × ventana` (puede ejecutarse en el mismo servicio al registrar un fallo, sin cron).

### Domain

- Sin entidad de dominio rica; el repositorio es técnico (como contadores).
- **`LoginIntentoRepositoryInterface`** en `Domain/Interfaces/`:
  - `contarFallosRecientes(string $dimension, string $clave, int $ventanaMin): int`
  - `registrarFallo(string $ip, string $emailNormalizado): void` — inserta 2 filas
  - `limpiarPara(string $ip, string $emailNormalizado): void` — borra filas de ambas dimensiones para esa clave
  - `purgarAntiguos(int $ventanaMin): void` — higiene opcional
- **`LoginIntentoRepository`** PDO en `Infrastructure/Repositories/`.

---

## §5 Flujo de aplicación

### Componentes nuevos

| Capa | Clase | Responsabilidad |
|---|---|---|
| Application | `LoginRateLimitService` | Política: ¿bloqueado?, registrar fallo, limpiar en éxito |
| Application | `LoginDTO` | Campo nuevo `clientIp: string` |
| Application | `LoginUseCase` | Orquestar límite antes/después de autenticar |
| Presentation | `AuthController::login` | Pasar `$request->ip()` al DTO |

### Secuencia en `LoginUseCase::execute`

```
1. Validar email/password (LoginValidator — sin cambios)
2. Normalizar email (misma regla que AuthService / Email VO)
3. LoginRateLimitService::asegurarPermitido(ip, email)
   → si IP o email ≥ max_intentos en ventana → AuthException('Credenciales incorrectas.')
4. try AuthService::autenticar(...)
   → catch AuthException por credenciales inválidas:
        registrarFallo(ip, email); re-lanzar
   → catch por cuenta inactiva: registrarFallo(ip, email); re-lanzar (cuenta fallida = intento fallido)
5. Éxito → limpiarPara(ip, email); iniciarSesion (sin cambios)
```

**Orden:** comprobar límite **antes** de tocar BD de usuarios (reduce trabajo en ataques ya bloqueados).

**Timing:** no se añade delay artificial en v1; mitigación futura si se detecta enumeración por tiempo de respuesta.

### `LoginRateLimitService` (firma orientativa)

```php
public function asegurarPermitido(string $ip, string $emailNormalizado): void;
public function registrarFallo(string $ip, string $emailNormalizado): void;
public function limpiarTrasExito(string $ip, string $emailNormalizado): void;
```

Inyecta `LoginIntentoRepositoryInterface`, `maxIntentos`, `ventanaMin`.

### Logging operativo (no usuario)

En bloqueo o umbral alcanzado, `InfraLogger` / log de aplicación a nivel `warning` con IP (truncada o hash si se prefiere privacidad), **sin** email en claro en producción — opcional: hash de email en log. No escribir en `log_bitacora` (no hay `usuario_id` autenticado).

---

## §6 Presentación

- **Sin cambios de vista** en `auth/login.php`.
- El flash `error` sigue siendo el mensaje de `AuthException`.
- CSRF y validación de formulario sin cambios.

---

## §7 Pruebas

Harness existente (`php tests/run.php` / PHPUnit), fakes en `tests/fixtures/`:

| Test | Comportamiento |
|---|---|
| `LoginRateLimitServiceTest` | 4 fallos permitidos; el 5.º en misma ventana → bloqueado; ventana distinta por IP vs email |
| `LoginUseCaseTest` (nuevo o extendido) | Con fake repo: fallo registra intento; éxito limpia; bloqueado no llama `autenticar` |
| `FakeLoginIntentoRepository` | En memoria, replica contrato |
| Regresión | Login correcto, validación, CSRF, usuarios inactivos — sin regresión |
| Smoke manual | 5 passwords incorrectas → 6.ª respuesta idéntica; esperar ventana o limpiar tabla → login OK |

---

## §8 Revisión OWASP Top 10 (2021) — estado y alcance de este spec

| ID | Categoría | Estado actual (login) | Qué aporta este spec |
|---|---|---|---|
| **A01** | Broken Access Control | RBAC post-login; sin cambio aquí | Indirecto: reduce probabilidad de obtener sesión válida por fuerza bruta |
| **A02** | Cryptographic Failures | Passwords con `Hash::verify`; sin cambio | N/A directo |
| **A03** | Injection | Email vía VO/repositorio parametrizado; sin cambio | N/A directo |
| **A04** | Insecure Design | M1 abierto — diseño sin límite de intentos | **Cierra M1** con política dual documentada |
| **A05** | Security Misconfiguration | Headers/SESSION en roadmap | N/A directo; operador configura proxy/IP |
| **A06** | Vulnerable Components | Composer audit aparte | N/A |
| **A07** | Identification & Authentication Failures | Sin rate limit; mensaje distinto para inactivo | **Principal:** throttle, sin lockout permanente, mensaje genérico en bloqueo |
| **A08** | Software & Data Integrity | CSRF en login ✓ | N/A |
| **A09** | Security Logging & Monitoring | Bitácora post-auth; pocos eventos pre-auth | Log `warning` en bloqueos (IP, sin PII innecesaria) |
| **A10** | SSRF | N/A en login | N/A |

### Controles OWASP Authentication — checklist cubierto por este spec

- [x] Limitar intentos fallidos (dual IP + identificador)
- [x] Bloqueo temporal, no permanente en cuenta
- [x] Mensaje uniforme en bloqueo por límite (anti-enumeración parcial)
- [x] Configuración centralizada y documentada
- [ ] Delay constante post-fallo — **fuera de v1**
- [ ] CAPTCHA tras N intentos — **fuera de alcance**
- [ ] Mensaje genérico también para cuenta inactiva — **fuera de v1** (nota en §2)

---

## §9 Fuera de alcance

- Lockout permanente o flag `bloqueado_hasta` en `auth_usuarios`
- CAPTCHA, 2FA/MFA
- Rate limit en APIs JSON futuras (`M3` del roadmap)
- Unificar mensaje de cuenta inactiva con credenciales incorrectas
- Rate limit por subnet / geolocalización
- Panel admin para ver/desbloquear IPs (solo logs + SQL manual en v1)

---

## §10 Criterios de aceptación

1. Tras `LOGIN_MAX_INTENTOS` fallos desde la misma IP **o** el mismo email en `LOGIN_VENTANA_MIN` minutos, nuevos intentos responden con flash `Credenciales incorrectas.` sin autenticar.
2. Login exitoso elimina contadores de esa IP y email.
3. Usuarios creados por admin y flujos de registro/recuperación no se alteran.
4. Tests automatizados cubren servicio y caso de uso con fake repository.
5. Migración y schema baseline actualizados.
6. `.env.example` y documentación de seguridad (`docs/core/auth_rbac_seguridad_v0.1.md` §11 Login) actualizados con el nuevo comportamiento.

---

## §11 Orden de implementación sugerido (para el plan)

1. Migración + interfaz/repositorio + fake
2. `LoginRateLimitService` + tests del servicio
3. Extender `LoginDTO` / `LoginUseCase` / `AuthController` + tests de caso de uso
4. Container, `config/auth.php`, `.env.example`
5. Docs + smoke manual

---

*Spec 3 — throttling de login. Implementación solo tras aprobación de este documento y plan derivado (`writing-plans`).*
