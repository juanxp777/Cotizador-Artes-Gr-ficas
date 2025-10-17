<?php
session_start();
require_once '../config/database.php';
require_once '../motor_cotizador.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$database = new Database();
$db = $database->getConnection();

// Procesar acciones
if ($_POST) {
    if (isset($_POST['agregar_acabado'])) {
        $nombre = $_POST['nombre_acabado'];
        $min = (int)$_POST['min_cantidad'];
        $max = (int)$_POST['max_cantidad'];
        $costo = (float)$_POST['costo'];
        
        if (agregarAcabado($nombre, $min, $max, $costo)) {
            $mensaje = "✅ Acabado agregado correctamente";
        } else {
            $mensaje = "❌ Error al agregar acabado";
        }
    }
    
    if (isset($_POST['actualizar_acabado'])) {
        $id = (int)$_POST['id'];
        $min = (int)$_POST['min_cantidad'];
        $max = (int)$_POST['max_cantidad'];
        $costo = (float)$_POST['costo'];
        
        if (actualizarAcabado($id, $min, $max, $costo)) {
            $mensaje = "✅ Acabado actualizado correctamente";
        } else {
            $mensaje = "❌ Error al actualizar acabado";
        }
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if (eliminarAcabado($id)) {
        $mensaje = "✅ Acabado eliminado correctamente";
    } else {
        $mensaje = "❌ Error al eliminar acabado";
    }
}

// Obtener acabados
$acabados = obtenerAcabados();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Acabados</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>🔧 Gestión de Acabados</h1>
        
        <div class="admin-nav">
            <a href="index.php">📊 Parámetros</a>
            <a href="acabados.php">🔧 Acabados</a>
            <a href="cotizaciones.php">📋 Cotizaciones</a>
            <a href="productos.php">📦 Productos</a>
            <a href="../index.php">🎯 Ir al Cotizador</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert <?php echo strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="seccion-parametros">
            <h2>➕ Agregar Nuevo Acabado</h2>
            <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: end;">
                <div>
                    <label>Nombre del Acabado:</label>
                    <input type="text" name="nombre_acabado" required>
                </div>
                <div>
                    <label>Mínimo:</label>
                    <input type="number" name="min_cantidad" min="1" required>
                </div>
                <div>
                    <label>Máximo:</label>
                    <input type="number" name="max_cantidad" min="1" required>
                </div>
                <div>
                    <label>Costo Unitario:</label>
                    <input type="number" name="costo" step="0.01" min="0" required>
                </div>
                <div>
                    <button type="submit" name="agregar_acabado" class="submit-btn">➕ Agregar</button>
                </div>
            </form>
        </div>

        <div class="seccion-parametros">
            <h2>📋 Lista de Acabados</h2>
            
            <?php 
            $acabados_agrupados = [];
            foreach ($acabados as $acabado) {
                $nombre = $acabado['nombre_acabado'];
                if (!isset($acabados_agrupados[$nombre])) {
                    $acabados_agrupados[$nombre] = [];
                }
                $acabados_agrupados[$nombre][] = $acabado;
            }
            ?>
            
            <?php foreach ($acabados_agrupados as $nombre => $rangos): ?>
                <h3 style="margin-top: 20px; color: #333;"><?php echo htmlspecialchars($nombre); ?></h3>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; text-align: left;">Rango</th>
                            <th style="padding: 10px; text-align: right;">Costo Unitario</th>
                            <th style="padding: 10px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rangos as $rango): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">
                                <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 5px; align-items: center;">
                                    <input type="hidden" name="id" value="<?php echo $rango['id']; ?>">
                                    <input type="number" name="min_cantidad" value="<?php echo $rango['min_cantidad']; ?>" min="1" style="padding: 5px;">
                                    <input type="number" name="max_cantidad" value="<?php echo $rango['max_cantidad']; ?>" min="1" style="padding: 5px;">
                                    <input type="number" name="costo" value="<?php echo $rango['costo']; ?>" step="0.01" min="0" style="padding: 5px;">
                                    <button type="submit" name="actualizar_acabado" style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">✏️</button>
                                </form>
                            </td>
                            <td style="padding: 10px; text-align: right;">
                                $<?php echo number_format($rango['costo'], 0, ',', '.'); ?>
                            </td>
                            <td style="padding: 10px; text-align: center;">
                                <a href="?eliminar=<?php echo $rango['id']; ?>" 
                                   onclick="return confirm('¿Estás seguro de eliminar este rango?')"
                                   style="color: #dc3545; text-decoration: none;">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>