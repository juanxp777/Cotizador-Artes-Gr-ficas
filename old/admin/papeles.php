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
    if (isset($_POST['agregar_papel'])) {
        $nombre = $_POST['nombre'];
        $tipo = $_POST['tipo'];
        $gramaje = (int)$_POST['gramaje'];
        $proveedor = $_POST['proveedor'];
        $costo = (float)$_POST['costo_pliego'];
        $color = $_POST['color'];
        
        if (agregarPapel($nombre, $tipo, $gramaje, $proveedor, $costo, $color)) {
            $mensaje = "✅ Papel agregado correctamente";
        } else {
            $mensaje = "❌ Error al agregar papel";
        }
    }
    
    if (isset($_POST['actualizar_papel'])) {
        $id = (int)$_POST['id'];
        $costo = (float)$_POST['costo_pliego'];
        $proveedor = $_POST['proveedor'];
        
        if (actualizarPrecioPapel($id, $costo, $proveedor)) {
            $mensaje = "✅ Precio actualizado correctamente";
        } else {
            $mensaje = "❌ Error al actualizar precio";
        }
    }
}

// Obtener datos
$papeles = obtenerPapeles();
$tipos_papel = ['bond' => 'Bond', 'esmaltado' => 'Esmaltado', 'cartulina' => 'Cartulina', 'earthpack' => 'Earthpack', 'especial' => 'Especial'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Papeles</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>📄 Gestión de Papeles</h1>
        
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

        <?php if ($mensaje): ?>
            <div class="alert <?php echo strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para agregar papel -->
        <div class="seccion-parametros">
            <h2>➕ Agregar Nuevo Papel</h2>
            <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto; gap: 10px; align-items: end;">
                <div>
                    <label>Nombre:</label>
                    <input type="text" name="nombre" placeholder="Ej: Bond 75g" required>
                </div>
                <div>
                    <label>Tipo:</label>
                    <select name="tipo" required>
                        <?php foreach ($tipos_papel as $valor => $etiqueta): ?>
                            <option value="<?php echo $valor; ?>"><?php echo $etiqueta; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Gramaje:</label>
                    <input type="number" name="gramaje" min="50" max="400" required>
                </div>
                <div>
                    <label>Proveedor:</label>
                    <input type="text" name="proveedor" placeholder="Ej: Principal" required>
                </div>
                <div>
                    <label>Costo Pliego:</label>
                    <input type="number" name="costo_pliego" step="0.01" min="0" required>
                </div>
                <div>
                    <label>Color:</label>
                    <input type="text" name="color" placeholder="Ej: Blanco" required>
                </div>
                <div>
                    <button type="submit" name="agregar_papel" class="submit-btn">➕ Agregar</button>
                </div>
            </form>
        </div>

        <!-- Lista de papeles -->
        <div class="seccion-parametros">
            <h2>📋 Papeles Disponibles</h2>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Papel</th>
                        <th style="padding: 12px; text-align: center;">Tipo</th>
                        <th style="padding: 12px; text-align: center;">Gramaje</th>
                        <th style="padding: 12px; text-align: center;">Color</th>
                        <th style="padding: 12px; text-align: center;">Proveedor</th>
                        <th style="padding: 12px; text-align: right;">Costo Pliego</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($papeles as $papel): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($papel['nombre']); ?></strong>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $tipos_papel[$papel['tipo']] ?? $papel['tipo']; ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo $papel['gramaje']; ?>g
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo htmlspecialchars($papel['color']); ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <?php echo htmlspecialchars($papel['proveedor']); ?>
                        </td>
                        <td style="padding: 10px; text-align: right;">
                            <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                                <input type="hidden" name="id" value="<?php echo $papel['id']; ?>">
                                <input type="number" name="costo_pliego" value="<?php echo $papel['costo_pliego']; ?>" 
                                       step="0.01" min="0" style="width: 100px; padding: 5px;">
                                <input type="text" name="proveedor" value="<?php echo htmlspecialchars($papel['proveedor']); ?>" 
                                       placeholder="Proveedor" style="width: 120px; padding: 5px;">
                                <button type="submit" name="actualizar_papel" 
                                        style="background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                                    💰 Actualizar
                                </button>
                            </form>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <a href="historial_papel.php?id=<?php echo $papel['id']; ?>" 
                               style="color: #007bff; text-decoration: none; margin-right: 10px;">📊 Historial</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>