<?php
/**
 * public/importar_csv.php
 * Importación masiva de equipos vía CSV
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Seguridad: Solo Administradores o Recursos
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    die("No tienes permisos para esta acción.");
}

$errores = [];
$exitos = 0;

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];

    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        $handle = fopen($archivo, "r");
        $header = fgetcsv($handle, 1000, ","); // Saltar la primera línea (encabezados)

        $pdo->beginTransaction(); // Iniciamos transacción para seguridad

        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Estructura esperada: Placa, Serial, Marca, Modelo, FechaCompra, Modalidad
                $placa = trim($data[0]);
                $serial = trim($data[1]);
                $marca = trim($data[2]);
                $modelo = trim($data[3]);
                $fecha_compra = trim($data[4]); // Formato YYYY-MM-DD
                $modalidad = trim($data[5]);

                if (empty($placa) || empty($serial)) continue;

                // 1. Insertar Equipo
                $sql_eq = "INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) 
                           VALUES (?, ?, ?, ?, ?, ?, 'Alta')";
                $stmt_eq = $pdo->prepare($sql_eq);
                $stmt_eq->execute([$placa, $serial, $marca, $modelo, $fecha_compra, $modalidad]);

                // 2. Crear primer evento en Bitácora (Ingreso a Bodega)
                $sql_bit = "INSERT INTO bitacora (serial_equipo, sede, ubicacion, tipo_evento, correo_responsable, tecnico_responsable, hostname) 
                            VALUES (?, 'BODEGA CENTRAL', 'STOCK INICIAL', 'Ingreso Masivo', 'almacen@universidad.edu.co', ?, 'PENDIENTE')";
                $stmt_bit = $pdo->prepare($sql_bit);
                $stmt_bit->execute([$serial, $_SESSION['correo']]);

                $exitos++;
            }
            $pdo->commit();
            $mensaje_exito = "Se han importado $exitos equipos correctamente.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = "Error en la fila $exitos: " . $e->getMessage();
        }
        fclose($handle);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Equipos - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; }
        body { font-family: sans-serif; background: var(--bg); padding: 40px; }
        .import-card { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .template-info { background: #e7f1ff; padding: 15px; border-radius: 5px; font-size: 0.9rem; margin-bottom: 20px; border-left: 4px solid var(--primary); }
        input[type="file"] { margin: 20px 0; display: block; }
        .btn-import { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; width: 100%; font-size: 1rem; }
        .btn-back { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

<div class="import-card">
    <h2 style="color:var(--primary); margin-top:0;">📥 Importación Masiva (CSV)</h2>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endforeach; ?>

    <div class="template-info">
        <strong>Estructura del CSV (Separado por comas):</strong><br>
        <code>placa, serial, marca, modelo, fecha_compra, modalidad</code><br><br>
        <small>Ejemplo: UR-100, SN-555, Dell, Latitude 5420, 2023-10-25, Compra Directa</small>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <label>Selecciona tu archivo .csv:</label>
        <input type="file" name="archivo_csv" accept=".csv" required>
        <button type="submit" name="importar" class="btn-import">Comenzar Importación</button>
    </form>

    <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>
</div>

</body>
</html>