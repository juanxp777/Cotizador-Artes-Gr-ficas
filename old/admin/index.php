<?php
session_start();
require_once '../config/database.php';
require_once '../motor_cotizador.php';

// Verificar autenticación básica (luego mejoraremos la seguridad)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$database = new Database();
$db = $database->getConnection();

// Procesar actualización de parámetros
if ($_POST && isset($_POST['actualizar_parametros'])) {
    foreach ($_POST['parametros'] as $nombre => $valor) {
        if (actualizarParametro($nombre, $valor)) {
            $mensaje = "Parámetros actualizados correctamente";
        }
    }
}

// Obtener parámetros actuales
$parametros_actuales = leerCostosDeDB();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Cotizador</title>
    <link rel="stylesheet" href="../estilos.css">
    <style>
        .admin-container { max-width: 1000px; }
        .parametro-group { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .parametro-info { flex: 2; }
        .parametro-input { flex: 1; }
        .parametro-input input { 
            width: 100%; 
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .admin-nav { 
            background: #f8f9fa; 
            padding: 15px; 
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .admin-nav a { 
            margin-right: 15px; 
            text-decoration: none;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container admin-container">
        <h1>Panel Administrativo</h1>
        
        <div class="admin-nav">
            <a href="index.php">Parámetros</a>
            <a href="acabados.php">Acabados</a>
            <a href="cotizaciones.php">Cotizaciones</a>
            <a href="../index.php">Ir al Cotizador</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <h2>Parámetros de Costos</h2>
            
            <div style="background: white; border-radius: 8px; padding: 20px;">
                <!-- Impresión Offset -->
                <h3>Impresión Offset</h3>
                <?php mostrarParametro('costo_plancha_cmyk_cuarto', 'Plancha CMYK Cuarto Pliego', $parametros_actuales); ?>
                <?php mostrarParametro('costo_plancha_cmyk_medio', 'Plancha CMYK Medio Pliego', $parametros_actuales); ?>
                <?php mostrarParametro('costo_plancha_1color_medio', 'Plancha 1 Color Medio Pliego', $parametros_actuales); ?>
                <?php mostrarParametro('costo_tiraje_cuarto_cmyk', 'Tiraje Cuarto Pliego CMYK (por mil)', $parametros_actuales); ?>
                <?php mostrarParametro('costo_tiraje_medio_cmyk', 'Tiraje Medio Pliego CMYK (por mil)', $parametros_actuales); ?>
                <?php mostrarParametro('costo_tiraje_medio_1color', 'Tiraje Medio Pliego 1 Color (por mil)', $parametros_actuales); ?>

                <!-- Impresión Digital -->
                <h3>Impresión Digital</h3>
                <?php mostrarParametro('costo_clic_color_normal', 'Clic Color Normal', $parametros_actuales); ?>
                <?php mostrarParametro('costo_clic_color_grande', 'Clic Color Grande Cantidad', $parametros_actuales); ?>
                <?php mostrarParametro('costo_clic_bw_normal', 'Clic B/N Normal', $parametros_actuales); ?>
                <?php mostrarParametro('costo_clic_bw_grande', 'Clic B/N Grande Cantidad', $parametros_actuales); ?>
                <?php mostrarParametro('cantidad_grande_digital', 'Cantidad para Precio Especial Digital', $parametros_actuales); ?>

                <!-- Papeles -->
                <h3>Costos de Papel</h3>
                <?php mostrarParametro('papel_propalcote_150g', 'Papel Propalcote 150g (por pliego)', $parametros_actuales); ?>
                <?php mostrarParametro('papel_bond_75g', 'Papel Bond 75g (por pliego)', $parametros_actuales); ?>
            </div>

            <button type="submit" name="actualizar_parametros" class="submit-btn" style="margin-top: 20px;">
                Actualizar Todos los Parámetros
            </button>
        </form>
    </div>
</body>
</html>

<?php
function mostrarParametro($nombre, $label, $parametros) {
    $valor = $parametros[$nombre] ?? 0;
    echo "
    <div class='parametro-group'>
        <div class='parametro-info'>
            <strong>$label</strong><br>
            <small>Parámetro: $nombre</small>
        </div>
        <div class='parametro-input'>
            <input type='number' name='parametros[$nombre]' value='$valor' step='0.01' min='0' required>
        </div>
    </div>
    ";
}
?>