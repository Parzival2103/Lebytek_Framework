# CRUD Uploads Hardening (CRUD-C6) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`
> (recommended) or `superpowers:executing-plans` to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar **CRUD-C6** con defensa en tres capas: allowlist obligatoria en config y runtime, jail de rutas bajo `public/uploads/`, y denylist de extensiones ejecutables — sin cambiar el contrato de rutas relativas `/uploads/...` ni introducir negocio Portal.

**Architecture:** Patrón Enfoque A del spec: (1) `CrudConfigValidator::uploadsBlockErrors` rechaza configs inseguras al load; (2) `UploadValidator::assertValid` falla si `$allowedExtensions` es `null` o `[]`; (3) `FileUploadService` resuelve destino con `realpath` y exige prefijo bajo `realpath(PUBLIC_PATH)/uploads`. `CrudDataService::handleUpload` conserva wiring actual; avatares ya cumplen allowlist explícita.

**Tech Stack:** PHP `>=8.2` (`composer.json`), harness `php tests/run.php` + `tests/lib/microtest.php`, capas `Lebytek\Framework\{Application,Domain}`, configs JSON `config/cruds/` + espejo `skeleton/config/cruds/`, `PUBLIC_PATH` del harness.

**Source spec:** `docs/superpowers/specs/2026-08-09-audit-crud-uploads-hardening-design.md` · **Modo:** normal  
**Source audit PR:** #106 — https://github.com/Parzival2103/Lebytek_Framework/pull/106  
**Target repository/branches:** `Parzival2103/Lebytek_Framework` @ `main` (`487ccd8132e7c42eabd2a0e3b335b075ccc123e1`); rama de trabajo `feature/crud-p03-uploads-hardening` (creable desde `main` — verificado `git ls-remote origin refs/heads/main`)

**Programa:** Remediación CRUD Engine · **Punto:** 3/12 · **IDs:** C6  
**Estructura programa:** `docs/superpowers/plans/2026-08-07-crud-engine-remediacion-estructura.md`  
**Alias programa:** `docs/superpowers/plans/2026-08-07-crud-p03-uploads-hardening.md` (mismo alcance; este archivo es el plan diario canónico 2026-08-09)

## Baseline asumida (puntos 1..2)

| Punto | Plan | Estado verificado | Evidencia |
|------:|------|-------------------|----## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-09 |
| Framework `origin/main` referencia | tip post-REL-C1 (`v1.2.7` publicado) |
| Tareas completadas / totales | 5 / 5 (código); tag `v1.2.8` ops post-merge |
| Siguiente | Publicar tag `v1.2.8` + Portal lock bump (humano) |
| Estado | Implementación en `cursor/crud-p03-uploads-hardening-c292` |

