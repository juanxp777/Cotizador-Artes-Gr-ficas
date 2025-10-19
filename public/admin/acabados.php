<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\FinishingRepo;
use function App\Helpers\{require_admin, flash_set, flash_get};

require_admin();
require_csrf_if_post();

$repo = new FinishingRepo(db());
$flashes = [];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

/* ================= Acciones ================= */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if ($action === 'create' || $action === 'update') {
    $name    = trim($_POST['name'] ?? '');
    $pricing = $_POST['pricing'] ?? 'per_unit';
    $setup   = (float)($_POST['setup'] ?? 0);
    $active  = isset($_POST['active']) ? 1 : 0;
    $notes   = trim($_POST['notes'] ?? '');

    if ($name === '') {
      flash_set('error', 'El nombre es obligatorio.');
    } else {
      if ($action === 'create') {
        $newId = $repo->create($name, $pricing, $setup, $active, $notes ?: null);
        $newId ? flash_set('success','Acabado creado.') : flash_set('error','No se pudo crear.');
        header('Location: /public/admin/acabados.php?id='.$newId);
        exit;
      } else {
        $ok = $repo->update($id, $name, $pricing, $setup, $active, $notes ?: null);
        $ok ? flash_set('success','Acabado actualizado.') : flash_set('error','No se pudo actualizar.');
      }
    }
    header('Location: /public/admin/acabados.php'.($id?'?id='.$id:''));
    exit;
  }

  if ($action === 'delete' && $id) {
    $ok = $repo->delete($id);
    $ok ? flash_set('success','Acabado eliminado.') : flash_set('error','No se pudo eliminar.');
    header('Location: /public/admin/acabados.php');
    exit;
  }

  if ($action === 'add_tier' && $id) {
    $min = (int)($_POST['min_qty'] ?? 1);
    $max = (int)($_POST['max_qty'] ?? 999999);
    $cost= (float)($_POST['cost'] ?? 0);
    if ($min <= 0 || $max < $min || $cost < 0) {
      flash_set('error','Valores inválidos en el tramo.');
    } else {
      $ok = $repo->addTier($id, $min, $max, $cost);
      $ok ? flash_set('success','Tramo agregado.') : flash_set('error','No se pudo agregar el tramo.');
    }
    header('Location: /public/admin/acabados.php?id='.$id);
    exit;
  }

  if ($action === 'delete_tier' && !empty($_POST['tier_id'])) {
    $tid = (int)$_POST['tier_id'];
    $ok = $repo->deleteTier($tid);
    $ok ? flash_set('success','Tramo eliminado.') : flash_set('error','No se pudo eliminar el tramo.');
    header('Location: /public/admin/acabados.php?id='.$id);
    exit;
  }
}

$flashes = flash_get();

