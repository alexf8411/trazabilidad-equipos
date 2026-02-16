<?php
/**
 * auth_query.php - Consultar usuario en LDAP
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Consultar Usuario</title>";
echo "<style>body{font-family:Arial;max-width:700px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo "form{background:#f8f9fa;padding:20px;border-radius:5px;margin:20px 0;}";
echo "input{width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:4px;}";
echo "button{background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;}";
echo "</style></head><body>";

echo "<h1>👤 Consultar Usuario en LDAP</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];
    
    if (!empty($usuario) && !empty($password)) {
        require_once '../core/auth.php';
        $resultado = autenticar_usuario($usuario, $password);
        
        if ($resultado['success']) {
            echo "<div class='success'>";
            echo "<h3>✅ Usuario Encontrado</h3>";
            echo "<table style='width:100%;border-collapse:collapse;'>";
            foreach ($resultado['data'] as $key => $value) {
                echo "<tr style='border-bottom:1px solid #ddd;'>";
                echo "<td style='padding:10px;font-weight:bold;'>$key:</td>";
                echo "<td style='padding:10px;'>" . htmlspecialchars($value) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<h3>❌ Error</h3>";
            echo "<p>" . htmlspecialchars($resultado['message']) . "</p>";
            echo "</div>";
        }
    }
}

echo "<form method='POST'>";
echo "<label><strong>Usuario (sin @dominio):</strong></label>";
echo "<input type='text' name='usuario' placeholder='guillermo.fonseca' required>";
echo "<label><strong>Contraseña:</strong></label>";
echo "<input type='password' name='password' required>";
echo "<button type='submit'>🔍 Consultar</button>";
echo "</form>";

echo "<p><a href='configuracion.php'>← Volver a Configuración</a></p>";
echo "</body></html>";
?>
