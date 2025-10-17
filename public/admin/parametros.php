<?php
require_once __DIR__ . '/../../bootstrap.php';

use App\Services\Params;
use function App\Helpers\{require_admin, flash_set, flash_get};

require_admin();
require_csrf_if_post();

$p = new Params(db());

// Acciones POST (crear/actualizar/eliminar)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $k = trim($_POST['key'] ?? '');
        $v = trim($_POST['value'] ?? '');
        if ($k === '') {
            flash_set('error', 'La clave no puede estar vacía.');
        } else {
            if ($p->set($k, $v)) {
                flash_set('success', "Parámetro guardado: $k");
            } else {
                flash_set('error', 'No se pudo guardar el parámetro.');
            }
        }
        header('Location: /public/admin/parametros.php');
        exit;
    }

    if ($action === 'delete') {
        $k = trim($_POST['key'] ?? '');
        if ($k !== '') {
            // borrado directo
            $stmt = db()->prepare("DELETE FROM pricing_params WHERE `key` = :k");
            $ok = $stmt->execute([':k'=>$k]);
            $ok ? flash_set('success', "Parámetro eliminado: $k") : flash_set('error','No se pudo eliminar.');
        }
        header('Location: /public/admin/parametros.php');
        exit;
    }
}

$data = $p->all();
$flashes = flash_get();
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin — Parámetros</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-5xl mx-auto p-6">
    <header class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold">Parámetros de Precios</h1>
        <p class="text-sm text-gray-600">Edita costos sin tocar el código.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="/public/admin/logout.php" class="text-sm text-gray-600 hover:underline">Cerrar sesión</a>
      </div>
    </header>

    <?php foreach ($flashes as $f): ?>
      <div class="mb-4 px-4 py-2 rounded <?= $f['type']==='error'?'bg-red-50 text-red-700':'bg-green-50 text-green-700' ?>">
        <?= htmlspecialchars($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <div class="grid md:grid-cols-2 gap-6">
      <!-- Listado -->
      <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="text-lg font-semibold mb-3">Listado</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="py-2 pr-3">Clave</th>
                <th class="py-2 pr-3">Valor</th>
                <th class="py-2">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$data): ?>
                <tr><td colspan="3" class="py-4 text-gray-500">No hay parámetros.</td></tr>
              <?php else: foreach ($data as $k=>$v): ?>
                <tr class="border-b">
                  <td class="py-2 pr-3 font-mono text-xs"><?= htmlspecialchars($k) ?></td>
                  <td class="py-2 pr-3"><?= htmlspecialchars($v) ?></td>
                  <td class="py-2">
                    <form method="POST" class="inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="key" value="<?= htmlspecialchars($k) ?>">
                      <button class="text-red-600 hover:underline" onclick="return confirm('¿Eliminar parámetro?');">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Crear / Editar -->
      <div class="bg-white rounded-2xl shadow p-5">
        <h2 class="text-lg font-semibold mb-3">Crear / Editar</h2>
        <form method="POST" class="space-y-3">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">

          <div>
            <label class="block text-sm font-medium mb-1">Clave</label>
            <input type="text" name="key" required placeholder="plate_cost" class="w-full border rounded-lg p-2 font-mono text-sm">
            <p class="text-xs text-gray-500 mt-1">Ejemplos: <code>plate_cost</code>, <code>offset_millar_cuarto</code>, <code>offset_millar_medio</code>, <code>digital_click_color</code>, <code>digital_click_bw</code>.</p>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1">Valor</label>
            <input type="text" name="value" required placeholder="20000" class="w-full border rounded-lg p-2">
          </div>

          <button class="w-full bg-black text-white rounded-lg py-2 hover:opacity-90">Guardar</button>
        </form>
      </div>
    </div>

    <div class="mt-6 bg-white rounded-2xl shadow p-5">
      <h3 class="text-md font-semibold mb-2">Sugerencias de claves</h3>
      <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
        <li><code>plate_cost</code> — costo por plancha (p. ej. 20000).</li>
        <li><code>offset_millar_cuarto</code> — impresión ¼ pliego por millar/forma (p. ej. 40000).</li>
        <li><code>offset_millar_medio</code> — impresión ½ pliego por millar/forma (p. ej. 80000).</li>
        <li><code>digital_click_color</code> — costo por click color (p. ej. 200).</li>
        <li><code>digital_click_bw</code> — costo por click B/N (p. ej. 80).</li>
      </ul>
    </div>
  </div>
</body>
</html>
