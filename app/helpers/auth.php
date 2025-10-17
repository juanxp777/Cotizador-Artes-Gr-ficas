<?php
/**
 * app/helpers/auth.php
 * Autenticación sencilla para panel admin (sin BD): usa variables de entorno.
 *
 * ENV requeridas (en .env.local.php o Hostinger):
 * - ADMIN_USER=admin
 * - ADMIN_PASS_HASH=$2y$10$...   (hash de password_hash())
 *   (opcional temporal en desarrollo) ADMIN_PASS=texto_plano
 */

namespace App\Helpers;

function flash_set(string $type, string $msg): void {
    \start_secure_session();
    $_SESSION['flash'][] = ['type'=>$type, 'msg'=>$msg];
}
function flash_get(): array {
    \start_secure_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function admin_logged_in(): bool {
    \start_secure_session();
    return !empty($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

function admin_login(string $user, string $pass): bool {
    \start_secure_session();
    $envUser = \env('ADMIN_USER', 'admin');
    $envHash = \env('ADMIN_PASS_HASH', '');
    $envPlain= \env('ADMIN_PASS', ''); // SOLO dev

    if ($user !== $envUser) return false;

    $ok = false;
    if ($envHash) {
        $ok = password_verify($pass, $envHash);
    } elseif ($envPlain) {
        $ok = hash_equals($envPlain, $pass);
    }

    if ($ok) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_user'] = $envUser;
        return true;
    }
    return false;
}

function admin_logout(): void {
    \start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure']??false, $params['httponly']??true);
    }
    session_destroy();
}

function require_admin(): void {
    if (!admin_logged_in()) {
        header('Location: /public/admin/login.php');
        exit;
    }
}
