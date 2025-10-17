<?php
require_once 'config/database.php';
require_once 'motor_cotizador.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $producto_id = (int)$_GET['id'];
    $producto = obtenerProducto($producto_id);
    
    if ($producto) {
        echo json_encode([
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'paginas_base' => $producto['paginas_base'],
            'tipo' => $producto['tipo'],
            'descripcion' => $producto['descripcion']
        ]);
    } else {
        echo json_encode(null);
    }
} else {
    echo json_encode(null);
}
?>