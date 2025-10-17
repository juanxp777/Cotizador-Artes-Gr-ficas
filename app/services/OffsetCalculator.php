<?php
namespace App\Services;

use PDO;
use function App\Helpers\{
    calcularUps,
    hojasTotales,
    costoPapel,
    areaM2,
    redondear,
    formatosBase
};

/**
 * OffsetCalculator (versión tercerizada)
 *
 * Modelo de costos cuando la impresión offset se terceriza:
 * - Planchas: costo unitario configurable (Params: plate_cost)
 * - Impresión: por millar o fracción y por FORMATO y por FORMA
 *     - Params: offset_millar_cuarto, offset_millar_medio
 * - Papel: por hoja del formato (¼ o ½), con merma por forma
 * - Acabados tercerizados con tramos por cantidad (tiers)
 *
 * La impresión por millar/fracción aplica por CADA FORMA.
 * Ejemplo: interiores 64 págs, ups=8 (por cara), 2 caras ⇒ 16 págs/forma ⇒ 4 formas
 * Si tiraje=1200 ⇒ millares = ceil(1200/1000) = 2 ⇒ impresión = (2 millares) * (4 formas) * tarifa
 */
class OffsetCalculator
{
    private PDO $db;
    private array $formats;
    private Params $params;

    public function __construct(PDO $db)
    {
        $this->db     = $db;
        $this->formats = formatosBase(); // ['digital','cuarto','medio']
        $this->params  = new Params($db);
    }

    /**
     * Cotiza en offset para un formato dado: 'cuarto' o 'medio'
     *
     * $spec esperado (mínimos):
     *  - nombre, ancho, alto, paginas, repetidas(bool), tiraje, colores (ej '4/4','4/0','1/1')
     *  - costo_hoja_offset_q / costo_hoja_offset_m (o pasar 'costo_hoja' ya resuelto)
     *  - acabados: array con items
     *      each: ['nombre'=>'Laminado','pricing'=>'per_m2|per_unit|flat','tiers'=>[{'min','max','cost'}...], 'setup'=>opc]
     *      (si no hay 'tiers', puede traer 'cost' fijo)
     *  - margen (0.30 por defecto)
     */
    public function quote(array $spec, string $variant = 'medio'): array
    {
        // -------- Formato y datos base
        $fmt = $this->formats[$variant] ?? $this->formats['medio'];
        $Wf = $fmt['W']; $Hf = $fmt['H'];

        $Wc = (float)($spec['ancho'] ?? 210);
        $Hc = (float)($spec['alto'] ?? 297);
        $tiraje  = (int)($spec['tiraje'] ?? 0);
        $paginas = (int)($spec['paginas'] ?? 2);
        $repetidas = (bool)($spec['repetidas'] ?? false);

        // -------- UPS y formas
        $ups = calcularUps($Wf, $Hf, $Wc, $Hc, 3);

        $colors = $this->parseColors($spec['colores'] ?? '4/4'); // ['front'=>4,'back'=>4]
        $pagesPerForm = max($ups * 2, 1); // dos caras por forma
        $numForms = $repetidas ? 1 : (int)max(1, ceil($paginas / $pagesPerForm));

        // Impresiones (por forma): tiraje / ups
        $impresionesPorForma = (int)ceil($tiraje / max($ups, 1));
        $impresionesTotales  = $impresionesPorForma * $numForms;

        // -------- Planchas y papel
        $plateUnit = $this->params->getFloat('plate_cost', 20000); // $/plancha
        $numPlanchas = ($colors['front'] + $colors['back']) * $numForms;
        $plateCost   = $numPlanchas * $plateUnit;

        // Hojas con merma (por forma)
        $setupWaste = (int)($spec['setup_waste_sheets'] ?? 150);
        $runWaste   = (float)($spec['run_waste_pct'] ?? 0.02);
        // Para papel: contamos tiraje * numForms (cada forma se imprime aparte)
        $hojas = hojasTotales($tiraje * $numForms, $ups, $setupWaste, $runWaste);

        // Costo hoja del formato elegido (permitimos override con 'costo_hoja')
        $costoHoja = (float)($spec['costo_hoja'] ??
                    ($variant === 'cuarto'
                        ? ($spec['costo_hoja_offset_q'] ?? 0)
                        : ($spec['costo_hoja_offset_m'] ?? 0)));
        $paperCost = costoPapel($costoHoja, $hojas);

        // -------- Impresión por millar/forma (tercerizado)
        $rate = ($variant === 'cuarto')
            ? $this->params->getFloat('offset_millar_cuarto', 40000)
            : $this->params->getFloat('offset_millar_medio', 80000);

        $millares = (int)max(1, ceil($tiraje / 1000));
        $pressCost = $millares * $numForms * $rate;

        // -------- Tintas (opcional): si tu proveedor las incluye en la tarifa, déjalo en 0
        // Por si quieres agregar un extra opcional por color:
        $inkCost = 0.0; // $this->inkCost($impresionesTotales, $colors, ...);

        // -------- Acabados con tramos (tiers)
        $acabCost = $this->costAcabados($spec, $tiraje, $Wc, $Hc);

        // -------- Totales
        $totalCost = $paperCost + $plateCost + $pressCost + $inkCost + $acabCost;
        $margin = (float)($spec['margen'] ?? 0.30);
        $pvp = redondear($totalCost * (1 + $margin), 100);

        return [
            'tecnologia' => "offset_{$variant}",
            'formato'    => "{$Wf}x{$Hf} mm",
            'ups'        => $ups,
            'num_forms'  => $numForms,
            'impresiones_por_forma' => $impresionesPorForma,
            'impresiones_totales'   => $impresionesTotales,
            'planchas'   => $numPlanchas,
            'hojas'      => $hojas,
            'costos'     => [
                'papel'    => $paperCost,
                'planchas' => $plateCost,
                'impresion'=> $pressCost, // por millar/forma
                'tintas'   => $inkCost,
                'acabados' => $acabCost,
            ],
            'total_costo' => $totalCost,
            'pvp'         => $pvp,
            'detalle' => sprintf(
                "Offset %s: %d ups, %d formas, %d hojas, %d impresiones totales, %d planchas, %d millar(es) x forma.",
                $variant, $ups, $numForms, $hojas, $impresionesTotales, $numPlanchas, $millares
            ),
        ];
    }

