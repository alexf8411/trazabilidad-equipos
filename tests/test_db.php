<?php
// 1. Incluimos la lógica de conexión
require_once 'conexion.php';

echo "<h1>Prueba de Conectividad: Sistema Trazabilidad</h1>";
echo "<hr>";

try {
    // 2. Invocamos la conexión
    $conn = Conexion::conectar();
    
    // 3. Si llegamos aquí, la conexión fue exitosa. Hacemos una consulta real.
    // Consultamos la versión de MySQL para confirmar que el motor responde.
    $stmt = $conn->query("SELECT VERSION() as version");
    $resultado = $stmt->fetch();

    echo "<p style='color:green; font-weight:bold;'>✅ ¡ÉXITO TOTAL!</p>";
    echo "<p>PHP se ha conectado correctamente a MySQL.</p>";
    echo "<p>Versión del Motor: " . $resultado['version'] . "</p>";
    echo "<p>Base de datos seleccionada: <code>trazabilidad_local</code></p>";

} catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ ERROR FATAL</p>";
    echo $e->getMessage();
}
?>