**Implicaciones:** AuthZ y states cerrados. Demos harness/skeleton tienen `uploads.enabled=false` — no requieren allowlist hasta que un operador habilite uploads. **Prerrequisito release:** tag Git `v1.2.7` (REL-C1, PR #105) debe publicarse **antes** del tag `v1.2.8` de este punto; el código puede implementarse en paralelo.

## Global Constraints

- Solo ID **C6** como entregable de producto en Framework.
- No editar `vendor/`; no negocio Portal / `dom_*` en este repo.
- No desactivar RBAC, CSRF, soft-delete ni tests existentes.
- Espejo obligatorio si se tocan demos JSON (hoy `uploads.enabled=false` en todos — verificar `cmp` post-cambio).
- Semver: **PATCH `1.2.8`** en trío `composer.json` / `config/app.php` / `skeleton/config/app.php` (Task 5). No publicar tag hasta REL-C1 `v1.2.7` exista.
- Mensajes runtime en español; conservar textos existentes salvo ampliación accionable U2.
- Errores de config vía `ValidationException('La configuración CRUD contiene errores.', $errors)` — **no** existe `CrudConfigException` en el paquete.
- Denylist global de extensiones (fail-closed incluso si aparecen en allowlist): `php`, `phtml`, `phar`, `htaccess`, `svg`.
- `public_path` canónico: regex `^uploads/[a-z0-9_/]+$`, sin `\`, sin segmentos `..`.

## Requisitos → tareas (matriz)

| ID | Requisito | Owner | Tarea | Verificación |
|----|-----------|-------|-------|--------------|
| C6 | Allowlist obligatoria runtime (`null`/`[]` rechazados) | Framework | Task 1 | `php tests/run.php Security/UploadValidator` |
| C6 | Denylist extensiones peligrosas + mensaje accionable U2 | Framework | Task 1 | idem |
| C6 | Path jail en `FileUploadService` | Framework | Task 2 | `php tests/run.php Archivos/FileUploadService` |
| C6 | Validación bloque `uploads` + campos `file` en config | Framework | Task 3 | `php tests/run.php Crud/Upload/CrudConfigValidatorUploads` |
| C6 | Docs § uploads + semver `1.2.8` | Framework | Task 4 | `php tests/run.php Docs/CrudModuleUploads PlatformVersionSemver` |
| C6 | Regresión avatares + suites gate | Framework | Task 5 | `php tests/run.php Security Archivos Crud/Upload Kernel/SkeletonPurity` |

## File Structure

| Archivo | Responsabilidad |
|---------|-----------------|
| `src/Application/Services/UploadValidator.php` | Rechazo allowlist vacía; denylist global; mensaje U2 con extensiones permitidas |
| `src/Application/Services/FileUploadService.php` | Normalización + jail `realpath` bajo `PUBLIC_PATH/uploads` |
| `src/Application/Services/CrudConfigValidator.php` | Nuevo `uploadsBlockErrors()` invocado desde `validate()` |
| `src/Application/Services/CrudDataService.php` | Sin cambio de contrato (`handleUpload` ya pasa allowlist) |
| `src/Application/UseCases/Avatares/SubirAvatarUseCase.php` | Sin cambio (allowlist explícita) |
| `docs/modules/crud/modulo-crud-engine.md` | § uploads: allowlist obligatoria, formato `public_path`, ejemplo seguro |
| `composer.json`, `config/app.php`, `skeleton/config/app.php` | bump `1.2.8` |
| `tests/Security/UploadValidatorTest.php` | Invertir test permisivo; casos denylist + U2 |
| `tests/Archivos/FileUploadServiceTest.php` | Casos path traversal / jail |
| `tests/Crud/Upload/CrudConfigValidatorUploadsTest.php` | **Create** — reglas config C6 |
| `tests/Crud/Upload/CrudUploadLedgerTest.php` | Regresión contrato ruta (sin cambio funcional esperado) |
| `tests/Docs/CrudModuleUploadsTest.php` | **Create** — frases obligatorias docs |

**Interfaces producidas:**

- `CrudConfigValidator::uploadsBlockErrors(array $config): array` — errores acumulados (strings en español).
- `UploadValidator::assertValid(...)` — misma firma; lanza si allowlist ausente/vacía o extensión en denylist.
- `FileUploadService::handle(...)` — misma firma; lanza `ValidationException` si destino escapa jail.

**Interfaces consumidas (sin cambio):**

- `FileUploadConfig` (`directorio`, `allowedExtensions`, …)
- `CrudResourceDefinition::uploadsEnabled()`, `uploadsPath()`
- `CrudFieldDefinition::validation()['allowed_extensions']`
- `ValidationException`

---

### Task 1: Allowlist obligatoria + denylist + mensaje accionable (runtime)

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p03-uploads-hardening`  
**Depends on:** None  
**Files:**
- Modify: `src/Application/Services/UploadValidator.php`
- Modify: `tests/Security/UploadValidatorTest.php`
- Test: `tests/Security/UploadValidatorTest.php`
**Interfaces:**
- Consumes: `ValidationException`
- Produces: `assertValid` fail-closed para `null`/`[]`; denylist `GLOBAL_DENIED_EXTENSIONS`

- [ ] **Step 1: Escribir el test que falla** — en `tests/Security/UploadValidatorTest.php`:

**Reemplazar** el test permisivo existente:

```php
test('UploadValidator acepta cuando no hay lista blanca declarada', function (): void {
```

**por** estos casos (mantener el resto del archivo):

```php
test('UploadValidator rechaza allowlist null (C6 fail-closed)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('doc.pdf', 2048), 'Doc', null, 'application/pdf');
    });
});

test('UploadValidator rechaza allowlist vacía (C6 fail-closed)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('doc.pdf', 2048), 'Doc', [], 'application/pdf');
    });
});

test('UploadValidator rechaza extensión en denylist global aunque esté en allowlist', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    assert_throws(ValidationException::class, function () use ($v): void {
        $v->assertValid(up_file('shell.php', 128), 'Adjunto', ['php', 'pdf'], null);
    });
});

test('UploadValidator mensaje accionable lista extensiones permitidas (U2)', function (): void {
    $v = new UploadValidator(10 * 1024 * 1024);
    try {
        $v->assertValid(up_file('malware.exe', 1024), 'Adjunto', ['pdf', 'jpg'], null);
        assert_true(false, 'debió lanzar');
    } catch (ValidationException $e) {
        assert_same('Extensión de archivo no permitida para Adjunto. Usa: pdf, jpg.', $e->getMessage());
    }
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Security/UploadValidator` / Expected: FAIL — test «acepta cuando no hay lista blanca» eliminado; nuevos casos FAIL (allowlist null pasa hoy).

- [ ] **Step 3: Implementar el cambio mínimo** — en `UploadValidator.php`:

```php
private const GLOBAL_DENIED_EXTENSIONS = ['php', 'phtml', 'phar', 'htaccess', 'svg'];
```

Tras calcular `$extension` (L61), **antes** del bloque allowlist actual:

```php
if (in_array($extension, self::GLOBAL_DENIED_EXTENSIONS, true)) {
    throw new ValidationException('Extensión de archivo no permitida para ' . $label . '.');
}

if ($allowedExtensions === null || $allowedExtensions === []) {
    throw new ValidationException('Extensión de archivo no permitida para ' . $label . '.');
}
```

Reemplazar el bloque L63–68 por:

```php
$allowedLower = array_map(static fn($x): string => strtolower((string) $x), $allowedExtensions);
if ($extension === '' || !in_array($extension, $allowedLower, true)) {
    $hint = implode(', ', $allowedLower);
    throw new ValidationException(
        'Extensión de archivo no permitida para ' . $label . '. Usa: ' . $hint . '.'
    );
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Security/UploadValidator` / Expected: PASS (todos los casos, incluidos MIME/tamaño legacy).

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Archivos/FileUploadService` / Expected: PASS (caso feliz usa allowlist implícita vía config — ver Task 2 si falla por allowlist null en helper `upload_config`; ajustar helper a `['txt']` default en tests Archivos si necesario).

- [ ] **Step 6: Commit** — `git add src/Application/Services/UploadValidator.php tests/Security/UploadValidatorTest.php` · mensaje: `fix(crud): require upload allowlist and deny dangerous extensions (C6)`

---

### Task 2: Path jail en `FileUploadService`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p03-uploads-hardening`  
**Depends on:** Task 1  
**Files:**
- Modify: `src/Application/Services/FileUploadService.php`
- Modify: `tests/Archivos/FileUploadServiceTest.php`
- Test: `tests/Archivos/FileUploadServiceTest.php`
**Interfaces:**
- Consumes: `PUBLIC_PATH`, `FileUploadConfig::directorio`
- Produces: escritura solo bajo `{realpath(PUBLIC_PATH)}/uploads/...`

- [ ] **Step 1: Escribir el test que falla** — añadir al final de `tests/Archivos/FileUploadServiceTest.php`:

```php
test('FileUploadService rechaza directorio con .. fuera del jail uploads (C6)', function (): void {
    $service = new FileUploadService(new ImageProcessor(), new FakeArchivoRepository());
    $evilDir = 'uploads/../outside_jail_' . bin2hex(random_bytes(3));
    $file    = upload_file_array('x.txt', 'data');

    try {
        $service->handle(
            $file,
            upload_config($evilDir, ['allowedExtensions' => ['txt']]),
            'Doc'
        );
        assert_true(false, 'debió lanzar ValidationException');
    } catch (ValidationException $e) {
        assert_true(
            str_contains($e->getMessage(), 'directorio') || str_contains($e->getMessage(), 'uploads'),
            'mensaje debe indicar path inválido: ' . $e->getMessage()
        );
    } finally {
        @unlink($file['tmp_name']);
        $abs = PUBLIC_PATH . '/outside_jail_' . substr($evilDir, -7);
        if (is_dir($abs)) {
            @unlink($abs . '/x.txt');
            @rmdir($abs);
        }
    }
});

test('FileUploadService rechaza directorio absoluto fuera de uploads/', function (): void {
    $service = new FileUploadService(new ImageProcessor(), new FakeArchivoRepository());
    $file    = upload_file_array('x.txt', 'data');
    assert_throws(ValidationException::class, function () use ($service, $file): void {
        $service->handle(
            $file,
            upload_config('storage/private', ['allowedExtensions' => ['txt']]),
            'Doc'
        );
    });
    @unlink($file['tmp_name']);
});
```

Actualizar helper `upload_config()` en el mismo archivo — default `allowedExtensions`:

```php
allowedExtensions: $overrides['allowedExtensions'] ?? ['txt'],
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Archivos/FileUploadService` / Expected: FAIL en casos jail (hoy escribe fuera o crea directorio sin validar).

- [ ] **Step 3: Implementar el cambio mínimo** — en `FileUploadService.php`, extraer método privado y usarlo en L62–67:

```php
private function resolvePublicUploadDirectory(string $directorio): string
{
    $root = realpath(PUBLIC_PATH);
    if ($root === false) {
        throw new ValidationException('No fue posible resolver el directorio público de uploads.');
    }

    $relative = trim(str_replace('\\', '/', $directorio), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        throw new ValidationException('El directorio de uploads no es válido.');
    }

    if (!str_starts_with($relative, 'uploads/')) {
        throw new ValidationException('El directorio de uploads debe comenzar con uploads/.');
    }

    $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
        throw new ValidationException('No fue posible crear el directorio de uploads.');
    }

    $resolved = realpath($absolute);
    $jailRoot = realpath($root . DIRECTORY_SEPARATOR . 'uploads');
    if ($resolved === false || $jailRoot === false || !str_starts_with($resolved, $jailRoot . DIRECTORY_SEPARATOR)) {
        throw new ValidationException('El directorio de uploads está fuera del área permitida.');
    }

    return $resolved;
}
```

Reemplazar construcción de `$publicAbsolute` por `$publicAbsolute = $this->resolvePublicUploadDirectory($cfg->directorio);`

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Archivos/FileUploadService` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/Upload/CrudUploadLedger` / Expected: PASS (rutas relativas `/uploads/...` conservadas).

- [ ] **Step 6: Commit** — `git add src/Application/Services/FileUploadService.php tests/Archivos/FileUploadServiceTest.php` · mensaje: `fix(crud): jail CRUD upload paths under public/uploads (C6)`

---

### Task 3: Validación config — `uploadsBlockErrors`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p03-uploads-hardening`  
**Depends on:** Task 1 (denylist compartida conceptualmente)  
**Files:**
- Modify: `src/Application/Services/CrudConfigValidator.php`
- Create: `tests/Crud/Upload/CrudConfigValidatorUploadsTest.php`
- Test: `tests/Crud/Upload/CrudConfigValidatorUploadsTest.php`
**Interfaces:**
- Consumes: shape JSON `uploads`, `form.fields[].type=file`, `validation.allowed_extensions`
- Produces: strings de error agregados a `validate()`

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Crud/Upload/CrudConfigValidatorUploadsTest.php`:

```php
<?php

declare(strict_types=1);

use Lebytek\Framework\Application\Services\CrudConfigValidator;

test('uploadsBlockErrors: sin bloque uploads pasa', function (): void {
    assert_same([], CrudConfigValidator::uploadsBlockErrors([]));
});

test('uploadsBlockErrors: uploads disabled no exige allowlist', function (): void {
    $config = [
        'uploads' => ['enabled' => false, 'public_path' => 'uploads/cruds/x'],
        'form' => ['fields' => [['name' => 'doc', 'type' => 'file']]],
    ];
    assert_same([], CrudConfigValidator::uploadsBlockErrors($config));
});

test('uploadsBlockErrors: uploads enabled exige public_path uploads/ válido', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => '../evil'],
        'form' => ['fields' => []],
    ]);
    assert_true(count($errors) >= 1, 'debe rechazar public_path inválido');
});

