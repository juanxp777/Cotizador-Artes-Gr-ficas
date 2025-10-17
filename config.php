<?php
/**
 * config.php
 * Configuración central de la aplicación (compatible con Hostinger / PHP 8+)
 * - Lee variables de entorno (ENV) para credenciales
 * - Expone una conexión PDO reutilizable vía db()
 * - Define helpers comunes (env, price, json)
 */

declare(strict_types=1);

// Cargar variables locales si existe el archivo (solo desarrollo)
if (is_file(__DIR__.'/.env.local.php')) { require __DIR__.'/.env.local.php'; }


/* -------------------------------------------------------
 |  Ajustes generales
 * ----------------------------------------------------- */
date_default_timezone_set(getenv('APP_TZ') ?: 'America/Bogota');

$debug = (getenv('APP_DEBUG') === '1');
if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

/* -------------------------------------------------------
 |  Helpers livianos
 * ----------------------------------------------------- */

/**
 * Lee una variable de entorno con valor por defecto.
 */
function env(string $key, $default = null) {
    $val = getenv($key);
    return ($val === false || $val === null || $val === '') ? $default : $val;
}

/**
 * Formato de moneda con separadores “colombianos” por defecto.
 * $0 -> "$0"
 * 1234567.8 -> "$1.234.567"
 */
function price(int|float $value, string $prefix = '$', string $thousands = '.', string $dec = ',', int $decimals = 0): string {
    return $prefix . number_format((float)$value, $decimals, $dec, $thousands);
}

/**
 * JSON seguro (sin errores por UTF-8).
 */
function json_encode_safe($data, int $flags = 0): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $flags);
    return $json === false ? 'null' : $json;
}

/**
 * Decodifica JSON a array asociativo.
 */
function json_decode_array(?string $json): array {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/* -------------------------------------------------------
 |  Configuración de base de datos (ENV en Hostinger)
 * ----------------------------------------------------- */

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'u196943154_cotiza'));
define('DB_USER', env('DB_USER', 'u196943154_user'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

/**
 * Conexión PDO singleton.
 * Uso: $pdo = db();
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Nunca exponer credenciales ni stack en producción
        if (env('APP_DEBUG', '0') === '1') {
            die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        error_log('[DB ERROR] ' . $e->getMessage());
        http_response_code(500);
        die('Error interno de servidor.');
    }

    return $pdo;
}

/* -------------------------------------------------------
 |  Seguridad básica (CSRF)
 * ----------------------------------------------------- */

/**
 * Inicia sesión con flags seguros (idempotente).
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Cookies de sesión “httponly”; en producción puedes activar 'secure' si usas https:
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // actívalo si tienes HTTPS
        ]);
        session_start();
    }
}

/**
 * CSRF token por sesión.
 */
function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Verifica token CSRF en POST.
 */
function csrf_verify(): void {
    start_secure_session();
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die('Solicitud inválida (CSRF).');
    }
}

/* -------------------------------------------------------
 |  Utilidades varias
 * ----------------------------------------------------- */

/**
 * Normaliza enteros provenientes de formularios (evita strings vacíos).
 */
function int_input($val, int $default = 0): int {
    if ($val === '' || $val === null) return $default;
    return (int)$val;
}

/**
 * Normaliza flotantes de formularios (reemplaza coma decimal).
 */
function float_input($val, float $default = 0.0): float {
    if ($val === '' || $val === null) return $default;
    if (is_string($val)) $val = str_replace(',', '.', $val);
    return (float)$val;
}

/**
 * Redondeo configurable (ej: a múltiplos de 100 o 500).
 */
function round_to($value, int $multiple = 100): float {
    if ($multiple <= 1) return (float)round($value);
    return (float)(round($value / $multiple) * $multiple);
}