/* ================= Datos para la vista ================= */
$current = $id ? $repo->find($id) : null;
$list    = $repo->all(false);

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin — Acabados con Tramos</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
    <header class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold">Acabados (con tramos)</h1>
        <p class="text-sm text-gray-600">Gestiona precios por rangos de cantidad.</p>
      </div>
      <nav class="text-sm">
        <a href="/public/admin/parametros.php" class="text-gray-600 hover:underline mr-4">Parámetros</a>
        <a href="/public/admin/logout.php" class="text-gray-600 hover:underline">Cerrar sesión</a>
      </nav>
    </header>

    <?php foreach ($flashes as $f): ?>
      <div class="mb-4 px-4 py-2 rounded <?= $f['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700' ?>">
        <?= htmlspecialchars($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Listado -->
      <div class="bg-white rounded-2xl shadow p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Listado</h2>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <button class="text-sm bg-black text-white px-3 py-1 rounded">+ Nuevo</button>
          </form>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="py-2 pr-3">Nombre</th>
                <th class="py-2 pr-3">Modo</th>
                <th class="py-2 pr-3">Setup</th>
                <th class="py-2">Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$list): ?>
                <tr><td colspan="4" class="py-4 text-gray-500">Sin registros.</td></tr>
              <?php else: foreach ($list as $row): ?>
                <tr class="border-b hover:bg-gray-50">
                  <td class="py-2 pr-3">
                    <a class="text-blue-600 hover:underline" href="/public/admin/acabados.php?id=<?= (int)$row['id'] ?>">
                      <?= htmlspecialchars($row['name']) ?>
                    </a>
                  </td>
                  <td class="py-2 pr-3"><?= htmlspecialchars($row['pricing']) ?></td>
                  <td class="py-2 pr-3">$<?= number_format((float)$row['setup'],0,',','.') ?></td>
                  <td class="py-2"><?= $row['active'] ? 'Activo' : 'Inactivo' ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Editor -->
      <div class="bg-white rounded-2xl shadow p-5">
        <?php if (!$current): ?>
          <h2 class="text-lg font-semibold mb-3">Crear nuevo acabado</h2>
          <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <div>
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input type="text" name="name" class="w-full border rounded-lg p-2" required placeholder="Plastificado Mate">
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Modo de cobro</label>
              <select name="pricing" class="w-full border rounded-lg p-2">
                <option value="per_unit">Por unidad</option>
                <option value="per_m2">Por m²</option>
                <option value="flat">Tarifa fija</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Setup (opcional)</label>
              <input type="number" name="setup" step="0.01" class="w-full border rounded-lg p-2" value="0">
            </div>

            <div class="flex items-center gap-2">
              <input type="checkbox" name="active" id="active" checked>
              <label for="active" class="text-sm">Activo</label>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Notas</label>
              <input type="text" name="notes" class="w-full border rounded-lg p-2" placeholder="Observaciones (opcional)">
            </div>

            <button class="bg-black text-white rounded-lg px-4 py-2">Guardar</button>
          </form>
        <?php else: ?>
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold mb-3">Editar: <?= htmlspecialchars($current['name']) ?></h2>
            <form method="POST" onsubmit="return confirm('¿Eliminar acabado?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
              <button class="text-red-600 hover:underline">Eliminar</button>
            </form>
          </div>

          <form method="POST" class="space-y-3 mb-6">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">

            <div>
              <label class="block text-sm font-medium mb-1">Nombre</label>
              <input type="text" name="name" class="w-full border rounded-lg p-2" required value="<?= htmlspecialchars($current['name']) ?>">
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Modo de cobro</label>
              <select name="pricing" class="w-full border rounded-lg p-2">
                <option value="per_unit" <?= $current['pricing']==='per_unit'?'selected':'' ?>>Por unidad</option>
                <option value="per_m2"   <?= $current['pricing']==='per_m2'?'selected':'' ?>>Por m²</option>
                <option value="flat"     <?= $current['pricing']==='flat'?'selected':'' ?>>Tarifa fija</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Setup</label>
              <input type="number" name="setup" step="0.01" class="w-full border rounded-lg p-2" value="<?= htmlspecialchars($current['setup']) ?>">
            </div>

            <div class="flex items-center gap-2">
              <input type="checkbox" name="active" id="active" <?= $current['active']?'checked':'' ?>>
              <label for="active" class="text-sm">Activo</label>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Notas</label>
              <input type="text" name="notes" class="w-full border rounded-lg p-2" value="<?= htmlspecialchars($current['notes'] ?? '') ?>">
            </div>

            <button class="bg-black text-white rounded-lg px-4 py-2">Guardar cambios</button>
          </form>

          <!-- Tramos -->
          <div>
            <h3 class="text-md font-semibold mb-3">Tramos de precio</h3>

            <div class="overflow-x-auto mb-4">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left text-gray-600 border-b">
                    <th class="py-2 pr-3">Mín</th>
                    <th class="py-2 pr-3">Máx</th>
                    <th class="py-2 pr-3">Costo</th>
                    <th class="py-2">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($current['tiers'])): ?>
                    <tr><td colspan="4" class="py-4 text-gray-500">Sin tramos aún.</td></tr>
                  <?php else: foreach ($current['tiers'] as $t): ?>
                    <tr class="border-b">
                      <td class="py-2 pr-3"><?= (int)$t['min_qty'] ?></td>
                      <td class="py-2 pr-3"><?= (int)$t['max_qty'] ?></td>
                      <td class="py-2 pr-3">$<?= number_format((float)$t['cost'],0,',','.') ?></td>
                      <td class="py-2">
                        <form method="POST" onsubmit="return confirm('¿Eliminar tramo?');" class="inline">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_tier">
                          <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
                          <input type="hidden" name="tier_id" value="<?= (int)$t['id'] ?>">
                          <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

            <form method="POST" class="grid grid-cols-3 gap-3">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_tier">
              <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">

              <div>
                <label class="block text-sm font-medium mb-1">Mín</label>
                <input type="number" name="min_qty" class="w-full border rounded-lg p-2" value="1" required>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Máx</label>
                <input type="number" name="max_qty" class="w-full border rounded-lg p-2" value="999" required>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Costo</label>
                <input type="number" step="0.01" name="cost" class="w-full border rounded-lg p-2" value="0" required>
              </div>

              <div class="col-span-3">
                <button class="bg-black text-white rounded-lg px-4 py-2">Agregar tramo</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
