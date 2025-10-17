<?php
session_start();
require_once '../config/database.php';
require_once '../motor_cotizador.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Obtener historial de cotizaciones
$cotizaciones = obtenerHistorialCotizaciones(100);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cotizaciones</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>📋 Historial de Cotizaciones</h1>
        
        <div class="admin-nav">
            <a href="index.php">📊 Parámetros</a>
            <a href="acabados.php">🔧 Acabados</a>
            <a href="cotizaciones.php">📋 Cotizaciones</a>
            <a href="productos.php">📦 Productos</a>
            <a href="../index.php">🎯 Ir al Cotizador</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>

        <div class="seccion-parametros">
            <h2>Últimas 100 Cotizaciones</h2>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Fecha</th>
                        <th style="padding: 12px; text-align: center;">Cantidad</th>
                        <th style="padding: 12px; text-align: center;">Páginas</th>
                        <th style="padding: 12px; text-align: left;">Tipo</th>
                        <th style="padding: 12px; text-align: left;">Acabado</th>
                        <th style="padding: 12px; text-align: right;">Costo Producción</th>
                        <th style="padding: 12px; text-align: left;">Mejor Opción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cotizaciones as $cotizacion): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <?php echo date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo number_format($cotizacion['cantidad']); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $cotizacion['paginas']; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php 
                            $tipo = $cotizacion['tipo_interior'];
                            $color = strpos($tipo, 'cmyk') !== false ? '#007bff' : '#6c757d';
                            $icono = strpos($tipo, 'cmyk') !== false ? '🎨' : '⚫';
                            echo "<span style='color: $color;'>$icono " . htmlspecialchars($tipo) . "</span>";
                            ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo $cotizacion['acabado'] ? htmlspecialchars($cotizacion['acabado']) : '<em style="color: #999;">Ninguno</em>'; ?>
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: bold;">
                            $<?php echo number_format($cotizacion['costo_total'], 0, ',', '.'); ?>
                        </td>
                        <td style="padding: 10px;">
                            <span style="background: #e7f3ff; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                <?php echo htmlspecialchars($cotizacion['mejor_opcion']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($cotizaciones)): ?>
                    <tr>
                        <td colspan="7" style="padding: 20px; text-align: center; color: #999;">
                            No hay cotizaciones en el historial
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>