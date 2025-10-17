<?php
// --- LÓGICA PHP MEJORADA ---
require_once 'motor_cotizador.php';

$resultado = null;
$cantidad_solicitada = 0;
$errores = [];
$margenes = ['Premium' => 0.30];

// Inicializar datos del formulario con valores por defecto o enviados
$datos_formulario = [
    'num_paginas' => $_POST['num_paginas'] ?? '', 
    'tipo_interior' => $_POST['tipo_interior'] ?? 'repetidas_cmyk', 
    'acabado' => $_POST['acabado'] ?? ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar cantidad
    $cantidad_solicitada = (int)($_POST['cantidad'] ?? 0);
    if ($cantidad_solicitada <= 0) {
        $errores[] = "La cantidad debe ser mayor a 0";
    }
    
    // Validar páginas
    $num_paginas = (int)($_POST['num_paginas'] ?? 0);
    if ($num_paginas <= 0) {
        $errores[] = "El número de páginas debe ser mayor a 0";
    }
    
    // Validar tipo interior
    $tipo_interior = $_POST['tipo_interior'] ?? '';
    $tipos_permitidos = [
        'repetidas_1_color', 'repetidas_cmyk', 
        'diferentes_1_color', 'diferentes_cmyk'
    ];
    if (!in_array($tipo_interior, $tipos_permitidos)) {
        $errores[] = "Tipo de contenido interior no válido";
    }
    
    // Actualizar datos del formulario con los valores enviados
    $datos_formulario = [
        'num_paginas' => $num_paginas,
        'tipo_interior' => $tipo_interior,
        'acabado' => $_POST['acabado'] ?? ''
    ];
    
    // Si no hay errores, procesar
    if (empty($errores)) {
        $datos_cotizacion = array_merge(
            ['cantidad' => $cantidad_solicitada], 
            $datos_formulario
        );
        
        $resultado = encontrarMejorOpcion($datos_cotizacion);
        
        if ($resultado && !isset($resultado['error'])) {
    guardarCotizacion($datos_cotizacion, $resultado);
}
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador de Impresión Inteligente</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
    <div class="container">
        <h1>Cotizador de Impresión Inteligente</h1>
        <p>Ingresa los detalles del trabajo para encontrar la opción de producción más económica.</p>
        
        <!-- Mostrar errores -->
        <?php if (!empty($errores)): ?>
            <div class="alert alert-error">
                <strong>Errores:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        
        
       
       
       
       
       
       
        <form action="index.php" method="POST" class="cotizador-form">
    <!-- Selector de Productos Predefinidos -->
    <div class="form-group">
        <label for="producto_predefinido">Producto Predefinido (Opcional):</label>
        <select id="producto_predefinido" name="producto_predefinido" onchange="cargarProducto(this.value)">
            <option value="">-- Seleccionar producto predefinido --</option>
            <?php
            $productos = obtenerProductos();
            foreach ($productos as $producto) {
                $selected = isset($_POST['producto_predefinido']) && $_POST['producto_predefinido'] == $producto['id'] ? 'selected' : '';
                echo "<option value='{$producto['id']}' $selected>{$producto['nombre']} ({$producto['paginas_base']} páginas)</option>";
            }
            ?>
        </select>
        <small style="color: #666;">Selecciona un producto para cargar automáticamente sus características</small>
    </div>

    <div class="form-group">
        <label for="cantidad">Cantidad de Ejemplares:</label>
        <input type="number" id="cantidad" name="cantidad" 
               value="<?php echo $cantidad_solicitada > 0 ? $cantidad_solicitada : ($_POST['cantidad'] ?? ''); ?>" 
               placeholder="Ej: 500" required min="1">
    </div>
    
    <div class="form-group">
        <label for="num_paginas">Número de Páginas Interiores:</label>
        <input type="number" id="num_paginas" name="num_paginas" 
               value="<?php echo htmlspecialchars($datos_formulario['num_paginas']); ?>" 
               placeholder="Ej: 96" required min="1">
    </div>

    <!-- NUEVO: Selector de Papel Interno -->
    <div class="form-group">
        <label for="papel_interno">Papel Interno:</label>
        <select id="papel_interno" name="papel_interno" required>
            <option value="">-- Seleccionar papel interno --</option>
            <?php
            $papeles_bond = obtenerPapeles('bond');
            foreach ($papeles_bond as $papel) {
                $selected = isset($_POST['papel_interno']) && $_POST['papel_interno'] == $papel['nombre'] ? 'selected' : '';
                echo "<option value='{$papel['nombre']}' $selected>{$papel['nombre']} - $" . number_format($papel['costo_pliego'], 0, ',', '.') . "/pliego</option>";
            }
            ?>
        </select>
    </div>

    <!-- NUEVO: Selector de Papel Portadas -->
    <div class="form-group">
        <label for="papel_portadas">Papel Portadas:</label>
        <select id="papel_portadas" name="papel_portadas" required>
            <option value="">-- Seleccionar papel portadas --</option>
            <?php
            $papeles_esmaltado = obtenerPapeles('esmaltado');
            foreach ($papeles_esmaltado as $papel) {
                $selected = isset($_POST['papel_portadas']) && $_POST['papel_portadas'] == $papel['nombre'] ? 'selected' : '';
                echo "<option value='{$papel['nombre']}' $selected>{$papel['nombre']} - $" . number_format($papel['costo_pliego'], 0, ',', '.') . "/pliego</option>";
            }
            ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="tipo_interior">Tipo de Contenido Interior:</label>
        <select id="tipo_interior" name="tipo_interior" required>
            <option value="repetidas_1_color" <?php echo ($datos_formulario['tipo_interior'] == 'repetidas_1_color') ? 'selected' : ''; ?>>Hojas Repetidas (1 color)</option>
            <option value="repetidas_cmyk" <?php echo ($datos_formulario['tipo_interior'] == 'repetidas_cmyk') ? 'selected' : ''; ?>>Hojas Repetidas (Full Color)</option>
            <option value="diferentes_1_color" <?php echo ($datos_formulario['tipo_interior'] == 'diferentes_1_color') ? 'selected' : ''; ?>>Hojas Diferentes (1 color)</option>
            <option value="diferentes_cmyk" <?php echo ($datos_formulario['tipo_interior'] == 'diferentes_cmyk') ? 'selected' : ''; ?>>Hojas Diferentes (Full Color)</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="acabado">Acabado Principal:</label>
        <select id="acabado" name="acabado">
            <option value="" <?php echo ($datos_formulario['acabado'] == '') ? 'selected' : ''; ?>>Ninguno</option>
            <option value="Anillado" <?php echo ($datos_formulario['acabado'] == 'Anillado') ? 'selected' : ''; ?>>Anillado Doble O</option>
            <option value="Grapado" <?php echo ($datos_formulario['acabado'] == 'Grapado') ? 'selected' : ''; ?>>Grapado al Centro (Revista)</option>
        </select>
    </div>
    
    <button type="submit" class="submit-btn">🎯 Optimizar Cotización</button>
</form>







            
         <?php if ($resultado && $cantidad_solicitada > 0 && empty($errores)): ?>
            <div class="results">
                <?php if (isset($resultado['error'])): ?>
                    <h2>Error</h2>
                    <p><?php echo htmlspecialchars($resultado['error']); ?></p>
                <?php else: 
                    $pvp_total_premium = $resultado['costo_optimizado'] / (1 - $margenes['Premium']);
                    $calculo_ganador = $resultado['todos_los_calculos'][$resultado['mejor_opcion']];
                ?>
                    <!-- Sección de PVP Destacado -->
                    <!-- Sección de PVP Destacado -->
                    <div class="pvp-destacado">
                        <p>💰 MEJOR OPCIÓN RECOMENDADA</p>
                        <span class="metodo-pvp"><?php echo htmlspecialchars($resultado['mejor_opcion']); ?></span>
                        <span class="precio-pvp">$<?php echo number_format($pvp_total_premium, 0, ',', '.'); ?></span>
                        <div style="margin-top: 10px; font-size: 14px;">
                            <strong>Desglose del PVP:</strong><br>
                            • Costo producción: $<?php echo number_format($resultado['costo_optimizado'], 0, ',', '.'); ?><br>
                            • Margen (30%): $<?php echo number_format($pvp_total_premium - $resultado['costo_optimizado'], 0, ',', '.'); ?>
                        </div>
                    </div>

                    <!-- Listado de Materiales -->
                    <div class="tabla-container listado-materiales">
                        <h3>Listado de Materiales Requeridos</h3>
                        <table class="tabla-resultados">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Cantidad Necesaria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($calculo_ganador['materiales'] as $material => $cantidad): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($material); ?></td>
                                    <td><?php echo number_format($cantidad, 0, ',', '.'); ?> unidades</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Análisis Detallado -->
                    <div class="analisis-costos">
                        <h2>Análisis Detallado de Producción</h2>
                        <?php foreach ($resultado['todos_los_calculos'] as $nombre_opcion => $calculo): ?>
                            <?php if ($calculo['costo_total'] > 0): ?>
                                <div class="tabla-container desglose-container <?php if($nombre_opcion == $resultado['mejor_opcion']) echo 'mejor-opcion-glow'; ?>">
                                    <h3><?php echo htmlspecialchars($nombre_opcion); ?></h3>
                                    <table class="tabla-resultados tabla-analisis-detallado">
                                        <thead>
                                            <tr>
                                                <th>Ítem</th>
                                                <th>Costo Total</th>
                                                <th>PVP Total (30%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calculo['desglose'] as $linea): 
                                                $pvp_item = $linea['costo_total'] / (1 - $margenes['Premium']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($linea['item']); ?>
                                                    <?php if (isset($linea['cantidad'])): ?>
                                                        <small>(<?php echo $linea['cantidad'] . ' ' . ($linea['unidad'] ?? 'unidades'); ?>)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($linea['costo_total'], 0, ',', '.'); ?></td>
                                                <td>$<?php echo number_format($pvp_item, 0, ',', '.'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td><strong>TOTAL</strong></td>
                                                <td><strong>$<?php echo number_format($calculo['costo_total'], 0, ',', '.'); ?></strong></td>
                                                <td><strong>$<?php echo number_format($calculo['costo_total'] / (1 - $margenes['Premium']), 0, ',', '.'); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
                        <script>
function cargarProducto(productoId) {
    if (!productoId) return;
    
    // Hacer una petición para obtener los datos del producto
    fetch('obtener_producto.php?id=' + productoId)
        .then(response => response.json())
        .then(producto => {
            if (producto) {
                document.getElementById('num_paginas').value = producto.paginas_base;
                // Puedes agregar más campos aquí si es necesario
            }
        })
        .catch(error => console.error('Error:', error));
}

// Cargar producto si ya estaba seleccionado
<?php if (isset($_POST['producto_predefinido']) && $_POST['producto_predefinido']): ?>
document.addEventListener('DOMContentLoaded', function() {
    cargarProducto(<?php echo $_POST['producto_predefinido']; ?>);
});
<?php endif; ?>
</script>

                        
</body>
</html>