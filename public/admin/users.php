<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\Auth;

start_secure_session();
require_csrf_if_post();

$auth = new Auth(db());
$auth->requireRole('admin');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

$msg = null; $err = null;

/* ========= Acciones ========= */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($action === 'create') {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'name'     => trim($_POST['name'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'role'     => $_POST['role'] ?? 'sales',
            'password' => $_POST['password'] ?? '',
            'must_change_pass' => 1,
            'active'   => isset($_POST['active']) ? 1 : 0,
        ];
        if ($data['username']==='' || $data['name']==='' || strlen($data['password'])<8) {
            $err = 'Datos inválidos (usuario/nombre obligatorios y contraseña >= 8).';
        } else {
            try {
                $auth->create($data);
                $msg = 'Usuario creado.';
            } catch (\Throwable $e) {
                $err = 'No se pudo crear (¿usuario duplicado?).';
            }
        }
    }

    if ($action === 'update' && $id) {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'name'     => trim($_POST['name'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'role'     => $_POST['role'] ?? 'sales',
            'active'   => isset($_POST['active']) ? 1 : 0,
        ];
        if ($data['username']==='' || $data['name']==='') {
            $err = 'Usuario y nombre son obligatorios.';
        } else {
            $ok = $auth->update($id, $data);
            $ok ? $msg='Usuario actualizado.' : $err='No se pudo actualizar.';
        }
    }

    if ($action === 'reset' && $id) {
        $temp = $_POST['temp_password'] ?? '';
        if (strlen($temp) < 8) {
            $err = 'La contraseña temporal debe tener al menos 8 caracteres.';
        } else {
            $ok = $auth->resetPassword($id, $temp, true);
            $ok ? $msg='Contraseña reseteada.' : $err='No se pudo resetear.';
        }
    }
}

$rows = $auth->listAll();
$current = $id ? $auth->findById($id) : null;

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin — Usuarios</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
    <header class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold">Usuarios</h1>
        <p class="text-sm text-gray-600">Crea, edita y gestiona accesos.</p>
      </div>
      <nav class="text-sm">
        <a href="/public/admin/parametros.php" class="text-gray-600 hover:underline mr-4">Parámetros</a>
        <a href="/public/admin/acabados.php" class="text-gray-600 hover:underline mr-4">Acabados</a>
        <a href="/public/admin/logout.php" class="text-gray-600 hover:underline">Salir</a>
      </nav>
    </header>

    <?php if ($msg): ?>
      <div class="mb-4 px-4 py-2 rounded bg-green-50 text-green-700 text-sm"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="mb-4 px-4 py-2 rounded bg-red-50 text-red-700 text-sm"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Listado -->
      <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Listado</h2>
          <a href="/public/admin/users.php" class="text-sm text-gray-600 hover:underline">Refrescar</a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="py-2 pr-3">Usuario</th>
                <th class="py-2 pr-3">Nombre</th>
                <th class="py-2 pr-3">Rol</th>
                <th class="py-2 pr-3">Estado</th>
                <th class="py-2">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5" class="py-4 text-gray-500">Sin usuarios.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr class="border-b hover:bg-gray-50">
                  <td class="py-2 pr-3 font-mono"><?= htmlspecialchars($r['username']) ?></td>
                  <td class="py-2 pr-3"><?= htmlspecialchars($r['name']) ?></td>
                  <td class="py-2 pr-3"><?= htmlspecialchars($r['role']) ?></td>
                  <td class="py-2 pr-3"><?= $r['active']?'Activo':'Inactivo' ?><?= $r['must_change_pass']?' · <span class="text-amber-600">Debe cambiar clave</span>':'' ?></td>
                  <td class="py-2">
                    <a class="text-blue-600 hover:underline" href="/public/admin/users.php?id=<?= (int)$r['id'] ?>">Editar</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Editor / Crear -->
      <div class="bg-white rounded-2xl shadow p-5">
        <?php if (!$current): ?>
          <h2 class="text-lg font-semibold mb-3">Crear usuario</h2>
          <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div>
              <label class="block text-sm font-medium mb-1">Usuario</label>
              <input type="text" name="username" required class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input type="text" name="name" required class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Email (opcional)</label>
              <input type="email" name="email" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Rol</label>
              <select name="role" class="w-full border rounded-lg p-2">
                <option value="admin">admin</option>
                <option value="sales" selected>sales</option>
                <option value="viewer">viewer</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Contraseña temporal</label>
              <input type="password" name="password" required class="w-full border rounded-lg p-2" placeholder="mín. 8 caracteres">
            </div>
            <div class="flex items-center gap-2">
              <input type="checkbox" name="active" id="active" checked>
              <label for="active" class="text-sm">Activo</label>
            </div>
            <button class="bg-black text-white rounded-lg px-4 py-2">Crear usuario</button>
          </form>
        <?php else: ?>
          <h2 class="text-lg font-semibold mb-3">Editar usuario</h2>
          <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
            <div>
              <label class="block text-sm font-medium mb-1">Usuario</label>
              <input type="text" name="username" required class="w-full border rounded-lg p-2" value="<?= htmlspecialchars($current['username']) ?>">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input type="text" name="name" required class="w-full border rounded-lg p-2" value="<?= htmlspecialchars($current['name']) ?>">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Email</label>
              <input type="email" name="email" class="w-full border rounded-lg p-2" value="<?= htmlspecialchars($current['email'] ?? '') ?>">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Rol</label>
              <select name="role" class="w-full border rounded-lg p-2">
                <?php foreach (['admin','sales','viewer'] as $r): ?>
                  <option value="<?= $r ?>" <?= $current['role']===$r?'selected':'' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <input type="checkbox" name="active" id="active" <?= $current['active']?'checked':'' ?>>
              <label for="active" class="text-sm">Activo</label>
            </div>
            <button class="bg-black text-white rounded-lg px-4 py-2">Guardar cambios</button>
          </form>

          <div class="mt-6 border-t pt-4">
            <h3 class="text-md font-semibold mb-2">Resetear contraseña</h3>
            <form method="POST" class="flex items-end gap-3">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="reset">
              <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
              <div class="flex-1">
                <label class="block text-sm font-medium mb-1">Nueva contraseña temporal</label>
                <input type="text" name="temp_password" class="w-full border rounded-lg p-2" placeholder="mín. 8 caracteres">
              </div>
              <button class="bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-4 py-2">Resetear</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
