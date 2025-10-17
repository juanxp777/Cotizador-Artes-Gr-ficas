<?php
require_once __DIR__.'/bootstrap.php';
use function App\Helpers\{calcularUps, hojasTotales, costoPapel};

$ups = calcularUps(500, 700, 210, 297); // medio pliego vs A4
$hojas = hojasTotales(1000, $ups);
$costo = costoPapel(120, $hojas); // papel $120 c/u

echo "UPS: $ups<br>Hojas totales: $hojas<br>Costo papel: $".number_format($costo);
