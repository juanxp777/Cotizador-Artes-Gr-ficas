<?php
namespace App\Services;

use PDO;
use function App\Helpers\{
    validarPaginas,
    requiereMultiplo4,
    formatosBase,
    redondear
};

/**
 * PricingEngine
 *
 * Orquesta el cálculo por PARTE (cover/interior/insert) contra 3 tecnologías:
 *   - Digital
 *   - Offset 1/4
 *   - Offset 1/2
 *
 * Selecciona automáticamente la mejor por parte (menor costo)
 * y suma las partes seleccionadas para el total del producto (posible híbrido).
 *
 * Requiere:
 *   - DigitalCalculator
 *   - OffsetCalculator
 */
class PricingEngine
{
    private PDO $db;
    private DigitalCalculator $digital;
    private OffsetCalculator $offset;

    public function __construct(PDO $db)
    {
        $this->db      = $db;
        $this->digital = new DigitalCalculator($db);
        $this->offset  = new OffsetCalculator($db);
    }

    /**
     * Cotiza un PRODUCTO completo (tapas/interiores/insertos)
     *
     * $productSpec = [
     *   'tipo' => 'revista'|'libro'|'cuaderno'|'agenda',
     *   'margen' => 0.30,
     *   'cover' => [ ...spec parte... ],
     *   'interior' => [ ...spec parte... ],
     *   'insert' => [ ...spec parte... ] // opcional
     * ]
     *
     * Cada "spec parte" debe traer el mínimo:
     *   [
     *     'nombre'   => 'Tapas'|'Interiores'|'Inserto X',
     *     'ancho'    => 210, 'alto' => 297,     // mm
     *     'paginas'  => 2|4|...                // revistas validan %4
     *     'tiraje'   => 500,
     *     'colores'  => '4/4'|'4/0'|'1/1'...,
     *     'margen'   => 0.30,                  // si no viene, hereda del producto
     *     'repetidas'=> bool,                  // true si todas las páginas iguales (interiores estándar)
     *
     *     // DIGITAL
     *     'click_cost' => 200,      // costo por impresión digital
     *     'costo_hoja' => 120,      // costo por hoja (SRA3)
     *
     *     // OFFSET
     *     'costo_hoja_offset_q' => 420,  // costo hoja formato 1/4
     *     'costo_hoja_offset_m' => 650,  // costo hoja formato 1/2
     *     'plate_cost' => 28000,
     *     'setup_waste_sheets' => 200,
     *     'run_waste_pct' => 0.02,
     *     'speed_iph' => 9000,
     *     'hourly_cost' => 120000,
     *     'ink_cost_per_1000_color' => 8000,
     *
     *     // Acabados (para todas las tecnologías; cada calc lo usa según corresponda):
     *     'acabados' => [
     *        // ejemplos:
     *        ['nombre'=>'Laminado mate', 'pricing'=>'per_m2', 'cost'=>6000, 'setup'=>30000],
     *        ['nombre'=>'Grapado', 'pricing'=>'per_unit', 'cost'=>120, 'setup'=>40000],
     *        ['nombre'=>'Troquel', 'pricing'=>'flat', 'cost'=>90000]
     *     ],
     *   ]
     */
    public function quoteProduct(array $productSpec): array
    {
        $tipoProducto = $productSpec['tipo'] ?? 'otro';
        $margenGlobal = (float)($productSpec['margen'] ?? 0.30);

        $warnings = [];
        // Regla: solo REVISTA valida múltiplos de 4
        if (isset($productSpec['interior']['paginas']) && requiereMultiplo4($tipoProducto)) {
            [$warn, $pag] = validarPaginas($tipoProducto, (int)$productSpec['interior']['paginas']);
            $productSpec['interior']['paginas'] = $pag;
            if ($warn) $warnings[] = $warn;
        }

        // Asegurar margen por parte
        foreach (['cover', 'interior', 'insert'] as $k) {
            if (!empty($productSpec[$k])) {
                if (!isset($productSpec[$k]['margen'])) {
                    $productSpec[$k]['margen'] = $margenGlobal;
                }
            }
        }

        $partsResult = [];
        $totalCost = 0.0;
        $totalPvp  = 0.0;

        // 1) TAPAS
        if (!empty($productSpec['cover'])) {
            $partsResult['cover'] = $this->quotePartAllTech($productSpec['cover']);
            $sel = $partsResult['cover']['selected'];
            $totalCost += $partsResult['cover']['options'][$sel]['total_costo'];
            $totalPvp  += $partsResult['cover']['options'][$sel]['pvp'];
        }

        // 2) INTERIORES
        if (!empty($productSpec['interior'])) {
            $partsResult['interior'] = $this->quotePartAllTech($productSpec['interior']);
            $sel = $partsResult['interior']['selected'];
            $totalCost += $partsResult['interior']['options'][$sel]['total_costo'];
            $totalPvp  += $partsResult['interior']['options'][$sel]['pvp'];
        }

        // 3) INSERTOS (opcionales, pueden venir varios en el futuro)
        if (!empty($productSpec['insert'])) {
            $partsResult['insert'] = $this->quotePartAllTech($productSpec['insert']);
            $sel = $partsResult['insert']['selected'];
            $totalCost += $partsResult['insert']['options'][$sel]['total_costo'];
            $totalPvp  += $partsResult['insert']['options'][$sel]['pvp'];
        }

        return [
            'ok'        => true,
            'tipo'      => $tipoProducto,
            'parts'     => $partsResult,       // matriz por parte (digital/offset_q/offset_m)
            'totals'    => [
                'cost' => $totalCost,
                'pvp'  => $totalPvp,
            ],
            'warnings'  => $warnings,
        ];
    }

