<?php
/**
 * public/importar_csv.php
 * Importación masiva sincronizada con alta_equipos.php
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONFIGURACIÓN DE PODER (Optimizado para tus 7GB RAM / 4 Cores)
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

// 2. SEGURIDAD
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    die("No tienes permisos para esta acción.");
}

$errores = [];
$exitos = 0;
$mensaje_exito = "";

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];

    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        try {
            // A. LOCALIZAR BODEGA (Exactamente igual que en alta_equipos.php)
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología' en el catálogo de lugares.");
            }

            $handle = fopen($archivo, "r");
            fgetcsv($handle, 1000, ","); // Saltar encabezados

            $pdo->beginTransaction();

            // 3. PREPARAR CONSULTAS (Sincronizadas con alta_equipos.php)
            $sql_eq = "INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Alta')";
            $stmt_eq = $pdo->prepare($sql_eq);

            // Nota: Se usa 'Ingreso' y 'Bodega de TI' para evitar errores de truncado y mantener consistencia
            $sql_bit = "INSERT INTO bitacora (
                            serial_equipo, id_lugar, sede, ubicacion, 
                            tipo_evento, correo_responsable, fecha_evento, 
                            tecnico_responsable, hostname
                         ) VALUES (?, ?, ?, ?, 'Ingreso', 'Bodega de TI', ?, ?, 'PENDIENTE')";
            $stmt_bit = $pdo->prepare($sql_bit);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (empty($data[0]) || empty($data[1])) continue;

                // Limpieza y formato
                $placa     = strtoupper(trim($data[0]));
                $serial    = strtoupper(trim($data[1]));
                $marca     = trim($data[2]);
                $modelo    = trim($data[3]);
                $raw_fecha = trim($data[4]);
                $modalidad = trim($data[5]);
                $fecha_evento = date('Y-m-d H:i:s'); // Misma variable que en alta_equipos.php

                // ESCUDO DE FECHAS (Normalización para MySQL)
                $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
                $timestamp = strtotime($fecha_normalizada);
                $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

                // EJECUCIÓN 1: TABLA EQUIPOS
                $stmt_eq->execute([$placa, $serial, $marca, $modelo, $fecha_compra, $modalidad]);

                // EJECUCIÓN 2: TABLA BITÁCORA (Mismo orden y datos que carga individual)
                $stmt_bit->execute([
                    $serial, 
                    $bodega['id'], 
                    $bodega['sede'], 
                    $bodega['nombre'],
                    $fecha_evento,
                    $_SESSION['nombre']
                ]);

                $exitos++;
            }
            
            $pdo->commit();
            $mensaje_exito = "✅ ¡Éxito! Se han importado $exitos equipos correctamente a la Bodega de Tecnología.";
            fclose($handle);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "❌ Error en la fila " . ($exitos + 1) . ": " . $e->getMessage();
        }
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
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 40px; }
        .import-card { max-width: 650px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .template-info { background: #e7f1ff; padding: 20px; border-radius: 5px; font-size: 0.9rem; margin-bottom: 25px; border-left: 5px solid var(--primary); }
        .code-box { background: #fff; padding: 10px; border: 1px solid #b6d4fe; border-radius: 4px; font-family: monospace; display: block; margin: 10px 0; }
        input[type="file"] { margin: 20px 0; display: block; width: 100%; padding: 10px; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 6px; }
        .btn-import { background: var(--primary); color: white; border: none; padding: 14px 25px; border-radius: 5px; cursor: pointer; width: 100%; font-size: 1rem; font-weight: bold; }
        .btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 25px; }
        .btn-secondary { display: block; text-align: center; text-decoration: none; color: var(--primary); padding: 10px; border: 1px solid var(--primary); border-radius: 5px; font-weight: 500; transition: 0.3s; }
    </style>
</head>
<body>

<div class="import-card">
    <h2 style="color:var(--primary); margin-top:0;">📥 Importación Masiva (Sincronizada)</h2>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endforeach; ?>

    <div class="template-info">
        <strong>📋 Orden de Columnas en CSV:</strong>
        <span class="code-box">placa, serial, marca, modelo, fecha_compra, modalidad</span>
        <small>ℹ️ El sistema usará la lógica de "Bodega de Tecnología" para el ingreso.</small>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <label style="font-weight:bold; color:#444;">Seleccionar Archivo CSV:</label>
        <input type="file" name="archivo_csv" accept=".csv" required>
        <button type="submit" name="importar" class="btn-import">🚀 Iniciar Carga Masiva</button>
    </form>

    <div class="btn-group">
        <a href="alta_equipos.php" class="btn-secondary">➕ Registro Individual</a>
        <a href="inventario.php" style="text-align:center; color:#666; text-decoration:none; font-size:0.85rem; margin-top:10px;">📦 Ver Inventario</a>
    </div>
</div>

</body>
</html>