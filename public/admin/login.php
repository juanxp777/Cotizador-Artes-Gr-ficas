<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\Auth;

start_secure_session();
require_csrf_if_post();

$auth = new Auth(db());

if ($auth->check()) {
    // Si debe cambiar password, vete a change_password
    $u = $auth->current();
    if (!empty($u['must_change_pass'])) {
        header('Location: /public/admin/change_password.php');
        exit;
    }
    header('Location: /public/admin/parametros.php');
    exit;
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = $_POST['pass'] ?? '';
    if ($auth->login($user, $pass)) {
        $u = $auth->current();
        if (!empty($u['must_change_pass'])) {
            header('Location: /public/admin/change_password.php');
        } else {
            header('Location: /public/admin/parametros.php');
        }
        exit;
    } else {
        $error = 'Usuario o contraseña inválidos, o usuario inactivo.';
    }
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin — Iniciar sesión</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="min-h-screen grid place-items-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold mb-2">Panel Administrativo</h1>
      <p class="text-sm text-gray-600 mb-4">Inicia sesión con tu usuario.</p>

      <?php if ($error): ?>
        <div class="mb-3 text-sm px-3 py-2 rounded bg-red-50 text-red-700"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Usuario</label>
          <input type="text" name="user" required class="w-full border rounded-lg p-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Contraseña</label>
          <input type="password" name="pass" required class="w-full border rounded-lg p-2">
        </div>
        <button class="w-full bg-black text-white rounded-lg py-2 hover:opacity-90">Entrar</button>
      </form>
      <p class="text-xs text-gray-500 mt-4">Si es tu primer ingreso, el admin te dará una contraseña temporal.</p>
    </div>
  </div>
</body>
</html>
