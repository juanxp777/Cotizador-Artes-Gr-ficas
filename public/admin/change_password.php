<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\Auth;

start_secure_session();
require_csrf_if_post();

$auth = new Auth(db());
$auth->requireLogin();

$u = $auth->current();
$msg = null; $err = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $current = $_POST['current'] ?? '';
    $new1 = $_POST['new1'] ?? '';
    $new2 = $_POST['new2'] ?? '';

    if ($new1 !== $new2) {
        $err = 'Las contraseñas nuevas no coinciden.';
    } elseif (strlen($new1) < 8) {
        $err = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } else {
        if ($auth->changeOwnPassword((int)$u['id'], $current, $new1)) {
            // actualiza flag en sesión
            $_SESSION['user']['must_change_pass'] = false;
            $msg = 'Contraseña actualizada.';
        } else {
            $err = 'Contraseña actual incorrecta.';
        }
    }
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambiar contraseña</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="min-h-screen grid place-items-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold mb-2">Cambiar contraseña</h1>
      <p class="text-sm text-gray-600 mb-4">Usuario: <b><?= htmlspecialchars($u['username']) ?></b></p>

      <?php if ($msg): ?>
        <div class="mb-3 text-sm px-3 py-2 rounded bg-green-50 text-green-700"><?= htmlspecialchars($msg) ?></div>
        <p class="text-sm"><a class="text-blue-600 hover:underline" href="/public/admin/parametros.php">Ir al panel</a></p>
      <?php endif; ?>

      <?php if ($err): ?>
        <div class="mb-3 text-sm px-3 py-2 rounded bg-red-50 text-red-700"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Contraseña actual</label>
          <input type="password" name="current" required class="w-full border rounded-lg p-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Nueva contraseña</label>
          <input type="password" name="new1" required class="w-full border rounded-lg p-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Repetir nueva contraseña</label>
          <input type="password" name="new2" required class="w-full border rounded-lg p-2">
        </div>
        <button class="w-full bg-black text-white rounded-lg py-2 hover:opacity-90">Actualizar</button>
      </form>
    </div>
  </div>
</body>
</html>
