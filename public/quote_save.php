<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\{PricingEngine, FinishingRepo, Params};

start_secure_session();
require_csrf_if_post();

$pdo  = db();
$repo = new FinishingRepo($pdo);
$params = new Params($pdo);

// ---------- Helpers UI ----------
function selected($a, $b): string { return (string)$a===(string)$b ? 'selected' : ''; }
function checked($cond): string { return $cond ? 'checked' : ''; }

function render_part_matrix(string $title, array $partRes): void {
  $sel = $partRes['selected'] ?? 'digital';
  $options = $partRes['options'] ?? [];
  ?>
  <div class="bg-white rounded-2xl shadow p-5">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-lg font-semibold"><?= htmlspecialchars($title) ?></h3>
      <span class="text-xs px-2 py-1 rounded bg-gray-100">Elegido: <b><?= strtoupper(str_replace('_',' ', $sel)) ?></b></span>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
      <?php foreach (['digital'=>'Digital','offset_q'=>'Offset ¼ pliego','offset_m'=>'Offset ½ pliego'] as $key => $label): 
        $o = $options[$key] ?? null; if (!$o) continue;
        $isSel = ($sel === $key);
      ?>
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
            <li><span class="text-gray-500">Hojas:</span> <?= number_format((int)$o['hojas'],0,',','.') ?></li>
            <li><span class="text-gray-500">Impresiones:</span> <?= number_format((int)$o['impresiones'],0,',','.') ?></li>
          </ul>
          <div class="mt-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-gray-500"><?= isset($o['costos']['papel'])?'Costo papel':'Clicks' ?></span>
              <span class="font-medium"><?= price($o['costos']['papel'] ?? ($o['costos']['clicks']??0)) ?></span>
            </div>
            <?php if(isset($o['costos']['planchas'])): ?>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Planchas</span>
                <span class="font-medium"><?= price($o['costos']['planchas']) ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Impresión</span>
                <span class="font-medium"><?= price($o['costos']['impresion'] ?? 0) ?></span>
              </div>
            <?php endif; ?>
            <div class="flex items-center justify-between">
              <span class="text-gray-500">Acabados</span>
              <span class="font-medium"><?= price($o['costos']['acabados'] ?? 0) ?></span>
            </div>
          </div>
          <div class="mt-3 border-t pt-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-500">Costo total</span>
              <span class="font-semibold"><?= price($o['total_costo']) ?></span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-500">PVP sugerido</span>
              <span class="font-semibold"><?= price($o['pvp']) ?></span>
            </div>
          </div>
          <details class="mt-3 text-sm text-gray-600">
            <summary class="cursor-pointer">Ver detalle</summary>
            <p class="mt-2"><?= htmlspecialchars($o['detalle'] ?? '') ?></p>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
}

// Cargar acabados activos para selects
$acabados = (new FinishingRepo($pdo))->all(true);

// Defaults de form
$defaults = [
  'tipo' => 'revista',
  'margen' => '0.30',
  // cover
  'cover_ancho' => '210', 'cover_alto' => '297', 'cover_pag' => '2',
  'cover_colores' => '4/0', 'cover_tiraje' => '500',
  'cover_costo_hoja' => '120',
  'cover_costo_hoja_q' => '420', 'cover_costo_hoja_m' => '650',
  // interior
  'int_ancho' => '210', 'int_alto' => '297', 'int_pag' => '64', 'int_rep' => '0',
  'int_colores' => '4/4', 'int_tiraje' => '500',
  'int_costo_hoja' => '120',
  'int_costo_hoja_q' => '420', 'int_costo_hoja_m' => '650',
  // insert
  'ins_on' => '0',
  'ins_ancho' => '100', 'ins_alto' => '200', 'ins_pag' => '2',
  'ins_colores' => '4/4', 'ins_tiraje' => '500',
  'ins_costo_hoja' => '120',
  'ins_costo_hoja_q' => '420', 'ins_costo_hoja_m' => '650',
];

$F = array_merge($defaults, $_GET, $_POST);

// Resultado
$result = null; $ladder = null; $warnings = [];
$productForSave = null; $resultForSave = null; $ladderForSave = null;

