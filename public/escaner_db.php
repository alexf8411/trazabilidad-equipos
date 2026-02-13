<?php
// public/escaner_db.php
require_once '../core/db.php';

try {
    // Ya NO llamamos a getDB(), usamos el $pdo que viene de db.php
    if (!isset($pdo)) {
        die("Error: No se encontró la variable de conexión.");
    }
    
    $db_name = 'trazabilidad_local';

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
            <tr style='background:#f2f2f2;'><th>Tabla</th><th>Tamaño Total (MB)</th><th>Filas Aproximadas</th></tr>";
    while ($row = $stmtSize->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['Tabla']}</td><td>{$row['Tamano_MB']}</td><td>{$row['Filas']}</td></tr>";
    }
    echo "</table>";

    // 2. Estructura de Columnas e Índices por Tabla
    echo "<h2>2. Estructura de Tablas (Columnas) y 3. Índices Detallados</h2>";
    $stmtTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema = '$db_name'");
    
    while ($table = $stmtTables->fetch(PDO::FETCH_ASSOC)) {
        $tableName = $table['TABLE_NAME'];
        echo "<h3>Tabla: <span style='color: #004b87;'>$tableName</span></h3>";
        
        // --- EXTRAER COLUMNAS ---
        $stmtCols = $pdo->query("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA 
            FROM information_schema.COLUMNS 
            WHERE table_schema = '$db_name' AND TABLE_NAME = '$tableName'
            ORDER BY ORDINAL_POSITION;
        ");
        
        echo "<h4>Columnas:</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>
                <tr style='background:#e6f2ff;'>
                    <th>Columna</th><th>Tipo</th><th>Nulo</th><th>Indicador Llave</th><th>Por Defecto</th><th>Extra</th>
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

        // --- EXTRAER ÍNDICES DETALLADOS ---
        $stmtIdx = $pdo->query("
            SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE 
            FROM information_schema.STATISTICS 
            WHERE table_schema = '$db_name' AND TABLE_NAME = '$tableName'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX;
        ");
        
        echo "<h4>Índices y Llaves (El motor de búsqueda de esta tabla):</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 30px;'>
                <tr style='background:#ffe6e6;'>
                    <th>Nombre del Índice</th><th>Columna(s) Indexada(s)</th><th>¿Es Único?</th><th>Tipo de Índice</th>
                </tr>";
        
        $hasIndexes = false;
        while ($idx = $stmtIdx->fetch(PDO::FETCH_ASSOC)) {
            $hasIndexes = true;
            $es_unico = ($idx['NON_UNIQUE'] == 0) ? '<span style="color:green; font-weight:bold;">Sí (PRI/UNI)</span>' : 'No (Múltiple)';
            echo "<tr>
                    <td><strong>{$idx['INDEX_NAME']}</strong></td>
                    <td>{$idx['COLUMN_NAME']}</td>
                    <td>{$es_unico}</td>
                    <td>{$idx['INDEX_TYPE']}</td>
                  </tr>";
        }
        if (!$hasIndexes) {
            echo "<tr><td colspan='4' style='text-align:center; color:red;'>Esta tabla no tiene índices configurados.</td></tr>";
        }
        echo "</table><hr>";
    }

} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>