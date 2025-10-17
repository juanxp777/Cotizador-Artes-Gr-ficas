<?php
require_once __DIR__ . '/bootstrap.php';

use App\Services\DigitalCalculator;

$pdo = db();
$calc = new DigitalCalculator($pdo);

$spec = [
  'nombre' => 'Tapa Cuaderno',
  'ancho' => 210,
  'alto' => 297,
  'paginas' => 2,
  'tiraje' => 300,
  'costo_hoja' => 120,
  'click_cost' => 200,
  'acabados' => [['nombre' => 'Laminado Mate', 'costo_m2' => 2500]],
  'margen' => 0.3,
];

$result = $calc->quote($spec);

echo "<pre>";
print_r($result);
echo "</pre>";
