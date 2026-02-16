<?php
/**
 * test_db.php - Prueba de conexión a Base de Datos 
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test BD</title>";
echo "<style>body{font-family:Arial;max-width:600px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🧪 Prueba de Conexión a Base de Datos</h1>";

try {
    require_once '../core/db.php';
    
    // Verificar conexión
    $stmt = $pdo->query("SELECT VERSION() as version");
    $row = $stmt->fetch();
    
    echo "<div class='success'>";
    echo "<h3>✅ Conexión Exitosa</h3>";
    echo "<p><strong>Versión MySQL:</strong> " . $row['version'] . "</p>";
    
    // Contar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>Tablas encontradas:</strong> " . count($tables) . "</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error de Conexión</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<p><a href='configuracion.php'>← Volver a Configuración</a></p>";
echo "</body></html>";
?>
