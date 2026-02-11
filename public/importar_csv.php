<?php
/**
 * public/importar_csv.php
 * Importación masiva - Versión V1.6 Responsive
 * Ajustes:
 * - CSS adaptado para móviles.
 * - Tabla con scroll horizontal en pantallas pequeñas.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONFIGURACIÓN
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Función para procesar cada fila del CSV
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos) {
    if (count($data) < 8) return;

    // 0: Serial | 1: Placa | 2: Marca | 3: Modelo | 4: Vida Útil | 5: Precio | 6: Fecha | 7: Modalidad
    $serial    = strtoupper(trim($data[0])); 
    $placa     = trim($data[1]); 
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) trim($data[5]);
    $raw_fecha = trim($data[6]);
    $modalidad = trim($data[7]);
    
    if (empty($serial) || empty($placa)) return;
    
    $fecha_evento = date('Y-m-d H:i:s');
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    $stmt_eq->execute([
        $placa, $serial, $marca, $modelo, 
        $vida_util, $precio, 
        $fecha_compra, $modalidad
    ]);
    
    $stmt_bit->execute([
        $serial, $bodega['id'], $bodega['sede'], $bodega['nombre'], 
        $fecha_evento, $_SESSION['nombre'], $serial
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
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología'.");

            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, 'Ingreso', 'Bodega de TI', ?, ?, ?)");

            $primera_fila = fgetcsv($handle, 1000, ",");
            if ($primera_fila) {
                $check = strtolower(trim($primera_fila[0]));
                $palabras_clave = ['serial', 'sn', 'placa', 'marca', 'modelo'];
                if (!in_array($check, $palabras_clave)) {
                    procesarFila($primera_fila, $stmt_eq, $stmt_bit, $bodega, $exitos);
                }
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos);
            }
            
            $pdo->commit();
            $mensaje_exito = "✅ ¡Éxito! Se han importado $exitos equipos.";
            fclose($handle);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == '23000') {
                preg_match("/Duplicate entry '(.*)' for key/", $e->getMessage(), $matches);
                $valor = $matches[1] ?? "desconocido";
                $errores[] = "⚠️ <b>Error de Duplicado:</b> El valor <b>'$valor'</b> ya existe.";
            } else {
                $errores[] = "❌ Error SQL: " . $e->getMessage();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Equipos - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; --warning: #ffc107; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 40px; color: #333; margin: 0; }
        
        .import-card { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; box-sizing: border-box; }
        
        .instruction-box { background: #fff8e1; border: 2px solid var(--warning); padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        
        /* Contenedor para hacer la tabla scrollable en móviles */
        .table-responsive { width: 100%; overflow-x: auto; }
        
        .csv-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85rem; min-width: 600px; /* Asegura que no se aplaste */ }
        .csv-table th { background: #eee; border: 1px solid #ccc; padding: 8px; text-align: left; }
        .csv-table td { border: 1px solid #ccc; padding: 8px; }
        
        .date-alert { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; border-left: 5px solid #17a2b8; margin-top: 10px; font-weight: bold; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        input[type="file"] { margin: 20px 0; display: block; width: 100%; padding: 15px; background: #f8f9fa; border: 2px dashed var(--primary); border-radius: 8px; cursor: pointer; box-sizing: border-box; }
        
        .btn-import { background: var(--primary); color: white; border: none; padding: 16px 25px; border-radius: 6px; cursor: pointer; width: 100%; font-size: 1.1rem; font-weight: bold; }
        .btn-import:hover { background: #001f52; }
        
        .btn-secondary { display: block; text-align: center; text-decoration: none; color: var(--primary); padding: 10px; margin-top: 20px; font-weight: 500; }

        /* --- MEDIA QUERIES --- */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .import-card { padding: 20px; }
            h2 { font-size: 1.5rem; }
            .instruction-box { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="import-card">
    <h2 style="color:var(--primary); margin-top:0; text-align:center;">📥 Carga Masiva</h2>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endforeach; ?>

    <div class="instruction-box">
        <h3 style="margin-top:0; color: #856404;">⚠️ ESTRUCTURA OBLIGATORIA DEL CSV</h3>
        <p>El archivo debe tener exactamente estas <strong>8 columnas</strong> en orden:</p>
        
        <div class="table-responsive">
            <table class="csv-table">
                <thead>
                    <tr>
                        <th>1. Serial</th>
                        <th>2. Placa UR</th>
                        <th>3. Marca</th>
                        <th>4. Modelo</th>
                        <th>5. Vida Útil</th>
                        <th>6. Precio</th>
                        <th>7. Fecha</th>
                        <th>8. Modalidad</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>SN882233</td>
                        <td>004589</td>
                        <td>HP</td>
                        <td>ProBook 440</td>
                        <td>5</td>
                        <td>4500000</td>
                        <td>25/10/2023</td>
                        <td>Leasing</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="date-alert">
            ℹ️ <strong>Nota:</strong> El Hostname se generará automáticamente igual al Serial.
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