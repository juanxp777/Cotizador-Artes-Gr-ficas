<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\QuoteRepo;

$publicId = $_GET['id'] ?? '';
if (!$publicId) {
    http_response_code(400);
    die('Falta ID público.');
}

$repo = new QuoteRepo(db());
$q = $repo->getByPublicId($publicId);
if (!$q) {
    http_response_code(404);
    die('Cotización no encontrada.');
}

$res = $q['result'];
$parts = $res['parts'] ?? [];
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cotización #<?= htmlspecialchars($q['public_id']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-5xl mx-auto p-6">
    <header class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Cotización</h1>
        <p class="text-sm text-gray-600">ID: <span class="font-mono"><?= htmlspecialchars($q['public_id']) ?></span></p>
        <p class="text-sm text-gray-600">Fecha: <?= htmlspecialchars(date('Y-m-d H:i', strtotime($q['created_at']))) ?></p>
      </div>
      <div class="text-right">
        <?php if ($q['customer_name']): ?>
          <p class="text-sm"><span class="text-gray-500">Cliente:</span> <?= htmlspecialchars($q['customer_name']) ?></p>
        <?php endif; ?>
        <?php if ($q['customer_email']): ?>
          <p class="text-sm"><span class="text-gray-500">Email:</span> <?= htmlspecialchars($q['customer_email']) ?></p>
        <?php endif; ?>
      </div>

<p class="mt-2">
  <a href="/public/quote_pdf.php?id=<?= urlencode($q['public_id']) ?>"
     class="inline-block bg-black text-white px-3 py-1 rounded">
     Descargar PDF
  </a>
</p>


    </header>

    <?php if (!empty($q['notes'])): ?>
      <div class="mb-4 bg-blue-50 text-blue-800 px-4 py-2 rounded-lg text-sm"><?= htmlspecialchars($q['notes']) ?></div>
    <?php endif; ?>

    <!-- Matriz por parte -->
    <?php
    function render_part_public($title, $part) {
      $sel = $part['selected'] ?? 'digital';
      $opt = $part['options'] ?? [];
      ?>
      <div class="bg-white rounded-2xl shadow p-5 mb-5">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h3>
          <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded">Elegido: <b><?= strtoupper(str_replace('_',' ', $sel)) ?></b></span>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
          <?php foreach (['digital'=>'Digital','offset_q'=>'Offset ¼','offset_m'=>'Offset ½'] as $k=>$label):
            if (empty($opt[$k])) continue; $o = $opt[$k]; $isSel = $k===$sel; ?>
            <div class="rounded-xl border <?= $isSel?'border-emerald-400 ring-2 ring-emerald-200':'' ?> p-4">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium"><?= $label ?></h4>
                <?php if ($isSel): ?><span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded">Seleccionado</span><?php endif; ?>
              </div>
              <ul class="text-sm text-gray-700 space-y-1">
                <li><span class="text-gray-500">Formato:</span> <?= htmlspecialchars($o['formato']) ?></li>
                <li><span class="text-gray-500">UPS:</span> <?= (int)$o['ups'] ?></li>
                <?php if(isset($o['planchas'])): ?>
                  <li><span class="text-gray-500">Planchas:</span> <?= (int)$o['planchas'] ?></li>
                <?php endif; ?>
              </ul>
              <div class="mt-3 text-sm">
                <div class="flex items-center justify-between">
                  <span class="text-gray-500">Costo total</span>
                  <span class="font-semibold"><?= price($o['total_costo']) ?></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-500">PVP sugerido</span>
                  <span class="font-semibold"><?= price($o['pvp']) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php } ?>

    <?php if (!empty($parts['cover']))    render_part_public('Tapas / Cubiertas', $parts['cover']); ?>
    <?php if (!empty($parts['interior'])) render_part_public('Hojas Interiores',  $parts['interior']); ?>
    <?php if (!empty($parts['insert']))   render_part_public('Inserto',          $parts['insert']); ?>

    <div class="bg-white rounded-2xl shadow p-5">
      <div class="grid md:grid-cols-2 gap-3 text-sm">
        <div class="flex items-center justify-between">
          <span class="text-gray-500">Costo total</span>
          <span class="font-semibold"><?= price($q['total_cost']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-gray-500">PVP total sugerido</span>
          <span class="font-semibold"><?= price($q['total_pvp']) ?></span>
        </div>
      </div>
      <?php if ($q['tax_pct'] !== null): ?>
        <div class="mt-2 text-sm text-gray-600">
          <span>Impuesto aplicado: <?= (float)$q['tax_pct']*100 ?>%</span>
        </div>
      <?php endif; ?>
      <p class="text-xs text-gray-500 mt-3">* Esta cotización puede variar según disponibilidad de papel y tarifas de proveedores.</p>
    </div>

    <?php if (!empty($q['ladder'])): ?>
      <div class="bg-white rounded-2xl shadow p-5 mt-5">
        <h3 class="text-lg font-semibold mb-3">Ladder de cantidades</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b">
                <th class="py-2 pr-3">Cantidad</th>
                <th class="py-2 pr-3">Costo total</th>
                <th class="py-2">PVP sugerido</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($q['ladder'] as $row): ?>
                <tr class="border-b">
                  <td class="py-2 pr-3"><?= number_format((int)$row['cantidad'],0,',','.') ?></td>
                  <td class="py-2 pr-3"><?= price($row['cost']) ?></td>
                  <td class="py-2"><?= price($row['pvp']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