// Procesar POST (cotizar)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $margen = (float)float_input($F['margen'], 0.30);

  // Acabados por parte (IDs -> specs)
  $repoFin = new FinishingRepo($pdo);
  $cov_fin_specs = $repoFin->asSpecs(array_map('intval', $_POST['cover_fin'] ?? []));
  $int_fin_specs = $repoFin->asSpecs(array_map('intval', $_POST['int_fin'] ?? []));
  $ins_fin_specs = $repoFin->asSpecs(array_map('intval', $_POST['ins_fin'] ?? []));

  $product = [
    'tipo'   => $F['tipo'],
    'margen' => $margen,
    'cover'  => [
      'nombre' => 'Tapas',
      'ancho'  => float_input($F['cover_ancho']),
      'alto'   => float_input($F['cover_alto']),
      'paginas'=> int_input($F['cover_pag'], 2),
      'tiraje' => int_input($F['cover_tiraje'], 0),
      'colores'=> $F['cover_colores'],
      'margen' => $margen,
      'costo_hoja' => float_input($F['cover_costo_hoja']),
      'costo_hoja_offset_q' => float_input($F['cover_costo_hoja_q']),
      'costo_hoja_offset_m' => float_input($F['cover_costo_hoja_m']),
      'acabados' => $cov_fin_specs,
    ],
    'interior'=> [
      'nombre' => 'Interiores',
      'ancho'  => float_input($F['int_ancho']),
      'alto'   => float_input($F['int_alto']),
      'paginas'=> int_input($F['int_pag'], 2),
      'repetidas' => (isset($F['int_rep']) && $F['int_rep']=='1'),
      'tiraje' => int_input($F['int_tiraje'], 0),
      'colores'=> $F['int_colores'],
      'margen' => $margen,
      'costo_hoja' => float_input($F['int_costo_hoja']),
      'costo_hoja_offset_q' => float_input($F['int_costo_hoja_q']),
      'costo_hoja_offset_m' => float_input($F['int_costo_hoja_m']),
      'acabados' => $int_fin_specs,
    ],
  ];

  if (!empty($F['ins_on']) && $F['ins_on']=='1') {
    $product['insert'] = [
      'nombre' => 'Inserto',
      'ancho'  => float_input($F['ins_ancho']),
      'alto'   => float_input($F['ins_alto']),
      'paginas'=> int_input($F['ins_pag'], 2),
      'tiraje' => int_input($F['ins_tiraje'], 0),
      'colores'=> $F['ins_colores'],
      'margen' => $margen,
      'costo_hoja' => float_input($F['ins_costo_hoja']),
      'costo_hoja_offset_q' => float_input($F['ins_costo_hoja_q']),
      'costo_hoja_offset_m' => float_input($F['ins_costo_hoja_m']),
      'acabados' => $ins_fin_specs,
    ];
  }

  // Ejecutar engine
  $engine = new PricingEngine($pdo);
  $result = $engine->quoteProduct($product);
  $warnings = $result['warnings'] ?? [];

  if (!empty($_POST['with_ladder'])) {
    $ladder = $engine->ladder($product, [50,100,200,300,500,800,1000,1500]);
  }

  // Paquete para guardado
  $productForSave = $product;
  $resultForSave  = $result;
  $ladderForSave  = $ladder;
}

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cotizador</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold">Cotizador de Impresión</h1>
      <p class="text-sm text-gray-600">Comparación automática entre Digital, Offset ¼ y Offset ½ por cada parte (Tapas / Interiores / Insertos).</p>
    </header>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Formulario -->
      <form method="POST" class="bg-white rounded-2xl shadow p-5 space-y-5">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium">Tipo de producto</label>
            <select name="tipo" class="w-full border rounded-lg p-2">
              <option value="revista" <?= selected($F['tipo'],'revista') ?>>Revista</option>
              <option value="libro"   <?= selected($F['tipo'],'libro') ?>>Libro</option>
              <option value="cuaderno"<?= selected($F['tipo'],'cuaderno') ?>>Cuaderno</option>
              <option value="agenda"  <?= selected($F['tipo'],'agenda') ?>>Agenda</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium">Margen comercial</label>
            <input type="number" step="0.01" name="margen" value="<?= htmlspecialchars($F['margen']) ?>" class="w-full border rounded-lg p-2">
            <p class="text-xs text-gray-500 mt-1">Ej: 0.30 = 30%</p>
          </div>
        </div>

        <!-- TAPAS -->
        <div class="border rounded-xl p-4">
          <h3 class="font-medium mb-3">Tapas / Cubiertas</h3>
          <div class="grid md:grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-medium">Ancho (mm)</label>
              <input type="number" step="0.01" name="cover_ancho" value="<?= htmlspecialchars($F['cover_ancho']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Alto (mm)</label>
              <input type="number" step="0.01" name="cover_alto" value="<?= htmlspecialchars($F['cover_alto']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Páginas</label>
              <input type="number" name="cover_pag" value="<?= htmlspecialchars($F['cover_pag']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-3 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Colores</label>
              <select name="cover_colores" class="w-full border rounded-lg p-2">
                <?php foreach (['4/0','4/4','1/1','1/0'] as $c): ?>
                  <option value="<?= $c ?>" <?= selected($F['cover_colores'],$c) ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium">Tiraje (unidades)</label>
              <input type="number" name="cover_tiraje" value="<?= htmlspecialchars($F['cover_tiraje']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja digital (SRA3)</label>
              <input type="number" step="0.01" name="cover_costo_hoja" value="<?= htmlspecialchars($F['cover_costo_hoja']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Hoja ¼ pliego</label>
              <input type="number" step="0.01" name="cover_costo_hoja_q" value="<?= htmlspecialchars($F['cover_costo_hoja_q']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja ½ pliego</label>
              <input type="number" step="0.01" name="cover_costo_hoja_m" value="<?= htmlspecialchars($F['cover_costo_hoja_m']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-sm font-medium">Acabados (tapitas)</label>
            <select name="cover_fin[]" multiple class="w-full border rounded-lg p-2 h-28">
              <?php foreach ($acabados as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']).' — '.$a['pricing'] ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">Puedes seleccionar varios (Ctrl/⌘ + clic).</p>
          </div>
        </div>

        <!-- INTERIORES -->
        <div class="border rounded-xl p-4">
          <h3 class="font-medium mb-3">Hojas interiores</h3>
          <div class="grid md:grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-medium">Ancho (mm)</label>
              <input type="number" step="0.01" name="int_ancho" value="<?= htmlspecialchars($F['int_ancho']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Alto (mm)</label>
              <input type="number" step="0.01" name="int_alto" value="<?= htmlspecialchars($F['int_alto']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Páginas</label>
              <input type="number" name="int_pag" value="<?= htmlspecialchars($F['int_pag']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-4 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Colores</label>
              <select name="int_colores" class="w-full border rounded-lg p-2">
                <?php foreach (['4/4','4/0','1/1','1/0'] as $c): ?>
                  <option value="<?= $c ?>" <?= selected($F['int_colores'],$c) ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="flex items-center">
              <label class="text-sm mr-2">¿Páginas repetidas?</label>
              <input type="checkbox" name="int_rep" value="1" <?= checked($F['int_rep']=='1') ?>>
            </div>
            <div>
              <label class="block text-sm font-medium">Tiraje</label>
              <input type="number" name="int_tiraje" value="<?= htmlspecialchars($F['int_tiraje']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja digital (SRA3)</label>
              <input type="number" step="0.01" name="int_costo_hoja" value="<?= htmlspecialchars($F['int_costo_hoja']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Hoja ¼ pliego</label>
              <input type="number" step="0.01" name="int_costo_hoja_q" value="<?= htmlspecialchars($F['int_costo_hoja_q']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja ½ pliego</label>
              <input type="number" step="0.01" name="int_costo_hoja_m" value="<?= htmlspecialchars($F['int_costo_hoja_m']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-sm font-medium">Acabados (interiores)</label>
            <select name="int_fin[]" multiple class="w-full border rounded-lg p-2 h-28">
              <?php foreach ($acabados as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']).' — '.$a['pricing'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- INSERTO (opcional) -->
        <div class="border rounded-xl p-4">
          <div class="flex items-center justify-between">
            <h3 class="font-medium mb-3">Inserto (opcional)</h3>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="ins_on" value="1" <?= checked($F['ins_on']=='1') ?>> Incluir</label>
          </div>
          <div class="grid md:grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-medium">Ancho (mm)</label>
              <input type="number" step="0.01" name="ins_ancho" value="<?= htmlspecialchars($F['ins_ancho']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Alto (mm)</label>
              <input type="number" step="0.01" name="ins_alto" value="<?= htmlspecialchars($F['ins_alto']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Páginas</label>
              <input type="number" name="ins_pag" value="<?= htmlspecialchars($F['ins_pag']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-3 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Colores</label>
              <select name="ins_colores" class="w-full border rounded-lg p-2">
                <?php foreach (['4/4','4/0','1/1','1/0'] as $c): ?>
                  <option value="<?= $c ?>" <?= selected($F['ins_colores'],$c) ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium">Tiraje</label>
              <input type="number" name="ins_tiraje" value="<?= htmlspecialchars($F['ins_tiraje']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja digital (SRA3)</label>
              <input type="number" step="0.01" name="ins_costo_hoja" value="<?= htmlspecialchars($F['ins_costo_hoja']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-sm font-medium">Hoja ¼ pliego</label>
              <input type="number" step="0.01" name="ins_costo_hoja_q" value="<?= htmlspecialchars($F['ins_costo_hoja_q']) ?>" class="w-full border rounded-lg p-2">
            </div>
            <div>
              <label class="block text-sm font-medium">Hoja ½ pliego</label>
              <input type="number" step="0.01" name="ins_costo_hoja_m" value="<?= htmlspecialchars($F['ins_costo_hoja_m']) ?>" class="w-full border rounded-lg p-2">
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-sm font-medium">Acabados (inserto)</label>
            <select name="ins_fin[]" multiple class="w-full border rounded-lg p-2 h-28">
              <?php foreach ($acabados as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']).' — '.$a['pricing'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <label class="text-sm flex items-center gap-2">
            <input type="checkbox" name="with_ladder" value="1"> Mostrar ladder (cantidades sugeridas)
          </label>
          <button class="bg-black text-white rounded-lg px-4 py-2">Calcular cotización</button>
        </div>
      </form>

      <!-- Resultados -->
      <div class="space-y-5">
        <?php if ($warnings): ?>
          <div class="bg-amber-50 text-amber-800 rounded-xl p-4 text-sm">
            <?php foreach ($warnings as $w): ?>
              <div>⚠️ <?= htmlspecialchars($w) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($result): ?>
          <?php if (!empty($result['parts']['cover']))   render_part_matrix('Tapas / Cubiertas', $result['parts']['cover']); ?>
          <?php if (!empty($result['parts']['interior']))render_part_matrix('Hojas Interiores', $result['parts']['interior']); ?>
          <?php if (!empty($result['parts']['insert']))  render_part_matrix('Inserto', $result['parts']['insert']); ?>

          <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold">Resumen</h3>
              <span class="text-xs text-gray-500"><?= htmlspecialchars(ucfirst($result['tipo'] ?? '')) ?></span>
            </div>
            <div class="mt-3 grid md:grid-cols-2 gap-3 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Costo total (suma partes seleccionadas)</span>
                <span class="font-semibold"><?= price($result['totals']['cost']) ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">PVP total sugerido</span>
                <span class="font-semibold"><?= price($result['totals']['pvp']) ?></span>
              </div>
            </div>
          </div>

          <?php if ($ladder): ?>
            <div class="bg-white rounded-2xl shadow p-5">
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
                    <?php foreach ($ladder as $row): ?>
                      <tr class="border-b">
                        <td class="py-2 pr-3"><?= number_format((int)$row['cantidad'],0,',','.') ?></td>
                        <td class="py-2 pr-3"><?= price($row['cost']) ?></td>
                        <td class="py-2"><?= price($row['pvp']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-gray-500 mt-2">* Cada fila recalcula Digital/Offset por parte y elige la mejor combinación híbrida.</p>
            </div>
          <?php endif; ?>

          <!-- Guardar cotización -->
          <div class="bg-white rounded-2xl shadow p-5">
            <h3 class="text-lg font-semibold mb-3">Guardar cotización</h3>
            <form action="/public/quote_save.php" method="POST" class="grid md:grid-cols-2 gap-3">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <!-- Paquete JSON oculto -->
              <input type="hidden" name="product_json" value='<?= htmlspecialchars(json_encode($productForSave, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
              <input type="hidden" name="result_json"  value='<?= htmlspecialchars(json_encode($resultForSave,  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>
              <input type="hidden" name="ladder_json"  value='<?= htmlspecialchars(json_encode($ladderForSave,  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>'>

              <div>
                <label class="block text-sm font-medium">Nombre del cliente</label>
                <input type="text" name="customer_name" class="w-full border rounded-lg p-2" placeholder="Empresa o persona">
              </div>
              <div>
                <label class="block text-sm font-medium">Email del cliente</label>
                <input type="email" name="customer_email" class="w-full border rounded-lg p-2" placeholder="correo@cliente.com">
              </div>
              <div>
                <label class="block text-sm font-medium">Impuesto (IVA opcional)</label>
                <input type="number" step="0.01" name="tax_pct" class="w-full border rounded-lg p-2" placeholder="0.19 para 19%">
                <p class="text-xs text-gray-500 mt-1">Si lo dejas vacío, no se aplica.</p>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium">Notas</label>
                <input type="text" name="notes" class="w-full border rounded-lg p-2" placeholder="Observaciones para el cliente">
              </div>
              <div class="md:col-span-2">
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2">Guardar y generar enlace</button>
              </div>
            </form>
          </div>

        <?php else: ?>
          <div class="text-gray-500 text-sm">
            Completa el formulario y presiona <b>Calcular cotización</b> para ver los resultados aquí.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
