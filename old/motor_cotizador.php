<?php
require_once 'config/database.php';

/**
 * ===================================================================
 * CONEXIÓN REAL A BASE DE DATOS
 * ===================================================================
 */
function leerCostosDeDB() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        return obtenerCostosPorDefecto();
    }
    
    try {
        // Leer parámetros de costos
        $query = "SELECT nombre, valor FROM parametros_costos";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $costos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $costos[$row['nombre']] = (float)$row['valor'];
        }
        
        // Leer acabados
        $query = "SELECT nombre_acabado, min_cantidad, max_cantidad, costo 
                  FROM acabados ORDER BY nombre_acabado, min_cantidad";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $acabados = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($acabados[$row['nombre_acabado']])) {
                $acabados[$row['nombre_acabado']] = [];
            }
            $acabados[$row['nombre_acabado']][] = [
                'min' => (int)$row['min_cantidad'],
                'max' => (int)$row['max_cantidad'],
                'costo' => (float)$row['costo']
            ];
        }
        
        $costos['acabados'] = $acabados;
        return $costos;
        
    } catch (Exception $e) {
        return obtenerCostosPorDefecto();
    }
}

/**
 * Datos por defecto en caso de error de conexión
 */
function obtenerCostosPorDefecto() {
    return [
        'costo_plancha_cmyk_cuarto' => 40000,
        'costo_plancha_cmyk_medio' => 80000,
        'costo_plancha_1color_medio' => 20000,
        'cantidad_grande_digital' => 500,
        'costo_tiraje_cuarto_cmyk' => 50000,
        'costo_tiraje_medio_cmyk' => 80000,
        'costo_tiraje_medio_1color' => 30000,
        'papel_bond_75g' => 400,
        'papel_esmaltado_150g' => 800,
        'costo_clic_color_normal' => 700,
        'costo_clic_color_grande' => 500,
        'costo_clic_bw_normal' => 200,
        'costo_clic_bw_grande' => 100,
        'acabados' => [
            'Anillado' => [
                ['min' => 1, 'max' => 50, 'costo' => 3000], 
                ['min' => 51, 'max' => 200, 'costo' => 2500], 
                ['min' => 201, 'max' => 9999, 'costo' => 2000]
            ],
            'Grapado' => [
                ['min' => 1, 'max' => 500, 'costo' => 200], 
                ['min' => 501, 'max' => 9999, 'costo' => 150]
            ],
            'Lomo Tapa Blanda (Hotmelt)' => [
                ['min' => 1, 'max' => 100, 'costo' => 5000],
                ['min' => 101, 'max' => 500, 'costo' => 3500],
                ['min' => 501, 'max' => 9999, 'costo' => 2500]
            ]
        ]
    ];
}

/**
 * ===================================================================
 * FUNCIÓN DE AYUDA PARA ACABADOS
 * ===================================================================
 */
function calcularCostoAcabados($datos) {
    if (!isset($datos['acabado']) || empty($datos['acabado'])) return 0;
    
    $COSTOS = leerCostosDeDB();
    $nombre_acabado = $datos['acabado'];
    $cantidad = $datos['cantidad'];
    $costo_unitario = 0;
    
    if (!isset($COSTOS['acabados'][$nombre_acabado])) return 0;
    
    foreach ($COSTOS['acabados'][$nombre_acabado] as $rango) {
        if ($cantidad >= $rango['min'] && $cantidad <= $rango['max']) {
            $costo_unitario = $rango['costo'];
            break;
        }
    }
    
    return $cantidad * $costo_unitario;
}

/**
 * ===================================================================
 * FUNCIÓN PRINCIPAL OPTIMIZADORA
 * ===================================================================
 */
function encontrarMejorOpcion($datos) {
    $opciones = [
        'Digital' => calcularCostoDigital($datos),
        'Offset Optimizado (Cuarto de Pliego)' => calcularCostoOffsetOptimizado($datos, 'cuarto_de_pliego'),
        'Offset Optimizado (Medio de Pliego)' => calcularCostoOffsetOptimizado($datos, 'medio_de_pliego')
    ];
    
    $resultados_validos = [];
    foreach ($opciones as $nombre => $calculo) {
        if (isset($calculo['costo_total']) && $calculo['costo_total'] > 0) {
            $resultados_validos[$nombre] = $calculo['costo_total'];
        }
    }
    
    if (empty($resultados_validos)) { 
        return ['error' => 'No se pudo generar una cotización.']; 
    }
    
    asort($resultados_validos);
    $mejor_opcion_nombre = key($resultados_validos);
    
    return [
        'mejor_opcion' => $mejor_opcion_nombre,
        'costo_optimizado' => $resultados_validos[$mejor_opcion_nombre],
        'todos_los_calculos' => $opciones
    ];
}

