<?php
session_start();

// Contraseña hasheada (cambia 'mi_contraseña_segura' por una real)
$password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password

if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    $username = 'admin';
    
    if ($_POST['username'] === $username && password_verify($_POST['password'], $password_hash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = "Credenciales incorrectas";
    }
}

// Para generar un nuevo hash, usa:
// echo password_hash('tu_contraseña_segura', PASSWORD_DEFAULT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <div class="container" style="max-width: 400px;">
        <h1>Acceso Administrativo</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="submit-btn">Ingresar</button>
        </form>
    </div>
</body>
</html>