test('uploadsBlockErrors: campo file sin allowed_extensions cuando uploads enabled', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => 'uploads/cruds/demo'],
        'form' => ['fields' => [
            ['name' => 'adjunto', 'label' => 'Adjunto', 'type' => 'file'],
        ]],
    ]);
    assert_true(
        (bool) preg_grep('/allowed_extensions/', $errors),
        'debe mencionar allowed_extensions: ' . json_encode($errors)
    );
});

test('uploadsBlockErrors: allowed_extensions con php en denylist falla', function (): void {
    $errors = CrudConfigValidator::uploadsBlockErrors([
        'uploads' => ['enabled' => true, 'public_path' => 'uploads/cruds/demo'],
        'form' => ['fields' => [
            ['name' => 'adjunto', 'type' => 'file', 'validation' => ['allowed_extensions' => ['php']]],
        ]],
    ]);
    assert_true(count($errors) >= 1, 'php debe estar prohibido');
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Crud/Upload/CrudConfigValidatorUploads` / Expected: FAIL — método `uploadsBlockErrors` no existe / reglas ausentes.

- [ ] **Step 3: Implementar el cambio mínimo** — en `CrudConfigValidator.php`:

Añadir constante (junto a `BLOCKED_PREFIXES`):

```php
private const UPLOAD_PUBLIC_PATH_PATTERN = '#^uploads/[a-z0-9_/]+$#';
private const UPLOAD_DENIED_EXTENSIONS = ['php', 'phtml', 'phar', 'htaccess', 'svg'];
```

Método estático público (patrón `statesBlockErrors`):

```php
public static function uploadsBlockErrors(array $config): array
{
    $errors = [];
    $uploads = $config['uploads'] ?? null;
    if (!is_array($uploads)) {
        return $errors;
    }
    $enabled = (bool) ($uploads['enabled'] ?? false);
    if (!$enabled) {
        return $errors;
    }

    $publicPath = trim(str_replace('\\', '/', (string) ($uploads['public_path'] ?? '')));
    if ($publicPath === '' || str_contains($publicPath, '..') || !preg_match(self::UPLOAD_PUBLIC_PATH_PATTERN, $publicPath)) {
        $errors[] = 'uploads.public_path debe comenzar con uploads/ y usar solo minúsculas, números, guiones y barras (sin ..).';
    }

    foreach (($config['form']['fields'] ?? []) as $index => $field) {
        if (!is_array($field) || ($field['type'] ?? '') !== 'file') {
            continue;
        }
        $name = (string) ($field['name'] ?? "fields[{$index}]");
        $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
        $exts = $validation['allowed_extensions'] ?? null;
        if (!is_array($exts) || $exts === []) {
            $errors[] = "El recurso tiene uploads habilitados pero el campo file '{$name}' debe declarar validation.allowed_extensions con al menos una extensión.";
            continue;
        }
        $seen = [];
        foreach ($exts as $ext) {
            $norm = strtolower(ltrim((string) $ext, '.'));
            if ($norm === '' || !preg_match('/^[a-z0-9]+$/', $norm)) {
                $errors[] = "form.fields[{$index}].validation.allowed_extensions contiene un valor inválido.";
            }
            if (in_array($norm, self::UPLOAD_DENIED_EXTENSIONS, true)) {
                $errors[] = "form.fields[{$index}].validation.allowed_extensions no puede incluir '{$norm}'.";
            }
            if (isset($seen[$norm])) {
                $errors[] = "form.fields[{$index}].validation.allowed_extensions contiene duplicados.";
            }
            $seen[$norm] = true;
        }
    }

    return $errors;
}
```

En `validate()`, tras el bloque `statesBlockErrors` (≈L164), añadir:

```php
foreach (self::uploadsBlockErrors($config) as $uploadError) {
    $errors[] = $uploadError;
}
```

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Crud/Upload/CrudConfigValidatorUploads` / Expected: PASS.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Crud/State/CrudConfigValidatorStates Crud/CrudConfigValidatorShape` / Expected: PASS (sin regresión validator existente).

- [ ] **Step 6: Commit** — `git add src/Application/Services/CrudConfigValidator.php tests/Crud/Upload/CrudConfigValidatorUploadsTest.php` · mensaje: `fix(crud): validate uploads block and require file allowlists (C6)`

---

### Task 4: Documentación § uploads + semver `1.2.8`

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p03-uploads-hardening`  
**Depends on:** Task 1–3  
**Files:**
- Modify: `docs/modules/crud/modulo-crud-engine.md`
- Create: `tests/Docs/CrudModuleUploadsTest.php`
- Modify: `composer.json`, `config/app.php`, `skeleton/config/app.php`
- Test: `tests/Docs/CrudModuleUploadsTest.php`, `tests/Docs/PlatformVersionSemverTest.php`
**Interfaces:**
- Consumes: trío semver @ `1.2.7`
- Produces: trío @ `1.2.8`; docs con ejemplo seguro

- [ ] **Step 1: Escribir el test que falla** — crear `tests/Docs/CrudModuleUploadsTest.php`:

```php
<?php

declare(strict_types=1);

test('modulo-crud-engine documenta allowlist obligatoria para uploads', function (): void {
    $doc = file_get_contents(dirname(__DIR__, 2) . '/docs/modules/crud/modulo-crud-engine.md');
    assert_true(str_contains($doc, 'allowed_extensions'), 'doc debe mencionar allowed_extensions');
    assert_true(str_contains($doc, 'uploads.enabled'), 'doc debe mencionar uploads.enabled');
    assert_true(
        str_contains($doc, 'uploads/') && str_contains($doc, 'public_path'),
        'doc debe documentar public_path bajo uploads/'
    );
});
```

- [ ] **Step 2: Ejecutar el test y comprobar el fallo** — Run: `php tests/run.php Docs/CrudModuleUploads` / Expected: FAIL (doc incompleta).

- [ ] **Step 3: Implementar el cambio mínimo** — en `docs/modules/crud/modulo-crud-engine.md`, ampliar la sección de uploads (≈L264) con:

```markdown
### Uploads seguros (C6)

Si `"uploads": { "enabled": true }`:

1. `public_path` **obligatorio**, formato `uploads/<colección>` (solo `[a-z0-9_/]`, sin `..`).
2. Cada campo `form.fields[]` con `"type": "file"` **debe** incluir `validation.allowed_extensions` (array no vacío, minúsculas, sin punto).
3. Extensiones prohibidas en plataforma: `php`, `phtml`, `phar`, `htaccess`, `svg`.
4. El motor rechaza configs inválidas al cargar (`CrudConfigValidator`) y rechaza uploads runtime sin allowlist.

Ejemplo mínimo:

\`\`\`json
"uploads": { "enabled": true, "public_path": "uploads/cruds/contratos" },
"form": { "fields": [
  {
    "name": "pdf_contrato",
    "label": "Contrato PDF",
    "type": "file",
    "help_text": "Solo PDF, máx. 5 MB",
    "validation": { "allowed_extensions": ["pdf"] }
  }
]}
\`\`\`

Consumidores (`Lebytek_Portal`, tenants): auditar `config/cruds/**/*.json` antes de bump a Framework `>=1.2.8`.
```

Bump semver a **`1.2.8`** en `composer.json`, `config/app.php`, `skeleton/config/app.php`.

- [ ] **Step 4: Verificación enfocada** — Run: `php tests/run.php Docs/CrudModuleUploads PlatformVersionSemver` / Expected: PASS @ `1.2.8`.

- [ ] **Step 5: Regresión relevante** — Run: `php tests/run.php Kernel/SkeletonPurity` / Expected: 13/13 PASS.

- [ ] **Step 6: Commit** — `git add docs/modules/crud/modulo-crud-engine.md tests/Docs/CrudModuleUploadsTest.php composer.json config/app.php skeleton/config/app.php` · mensaje: `docs(crud): uploads hardening runbook and bump 1.2.8 (C6)`

---

### Task 5: Gate final regresión + evidencia consumidor

**Repository:** `Parzival2103/Lebytek_Framework`  
**Branch:** `feature/crud-p03-uploads-hardening`  
**Depends on:** Task 1–4  
**Files:**
- Test: suites listadas (sin cambios de código salvo fixes menores si gate falla)
**Interfaces:**
- Consumes: todas las tareas previas
- Produces: evidencia PASS para PR Framework

- [ ] **Step 1: Escribir el test que falla** — N/A (gate de integración). Confirmar demos cargan:

```bash
php -r "
require 'vendor/autoload.php';
// smoke: CrudConfigValidator en harness requiere bootstrap completo — usar tests/run.php
"
```

Usar tests existentes como gate.

- [ ] **Step 2: Ejecutar regresión funcional completa** — Run:

```bash
php tests/run.php Security/UploadValidator
php tests/run.php Archivos/FileUploadService
php tests/run.php Crud/Upload
php tests/run.php Security
php tests/run.php Kernel/SkeletonPurity
```

Expected: **0 failed** en todas.

- [ ] **Step 3: Verificar avatares sin regresión (U8)** — Run: `php tests/run.php Archivos` (incluye paths avatar si existen) y, si presente en harness, suite Avatares:

```bash
php tests/run.php 2>&1 | rg -i avatar || true
php tests/run.php Archivos
```

Expected: PASS; `SubirAvatarUseCase` sigue pasando allowlist `['jpg','jpeg','png','webp']`.

- [ ] **Step 4: Verificación espejo demos** — Run:

```bash
cmp config/cruds/demo_productos.json skeleton/config/cruds/demo_productos.json
cmp config/cruds/demo_pedidos.json skeleton/config/cruds/demo_pedidos.json
rg '"enabled": true' config/cruds skeleton/config/cruds || true
```

Expected: `cmp` exit 0; ningún demo con `uploads.enabled=true` (mitigación accidental intacta).

- [ ] **Step 5: Regresión cross-suite** — Run: `php tests/run.php Crud/State Crud/Action/Security` / Expected: PASS (p01/p02 no regresionados).

- [ ] **Step 6: Commit** — solo si fixes de gate: `git add <archivos>` · mensaje: `test(crud): uploads C6 regression gate fixes`

**Requiere operador humano:** sí — publicar tag `v1.2.8` **después** de tag `v1.2.7` (REL-C1); bump `composer.lock` Portal; auditar JSON `dom_*` en `Lebytek_Portal` (repo no verificable desde automation — M6).

---

## Criterios de aceptación

- [ ] **C6 config:** recurso con `uploads.enabled=true` y campo `file` sin `allowed_extensions` no carga (validator).
- [ ] **C6 config:** `public_path` con `..` o fuera de patrón `uploads/...` rechazado.
- [ ] **C6 runtime:** `UploadValidator` rechaza `null`/`[]` allowlist.
- [ ] **C6 runtime:** denylist bloquea `php`, `svg`, etc. incluso en allowlist.
- [ ] **C6 path:** `FileUploadService` no escribe fuera de `{PUBLIC_PATH}/uploads/`.
- [ ] **U2:** mensaje runtime incluye extensiones permitidas cuando allowlist conocida.
- [ ] **U4/U5:** errores config vs runtime distinguibles (validator load vs POST form).
- [ ] **U8:** avatares sin regresión.
- [ ] Semver trío @ `1.2.8`; tag Git publicado post-merge y post-REL-C1.
- [ ] `php tests/run.php SkeletonPurity` PASS.
- [ ] Sin cambios Portal en este repo.

## Fuera de alcance

- CAS/TOCTOU (CRUD-C4, punto 4).
- RBAC router CRUD (M3 / plan 2026-08-06).
- API health público (M4).
- Invoicing Facturapi hardening (plan 2026-08-08, 0/10).
- Migrar uploads a disco private + descarga autenticada (Enfoque C del spec).
- Editar JSON CRUD negocio en `Lebytek_Portal` (checklist documentado only).
- Deploy VPS / SSH / producción.
- Merge `feature/backoffice-api-integration` → `main`.

## Riesgos y rollback

| Riesgo | Mitigación |
|--------|------------|
| Config Portal legacy rechazada tras bump | Checklist spec § Migración; mensajes validator explícitos |
| REL-C1 no publicado antes de tag 1.2.8 | Task 5 marca ops humano; no taggear 1.2.8 antes de v1.2.7 |
| Regresión avatares | Allowlist explícita ya cumple; tests Archivos en gate |
| `realpath(PUBLIC_PATH)` falla en install mínimo | Harness crea `public/`; test usa `PUBLIC_PATH` temporal |
| Breaking configs con uploads ON sin allowlist | Deseado — corrección en consumidor |

**Rollback:** revertir PR implementación; consumidores mantienen lock anterior; mitigación operativa temporal: `uploads.enabled=false`.

## Evidencia que debe recopilar el ejecutor

- Salida PASS de comandos Task 5.
- PR Framework URL + SHA merge.
- Nota semver: tag `v1.2.8` publicado tras `v1.2.7`.
- Para Portal (cuando accesible): lista de JSON CRUD corregidos con `allowed_extensions`.

## Estado de ejecución

| Campo | Valor |
|-------|-------|
| Plan creado UTC | 2026-08-09 |
| Framework `origin/main` referencia | `487ccd8132e7c42eabd2a0e3b335b075ccc123e1` |
| Tareas completadas / totales | 0 / 5 |
| Siguiente tarea ejecutable | Task 1 |
| Prerrequisitos | p01 (#95) + p02 (#100) en main; PHP ≥8.2 |
| Bloqueos | Tag `v1.2.7` (REL-C1, PR #105) no publicado — bloquea release tag `v1.2.8`, no bloquea rama feature. Portal SHA no verificable (M6). |
| Estado | Pendiente de implementación |