/**
 * ===================================================================
 * CEREBRO DE CÁLCULO #1: OFFSET OPTIMIZADO
 * ===================================================================
 */
function calcularCostoOffsetOptimizado($datos, $tamano_pliego) {
    $COSTOS = leerCostosDeDB();
    $cantidad = $datos['cantidad'];
    $desglose = [];
    $materiales = [];

    // Cálculo para cubiertas
    $costo_planchas_cub_offset = ($tamano_pliego == 'cuarto_de_pliego') ? $COSTOS['costo_plancha_cmyk_cuarto'] : $COSTOS['costo_plancha_cmyk_medio'];
    $costo_tirajes_cub_offset = ceil($cantidad / 1000) * 4 * (($tamano_pliego == 'cuarto_de_pliego') ? $COSTOS['costo_tiraje_cuarto_cmyk'] : $COSTOS['costo_tiraje_medio_cmyk']);
    $costo_cubiertas_offset = $costo_planchas_cub_offset + $costo_tirajes_cub_offset;
    
    $costo_clic_color = ($cantidad >= $COSTOS['cantidad_grande_digital']) ? $COSTOS['costo_clic_color_grande'] : $COSTOS['costo_clic_color_normal'];
    $costo_cubiertas_digital = (4 * $cantidad) * $costo_clic_color;

    // Decidir si imprimir cubiertas en digital u offset
    if ($costo_cubiertas_digital < $costo_cubiertas_offset && $cantidad < 500) {
        $desglose[] = ['item' => 'Impresión Cubiertas (Digital)', 'costo_total' => $costo_cubiertas_digital, 'cantidad' => null];
    } else {
        $desglose[] = ['item' => 'Planchas Cubiertas (Offset)', 'costo_total' => $costo_planchas_cub_offset, 'cantidad' => 4, 'unidad' => 'planchas'];
        $materiales['Planchas CMYK'] = ($materiales['Planchas CMYK'] ?? 0) + 4;
    }

    // Cálculo para interiores
    $PAGINAS_POR_PLANCHA = ($tamano_pliego == 'cuarto_de_pliego') ? 8 : 16;
    $es_cmyk = strpos($datos['tipo_interior'], 'cmyk') !== false;
    $son_diferentes = strpos($datos['tipo_interior'], 'diferentes') !== false;
    
    $num_formas = $son_diferentes ? ceil($datos['num_paginas'] / 2) : ceil($datos['num_paginas'] / $PAGINAS_POR_PLANCHA);
    $planchas_por_forma = $es_cmyk ? 4 : 1;
    $planchas_interiores = $num_formas * $planchas_por_forma;
    
    $costo_unitario_plancha_int = $es_cmyk ? $costo_planchas_cub_offset : $COSTOS['costo_plancha_1color_medio'];
    $costo_total_planchas_int = $num_formas * $costo_unitario_plancha_int;
    
    $desglose[] = ['item' => 'Planchas Interiores', 'costo_total' => $costo_total_planchas_int, 'cantidad' => $planchas_interiores, 'unidad' => 'planchas'];
    
    if ($es_cmyk) { 
        $materiales['Planchas CMYK'] = ($materiales['Planchas CMYK'] ?? 0) + $planchas_interiores; 
    } else { 
        $materiales['Planchas 1 Color'] = ($materiales['Planchas 1 Color'] ?? 0) + $planchas_interiores; 
    }

    // Cálculo de papel - USANDO PAPELES DINÁMICOS
    $pliegos_cubierta = ceil($cantidad / ($PAGINAS_POR_PLANCHA / 4));
    $pliegos_interiores = $num_formas * $cantidad;
    
    // Obtener costos reales de papel
    $costo_papel_interno = obtenerCostoPapel($datos['papel_interno'] ?? 'Bond 75g');
    $costo_papel_portadas = obtenerCostoPapel($datos['papel_portadas'] ?? 'Esmaltado 150g');
    
    $costo_papel = ($pliegos_interiores * $costo_papel_interno) + ($pliegos_cubierta * $costo_papel_portadas);
    
    $desglose[] = [
        'item' => 'Papel Interno (' . ($datos['papel_interno'] ?? 'Bond 75g') . ')', 
        'costo_total' => $pliegos_interiores * $costo_papel_interno, 
        'cantidad' => $pliegos_interiores, 
        'unidad' => 'pliegos'
    ];
    
    $desglose[] = [
        'item' => 'Papel Portadas (' . ($datos['papel_portadas'] ?? 'Esmaltado 150g') . ')', 
        'costo_total' => $pliegos_cubierta * $costo_papel_portadas, 
        'cantidad' => $pliegos_cubierta, 
        'unidad' => 'pliegos'
    ];
    
    $materiales['Papel Interno (' . ($datos['papel_interno'] ?? 'Bond 75g') . ')'] = $pliegos_interiores;
    $materiales['Papel Portadas (' . ($datos['papel_portadas'] ?? 'Esmaltado 150g') . ')'] = $pliegos_cubierta;

    // Acabados
    $costo_acabados = calcularCostoAcabados($datos);
    $desglose[] = ['item' => 'Acabados', 'costo_total' => $costo_acabados, 'cantidad' => null];

    // Costo total
    $costo_total_produccion = 0;
    foreach ($desglose as $linea) { 
        $costo_total_produccion += $linea['costo_total']; 
    }
    
    return [
        'costo_total' => $costo_total_produccion, 
        'desglose' => $desglose, 
        'materiales' => $materiales
    ];
}

