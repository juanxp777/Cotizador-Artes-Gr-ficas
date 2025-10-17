<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener tamaños
$tamanos = obtenerTamanosEstandar();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Tamaños</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>📐 Catálogo de Tamaños Estándar</h1>
        
        <div class="admin-nav">
            <a href="index.php">📊 Parámetros</a>
            <a href="acabados.php">🔧 Acabados</a>
            <a href="cotizaciones.php">📋 Cotizaciones</a>
            <a href="productos.php">📦 Productos</a>
            <a href="papeles.php">📄 Papeles</a>
            <a href="tamanos.php">📐 Tamaños</a>
            <a href="../index.php">🎯 Cotizador</a>
            <a href="logout.php">🚪 Salir</a>
        </div>

        <div class="seccion-parametros">
            <h2>📏 Tamaños Predefinidos</h2>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Nombre</th>
                        <th style="padding: 12px; text-align: center;">Ancho (cm)</th>
                        <th style="padding: 12px; text-align: center;">Alto (cm)</th>
                        <th style="padding: 12px; text-align: center;">Tipo</th>
                        <th style="padding: 12px; text-align: left;">Descripción</th>
                        <th style="padding: 12px; text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tamanos as $tamano): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($tamano['nombre']); ?></strong>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $tamano['ancho_cm']; ?> cm
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $tamano['alto_cm']; ?> cm
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo ucfirst($tamano['tipo']); ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo htmlspecialchars($tamano['descripcion']); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <span style="color: <?php echo $tamano['activo'] ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $tamano['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px;">
                <h4>💡 Nota:</h4>
                <p>Los tamaños están predefinidos según estándares de la industria. Para tamaños personalizados, se pueden ingresar manualmente en el cotizador.</p>
            </div>
        </div>
    </div>
</body>
</html>