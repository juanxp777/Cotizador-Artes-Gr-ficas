<?php
require_once '../config/database.php';
require_once '../motor_cotizador.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['mensaje'])) {
            $response['message'] = 'Mensaje requerido';
            echo json_encode($response);
            exit;
        }

        $mensaje = strtolower(trim($input['mensaje']));
        $numero = $input['numero'] ?? 'desconocido';
        
        // Guardar interacción
        guardarInteraccionWhatsApp($numero, $mensaje, '');
        
        // Procesar mensaje
        $respuesta = procesarMensajeIA($mensaje, $numero);
        
        // Actualizar interacción con respuesta
        actualizarInteraccionWhatsApp($numero, $mensaje, $respuesta['mensaje']);
        
        $response = $respuesta;
        
    } else {
        $response['message'] = 'Método no permitido';
    }
} catch (Exception $e) {
    $response['message'] = 'Error interno: ' . $e->getMessage();
}

echo json_encode($response);

function procesarMensajeIA($mensaje, $numero) {
    // Buscar en base de conocimiento
    $respuesta = buscarEnConocimiento($mensaje);
    
    if ($respuesta) {
        return [
            'success' => true,
            'message' => $respuesta,
            'tipo' => 'conocimiento'
        ];
    }
    
    // Intentar extraer datos para cotización
    $datos_cotizacion = extraerDatosCotizacion($mensaje);
    
    if ($datos_cotizacion) {
        $resultado = encontrarMejorOpcion($datos_cotizacion);
        
        if (!isset($resultado['error'])) {
            guardarCotizacion($datos_cotizacion, $resultado);
            $respuesta_cotizacion = formatearRespuestaWhatsApp($datos_cotizacion, $resultado);
            
            return [
                'success' => true,
                'message' => $respuesta_cotizacion['data']['mensaje'],
                'tipo' => 'cotizacion',
                'data' => $respuesta_cotizacion['data']
            ];
        }
    }
    
    // Respuesta por defecto
    return [
        'success' => true,
        'message' => "¡Hola! 👋 Soy tu asistente de cotizaciones.\n\nPuedo ayudarte con:\n• Cotizaciones automáticas\n• Información sobre impresión\n• Tipos de papel y acabados\n\nPara una cotización, dime:\n- Cantidad de ejemplares\n- Número de páginas\n- Tipo de producto (agenda, revista, etc.)",
        'tipo' => 'ayuda'
    ];
}

function buscarEnConocimiento($mensaje) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return null;
    
    try {
        $query = "SELECT pregunta, respuesta, palabras_clave FROM conocimiento_ia WHERE activo = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $conocimiento = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($conocimiento as $item) {
            $palabras_clave = json_decode($item['palabras_clave'], true) ?? [];
            
            foreach ($palabras_clave as $palabra) {
                if (stripos($mensaje, $palabra) !== false) {
                    // Incrementar contador de uso
                    actualizarUsoConocimiento($item['id']);
                    return $item['respuesta'];
                }
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        return null;
    }
}

function extraerDatosCotizacion($mensaje) {
    $datos = [];
    
    // Extraer cantidad
    if (preg_match('/(\d+)\s*(ejemplares|unidades|copias|pzas)/i', $mensaje, $matches)) {
        $datos['cantidad'] = (int)$matches[1];
    } elseif (preg_match('/(\d+)\s*(agendas|cuadernos|revistas)/i', $mensaje, $matches)) {
        $datos['cantidad'] = (int)$matches[1];
    }
    
    // Extraer páginas
    if (preg_match('/(\d+)\s*páginas/i', $mensaje, $matches)) {
        $datos['num_paginas'] = (int)$matches[1];
    } elseif (preg_match('/(\d+)\s*pags/i', $mensaje, $matches)) {
        $datos['num_paginas'] = (int)$matches[1];
    }
    
    // Detectar tipo de producto
    if (stripos($mensaje, 'agenda') !== false) {
        $datos['producto'] = 'agenda';
    } elseif (stripos($mensaje, 'cuaderno') !== false) {
        $datos['producto'] = 'cuaderno';
    } elseif (stripos($mensaje, 'revista') !== false) {
        $datos['producto'] = 'revista';
    }
    
    // Si tenemos datos mínimos, retornar
    if (!empty($datos['cantidad']) && !empty($datos['num_paginas'])) {
        $datos['tipo_interior'] = 'repetidas_cmyk'; // Por defecto
        $datos['acabado'] = '';
        return $datos;
    }
    
    return null;
}

function guardarInteraccionWhatsApp($numero, $mensaje_recibido, $mensaje_respuesta) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "INSERT INTO interacciones_whatsapp (numero_whatsapp, mensaje_recibido, mensaje_respuesta) 
                  VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$numero, $mensaje_recibido, $mensaje_respuesta]);
        
    } catch (Exception $e) {
        return false;
    }
}

function actualizarInteraccionWhatsApp($numero, $mensaje_recibido, $mensaje_respuesta) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "UPDATE interacciones_whatsapp 
                  SET mensaje_respuesta = ?, fecha_interaccion = CURRENT_TIMESTAMP 
                  WHERE numero_whatsapp = ? AND mensaje_recibido = ? 
                  ORDER BY id DESC LIMIT 1";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$mensaje_respuesta, $numero, $mensaje_recibido]);
        
    } catch (Exception $e) {
        return false;
    }
}

function actualizarUsoConocimiento($id) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) return false;
    
    try {
        $query = "UPDATE conocimiento_ia SET uso_count = uso_count + 1 WHERE id = ?";
        $stmt = $db->prepare($query);
        
        return $stmt->execute([$id]);
        
    } catch (Exception $e) {
        return false;
    }
}
?>