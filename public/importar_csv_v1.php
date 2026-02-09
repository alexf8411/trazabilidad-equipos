<?php
/**
 * public/importar_csv.php
 * Importación masiva con detector de duplicados amigable y lógica sincronizada
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONFIGURACIÓN DE PODER (Aprovechando tus 7GB RAM)
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    die("No tienes permisos para esta acción.");
}

/**
 * Función para procesar cada fila del CSV
 * Sincronizada con la lógica de alta_equipos.php
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos) {
    if (count($data) < 2 || empty($data[0]) || empty($data[1])) return;

    // Limpieza y Normalización
    $placa     = strtoupper(trim($data[0]));
    $serial    = strtoupper(trim($data[1]));
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $raw_fecha = trim($data[4]);
    $modalidad = trim($data[5]);
    $fecha_evento = date('Y-m-d H:i:s');

    // Escudo de Fechas (DD/MM/AAAA a YYYY-MM-DD)
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    // 1. Insertar en tabla Equipos
    $stmt_eq->execute([$placa, $serial, $marca, $modelo, $fecha_compra, $modalidad]);
    
    // 2. Insertar en tabla Bitácora (Sincronizado con carga individual)
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

$errores = [];
$exitos = 0;
$mensaje_exito = "";

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];

    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        try {
            // A. LOCALIZAR BODEGA (Debe existir en la tabla lugares)
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología' en el catálogo de lugares.");
            }

            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            // Preparar Consultas
            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, 'Alta')");
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, 'Ingreso', 'Bodega de TI', ?, ?, 'PENDIENTE')");

            // --- DETECTOR INTELIGENTE DE ENCABEZADOS ---
            $primera_fila = fgetcsv($handle, 1000, ",");
            if ($primera_fila) {
                $check = strtolower(trim($primera_fila[0]));
                $palabras_clave = ['placa', 'id', 'ur', 'placa_ur', 'equipo', 'codigo'];
                
                if (!in_array($check, $palabras_clave)) {
                    procesarFila($primera_fila, $stmt_eq, $stmt_bit, $bodega, $exitos);
                }
            }

            // --- PROCESAR EL RESTO DEL ARCHIVO ---
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos);
            }
            
            $pdo->commit();
            $mensaje_exito = "✅ ¡Éxito! Se han importado $exitos equipos correctamente a la Bodega.";
            fclose($handle);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            
            if ($e->getCode() == '23000') {
                preg_match("/Duplicate entry '(.*)' for key/", $e->getMessage(), $matches);
                $valor = $matches[1] ?? "desconocido";
                $errores[] = "⚠️ <b>Error de Duplicado:</b> El equipo con placa o serial <b>'$valor'</b> ya existe en la base de datos. Se canceló la carga para evitar errores.";
            } else {
                $errores[] = "❌ Error de Base de Datos: " . $e->getMessage();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "❌ Error: " . $e->getMessage();
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
        :root { --primary: #002D72; --bg: #f4f6f9; --warning: #ffc107; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 40px; }
        .import-card { max-width: 750px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .instruction-box { background: #fff8e1; border: 2px solid var(--warning); padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .csv-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85rem; }
        .csv-table th { background: #eee; border: 1px solid #ccc; padding: 8px; text-align: left; }
        .csv-table td { border: 1px solid #ccc; padding: 8px; }
        .date-alert { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; border-left: 5px solid #17a2b8; margin-top: 10px; font-weight: bold; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        input[type="file"] { margin: 20px 0; display: block; width: 100%; padding: 15px; background: #f8f9fa; border: 2px dashed var(--primary); border-radius: 8px; cursor: pointer; }
        .btn-import { background: var(--primary); color: white; border: none; padding: 16px 25px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 1.1rem; font-weight: bold; }
        .btn-secondary { display: block; text-align: center; text-decoration: none; color: var(--primary); padding: 10px; margin-top: 20px; font-weight: 500; }
    </style>
</head>
<body>

<div class="import-card">
    <h2 style="color:var(--primary); margin-top:0; text-align:center;">📥 Carga Masiva de Inventario</h2>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endforeach; ?>

    <div class="instruction-box">
        <h3 style="margin-top:0; color: #856404;">⚠️ ¡LEER ANTES DE SUBIR!</h3>
        <p>El archivo debe ser <strong>CSV (delimitado por comas)</strong> con estas 6 columnas:</p>
        
        <table class="csv-table">
            <thead>
                <tr>
                    <th>1. Placa</th><th>2. Serial</th><th>3. Marca</th><th>4. Modelo</th><th>5. Fecha</th><th>6. Modalidad</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>UR-1001</td><td>SN882233</td><td>HP</td><td>ProBook 440</td><td>25/10/2023</td><td>Leasing</td>
                </tr>
            </tbody>
        </table>

        <div class="date-alert">
            📅 FORMATO DE FECHA: Use DIA/MES/AÑO (Ejemplo: 31/12/2025).
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <label style="font-weight:bold; color:#444;">Seleccione el archivo CSV:</label>
        <input type="file" name="archivo_csv" accept=".csv" required>
        <button type="submit" name="importar" class="btn-import">🚀 SUBIR TODO A BODEGA</button>
    </form>

    <a href="alta_equipos.php" class="btn-secondary">⬅ Volver al Registro Individual</a>
</div>

</body>
</html>