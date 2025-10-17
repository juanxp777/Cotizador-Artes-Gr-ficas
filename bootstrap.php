<?php
/**
 * bootstrap.php
 *
 * Punto de arranque de la app (XAMPP/Hostinger).
 * - Carga config.php
 * - Inicia sesión segura y configura errores
 * - Define rutas BASE/APP
 * - Autoload simple para clases en app/ (sin Composer)
 * - Incluye helpers (p.ej. pricing.php) cuando existan
 */

declare(strict_types=1);

// 1) Cargar configuración y utilidades base
require_once __DIR__ . '/config.php';

// 2) Sesión segura y modo debug
start_secure_session();
$DEBUG = (env('APP_DEBUG', '0') === '1');

// 3) Rutas base (ajústalas si mueves carpetas)
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');

// 4) Asegurar estructura mínima de carpetas (no falla si ya existen)
foreach (['/app', '/app/helpers', '/app/services', '/storage'] as $rel) {
    $dir = BASE_PATH . $rel;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// 5) Autoloader simple para clases bajo el namespace App\*
/*
 * Convención:
 *   App\Services\PricingEngine    -> app/services/PricingEngine.php
 *   App\Helpers\Whatever          -> app/helpers/Whatever.php   (si usas clases)
 *
 * Para helpers "tipo funciones" en un archivo único (pricing.php),
 * se cargan manualmente más abajo con require_once.
 */
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . DIRECTORY_SEPARATOR;
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return; // otra librería/namespace, ignorar
    }
    $relative = substr($class, $len);
    $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative) . '.php';

    // services o helpers con clases
    $candidate = $baseDir . $relativePath;
    if (is_file($candidate)) {
        require_once $candidate;
        return;
    }
    // fallback: intenta en minúsculas por si decides así
    $candidateLower = $baseDir . strtolower($relativePath);
    if (is_file($candidateLower)) {
        require_once $candidateLower;
        return;
    }
});

// 6) Helpers funcionales (no clases)
//    El siguiente archivo lo crearemos en el próximo paso:
//    app/helpers/pricing.php  -> UPS, mermas, lomo, etc.
$helpers = [
    APP_PATH . '/helpers/pricing.php',
    APP_PATH . '/helpers/auth.php'
];

foreach ($helpers as $helperFile) {
    if (is_file($helperFile)) {
        require_once $helperFile;
    } else if ($DEBUG) {
        // En desarrollo, avisa si falta (no es fatal para no romper flujo)
        error_log("[bootstrap] Helper faltante (se intentó incluir): $helperFile");
    }
}

// 7) Conexión a BD de prueba temprana (opcional, asegura que hay acceso)
//    Puedes comentarlo si no quieres “tocar” la BD en cada request del front.
try {
    $pdo = db();
} catch (Throwable $e) {
    if ($DEBUG) {
        die('No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage()));
    }
    http_response_code(500);
    die('Error interno de servidor.');
}

// 8) Protección mínima para endpoints POST (CSRF)
//    En tus formularios, incluye: <input type="hidden" name="csrf" value="<?= csrf_token() ? >
function require_csrf_if_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();
    }
}

// 9) Pequeñas utilidades de rutas para vistas/includes
function view_path(string $file): string {
    // Si usas /public como docroot en Hostinger, tus vistas pueden quedar en /views
    // Puedes ajustar esta función más adelante si creas un motor de vistas
    return BASE_PATH . '/views/' . ltrim($file, '/');
}

function asset_url(string $path): string {
    // Simple helper para assets estáticos si sirves desde /public
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $base . '/public/' . ltrim($path, '/');
}

// 10) Logger simple (a /storage/app.log)
function app_log(string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(BASE_PATH . '/storage/app.log', $line, FILE_APPEND);
}

// 11) Cabeceras por defecto (seguras y útiles)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
// Puedes habilitar CSP si no usas Tailwind CDN en producción
// header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'");

// 12) ¡Listo! El resto de scripts puede incluir solo este archivo
// require_once __DIR__ . '/bootstrap.php';