    /* ==================== Helpers internos ==================== */

    private function parseColors(string $c): array
    {
        $c = trim($c);
        if (!str_contains($c, '/')) {
            $n = (int)$c;
            return ['front' => $n, 'back' => 0];
        }
        [$f, $b] = array_map('trim', explode('/', $c, 2));
        return ['front' => (int)$f, 'back' => (int)$b];
    }

    /**
     * Acabados tercerizados con tramos (tiers)
     * - pricing:
     *   - 'per_m2': cost * área * tiraje + setup
     *   - 'per_unit': cost * tiraje + setup
     *   - 'flat': cost + setup
     * - 'tiers' (opcional) sustituye a 'cost':
     *   [
     *     ['min'=>1,'max'=>199,'cost'=>6000],
     *     ['min'=>200,'max'=>999,'cost'=>5200],
     *     ['min'=>1000,'max'=>999999,'cost'=>4800]
     *   ]
     */
    private function costAcabados(array $spec, int $tiraje, float $Wc, float $Hc): float
    {
        $total = 0.0;
        if (empty($spec['acabados']) || !is_array($spec['acabados'])) {
            return 0.0;
        }
        $area = areaM2($Wc, $Hc);

        foreach ($spec['acabados'] as $a) {
            $pricing = $a['pricing'] ?? 'per_unit';
            $setup   = (float)($a['setup'] ?? 0);
            $unitCost = $this->unitCostFromTiersOrFixed($a, $tiraje);

            if ($pricing === 'per_m2') {
                $total += $unitCost * $area * $tiraje + $setup;
            } elseif ($pricing === 'flat') {
                $total += $unitCost + $setup;
            } else { // 'per_unit'
                $total += $unitCost * $tiraje + $setup;
            }
        }
        return $total;
    }

    /**
     * Obtiene el costo unitario desde 'tiers' o 'cost' fijo.
     */
    private function unitCostFromTiersOrFixed(array $a, int $qty): float
    {
        // Tiers
        if (!empty($a['tiers']) && is_array($a['tiers'])) {
            foreach ($a['tiers'] as $t) {
                $min = (int)($t['min'] ?? 0);
                $max = (int)($t['max'] ?? PHP_INT_MAX);
                if ($qty >= $min && $qty <= $max) {
                    return (float)$t['cost'];
                }
            }
        }
        // Fijo
        return (float)($a['cost'] ?? 0);
    }
}
