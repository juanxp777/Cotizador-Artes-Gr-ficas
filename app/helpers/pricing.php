<?php
/**
 * app/helpers/pricing.php
 * Funciones genéricas para cálculo de impresión.
 *
 * Incluye:
 * - Validación de páginas (revistas)
 * - Cálculo de lomo (spine)
 * - UPS por formato
 * - Merma
 * - Costos base por hoja
 */

namespace App\Helpers;

/* =======================================================
 * VALIDACIÓN Y MEDIDAS
 * =======================================================
 */

/**
 * Valida páginas (solo revistas necesitan múltiplos de 4)
 */
function validarPaginas(string $tipoProducto, int $paginas): array {
    $warn = null;
    if ($tipoProducto === 'revista' && $paginas % 4 !== 0) {
        $warn = "Se recomienda que las revistas tengan múltiplos de 4 páginas.";
    }
    return [$warn, $paginas];
}

/**
 * Calcula el lomo (spine) según cantidad de páginas y caliper del papel (en mm)
 */
function lomoMM(int $paginas, float $caliperMM): float {
    return round(($paginas / 2) * $caliperMM, 2);
}

/**
 * Conversión mm → metros cuadrados (útil para acabados por m²)
 */
function areaM2(float $anchoMM, float $altoMM): float {
    return ($anchoMM / 1000) * ($altoMM / 1000);
}

/* =======================================================
 * UPS (piezas por pliego)
 * =======================================================
 */

/**
 * Calcula UPS (número de piezas que caben en el formato de impresión)
 * considerando sangrado y posible rotación.
 */
function calcularUps(
    float $Wformato, float $Hformato,
    float $Wpieza, float $Hpieza,
    float $sangrado = 3
): int {
    $w = $Wpieza + 2 * $sangrado;
    $h = $Hpieza + 2 * $sangrado;

    $orient1 = floor($Wformato / $w) * floor($Hformato / $h);
    $orient2 = floor($Wformato / $h) * floor($Hformato / $w);

    return max($orient1, $orient2, 1);
}

/**
 * Retorna el área útil impresa por hoja (mm²)
 */
function areaUtilMM2(float $Wpieza, float $Hpieza, int $ups): float {
    return $Wpieza * $Hpieza * $ups;
}

/* =======================================================
 * MERMAS Y PRODUCCIÓN
 * =======================================================
 */

/**
 * Calcula hojas requeridas incluyendo mermas.
 */
function hojasTotales(
    int $tiraje, int $ups,
    int $setupWaste = 50,
    float $runWastePct = 0.03
): int {
    $base = ceil($tiraje / max($ups, 1));
    $merma = ceil($base * $runWastePct) + $setupWaste;
    return $base + $merma;
}

/**
 * Calcula costo de papel total (por hoja)
 */
function costoPapel(float $costoHoja, int $hojasTotales): float {
    return $costoHoja * $hojasTotales;
}

/**
 * Calcula costo de planchas para offset
 */
function costoPlanchas(int $numPlanchas, float $costoPorPlancha): float {
    return $numPlanchas * $costoPorPlancha;
}

/**
 * Calcula tiempo de impresión (en horas)
 */
function tiempoProduccionHoras(int $impresiones, int $velocidadIPH): float {
    return round($impresiones / max($velocidadIPH, 1), 2);
}

/**
 * Calcula costo de máquina (por hora)
 */
function costoMaquina(float $costoHora, float $horas): float {
    return $costoHora * $horas;
}

/**
 * Redondeo configurable
 */
function redondear($valor, int $multiple = 100): float {
    return round($valor / $multiple) * $multiple;
}

/* =======================================================
 * AUXILIARES DE PRODUCTO
 * =======================================================
 */

/**
 * Retorna un arreglo con formatos típicos (mm)
 */
function formatosBase(): array {
    return [
        'digital'   => ['W' => 320, 'H' => 450],   // SRA3
        'cuarto'    => ['W' => 350, 'H' => 500],
        'medio'     => ['W' => 500, 'H' => 700],
    ];
}

/**
 * Determina si el producto requiere validar múltiplos de 4
 */
function requiereMultiplo4(string $tipo): bool {
    return ($tipo === 'revista');
}
