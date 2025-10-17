<?php
require __DIR__.'/bootstrap.php';

use App\Services\OffsetCalculator;

$pdo = db();
$calc = new OffsetCalculator($pdo);

$spec = [
  'nombre' => 'Interiores Revista',
  'ancho' => 210, 'alto' => 297,
  'paginas' => 64,
  'repetidas' => false,    // pon true si todas las páginas son iguales (ej. cuaderno rayado)
  'tiraje' => 1000,
  'colores' => '4/4',
  'costo_hoja' => 450,     // costo hoja del formato (¼ o ½, según variant)
  'plate_cost' => 28000,
  'setup_waste_sheets' => 200,
  'run_waste_pct' => 0.02,
  'speed_iph' => 9000,
  'hourly_cost' => 120000,
  'ink_cost_per_1000_color' => 8000,
  'acabados' => [
    ['nombre'=>'Grapado','pricing'=>'per_unit','cost'=>120,'setup'=>40000],
  ],
  'margen' => 0.30,
];

$resQ = $calc->quote($spec, 'cuarto');
$resM = $calc->quote($spec, 'medio');

echo "<h3>Offset ¼ pliego</h3><pre>"; print_r($resQ); echo "</pre>";
echo "<h3>Offset ½ pliego</h3><pre>"; print_r($resM); echo "</pre>";
