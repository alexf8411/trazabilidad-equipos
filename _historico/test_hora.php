<?php
/**
 * public/test_hora.php
 * Verificador de integridad temporal para auditoría
 */
require_once '../core/db.php'; // Usamos tu conexión real

// 1. Hora del Sistema Operativo
$os_date = shell_exec('date');

// 2. Hora de PHP
$php_date = date('Y-m-d H:i:s P'); // P muestra la diferencia horaria (-05:00)

// 3. Hora de MySQL (Donde se guardan los logs)
$stmt = $pdo->query("SELECT NOW() as db_time, @@system_time_zone as tz");
$row = $stmt->fetch();
$db_date = $row['db_time'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría de Tiempo</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f0f0f0; }
        .card { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #eee; padding: 10px 0; }
        .label { font-weight: bold; color: #555; }
        .value { font-family: monospace; font-size: 1.2em; color: #007bff; }
        .success { color: green; font-weight: bold; text-align: center; margin-top: 20px; }
        .alert { color: red; font-weight: bold; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>⏱️ Verificación de Hora Legal</h2>
        
        <div class="row">
            <span class="label">Sistema Operativo (Linux):</span>
            <span class="value"><?php echo $os_date; ?></span>
        </div>

        <div class="row">
            <span class="label">Aplicación (PHP):</span>
            <span class="value"><?php echo $php_date; ?></span>
        </div>

        <div class="row">
            <span class="label">Base de Datos (MySQL):</span>
            <span class="value"><?php echo $db_date; ?></span>
        </div>

        <?php 
        // Comparación simple de horas (ignorando segundos para evitar latencia)
        $php_simple = date('Y-m-d H:i');
        $db_simple = substr($db_date, 0, 16);
        
        if ($php_simple === $db_simple) {
            echo '<div class="success">✅ SINCRONIZACIÓN CORRECTA: Los logs serán válidos.</div>';
        } else {
            echo '<div class="alert">⚠️ ALERTA: Hay discrepancia de tiempo. Revisar configuración.</div>';
        }
        ?>
    </div>
</body>
</html>