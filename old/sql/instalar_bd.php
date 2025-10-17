<?php
require_once '../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Instalación Base de Datos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Instalación de Base de Datos</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos. Verifica las credenciales en config/database.php");
    }

    // Crear tabla de parámetros de costos
    $sql = "CREATE TABLE IF NOT EXISTS parametros_costos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        valor DECIMAL(12,2) NOT NULL,
        descripcion TEXT,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "<div class='success'>✓ Tabla 'parametros_costos' creada correctamente</div>";

    // Crear tabla de acabados
    $sql = "CREATE TABLE IF NOT EXISTS acabados (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre_acabado VARCHAR(50) NOT NULL,
        min_cantidad INT NOT NULL,
        max_cantidad INT NOT NULL,
        costo DECIMAL(10,2) NOT NULL
    )";
    
    $db->exec($sql);
    echo "<div class='success'>✓ Tabla 'acabados' creada correctamente</div>";

    // Insertar parámetros de costos
    $parametros = [
        ['costo_plancha_cmyk_cuarto', 40000, 'Costo por plancha CMYK cuarto pliego'],
        ['costo_plancha_cmyk_medio', 80000, 'Costo por plancha CMYK medio pliego'],
        ['costo_plancha_1color_medio', 20000, 'Costo por plancha 1 color medio pliego'],
        ['cantidad_grande_digital', 500, 'Cantidad mínima para precio especial digital'],
        ['costo_tiraje_cuarto_cmyk', 50000, 'Costo de tiraje por mil cuarto pliego CMYK'],
        ['costo_tiraje_medio_cmyk', 80000, 'Costo de tiraje por mil medio pliego CMYK'],
        ['costo_tiraje_medio_1color', 30000, 'Costo de tiraje por mil medio pliego 1 color'],
        ['papel_propalcote_150g', 600, 'Costo por pliego de papel propalcote 150g'],
        ['papel_bond_75g', 400, 'Costo por pliego de papel bond 75g'],
        ['costo_clic_color_normal', 700, 'Costo por clic color normal'],
        ['costo_clic_color_grande', 500, 'Costo por clic color para grandes cantidades'],
        ['costo_clic_bw_normal', 200, 'Costo por clic blanco y negro normal'],
        ['costo_clic_bw_grande', 100, 'Costo por clic blanco y negro para grandes cantidades']
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO parametros_costos (nombre, valor, descripcion) VALUES (?, ?, ?)");
    
    foreach ($parametros as $param) {
        $stmt->execute($param);
    }
    echo "<div class='success'>✓ Parámetros de costos insertados</div>";

    // Insertar acabados
    $acabados = [
        ['Anillado', 1, 50, 3000],
        ['Anillado', 51, 200, 2500],
        ['Anillado', 201, 9999, 2000],
        ['Grapado', 1, 500, 200],
        ['Grapado', 501, 9999, 150]
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO acabados (nombre_acabado, min_cantidad, max_cantidad, costo) VALUES (?, ?, ?, ?)");
    
    foreach ($acabados as $acabado) {
        $stmt->execute($acabado);
    }
    echo "<div class='success'>✓ Acabados insertados</div>";

    echo "<div class='info' style='margin-top: 20px;'>
        <strong>¡Instalación completada!</strong><br>
        Ya puedes usar el cotizador. <a href='../index.php'>Ir al cotizador</a>
    </div>";

} catch (Exception $e) {
    echo "<div class='error'>Error durante la instalación: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>