/**
 * ===================================================================
 * CEREBRO DE CÁLCULO #2: DIGITAL
 * ===================================================================
 */
function calcularCostoDigital($datos) {
    $COSTOS = leerCostosDeDB();
    $cantidad = $datos['cantidad'];
    $materiales = [];

    // Costos por clic
    $costo_clic_color = ($cantidad >= $COSTOS['cantidad_grande_digital']) ? $COSTOS['costo_clic_color_grande'] : $COSTOS['costo_clic_color_normal'];
    $costo_clic_bw = ($cantidad >= $COSTOS['cantidad_grande_digital']) ? $COSTOS['costo_clic_bw_grande'] : $COSTOS['costo_clic_bw_normal'];

    // Impresión cubiertas (siempre a color)
    $costo_impresion_cubiertas = (4 * $cantidad) * $costo_clic_color;
    
    // Impresión interiores (depende del tipo)
    $es_color = strpos($datos['tipo_interior'], 'cmyk') !== false;
    $costo_por_pagina_interior = $es_color ? $costo_clic_color : $costo_clic_bw;
    $costo_impresion_interiores = ($datos['num_paginas'] * $cantidad) * $costo_por_pagina_interior;

    // Acabados
    $costo_acabados = calcularCostoAcabados($datos);

    // Cálculo de papel (estimado)
    $hojas_cubiertas = ceil($cantidad / 2); // 2 cubiertas por tabloide
    $hojas_interiores = ceil(($datos['num_paginas'] * $cantidad) / 4); // 4 páginas por tabloide
    $total_hojas = $hojas_cubiertas + $hojas_interiores;
    $materiales['Papel Tabloide'] = $total_hojas;
    
    // Desglose
    $desglose = [
        ['item' => 'Impresión Cubiertas', 'costo_total' => $costo_impresion_cubiertas, 'cantidad' => null],
        ['item' => 'Impresión Interiores', 'costo_total' => $costo_impresion_interiores, 'cantidad' => null],
        ['item' => 'Papel (Estimado)', 'costo_total' => 0, 'cantidad' => $total_hojas, 'unidad' => 'tabloides'],
        ['item' => 'Acabados', 'costo_total' => $costo_acabados, 'cantidad' => null]
    ];
    
    // Costo total
    $costo_total_produccion = $costo_impresion_cubiertas + $costo_impresion_interiores + $costo_acabados;

    return [
        'costo_total' => $costo_total_produccion, 
        'desglose' => $desglose, 
        'materiales' => $materiales
    ];
}

/**
 * ===================================================================
 * FUNCIONES PARA GESTIÓN DE PAPELES Y TAMAÑOS
 * ===================================================================
 */
function obtenerPapeles($filtro_tipo = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM papeles WHERE activo = 1";
        
        if ($filtro_tipo) {
            $query .= " AND tipo = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$filtro_tipo]);
        } else {
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function obtenerTamanosEstandar() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM tamanos_estandar WHERE activo = 1 ORDER BY tipo, nombre";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function agregarPapel($nombre, $tipo, $gramaje, $proveedor, $costo_pliego, $color) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO papeles (nombre, tipo, gramaje, proveedor, costo_pliego, color) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$nombre, $tipo, $gramaje, $proveedor, $costo_pliego, $color]);
        
    } catch (Exception $e) {
        error_log("Error agregando papel: " . $e->getMessage());
        return false;
    }
}

