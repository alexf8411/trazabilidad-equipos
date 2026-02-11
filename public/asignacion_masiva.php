<?php
/**
 * public/asignacion_masiva.php
 * Versión 6.1 - CORRECCIÓN: Validación y guardado de equipos
 * PROTECCIÓN: Detección automática de delimitadores, limpieza de datos, validación robusta
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD RBAC
if ($_SESSION['rol'] === 'Auditor') {
    header('Location: dashboard.php'); exit;
}

// 2. CARGAR CATÁLOGOS
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
$step = 1; 
$preview_data = [];
$csv_errors = false;
$delimitador_usado = ",";

/**
 * Detecta automáticamente el delimitador del CSV (coma, punto y coma, tabulador)
 */
function detectarDelimitador($archivo) {
    $handle = fopen($archivo, 'r');
    $primera_linea = fgets($handle);
    fclose($handle);
    
    $delimitadores = [',', ';', "\t", '|'];
    $max_count = 0;
    $delimitador_detectado = ',';
    
    foreach ($delimitadores as $delim) {
        $count = substr_count($primera_linea, $delim);
        if ($count > $max_count) {
            $max_count = $count;
            $delimitador_detectado = $delim;
        }
    }
    
    return $delimitador_detectado;
}

/**
 * Limpia y normaliza una fila del CSV
 */
function limpiarFila($data) {
    // Eliminar columnas vacías al final
    while (count($data) > 0 && trim(end($data)) === '') {
        array_pop($data);
    }
    
    // Limpiar cada celda: trim, eliminar BOM, saltos de línea internos
    return array_map(function($celda) {
        $celda = trim($celda);
        $celda = str_replace(["\r", "\n", "\r\n"], ' ', $celda); // Quitar saltos internos
        $celda = preg_replace('/\s+/', ' ', $celda); // Múltiples espacios -> uno solo
        $celda = preg_replace('/^\xEF\xBB\xBF/', '', $celda); // Eliminar BOM UTF-8
        return $celda;
    }, $data);
}

/**
 * Verifica si una fila es un encabezado
 */
function esEncabezado($fila) {
    if (empty($fila) || count($fila) < 1) return false;
    
    $primera_celda = strtolower(trim($fila[0]));
    $palabras_clave = ['placa', 'placa_ur', 'serial', 'hostname', 'equipo'];
    
    foreach ($palabras_clave as $palabra) {
        if (stripos($primera_celda, $palabra) !== false) {
            return true;
        }
    }
    
    return false;
}

// --- LÓGICA PHP ---

