<?php

declare(strict_types=1);

/** Helper de escape para las vistas. */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES); }

switch ($paso) {

    case 'requisitos':
        $checks = $installer->requisitosCheck();
        $puedeSeguir = !in_array(false, array_column($checks, 'ok'), true);
        wizard_render('paso_requisitos', [
            'tituloPaso' => 'Requisitos', 'checks' => $checks, 'puedeSeguir' => $puedeSeguir, 'csrf' => $csrf,
        ]);
        break;

    case 'bd':
        $checks = $installer->requisitosCheck();
        $bd = array_values(array_filter($checks, fn($c) => $c['clave'] === 'bd'))[0] ?? ['ok' => false, 'detalle' => 'Sin información'];
        wizard_render('paso_bd', ['tituloPaso' => 'Base de datos', 'bd' => $bd, 'csrf' => $csrf]);
        break;

    case 'modulos':
        $manifests = $registry->all();
        wizard_render('paso_modulos', ['tituloPaso' => 'Módulos', 'manifests' => $manifests, 'csrf' => $csrf]);
        break;

    case 'admin':
        // Guarda selección de módulos en sesión.
        $sel = $_POST['modulos'] ?? ['core'];
        $_SESSION['install_modulos'] = array_values(array_unique(array_merge(['core'], is_array($sel) ? $sel : [])));
        wizard_render('paso_admin', ['tituloPaso' => 'Cuenta admin', 'csrf' => $csrf]);
        break;

    case 'revision':
        // Guarda y valida datos del admin.
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');
        $errores = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errores[] = 'Email inválido.'; }
        if (strlen($pass) < 8) { $errores[] = 'La contraseña debe tener al menos 8 caracteres.'; }
        if ($errores !== []) {
            wizard_render('paso_admin', ['tituloPaso' => 'Cuenta admin', 'csrf' => $csrf, 'errores' => $errores, 'email' => $email]);
        }
        $_SESSION['install_admin'] = ['email' => $email, 'password' => $pass];

        $seleccion = $_SESSION['install_modulos'] ?? ['core'];
        $plan = $installer->plan($seleccion);
        wizard_render('paso_revision', ['tituloPaso' => 'Revisión', 'plan' => $plan, 'seleccion' => $seleccion, 'csrf' => $csrf]);
        break;

    case 'ejecutar':
        $seleccion = $_SESSION['install_modulos'] ?? ['core'];
        $admin     = $_SESSION['install_admin'] ?? null;

        // Schema base primero.
        (new App\Infrastructure\Install\SqlFileRunner())->ejecutar(ROOT_PATH . '/database/schema/schema.sql');

        $plan = $installer->plan($seleccion);
        $installer->aplicar($plan);

        // Escribir config/vertical.php con módulos activos.
        instalar_escribir_vertical($seleccion);

        // Crear/actualizar admin.
        if (is_array($admin)) {
            instalar_crear_admin($admin['email'], $admin['password']);
        }

        // Lock file.
        $resumen = json_encode([
            'version'  => Config::get('app.version', '0.0.0'),
            'fecha'    => date('c'),
            'modulos'  => $seleccion,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(STORAGE_PATH . '/install.lock', (string) $resumen);

        // Limpiar estado del asistente.
        unset($_SESSION['install_modulos'], $_SESSION['install_admin']);

        wizard_render('paso_resultado', [
            'tituloPaso' => 'Listo',
            'version'    => (string) Config::get('app.version', '0.0.0'),
            'seleccion'  => $seleccion,
        ]);
        break;

    default:
        http_response_code(404);
        echo 'Paso desconocido.';
}

/**
 * Escribe config/vertical.php conservando labels si existían.
 *
 * @param list<string> $seleccion
 */
function instalar_escribir_vertical(array $seleccion): void
{
    $ruta = ROOT_PATH . '/config/vertical.php';
    $labels = ['menu' => []];
    if (is_file($ruta)) {
        $actual = require $ruta;
        $labels = $actual['labels'] ?? ['menu' => []];
    }

    // dashboard/administracion son módulos de plataforma siempre activos;
    // el resto se enciende según selección.
    $modules = ['dashboard' => true, 'administracion' => true];
    foreach ($seleccion as $clave) {
        if ($clave === 'core') { continue; }
        $modules[$clave] = true;
    }

    $export = var_export(['modules' => $modules, 'labels' => $labels], true);
    $php = "<?php\n\ndeclare(strict_types=1);\n\n// Generado por el instalador (" . date('c') . ").\nreturn {$export};\n";
    file_put_contents($ruta, $php);
}

function instalar_crear_admin(string $email, string $passwordPlano): void
{
    $pdo  = Connection::getInstance();
    $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM auth_usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        $upd = $pdo->prepare("UPDATE auth_usuarios SET password = ?, activo = 1 WHERE id = ?");
        $upd->execute([$hash, (int) $row['id']]);
        $usuarioId = (int) $row['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO auth_usuarios (nombre, apellido, email, password, activo) VALUES (?, ?, ?, ?, 1)");
        $ins->execute(['Administrador', 'Sistema', $email, $hash]);
        $usuarioId = (int) $pdo->lastInsertId();
    }

    // Asignar rol administrador.
    $rol = $pdo->query("SELECT id FROM auth_roles WHERE slug = 'administrador' LIMIT 1")->fetch();
    if ($rol) {
        $rel = $pdo->prepare("INSERT IGNORE INTO auth_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)");
        $rel->execute([$usuarioId, (int) $rol['id']]);
    }
}
