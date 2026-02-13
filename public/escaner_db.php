<?php
// public/escaner_db.php
require_once '../core/db.php';

try {
    $pdo = getDB();
    $db_name = 'trazabilidad_local'; // Nombre de la BD

    echo "<h1>Radiografía de la Base de Datos: URTRACK</h1>";

    // 1. Tamaño de las Tablas
    echo "<h2>1. Tamaño y Filas de Tablas</h2>";
    $stmtSize = $pdo->query("
        SELECT table_name AS 'Tabla', 
               round(((data_length + index_length) / 1024 / 1024), 2) AS 'Tamano_MB', 
               table_rows AS 'Filas'
        FROM information_schema.TABLES 
        WHERE table_schema = '$db_name' 
        ORDER BY (data_length + index_length) DESC;
    ");
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
            <tr style='background:#f2f2f2;'><th>Tabla</th><th>Tamaño (MB)</th><th>Filas Aproximadas</th></tr>";
    while ($row = $stmtSize->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['Tabla']}</td><td>{$row['Tamano_MB']}</td><td>{$row['Filas']}</td></tr>";
    }
    echo "</table>";

    // 2. Estructura de Columnas por Tabla
    echo "<h2>2. Estructura de Tablas (Columnas y Llaves)</h2>";
    $stmtTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema = '$db_name'");
    
    while ($table = $stmtTables->fetch(PDO::FETCH_ASSOC)) {
        $tableName = $table['TABLE_NAME'];
        echo "<h3>Tabla: $tableName</h3>";
        
        $stmtCols = $pdo->query("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA 
            FROM information_schema.COLUMNS 
            WHERE table_schema = '$db_name' AND TABLE_NAME = '$tableName'
            ORDER BY ORDINAL_POSITION;
        ");
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
                <tr style='background:#e6f2ff;'>
                    <th>Columna</th><th>Tipo</th><th>Nulo</th><th>Llave</th><th>Por Defecto</th><th>Extra</th>
                </tr>";
        while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                    <td><strong>{$col['COLUMN_NAME']}</strong></td>
                    <td>{$col['COLUMN_TYPE']}</td>
                    <td>{$col['IS_NULLABLE']}</td>
                    <td><strong>{$col['COLUMN_KEY']}</strong></td>
                    <td>{$col['COLUMN_DEFAULT']}</td>
                    <td>{$col['EXTRA']}</td>
                  </tr>";
        }
        echo "</table>";
    }

} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>