// FASE 1: PROCESAMIENTO DEL CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $ext = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'csv') {
            $msg = "<div class='alert error'>❌ Formato incorrecto. Solo .CSV</div>";
        } else {
            try {
                // Detectar delimitador automáticamente
                $delimitador_usado = detectarDelimitador($_FILES['csv_file']['tmp_name']);
                
                $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
                $row_count = 0;
                $placas_vistas = [];
                $primera_fila_procesada = false;
                
                while (($data = fgetcsv($handle, 10000, $delimitador_usado)) !== FALSE) {
                    $row_count++;
                    
                    // Limpiar la fila
                    $data = limpiarFila($data);
                    
                    // Saltar filas completamente vacías
                    if (count(array_filter($data)) == 0) continue;
                    
                    // Detectar y saltar encabezado solo en la primera fila con datos
                    if (!$primera_fila_procesada && esEncabezado($data)) {
                        $primera_fila_procesada = true;
                        continue;
                    }
                    
                    $primera_fila_procesada = true;
                    
                    // Límite de 100 equipos
                    if (count($preview_data) >= 100) {
                        $msg = "<div class='alert warning'>⚠️ Se alcanzó el límite de 100 equipos. El resto del archivo fue ignorado.</div>";
                        break;
                    }

                    // Extraer y limpiar datos
                    $placa = strtoupper(trim($data[0] ?? ''));
                    $hostname = strtoupper(trim($data[1] ?? ''));
                    $adic1 = trim($data[2] ?? '');
                    $adic2 = trim($data[3] ?? '');

                    // Saltar si la placa está vacía
                    if (empty($placa)) continue;

                    // Buscar equipo en la base de datos
                    $stmt = $pdo->prepare("SELECT serial, estado_maestro FROM equipos WHERE placa_ur = ? LIMIT 1");
                    $stmt->execute([$placa]);
                    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

                    $status = 'valid'; 
                    $note = 'OK'; 
                    $serial = '';

                    if (!$equipo) { 
                        $status = 'invalid'; 
                        $note = 'No existe en Inventario'; 
                        $csv_errors = true; 
                    } 
                    elseif ($equipo['estado_maestro'] === 'Baja') { 
                        $status = 'invalid'; 
                        $note = 'Equipo en BAJA'; 
                        $csv_errors = true; 
                    } 
                    elseif (in_array($placa, $placas_vistas)) { 
                        $status = 'duplicated'; 
                        $note = 'Placa repetida en CSV'; 
                        $csv_errors = true; 
                    } 
                    else { 
                        $serial = $equipo['serial']; 
                        $placas_vistas[] = $placa; 
                    }

                    $preview_data[] = [
                        'placa' => $placa, 
                        'hostname' => $hostname, 
                        'serial' => $serial, 
                        'adic1' => $adic1, 
                        'adic2' => $adic2, 
                        'status' => $status, 
                        'note' => $note
                    ];
                }
                
                fclose($handle);
                
                if (count($preview_data) > 0) {
                    $step = 2;
                    $delimitador_nombre = ($delimitador_usado == ',') ? 'coma (,)' : 
                                          (($delimitador_usado == ';') ? 'punto y coma (;)' : 
                                          (($delimitador_usado == "\t") ? 'tabulador' : 'otro'));
                    
                    if (!$msg) {
                        $msg = "<div class='alert success'>✅ Archivo procesado correctamente. Delimitador detectado: <strong>$delimitador_nombre</strong></div>";
                    }
                } else {
                    $msg = "<div class='alert error'>⚠️ Archivo vacío o sin datos válidos después de limpiar filas vacías y encabezados.</div>";
                }
                
            } catch (Exception $e) {
                $msg = "<div class='alert error'>❌ Error al procesar archivo: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        $msg = "<div class='alert error'>❌ Error al subir el archivo. Por favor, inténtalo de nuevo.</div>";
    }
}

