<?php
session_start();
require_once '../config/database.php';
require_once '../motor_cotizador.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas
$stats = obtenerEstadisticas();
$interacciones = obtenerInteraccionesWhatsApp(50);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analíticas y Reportes</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>📈 Analíticas y Reportes</h1>
        
        <div class="admin-nav">
            <a href="index.php">📊 Parámetros</a>
            <a href="acabados.php">🔧 Acabados</a>
            <a href="cotizaciones.php">📋 Cotizaciones</a>
            <a href="productos.php">📦 Productos</a>
            <a href="analiticas.php">📈 Analíticas</a>
            <a href="../index.php">🎯 Cotizador</a>
            <a href="logout.php">🚪 Salir</a>
        </div>

        <!-- Estadísticas -->
        <div class="seccion-parametros">
            <h2>📊 Resumen General</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: #e7f3ff; border-radius: 8px;">
                    <h3 style="margin: 0; color: #007bff;"><?php echo $stats['total_cotizaciones']; ?></h3>
                    <p style="margin: 5px 0 0 0;">Cotizaciones Totales</p>
                </div>
                <div style="text-align: center; padding: 20px; background: #d4edda; border-radius: 8px;">
                    <h3 style="margin: 0; color: #28a745;"><?php echo $stats['interacciones_whatsapp']; ?></h3>
                    <p style="margin: 5px 0 0 0;">Interacciones WhatsApp</p>
                </div>
                <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px;">
                    <h3 style="margin: 0; color: #ffc107;"><?php echo $stats['productos_activos']; ?></h3>
                    <p style="margin: 5px 0 0 0;">Productos Activos</p>
                </div>
            </div>
        </div>

        <!-- Interacciones WhatsApp -->
        <div class="seccion-parametros">
            <h2>💬 Últimas Interacciones WhatsApp</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Fecha</th>
                        <th style="padding: 12px; text-align: left;">Número</th>
                        <th style="padding: 12px; text-align: left;">Mensaje</th>
                        <th style="padding: 12px; text-align: left;">Respuesta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interacciones as $interaccion): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <?php echo date('d/m/Y H:i', strtotime($interaccion['fecha_interaccion'])); ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($interaccion['numero_whatsapp']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <em><?php echo htmlspecialchars($interaccion['mensaje_recibido']); ?></em>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo nl2br(htmlspecialchars($interaccion['mensaje_respuesta'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>