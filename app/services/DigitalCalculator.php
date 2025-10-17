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
 * DigitalCalculator (con Params y tramos de acabados)
 *
 * - Usa digital_click_color / digital_click_bw desde Params
 * - Determina click según 'colores' de la parte:
 *   * '1/x' o 'x/1' => B/N
 *   * >=2 colores en cualquier cara => Color
 * - Acabados tercerizados con tramos (tiers) como en offset
 */
class DigitalCalculator
{
    private PDO $db;
    private array $ctx;
    private Params $params;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ctx = formatosBase();   // tamaños base
        $this->params = new Params($db);
    }

    /**
     * $spec esperado (mínimos):
     *  - nombre, ancho, alto, paginas, tiraje, colores ('4/4','1/1', etc.)
     *  - costo_hoja (SRA3 u hoja digital que uses)
     *  - acabados: array (ver descripción en OffsetCalculator)
     *  - margen (0.30 por defecto)
     */
    public function quote(array $spec): array
    {
        $formato = $this->ctx['digital']; // SRA3 por defecto
        $ups = calcularUps($formato['W'], $formato['H'], (float)$spec['ancho'], (float)$spec['alto'], 3);

        $tiraje  = (int)($spec['tiraje'] ?? 0);
        $paginas = (int)($spec['paginas'] ?? 2);

        // Hojas con merma (suave en digital)
        $hojas = hojasTotales($tiraje, $ups, 20, 0.02);

        // Impresiones: tiraje * páginas / ups
        $impresiones = (int)ceil(($tiraje * $paginas) / max($ups, 1));

        // Click cost desde Params (color vs B/N)
        $colors = $this->parseColors($spec['colores'] ?? '4/4');
        $isBW   = ($colors['front'] <= 1 && $colors['back'] <= 1);
        $clickCostUnit = $isBW
            ? $this->params->getFloat('digital_click_bw', 80)
            : $this->params->getFloat('digital_click_color', 200);

        $costo_click = $impresiones * $clickCostUnit;

        // Papel
        $costo_papel = costoPapel((float)($spec['costo_hoja'] ?? 0), $hojas);

        // Acabados con tramos
        $area_m2 = areaM2((float)$spec['ancho'], (float)$spec['alto']);
        $costo_acabados = $this->costAcabados($spec, $tiraje, $area_m2);

        // Total + margen
        $total_costo = $costo_click + $costo_papel + $costo_acabados;
        $margen = (float)($spec['margen'] ?? 0.30);
        $pvp = redondear($total_costo * (1 + $margen), 100);

        return [
            'tecnologia' => 'digital',
            'formato' => "{$formato['W']}x{$formato['H']} mm",
            'ups' => $ups,
            'hojas' => $hojas,
            'impresiones' => $impresiones,
            'costos' => [
                'clicks'    => $costo_click,
                'papel'     => $costo_papel,
                'acabados'  => $costo_acabados,
            ],
            'total_costo' => $total_costo,
            'pvp' => $pvp,
            'detalle' => sprintf(
                "Digital (%s): %d ups, %d hojas, %d impresiones. Papel $%.0f, Clicks $%.0f, Acabados $%.0f",
                $isBW ? 'B/N' : 'Color',
                $ups, $hojas, $impresiones,
                $costo_papel, $costo_click, $costo_acabados
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

    private function costAcabados(array $spec, int $tiraje, float $area_m2): float
    {
        $total = 0.0;
        if (empty($spec['acabados']) || !is_array($spec['acabados'])) {
            return 0.0;
        }
        foreach ($spec['acabados'] as $a) {
            $pricing = $a['pricing'] ?? 'per_unit';
            $setup   = (float)($a['setup'] ?? 0);
            $unitCost = $this->unitCostFromTiersOrFixed($a, $tiraje);

            if ($pricing === 'per_m2') {
                $total += $unitCost * $area_m2 * $tiraje + $setup;
            } elseif ($pricing === 'flat') {
                $total += $unitCost + $setup;
            } else { // 'per_unit'
                $total += $unitCost * $tiraje + $setup;
            }
        }
        return $total;
    }

    private function unitCostFromTiersOrFixed(array $a, int $qty): float
    {
        if (!empty($a['tiers']) && is_array($a['tiers'])) {
            foreach ($a['tiers'] as $t) {
                $min = (int)($t['min'] ?? 0);
                $max = (int)($t['max'] ?? PHP_INT_MAX);
                if ($qty >= $min && $qty <= $max) {
                    return (float)$t['cost'];
                }
            }
        }
        return (float)($a['cost'] ?? 0);
    }
}
