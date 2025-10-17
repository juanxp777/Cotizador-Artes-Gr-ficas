<?php
require __DIR__.'/bootstrap.php';

use App\Services\PricingEngine;

$engine = new PricingEngine(db());

$producto = [
  'tipo' => 'revista',    // aplica validación múltiplos de 4 SOLO si 'revista'
  'margen' => 0.30,

  'cover' => [
    'nombre' => 'Tapas',
    'ancho' => 210, 'alto' => 297,
    'paginas' => 2,
    'tiraje' => 500,
    'colores' => '4/0',
    // digital
    'costo_hoja' => 120, 'click_cost' => 200,
    // offset
    'costo_hoja_offset_q' => 420, 'costo_hoja_offset_m' => 650,
    'plate_cost' => 28000, 'setup_waste_sheets' => 150, 'run_waste_pct' => 0.02,
    'speed_iph' => 9000, 'hourly_cost' => 120000, 'ink_cost_per_1000_color' => 8000,
    'acabados' => [
      ['nombre'=>'Laminado mate','pricing'=>'per_m2','cost'=>6000,'setup'=>30000]
    ],
  ],

  'interior' => [
    'nombre' => 'Interiores',
    'ancho' => 210, 'alto' => 297,
    'paginas' => 64,       // si 'revista', valida múltiplos de 4 (no bloquea)
    'repetidas' => false,
    'tiraje' => 500,
    'colores' => '4/4',
    // digital
    'costo_hoja' => 120, 'click_cost' => 200,
    // offset
    'costo_hoja_offset_q' => 420, 'costo_hoja_offset_m' => 650,
    'plate_cost' => 28000, 'setup_waste_sheets' => 200, 'run_waste_pct' => 0.02,
    'speed_iph' => 9000, 'hourly_cost' => 120000, 'ink_cost_per_1000_color' => 8000,
    'acabados' => [
      ['nombre'=>'Grapado','pricing'=>'per_unit','cost'=>120,'setup'=>40000]
    ],
  ],
];

$res = $engine->quoteProduct($producto);

echo "<h2>RESULTADO</h2><pre>";
print_r($res);
echo "</pre>";

echo "<h2>LADDER</h2><pre>";
print_r($engine->ladder($producto, [100, 300, 500, 1000]));
echo "</pre>";
