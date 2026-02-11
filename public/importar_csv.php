<?php
/**
 * public/importar_csv.php
 * Versión V1.9.1 URTRACK - Fase 3: Registro Maestro
 * Revisión: Integridad de DB + UI Institucional Responsive
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD Y PERFORMANCE
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Procesa cada fila validando el delimitador ";" y el mapeo de tablas
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos, &$errores_filas) {
    if (count($data) < 8) {
        return; // Fila vacía o mal formada
    }

    // Limpieza y Normalización
    $serial    = strtoupper(trim($data[0])); 
    $placa     = trim($data[1]); 
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) trim($data[5]);
    $raw_fecha = trim($data[6]);
    
    // Normalización para ENUM de la base de datos
    $modalidad_input = strtolower(trim($data[7]));
    $modalidad = ucfirst($modalidad_input); // Convierte 'leasing' a 'Leasing'

    if (empty($serial) || empty($placa)) return;
    
    // Gestión de Fechas (Soporta DD/MM/YYYY o YYYY-MM-DD)
    $fecha_evento = date('Y-m-d H:i:s');
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    try {
        // Ejecución Atómica 1: Crear la Hoja de Vida del Equipo
        $stmt_eq->execute([
            $serial, 
            $placa, 
            $marca, 
            $modelo, 
            $vida_util, 
            $precio, 
            $fecha_compra, 
            $modalidad
        ]);
        
        // Ejecución Atómica 2: Registrar primer evento en Bitácora (Alta)
        $stmt_bit->execute([
            $serial,              // serial_equipo
            $bodega['id'],        // id_lugar
            'PENDIENTE',          // hostname (manual en Fase 4)
            $bodega['sede'],      // sede
            $bodega['nombre'],    // ubicacion (Bodega de Tecnología)
            $_SESSION['usuario'], // correo_responsable
            0,                    // equipo_adic (False por defecto)
            'Alta',               // tipo_evento (ENUM)
            $_SESSION['usuario']  // tecnico_responsable
        ]);
        
        $exitos++;
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $errores_filas[] = "Duplicado: Serial $serial o Placa $placa ya existen en el sistema.";
        } else {
            $errores_filas[] = "Error SQL en $serial: " . $e->getMessage();
        }
    }
}

$errores = [];
$mensaje_exito = "";
$exitos = 0;

if (isset($_POST['importar'])) {
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo o no se seleccionó ninguno.";
    } else {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        try {
            // Buscamos la Bodega de Tecnología como punto de entrada obligatorio
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Configuración Crítica: No existe 'Bodega de Tecnología' en la tabla lugares.");

            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            // SQL preparado según el DESCRIBE de tus tablas
            $stmt_eq = $pdo->prepare("INSERT INTO equipos (serial, placa_ur, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
            
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, hostname, sede, ubicacion, correo_responsable, equipo_adic, tipo_evento, tecnico_responsable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Procesar cabecera (Detectar si la primera fila es texto)
            $primera_fila = fgetcsv($handle, 1000, ";");
            if ($primera_fila) {
                // Si la primera columna contiene letras y no es un serial largo, asumimos que es cabecera
                if (!preg_match('/[0-9]/', $primera_fila[0])) {
                    // Es cabecera, se salta
                } else {
                    procesarFila($primera_fila, $stmt_eq, $stmt_bit, $bodega, $exitos, $errores);
                }
            }

            // Procesar el resto del archivo
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos, $errores);
            }
            
            $pdo->commit();
            if ($exitos > 0) {
                $mensaje_exito = "Se han procesado e ingresado <strong>$exitos</strong> equipos a Bodega exitosamente.";
            }
            fclose($handle);

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
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f8f9fa; --success: #28a745; --danger: #dc3545; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .header-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 5px solid var(--primary); display: flex; justify-content: space-between; align-items: center; }
        .header-box h1 { margin: 0; font-size: 1.6rem; color: var(--primary); }

        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        
        .instruction-banner { background: #e7f1ff; border: 1px solid #b6d4fe; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .instruction-banner h3 { margin-top: 0; color: #084298; font-size: 1.1rem; }
        
        .table-wrapper { overflow-x: auto; margin: 15px 0; border-radius: 8px; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; background: white; min-width: 700px; }
        th { background: #f1f3f5; padding: 12px; text-align: left; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid #eee; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 5px solid; font-size: 0.95rem; }
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
        <a href="dashboard.php" class="btn-back">⬅ Volver al Panel</a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success">✅ <?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
            <strong>Se encontraron los siguientes problemas:</strong>
            <ul style="margin: 10px 0 0 20px; padding: 0;">
                <?php foreach ($errores as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="instruction-banner">
            <h3>📖 Requerimiento de Formato CSV (Excel)</h3>
            <p style="font-size: 0.9rem; margin-bottom: 10px;">El archivo debe usar <strong>Punto y Coma (;)</strong> como separador y tener este orden:</p>
            
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
                            <td>SNAPL200000</td>
                            <td>121180</td>
                            <td>Apple</td>
                            <td>MacBook Pro 14</td>
                            <td>5</td>
                            <td>8500000</td>
                            <td>15/01/2025</td>
                            <td>Propio</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size: 0.8rem; color: #555;">* Formatos de fecha: 15/01/2025 o 2025-01-15. Modalidades: Propio, Leasing, Proyecto.</p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="file-upload-area" onclick="document.getElementById('archivo_csv').click();">
                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required onchange="updateFileName(this)">
                <span class="file-label" id="file-name-label">
                    Arrastra aquí tu archivo CSV o <strong>haz clic para buscar</strong>
                </span>
            </div>
            
            <button type="submit" name="importar" class="btn-action" id="submitBtn">🚀 INICIAR CARGA A BODEGA</button>
        </form>
    </div>
</div>

<script>
function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : "Arrastra aquí tu archivo o haz clic para buscar";
    document.getElementById('file-name-label').innerHTML = "Archivo seleccionado: <strong>" + fileName + "</strong>";
}

// Prevenir múltiples envíos
document.getElementById('uploadForm').onsubmit = function() {
    document.getElementById('submitBtn').innerHTML = "Procesando registros... por favor espere";
    document.getElementById('submitBtn').style.opacity = "0.7";
    document.getElementById('submitBtn').disabled = true;
};
</script>

</body>
</html>