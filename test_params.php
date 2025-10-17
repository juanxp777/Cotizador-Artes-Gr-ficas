<?php
require __DIR__.'/bootstrap.php';

use App\Services\Params;

$p = new Params(db());

echo "<pre>";
print_r($p->all());
echo "\nplate_cost = ".$p->getFloat('plate_cost');
echo "\noffset_millar_cuarto = ".$p->getFloat('offset_millar_cuarto');
echo "\noffset_millar_medio = ".$p->getFloat('offset_millar_medio');
echo "\ndigital_click_color = ".$p->getFloat('digital_click_color');
echo "\ndigital_click_bw = ".$p->getFloat('digital_click_bw');
echo "</pre>";