function actualizarPrecioPapel($id, $costo_pliego, $proveedor) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        // Primero obtener el costo anterior para el historial
        $query = "SELECT costo_pliego FROM papeles WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $papel_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Actualizar el precio
        $query = "UPDATE papeles SET costo_pliego = ?, proveedor = ?, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $db->prepare($query);
        $resultado = $stmt->execute([$costo_pliego, $proveedor, $id]);
        
        // Guardar en historial
        if ($resultado && $papel_actual) {
            $query = "INSERT INTO historial_precios_papel (papel_id, costo_anterior, costo_nuevo, proveedor) 
                      VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$id, $papel_actual['costo_pliego'], $costo_pliego, $proveedor]);
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error actualizando papel: " . $e->getMessage());
        return false;
    }
}

function obtenerCostoPapel($nombre_papel) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return 0;
    
    try {
        $query = "SELECT costo_pliego FROM papeles WHERE nombre = ? AND activo = 1 ORDER BY fecha_actualizacion DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$nombre_papel]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? $resultado['costo_pliego'] : 0;
        
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * ===================================================================
 * FUNCIONES DE GESTIÓN DE COTIZACIONES
 * ===================================================================
 */
function guardarCotizacion($datos_cotizacion, $resultado) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO cotizaciones 
                  (producto, cantidad, paginas, tipo_interior, acabado, costo_total, mejor_opcion, datos_cotizacion) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        
        // Serializar datos para guardar en BD
        $datos_json = json_encode($datos_cotizacion);
        $resultado_json = json_encode($resultado);
        
        return $stmt->execute([
            $datos_cotizacion['producto'] ?? 'general',
            $datos_cotizacion['cantidad'],
            $datos_cotizacion['num_paginas'],
            $datos_cotizacion['tipo_interior'],
            $datos_cotizacion['acabado'] ?? '',
            $resultado['costo_optimizado'],
            $resultado['mejor_opcion'],
            $resultado_json
        ]);
        
    } catch (Exception $e) {
        error_log("Error guardando cotización: " . $e->getMessage());
        return false;
    }
}

function obtenerHistorialCotizaciones($limite = 50) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM cotizaciones 
                  ORDER BY fecha_creacion DESC 
                  LIMIT ?";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * ===================================================================
 * FUNCIONES PARA GESTIÓN DE ACABADOS
 * ===================================================================
 */
function obtenerAcabados() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM acabados ORDER BY nombre_acabado, min_cantidad";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function agregarAcabado($nombre, $min, $max, $costo) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO acabados (nombre_acabado, min_cantidad, max_cantidad, costo) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$nombre, $min, $max, $costo]);
        
    } catch (Exception $e) {
        error_log("Error agregando acabado: " . $e->getMessage());
        return false;
    }
}

function actualizarAcabado($id, $min, $max, $costo) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "UPDATE acabados SET min_cantidad = ?, max_cantidad = ?, costo = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$min, $max, $costo, $id]);
        
    } catch (Exception $e) {
        error_log("Error actualizando acabado: " . $e->getMessage());
        return false;
    }
}

function eliminarAcabado($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "DELETE FROM acabados WHERE id = ?";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$id]);
        
    } catch (Exception $e) {
        error_log("Error eliminando acabado: " . $e->getMessage());
        return false;
    }
}

/**
 * ===================================================================
 * FUNCIONES PARA PRODUCTOS
 * ===================================================================
 */
function obtenerProductos() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return [];
    
    try {
        $query = "SELECT * FROM productos WHERE activo = 1 ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

function agregarProducto($nombre, $tipo, $paginas_base, $descripcion) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO productos (nombre, tipo, paginas_base, descripcion) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$nombre, $tipo, $paginas_base, $descripcion]);
        
    } catch (Exception $e) {
        error_log("Error agregando producto: " . $e->getMessage());
        return false;
    }
}

function actualizarProducto($id, $nombre, $tipo, $paginas_base, $descripcion) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "UPDATE productos SET nombre = ?, tipo = ?, paginas_base = ?, descripcion = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$nombre, $tipo, $paginas_base, $descripcion, $id]);
        
    } catch (Exception $e) {
        error_log("Error actualizando producto: " . $e->getMessage());
        return false;
    }
}

function eliminarProducto($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "DELETE FROM productos WHERE id = ?";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$id]);
        
    } catch (Exception $e) {
        error_log("Error eliminando producto: " . $e->getMessage());
        return false;
    }
}

function obtenerProducto($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return null;
    
    try {
        $query = "SELECT * FROM productos WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * ===================================================================
 * FUNCIÓN PARA ACTUALIZAR PARÁMETROS (PARA EL ADMIN)
 * ===================================================================
 */
function actualizarParametro($nombre, $valor) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO parametros_costos (nombre, valor) 
                  VALUES (:nombre, :valor) 
                  ON DUPLICATE KEY UPDATE valor = :valor, fecha_actualizacion = CURRENT_TIMESTAMP";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':valor', $valor);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        return false;
    }
}
?>