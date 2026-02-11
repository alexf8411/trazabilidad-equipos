<?php
/**
 * public/importar_csv.php
 * Importación masiva - Versión V1.8 URTRACK (Revisión Profesional)
 * Ajustes: Normalización de ENUM, Sincronización de Eventos y UI Institucional.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD Y CONFIGURACIÓN
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Procesa cada fila con normalización de datos para evitar errores de truncado
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos) {
    if (count($data) < 8) return;

    // Limpieza y Normalización
    $serial    = strtoupper(trim($data[0])); 
    $placa     = trim($data[1]); 
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) trim($data[5]);
    $raw_fecha = trim($data[6]);
    
    // Solución al error 'Data truncated': Normalizamos a CamelCase
    $modalidad_raw = strtolower(trim($data[7]));
    $modalidad = ucfirst($modalidad_raw); // convierte 'leasing' en 'Leasing'

    if (empty($serial) || empty($placa)) return;
    
    // Fechas
    $fecha_evento = date('Y-m-d H:i:s');
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    // Ejecución Atómica
    $stmt_eq->execute([$placa, $serial, $marca, $modelo, $vida_util, $precio, $fecha_compra, $modalidad]);
    
    $stmt_bit->execute([
        $serial, 
        $bodega['id'], 
        $bodega['sede'], 
        $bodega['nombre'], 
        'Alta',             // Evento actualizado Fase 3
        $_SESSION['usuario'], 
        $fecha_evento, 
        $_SESSION['usuario'], 
        $serial
    ]);
    
    $exitos++;
}

$errores = [];
$exitos = 0;
$mensaje_exito = "";

if (isset($_POST['importar'])) {
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo o no se seleccionó ninguno.";
    } else {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        try {
            // Buscamos la Bodega de Tecnología como punto de entrada universal
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Configuración faltante: No existe 'Bodega de Tecnología' en la tabla lugares.");

            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $primera_fila = fgetcsv($handle, 1000, ",");
            // Saltar cabecera si existe
            if ($primera_fila) {
                if (!in_array(strtolower(trim($primera_fila[0])), ['serial', 'sn', 'placa'])) {
                    procesarFila($primera_fila, $stmt_eq, $stmt_bit, $bodega, $exitos);
                }
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos);
            }
            
            $pdo->commit();
            $mensaje_exito = "Se han procesado e ingresado <strong>$exitos</strong> equipos a Bodega exitosamente.";
            fclose($handle);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = ($e->getCode() == '23000') ? "Error: Placa o Serial duplicado en el archivo." : "Error SQL: " . $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva - URTRACK</title>
    <style>
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f8f9fa; --success: #28a745; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 5px solid var(--primary); display: flex; justify-content: space-between; align-items: center; }
        .header-box h1 { margin: 0; font-size: 1.6rem; color: var(--primary); }

        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        .instruction-banner { background: #e7f1ff; border: 1px solid #b6d4fe; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .instruction-banner h3 { margin-top: 0; color: #084298; font-size: 1.1rem; }
        
        /* Tabla de ejemplo Responsive */
        .table-wrapper { overflow-x: auto; margin: 15px 0; border-radius: 8px; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; background: white; min-width: 700px; }
        th { background: #f1f3f5; padding: 12px; text-align: left; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid #eee; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 5px solid; }
        .alert-success { background: #d4edda; color: #155724; border-color: #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #dc3545; }

        .file-upload-area { border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 10px; cursor: pointer; transition: 0.3s; margin-bottom: 20px; background: #fafafa; }
        .file-upload-area:hover { border-color: var(--primary); background: #f0f4f9; }
        .file-upload-area input[type="file"] { display: none; }
        .file-label { font-size: 1.1rem; color: #666; cursor: pointer; display: block; }
        .file-label strong { color: var(--primary); }

        .btn-action { background: var(--primary); color: white; border: none; padding: 15px 30px; border-radius: 6px; font-size: 1.1rem; font-weight: bold; width: 100%; cursor: pointer; transition: 0.2s; }
        .btn-action:hover { background: #001a45; transform: translateY(-1px); }
        
        .btn-back { text-decoration: none; color: #666; font-weight: 600; font-size: 0.9rem; }

        @media (max-width: 768px) {
            .header-box { flex-direction: column; text-align: center; gap: 10px; }
            .card { padding: 20px; }
            .file-upload-area { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-box">
        <h1>📥 Carga Masiva de Activos</h1>
        <a href="alta_equipos.php" class="btn-back">⬅ Volver al registro individual</a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success">✅ <?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error">⚠️ <?= $error ?></div>
    <?php endforeach; ?>

    <div class="card">
        <div class="instruction-banner">
            <h3>📖 Requerimiento de Formato CSV</h3>
            <p style="font-size: 0.9rem; margin-bottom: 10px;">Para garantizar la integridad, el archivo debe contener 8 columnas en el siguiente orden estricto:</p>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>1. Serial</th>
                            <th>2. Placa UR</th>
                            <th>3. Marca</th>
                            <th>4. Modelo</th>
                            <th>5. Vida Útil</th>
                            <th>6. Precio</th>
                            <th>7. Fecha Compra</th>
                            <th>8. Modalidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>5CD240JL01</td>
                            <td>004589</td>
                            <td>HP</td>
                            <td>ProBook 440</td>
                            <td>5</td>
                            <td>3850000</td>
                            <td>25/10/2023</td>
                            <td>Leasing</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size: 0.8rem; color: #555;">* Formatos de fecha aceptados: 25/10/2023 o 2023-10-25. Modalidades válidas: Propio, Leasing, Proyecto.</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="file-upload-area" onclick="document.getElementById('archivo_csv').click();">
                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required onchange="updateFileName(this)">
                <span class="file-label" id="file-name-label">
                    Arrastra aquí tu archivo o <strong>haz clic para buscar</strong>
                </span>
            </div>
            
            <button type="submit" name="importar" class="btn-action">🚀 INICIAR CARGA A BODEGA</button>
        </form>
    </div>
</div>

<script>
function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : "Arrastra aquí tu archivo o haz clic para buscar";
    document.getElementById('file-name-label').innerHTML = "Archivo seleccionado: <strong>" + fileName + "</strong>";
}
</script>

</body>
</html>