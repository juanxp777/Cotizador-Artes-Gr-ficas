<?php
class Database {
    // REEMPLAZA ESTOS VALORES CON TUS DATOS REALES
    private $host = 'localhost';
    private $db_name = 'cotizador';  // Cambiar por tu BD real
    private $username = 'root';               // Cambiar por tu usuario
    private $password = '';            // Cambiar por tu contraseña
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "<div class='alert alert-error'>";
            echo "<strong>Error de conexión:</strong> " . $exception->getMessage();
            echo "<br><small>Asegúrate de que la base de datos existe y las credenciales son correctas</small>";
            echo "</div>";
        }
        return $this->conn;
    }
}
?>