// FASE 2: GUARDADO EN BASE DE DATOS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_save'])) {
    try {
        // Decodificar JSON
        $items = json_decode($_POST['items_json'], true);
        
        // VALIDACIÓN: Verificar que el JSON se decodificó correctamente
        if (!is_array($items) || count($items) == 0) {
            throw new Exception("Error al decodificar los datos del formulario. Por favor, intenta nuevamente.");
        }
        
        // Obtener información del lugar
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();
        
        if (!$l) {
            throw new Exception("Lugar seleccionado no válido.");
        }
        
        $pdo->beginTransaction();
        $serials_procesados = [];
        $equipos_procesados = 0;
        $equipos_saltados = 0;
        
        // Preparar consulta SQL
        $sql = "INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, campo_adic1, campo_adic2, tipo_evento, correo_responsable, responsable_secundario, tecnico_responsable, hostname, fecha_evento, check_dlo, check_antivirus) VALUES (?, ?, ?, ?, ?, ?, 'Asignacion_Masiva', ?, ?, ?, ?, NOW(), ?, ?)";
        $stmt = $pdo->prepare($sql);

        // Captura de estados de los switches
        $dlo_status = isset($_POST['check_dlo']) ? 1 : 0;
        $av_status  = isset($_POST['check_antivirus']) ? 1 : 0;

        // Procesar cada equipo
        foreach ($items as $key => $item) {
            // VALIDACIÓN MEJORADA: Solo procesar equipos válidos con serial
            if (!isset($item['serial']) || empty($item['serial'])) {
                $equipos_saltados++;
                continue;
            }
            
            if (isset($item['status']) && $item['status'] !== 'valid') {
                $equipos_saltados++;
                continue;
            }
            
            // Ejecutar inserción
            $stmt->execute([
                $item['serial'], 
                $_POST['id_lugar'], 
                $l['sede'], 
                $l['nombre'], 
                $item['adic1'] ?? '', 
                $item['adic2'] ?? '',
                $_POST['correo_resp_real'], 
                $_POST['correo_sec_real'] ?: null, 
                $_SESSION['nombre'],
                strtoupper($item['hostname'] ?? ''), 
                $dlo_status, 
                $av_status
            ]);
            
            $serials_procesados[] = $item['serial'];
            $equipos_procesados++;
        }
        
        $pdo->commit();

        // Verificar resultados
        if (count($serials_procesados) > 0) {
            $serials_str = implode(',', $serials_procesados);
            header("Location: generar_acta_masiva.php?serials=" . urlencode($serials_str));
            exit;
        } else {
            // Si llegamos aquí, algo salió mal
            $msg = "<div class='alert error'>⚠️ No se procesó ningún equipo válido. Equipos procesados: $equipos_procesados, Equipos saltados: $equipos_saltados</div>"; 
            $step = 1;
            
            // LOG DE DEPURACIÓN
            error_log("=== DEBUG ASIGNACIÓN MASIVA ===");
            error_log("Total items recibidos: " . count($items));
            error_log("Equipos procesados: $equipos_procesados");
            error_log("Equipos saltados: $equipos_saltados");
            error_log("Items completos: " . print_r($items, true));
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>"; 
        $step = 2;
        
        // LOG DE ERROR
        error_log("Error en asignación masiva: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación Masiva | URTRACK</title>
    <style>
        :root { 
            --primary: #4f46e5; 
            --primary-hover: #4338ca; 
            --bg: #f8fafc; 
            --text: #334155; 
            --border: #cbd5e1; 
            --success: #22c55e; 
            --warning: #fbbf24;
        }
        
        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: var(--bg); 
            padding: 20px; 
            margin: 0; 
            color: var(--text); 
        }
        
        .card { 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
            max-width: 900px; 
            margin: auto; 
        }
        
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h2 { 
            margin: 0; 
            color: var(--primary); 
            font-size: 1.8rem; 
        }
        
        .btn-back { 
            color: #64748b; 
            text-decoration: none; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            transition: color 0.2s; 
        }
        
        .btn-back:hover { color: var(--primary); }

        .dropzone-container {
            border: 3px dashed var(--border);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #fdfdfd;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        
        .dropzone-container:hover, .dropzone-container.dragover {
            border-color: var(--primary);
            background: #eef2ff;
        }
        
        .dropzone-icon { 
            font-size: 4rem; 
            color: #94a3b8; 
            margin-bottom: 15px; 
            display: block; 
        }
        
        .dropzone-title { 
            font-size: 1.2rem; 
            font-weight: bold; 
            color: var(--text); 
            margin-bottom: 5px; 
        }
        
        .dropzone-desc { 
            color: #64748b; 
            font-size: 0.9rem; 
            margin-bottom: 20px; 
        }
        
        .file-input { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            opacity: 0; 
            cursor: pointer; 
        }
        
        .file-info {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background: #dcfce7;
            border: 1px solid #86efac;
            border-radius: 8px;
            color: #166534;
            font-weight: bold;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        .instructions { 
            margin-top: 30px; 
            display: flex; 
            gap: 20px; 
            flex-wrap: wrap; 
        }
        
        .instruction-box { 
            flex: 1; 
            background: #f1f5f9; 
            padding: 20px; 
            border-radius: 8px; 
            min-width: 250px; 
        }
        
        .instruction-box h4 { 
            margin-top: 0; 
            color: var(--primary); 
        }
        
        .code-pill { 
            background: #e2e8f0; 
            padding: 3px 8px; 
            border-radius: 4px; 
            font-family: monospace; 
            font-weight: bold; 
            font-size: 0.9em; 
        }
        
        .tips-box {
            background: #e7f3ff;
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin-top: 20px;
            border-radius: 6px;
        }
        
        .tips-box h4 {
            margin: 0 0 10px 0;
            color: var(--primary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tips-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.85rem;
            color: #333;
        }
        
        .tips-box li {
            margin-bottom: 5px;
        }

        .btn-primary { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 15px 30px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 1rem; 
            width: 100%; 
            margin-top: 20px; 
            transition: background 0.2s; 
            display: none;
        }
        
        .btn-primary:hover { background: var(--primary-hover); }

        .alert { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: bold;
            animation: slideIn 0.4s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .error { 
            background: #fee2e2; 
            color: #991b1b; 
            border-left: 6px solid #dc2626;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 6px solid var(--success);
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            border-left: 6px solid var(--warning);
        }

        .table-container { 
            overflow-x: auto; 
            margin-top: 20px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
        }
        
        .preview-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 600px; 
        }
        
        .preview-table th { 
            background: #f1f5f9; 
            padding: 12px; 
            text-align: left; 
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
        }
        
        .preview-table td { 
            padding: 10px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 0.9rem;
        }
        
        .row-valid { 
            border-left: 4px solid #22c55e; 
            background: #f0fdf4; 
        }
        
        .row-invalid { 
            border-left: 4px solid #ef4444; 
            background: #fef2f2; 
        }
        
        .row-duplicated {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            background: #f8fafc; 
            padding: 25px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
            margin-top: 20px; 
        }
        
        input[type="text"], select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 0.95rem;
        }

        /* ESTILOS PARA LOS TOGGLES DE COMPLIANCE */
        .compliance-section { 
            grid-column: span 2; 
            display: flex; 
            gap: 20px; 
            background: #f0fdf4; 
            border: 1px solid #bbf7d0; 
            padding: 15px; 
            border-radius: 8px; 
            align-items: center; 
            justify-content: space-around; 
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .switch-container { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        .switch { 
            position: relative; 
            display: inline-block; 
            width: 50px; 
            height: 26px; 
        }
        
        .switch input { 
            opacity: 0; 
            width: 0; 
            height: 0; 
        }
        
        .slider { 
            position: absolute; 
            cursor: pointer; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background-color: #dc3545;
            transition: .4s; 
            border-radius: 34px; 
        }
        
        .slider:before { 
            position: absolute; 
            content: ""; 
            height: 20px; 
            width: 20px; 
            left: 3px; 
            bottom: 3px; 
            background-color: white; 
            transition: .4s; 
            border-radius: 50%; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.2); 
        }
        
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(24px); }
        
        .switch-label { 
            font-weight: bold; 
            color: #166534; 
            font-size: 0.9rem; 
        }
        
        @media (max-width: 768px) {
            .card { padding: 20px; }
            .header h2 { font-size: 1.4rem; }
            .form-grid { grid-template-columns: 1fr; }
            .instructions { flex-direction: column; }
            .instruction-box { min-width: 100%; }
            .compliance-section { 
                flex-direction: column; 
                gap: 15px;
                align-items: flex-start;
            }
            .preview-table th,
            .preview-table td {
                padding: 8px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .header h2 { font-size: 1.2rem; }
            .dropzone-icon { font-size: 3rem; }
            .dropzone-title { font-size: 1rem; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2>🚀 Asignación Masiva</h2>
        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <?= $msg ?>

    <?php if ($step === 1): ?>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="dropzone-container" id="dropzone">
                <input type="file" name="csv_file" id="csv_file" class="file-input" accept=".csv" required>
                <div class="dropzone-content">
                    <span class="dropzone-icon">☁️</span>
                    <div class="dropzone-title">Arrastra tu archivo CSV aquí</div>
                    <div class="dropzone-desc">o haz clic para explorar en tu equipo</div>
                </div>
            </div>

            <div id="fileInfo" class="file-info">
                📄 Archivo seleccionado: <span id="fileName"></span>
            </div>

            <div class="instructions">
                <div class="instruction-box">
                    <h4>📋 Formato Requerido</h4>
                    <p style="font-size:0.9rem; color:#555;">El archivo CSV debe tener las siguientes columnas (sin tildes):</p>
                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                        <span class="code-pill">PLACA_UR</span>
                        <span class="code-pill">HOSTNAME</span>
                        <span class="code-pill">INFO_ADIC1</span>
                        <span class="code-pill">INFO_ADIC2</span>
                    </div>
                </div>
                <div class="instruction-box">
                    <h4>💡 Tips "Anti-Error"</h4>
                    <ul style="padding-left:20px; font-size:0.9rem; color:#555; margin-bottom:0;">
                        <li>Máximo 100 filas por carga.</li>
                        <li>Guardar como <b>CSV (delimitado por comas)</b>.</li>
                        <li>No dejar filas vacías intermedias.</li>
                    </ul>
                </div>
            </div>
            
            <div class="tips-box">
                <h4>🛡️ Protecciones Automáticas</h4>
                <ul>
                    <li>✅ <strong>Detección automática de delimitador</strong>: Soporta coma (,), punto y coma (;), tabulador</li>
                    <li>✅ <strong>Limpieza de espacios</strong>: Elimina espacios adicionales y saltos de línea internos</li>
                    <li>✅ <strong>Columnas vacías</strong>: Ignora columnas extras vacías al final</li>
                    <li>✅ <strong>Detección de encabezados</strong>: Salta automáticamente la primera fila si contiene títulos</li>
                    <li>✅ <strong>Validación de duplicados</strong>: Previene cargas repetidas en el mismo archivo</li>
                    <li>✅ <strong>Normalización automática</strong>: Convierte placas y hostnames a mayúsculas</li>
                </ul>
            </div>

            <button type="submit" name="upload_csv" id="btnUpload" class="btn-primary">
                📂 Analizar Archivo
            </button>
        </form>

        <script>
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('csv_file');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const btnUpload = document.getElementById('btnUpload');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
            });

            dropzone.addEventListener('drop', handleDrop, false);
            fileInput.addEventListener('change', handleFiles, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                handleFiles();
            }

            function handleFiles() {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.name.toLowerCase().endsWith('.csv')) {
                        fileName.textContent = file.name;
                        fileInfo.style.display = 'block';
                        fileInfo.style.background = '#dcfce7';
                        fileInfo.style.color = '#166534';
                        btnUpload.style.display = 'block';
                    } else {
                        fileName.textContent = "❌ Error: El archivo no es un CSV.";
                        fileInfo.style.display = 'block';
                        fileInfo.style.background = '#fee2e2';
                        fileInfo.style.color = '#991b1b';
                        btnUpload.style.display = 'none';
                        fileInput.value = '';
                    }
                }
            }
        </script>
    <?php endif; ?>

    <?php if ($step === 2): ?>
        <form method="POST">
            <h3>1. Validación de Datos (<?= count($preview_data) ?> registros)</h3>
            <div class="table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Placa</th>
                            <th>Serial</th>
                            <th>Hostname</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $validos = 0; 
                        foreach($preview_data as $row): 
                            if($row['status'] == 'valid') $validos++;
                            $row_class = 'row-' . $row['status'];
                            $icon = $row['status'] == 'valid' ? '✅' : ($row['status'] == 'duplicated' ? '⚠️' : '❌');
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $icon ?></td>
                                <td><?= htmlspecialchars($row['placa']) ?></td>
                                <td><?= htmlspecialchars($row['serial']) ?></td>
                                <td><?= htmlspecialchars($row['hostname']) ?></td>
                                <td><?= htmlspecialchars($row['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top: 30px;">2. Configuración Global</h3>
            <div class="form-grid">
                <div>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Sede Destino</label>
                    <select id="selectSede" required>
                        <option value="">-- Seleccionar --</option>
                        <?php 
                        $sedes = array_unique(array_column($lugares, 'sede')); 
                        foreach($sedes as $s) echo "<option value='" . htmlspecialchars($s) . "'>" . htmlspecialchars($s) . "</option>"; 
                        ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Ubicación Específica</label>
                    <select id="selectLugar" name="id_lugar" required disabled>
                        <option value="">-- Elija Sede --</option>
                    </select>
                </div>
                <div style="grid-column: span 2;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">👤 Responsable Principal (LDAP)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuario()" style="background:var(--primary); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">🔍</button>
                    </div>
                    <div id="userCard" style="margin-top:10px; padding:10px; background:#f1f5f9; border-radius:6px; display:none;">
                        <h4 id="ldap_nombre" style="margin:0; color:var(--primary);"></h4>
                        <div id="ldap_info" style="font-size:0.85rem; color:#666;"></div>
                    </div>
                    <input type="hidden" name="correo_resp_real" id="correo_resp_real" required>
                </div>
                <div style="grid-column: span 2;">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">👥 Responsable Secundario (Opcional)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id_sec" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuarioOpcional()" style="background:#64748b; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">🔍</button>
                    </div>
                    <div id="userCard_sec" style="margin-top:10px; padding:10px; background:#f1f5f9; border-radius:6px; display:none;">
                        <h4 id="ldap_nombre_sec" style="margin:0; color:#444;"></h4>
                        <div id="ldap_info_sec" style="font-size:0.85rem; color:#666;"></div>
                    </div>
                    <input type="hidden" name="correo_sec_real" id="correo_sec_real">
                </div>
                
                <div class="compliance-section">
                    <span style="font-size:0.8rem; text-transform:uppercase; color:#166534; font-weight:bold;">🛡️ Verificación de Seguridad Masiva</span>
                    
                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="check_dlo" value="1">
                            <span class="slider"></span>
                        </label>
                        <span class="switch-label">Agente DLO/Backup</span>
                    </div>

                    <div class="switch-container">
                        <label class="switch">
                            <input type="checkbox" name="check_antivirus" value="1">
                            <span class="slider"></span>
                        </label>
                        <span class="switch-label">Antivirus Corp.</span>
                    </div>
                </div>
            </div>

            <!-- JSON CON HTMLSPECIALCHARS PARA EVITAR PROBLEMAS -->
            <input type="hidden" name="items_json" value="<?php echo htmlspecialchars(json_encode($preview_data), ENT_QUOTES, 'UTF-8'); ?>">
            
            <div style="display:flex; gap:20px; margin-top:30px; flex-wrap: wrap;">
                <a href="asignacion_masiva.php" style="flex:1; min-width: 200px; text-align:center; padding:15px; background:#64748b; color:white; text-decoration:none; border-radius:8px; font-weight:bold;">CANCELAR</a>
                <?php if ($validos > 0): ?>
                    <button type="submit" name="confirm_save" id="btnSubmit" style="flex:2; min-width: 250px; background:var(--primary); color:white; border:none; padding:15px; border-radius:8px; font-weight:bold; cursor:pointer;" disabled>CONFIRMAR (<?= $validos ?> Equipos)</button>
                <?php else: ?>
                    <div class="alert error" style="flex:2; min-width: 250px; margin:0;">No hay equipos válidos</div>
                <?php endif; ?>
            </div>
        </form>
        <script>const URTRACK_LUGARES = <?= json_encode($lugares) ?>;</script>
        <script src="js/verificar_ldap.js"></script>
        <script src="js/verificar_ldap_opcional.js"></script>
        <script src="js/asignacion_masiva.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(document.getElementById('ldap_nombre').innerText !== '') document.getElementById('userCard').style.display='block';
            });
        </script>
    <?php endif; ?>
</div>

</body>
</html>