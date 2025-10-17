<?php
require_once '../config/database.php';
require_once '../motor_cotizador.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $response['message'] = 'Datos JSON inválidos';
            echo json_encode($response);
            exit;
        }

        $action = $input['action'] ?? 'cotizar';

        switch ($action) {
            case 'cotizar':
                $response = procesarCotizacionWhatsApp($input);
                break;
                
            case 'productos':
                $response = obtenerListaProductos();
                break;
                
            default:
                $response['message'] = 'Acción no válida';
        }
    } else {
        $response['message'] = 'Método no permitido';
    }
} catch (Exception $e) {
    $response['message'] = 'Error interno del servidor: ' . $e->getMessage();
}

echo json_encode($response);

function procesarCotizacionWhatsApp($data) {
    // Validar datos mínimos
    if (empty($data['cantidad']) || empty($data['paginas'])) {
        return [
            'success' => false,
            'message' => 'Faltan datos requeridos: cantidad y páginas'
        ];
    }

    $datos_cotizacion = [
        'cantidad' => (int)$data['cantidad'],
        'num_paginas' => (int)$data['paginas'],
        'tipo_interior' => $data['tipo_interior'] ?? 'repetidas_cmyk',
        'acabado' => $data['acabado'] ?? '',
        'producto' => $data['producto'] ?? 'general'
    ];

    // Calcular cotización
    $resultado = encontrarMejorOpcion($datos_cotizacion);
    
    if (isset($resultado['error'])) {
        return [
            'success' => false,
            'message' => $resultado['error']
        ];
    }

    // Guardar en historial
    guardarCotizacion($datos_cotizacion, $resultado);

    // Formatear respuesta para WhatsApp
    return formatearRespuestaWhatsApp($datos_cotizacion, $resultado);
}

function obtenerListaProductos() {
    $productos = obtenerProductos();
    
    $lista = [];
    foreach ($productos as $producto) {
        $lista[] = [
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'paginas_base' => $producto['paginas_base'],
            'tipo' => $producto['tipo']
        ];
    }
    
    return [
        'success' => true,
        'data' => $lista,
        'message' => count($lista) . ' productos encontrados'
    ];
}

function formatearRespuestaWhatsApp($datos, $resultado) {
    $costo_produccion = $resultado['costo_optimizado'];
    $pvp = $costo_produccion / (1 - 0.30); // 30% margen
    
    $mensaje = "📊 *COTIZACIÓN GENERADA*\n\n";
    $mensaje .= "📝 *Detalles:*\n";
    $mensaje .= "• Cantidad: " . number_format($datos['cantidad']) . " unidades\n";
    $mensaje .= "• Páginas: " . $datos['num_paginas'] . "\n";
    $mensaje .= "• Tipo: " . ucfirst(str_replace('_', ' ', $datos['tipo_interior'])) . "\n";
    
    if (!empty($datos['acabado'])) {
        $mensaje .= "• Acabado: " . $datos['acabado'] . "\n";
    }
    
    $mensaje .= "\n💰 *Costos:*\n";
    $mensaje .= "• Producción: $" . number_format($costo_produccion, 0, ',', '.') . "\n";
    $mensaje .= "• PVP (30%): $" . number_format($pvp, 0, ',', '.') . "\n";
    
    $mensaje .= "\n🏆 *Mejor Opción:*\n";
    $mensaje .= "• " . $resultado['mejor_opcion'] . "\n";
    
    $mensaje .= "\n📦 *Materiales Requeridos:*\n";
    $materiales = $resultado['todos_los_calculos'][$resultado['mejor_opcion']]['materiales'];
    foreach ($materiales as $material => $cantidad) {
        $mensaje .= "• " . $material . ": " . number_format($cantidad) . " unidades\n";
    }
    
    $mensaje .= "\n💡 *Sugerencia:*\n";
    $mensaje .= "Para más detalles visite: " . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . '/cotizador' : 'nuestro sitio web');

    return [
        'success' => true,
        'data' => [
            'mensaje' => $mensaje,
            'costo_produccion' => $costo_produccion,
            'pvp' => $pvp,
            'mejor_opcion' => $resultado['mejor_opcion'],
            'materiales' => $materiales
        ],
        'message' => 'Cotización generada exitosamente'
    ];
}
?>