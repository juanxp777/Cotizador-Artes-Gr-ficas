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
    if (isset($_POST['agregar_producto'])) {
        $nombre = $_POST['nombre'];
        $tipo = $_POST['tipo'];
        $paginas_base = (int)$_POST['paginas_base'];
        $descripcion = $_POST['descripcion'];
        
        if (agregarProducto($nombre, $tipo, $paginas_base, $descripcion)) {
            $mensaje = "✅ Producto agregado correctamente";
        } else {
            $mensaje = "❌ Error al agregar producto";
        }
    }
    
    if (isset($_POST['actualizar_producto'])) {
        $id = (int)$_POST['id'];
        $nombre = $_POST['nombre'];
        $tipo = $_POST['tipo'];
        $paginas_base = (int)$_POST['paginas_base'];
        $descripcion = $_POST['descripcion'];
        
        if (actualizarProducto($id, $nombre, $tipo, $paginas_base, $descripcion)) {
            $mensaje = "✅ Producto actualizado correctamente";
        } else {
            $mensaje = "❌ Error al actualizar producto";
        }
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if (eliminarProducto($id)) {
        $mensaje = "✅ Producto eliminado correctamente";
    } else {
        $mensaje = "❌ Error al eliminar producto";
    }
}

// Obtener productos
$productos = obtenerProductos();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container admin-container">
        <h1>📦 Gestión de Productos Predefinidos</h1>
        
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
            <h2>➕ Agregar Nuevo Producto</h2>
            <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr auto; gap: 10px; align-items: end;">
                <div>
                    <label>Nombre del Producto:</label>
                    <input type="text" name="nombre" placeholder="Ej: Agenda Ejecutiva 2024" required>
                </div>
                <div>
                    <label>Tipo:</label>
                    <select name="tipo" required>
                        <option value="agenda">Agenda</option>
                        <option value="cuaderno">Cuaderno</option>
                        <option value="revista">Revista</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label>Páginas Base:</label>
                    <input type="number" name="paginas_base" min="1" value="100" required>
                </div>
                <div>
                    <label>Descripción:</label>
                    <input type="text" name="descripcion" placeholder="Descripción breve del producto">
                </div>
                <div>
                    <button type="submit" name="agregar_producto" class="submit-btn">➕ Agregar</button>
                </div>
            </form>
        </div>

        <div class="seccion-parametros">
            <h2>📋 Productos Configurados</h2>
            
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Producto</th>
                        <th style="padding: 12px; text-align: center;">Tipo</th>
                        <th style="padding: 12px; text-align: center;">Páginas</th>
                        <th style="padding: 12px; text-align: left;">Descripción</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">
                            <form method="POST" style="display: grid; grid-template-columns: 1fr; gap: 5px;">
                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required style="padding: 8px;">
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <select name="tipo" style="padding: 8px;">
                                <option value="agenda" <?php echo $producto['tipo'] == 'agenda' ? 'selected' : ''; ?>>Agenda</option>
                                <option value="cuaderno" <?php echo $producto['tipo'] == 'cuaderno' ? 'selected' : ''; ?>>Cuaderno</option>
                                <option value="revista" <?php echo $producto['tipo'] == 'revista' ? 'selected' : ''; ?>>Revista</option>
                                <option value="otro" <?php echo $producto['tipo'] == 'otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <input type="number" name="paginas_base" value="<?php echo $producto['paginas_base']; ?>" min="1" required style="padding: 8px; width: 80px;">
                        </td>
                        <td style="padding: 10px;">
                            <input type="text" name="descripcion" value="<?php echo htmlspecialchars($producto['descripcion']); ?>" style="padding: 8px; width: 100%;">
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <button type="submit" name="actualizar_producto" style="background: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-right: 5px;">✏️ Actualizar</button>
                            </form>
                            <a href="?eliminar=<?php echo $producto['id']; ?>" 
                               onclick="return confirm('¿Estás seguro de eliminar este producto?')"
                               style="background: #dc3545; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; display: inline-block;">🗑️ Eliminar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($productos)): ?>
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: #999;">
                            No hay productos configurados
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>