    /**
     * Calcula una PARTE con las 3 tecnologías y selecciona la menor por COSTO.
     * Devuelve matriz de opciones + key 'selected'
     */
    public function quotePartAllTech(array $partSpec): array
    {
        // 1) DIGITAL
        $dSpec = $partSpec;
        // asegure campos mínimos usados por DigitalCalculator
        $dSpec['costo_hoja'] = $partSpec['costo_hoja'] ?? ($partSpec['costo_hoja_sra3'] ?? 0);
        $digital = $this->digital->quote($dSpec);

        // 2) OFFSET 1/4
        $qSpec = $partSpec;
        $qSpec['costo_hoja'] = $partSpec['costo_hoja_offset_q'] ?? 0;
        $offsetQ = $this->offset->quote($qSpec, 'cuarto');

        // 3) OFFSET 1/2
        $mSpec = $partSpec;
        $mSpec['costo_hoja'] = $partSpec['costo_hoja_offset_m'] ?? 0;
        $offsetM = $this->offset->quote($mSpec, 'medio');

        // Normalizar estructura para la UI (asegurar nombres de campos)
        $options = [
            'digital'  => [
                'tecnologia'  => $digital['tecnologia'],
                'formato'     => $digital['formato'],
                'ups'         => $digital['ups'],
                'hojas'       => $digital['hojas'],
                'impresiones' => $digital['impresiones'],
                'costos'      => $digital['costos'],
                'total_costo' => (float)$digital['total_costo'],
                'pvp'         => (float)$digital['pvp'],
                'detalle'     => $digital['detalle'],
            ],
            'offset_q' => [
                'tecnologia'  => $offsetQ['tecnologia'],
                'formato'     => $offsetQ['formato'],
                'ups'         => $offsetQ['ups'],
                'planchas'    => $offsetQ['planchas'],
                'hojas'       => $offsetQ['hojas'],
                'impresiones' => $offsetQ['impresiones_totales'],
                'costos'      => $offsetQ['costos'],
                'total_costo' => (float)$offsetQ['total_costo'],
                'pvp'         => (float)$offsetQ['pvp'],
                'detalle'     => $offsetQ['detalle'],
            ],
            'offset_m' => [
                'tecnologia'  => $offsetM['tecnologia'],
                'formato'     => $offsetM['formato'],
                'ups'         => $offsetM['ups'],
                'planchas'    => $offsetM['planchas'],
                'hojas'       => $offsetM['hojas'],
                'impresiones' => $offsetM['impresiones_totales'],
                'costos'      => $offsetM['costos'],
                'total_costo' => (float)$offsetM['total_costo'],
                'pvp'         => (float)$offsetM['pvp'],
                'detalle'     => $offsetM['detalle'],
            ],
        ];

        // Seleccionar por menor COSTO
        $selected = 'digital';
        $minCost = $options['digital']['total_costo'];
        foreach (['offset_q', 'offset_m'] as $k) {
            if ($options[$k]['total_costo'] < $minCost) {
                $minCost = $options[$k]['total_costo'];
                $selected = $k;
            }
        }

        return [
            'part_name' => $partSpec['nombre'] ?? 'Parte',
            'options'   => $options,
            'selected'  => $selected
        ];
    }

    /**
     * Ladder (escalera) de cantidades para el PRODUCTO completo.
     * Recalcula la matriz por parte en cada cantidad y resume total.
     *
     * @param array $productSpec  (mismo formato que quoteProduct)
     * @param array $breaks       [50,100,200,300,500,800,1000,1500]
     */
    public function ladder(array $productSpec, array $breaks = [50,100,200,300,500,800,1000,1500]): array
    {
        $out = [];
        foreach ($breaks as $q) {
            $tmp = $productSpec;

            // inyectar tiraje por parte
            foreach (['cover','interior','insert'] as $k) {
                if (!empty($tmp[$k])) {
                    $tmp[$k]['tiraje'] = $q;
                }
            }
            $res = $this->quoteProduct($tmp);
            $out[] = [
                'cantidad' => $q,
                'cost'     => $res['totals']['cost'],
                'pvp'      => $res['totals']['pvp'],
                'parts'    => $res['parts'],     // puedes ocultarlo si solo quieres totals
            ];
        }
        return $out;
    }
}
