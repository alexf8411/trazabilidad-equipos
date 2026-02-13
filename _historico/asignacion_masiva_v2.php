<?php
/**
 * public/asignacion_masiva.php
 * Versión 6.0 - BLINDADO CONTRA ERRORES CSV
 * 
 * Mejoras implementadas:
 * - Detección automática de delimitador (,;|\t)
 * - Manejo de múltiples encodings (UTF-8, Latin1, Windows-1252, BOM)
 * - Validación robusta de estructura
 * - Limpieza profunda de datos
 * - Protección contra archivos corruptos
 * - Límites de memoria y timeout
 */

require_once '../core/db.php';
require_once '../core/session.php';

// ==========================================
// CONFIGURACIÓN DE SEGURIDAD
// ==========================================
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 300); // 5 minutos
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_CSV_ROWS', 100);

// ==========================================
// 1. SEGURIDAD RBAC
// ==========================================
if ($_SESSION['rol'] === 'Auditor') {
    header('Location: dashboard.php'); 
    exit;
}

// ==========================================
// 2. CARGAR CATÁLOGOS
// ==========================================
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
$step = 1; 
$preview_data = [];
$csv_errors = false;
$csv_warnings = [];

// ==========================================
// FUNCIONES DE VALIDACIÓN Y PROCESAMIENTO CSV
// ==========================================

/**
 * Detecta el delimitador del archivo CSV
 * Soporta: coma (,), punto y coma (;), tabulador (\t), pipe (|)
 */
function detectar_delimitador($filepath, $check_lines = 5) {
    $delimitadores = [',', ';', "\t", '|'];
    $handle = fopen($filepath, 'r');
    
    if (!$handle) {
        return ','; // Fallback
    }
    
    $lineas = [];
    for ($i = 0; $i < $check_lines && !feof($handle); $i++) {
        $lineas[] = fgets($handle);
    }
    fclose($handle);
    
    $mejor_delimitador = ',';
    $max_consistencia = 0;
    
    foreach ($delimitadores as $delim) {
        $conteos = [];
        foreach ($lineas as $linea) {
            if (empty(trim($linea))) continue;
            $conteos[] = substr_count($linea, $delim);
        }
        
        if (empty($conteos)) continue;
        
        // Calcular consistencia (todas las líneas tienen el mismo número de delimitadores)
        $min = min($conteos);
        $max = max($conteos);
        
        if ($max > 0 && $min == $max && $max > $max_consistencia) {
            $max_consistencia = $max;
            $mejor_delimitador = $delim;
        }
    }
    
    return $mejor_delimitador;
}

/**
 * Detecta y convierte el encoding del archivo a UTF-8
 */
function normalizar_encoding($filepath) {
    $contenido = file_get_contents($filepath);
    
    if ($contenido === false) {
        throw new Exception("No se pudo leer el archivo");
    }
    
    // Detectar y remover BOM UTF-8
    $bom_utf8 = pack('H*', 'EFBBBF');
    $bom_utf16_be = pack('H*', 'FEFF');
    $bom_utf16_le = pack('H*', 'FFFE');
    
    $contenido = preg_replace("/^($bom_utf8|$bom_utf16_be|$bom_utf16_le)/", '', $contenido);
    
    // Detectar encoding
    $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];
    $encoding_actual = mb_detect_encoding($contenido, $encodings, true);
    
    if ($encoding_actual && $encoding_actual !== 'UTF-8') {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', $encoding_actual);
    }
    
    // Crear archivo temporal con UTF-8
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_utf8_');
    file_put_contents($temp_file, $contenido);
    
    return $temp_file;
}

/**
 * Limpia y sanitiza un valor de celda CSV
 */
function limpiar_celda($valor) {
    if ($valor === null || $valor === false) {
        return '';
    }
    
    // Convertir a string
    $valor = (string)$valor;
    
    // Remover espacios en blanco invisibles (nbsp, zero-width, etc)
    $valor = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $valor);
    
    // Trim normal
    $valor = trim($valor);
    
    // Remover comillas que Excel a veces agrega
    $valor = trim($valor, '"\'');
    
    // Normalizar espacios múltiples
    $valor = preg_replace('/\s+/', ' ', $valor);
    
    return $valor;
}

/**
 * Valida la estructura del CSV (columnas esperadas)
 */
function validar_estructura_csv($primera_fila) {
    $columnas_esperadas = ['PLACA_UR', 'PLACA', 'HOSTNAME', 'INFO_ADIC1', 'INFO_ADIC2', 'ADIC1', 'ADIC2'];
    
    // Limpiar headers
    $headers = array_map('limpiar_celda', $primera_fila);
    $headers = array_map('strtoupper', $headers);
    
    // Verificar que tenga al menos 2 columnas
    if (count($headers) < 2) {
        return [
            'valido' => false,
            'error' => 'El CSV debe tener al menos 2 columnas (PLACA_UR y HOSTNAME)'
        ];
    }
    
    // Verificar primera columna (PLACA)
    $primera_col_valida = false;
    foreach ($columnas_esperadas as $nombre) {
        if (stripos($headers[0], $nombre) !== false || 
            stripos($headers[0], 'PLACA') !== false) {
            $primera_col_valida = true;
            break;
        }
    }
    
    if (!$primera_col_valida) {
        return [
            'valido' => false,
            'error' => 'La primera columna debe ser PLACA_UR o similar'
        ];
    }
    
    return ['valido' => true, 'headers' => $headers];
}

/**
 * Procesa el archivo CSV de forma robusta
 */
function procesar_csv_robusto($filepath) {
    global $csv_warnings;
    
    // 1. Normalizar encoding
    try {
        $archivo_utf8 = normalizar_encoding($filepath);
    } catch (Exception $e) {
        return [
            'exito' => false,
            'error' => 'Error al procesar encoding: ' . $e->getMessage()
        ];
    }
    
    // 2. Detectar delimitador
    $delimitador = detectar_delimitador($archivo_utf8);
    $csv_warnings[] = "Delimitador detectado: " . ($delimitador === "\t" ? 'TAB' : 
                      ($delimitador === ',' ? 'COMA' : 
                      ($delimitador === ';' ? 'PUNTO Y COMA' : 'OTRO')));
    
    // 3. Abrir archivo
    $handle = fopen($archivo_utf8, 'r');
    if (!$handle) {
        if (file_exists($archivo_utf8)) unlink($archivo_utf8);
        return ['exito' => false, 'error' => 'No se pudo abrir el archivo'];
    }
    
    $datos = [];
    $row_num = 0;
    $errores_fila = [];
    $es_header = true;
    $num_columnas_esperadas = 0;
    
    while (($fila = fgetcsv($handle, 10000, $delimitador)) !== false) {
        $row_num++;
        
        // Ignorar filas completamente vacías
        if (empty(array_filter($fila, 'strlen'))) {
            continue;
        }
        
        // Limpiar cada celda
        $fila = array_map('limpiar_celda', $fila);
        
        // Primera fila = headers
        if ($es_header) {
            $validacion = validar_estructura_csv($fila);
            if (!$validacion['valido']) {
                fclose($handle);
                if (file_exists($archivo_utf8)) unlink($archivo_utf8);
                return ['exito' => false, 'error' => $validacion['error']];
            }
            $num_columnas_esperadas = count($fila);
            $es_header = false;
            continue;
        }
        
        // Límite de filas
        if (count($datos) >= MAX_CSV_ROWS) {
            $csv_warnings[] = "Se alcanzó el límite de " . MAX_CSV_ROWS . " filas. Filas adicionales ignoradas.";
            break;
        }
        
        // Validar número de columnas
        if (count($fila) < 2) {
            $errores_fila[] = "Fila $row_num: menos de 2 columnas";
            continue;
        }
        
        // Rellenar columnas faltantes con vacíos
        while (count($fila) < 4) {
            $fila[] = '';
        }
        
        // Extraer datos (asegurar índices)
        $placa = isset($fila[0]) ? $fila[0] : '';
        $hostname = isset($fila[1]) ? $fila[1] : '';
        $adic1 = isset($fila[2]) ? $fila[2] : '';
        $adic2 = isset($fila[3]) ? $fila[3] : '';
        
        $datos[] = [
            'placa' => $placa,
            'hostname' => $hostname,
            'adic1' => $adic1,
            'adic2' => $adic2,
            'fila_original' => $row_num
        ];
    }
    
    fclose($handle);
    
    // Limpiar archivo temporal
    if (file_exists($archivo_utf8)) {
        unlink($archivo_utf8);
    }
    
    // Reportar errores de filas si los hay
    if (!empty($errores_fila)) {
        $csv_warnings[] = implode(', ', $errores_fila);
    }
    
    if (empty($datos)) {
        return [
            'exito' => false,
            'error' => 'No se encontraron datos válidos en el archivo'
        ];
    }
    
    return [
        'exito' => true,
        'datos' => $datos,
        'total_filas' => count($datos)
    ];
}

/**
 * Valida el formato de una placa UR
 */
function validar_placa($placa) {
    $placa = limpiar_celda($placa);
    
    if (empty($placa)) {
        return ['valido' => false, 'error' => 'Placa vacía'];
    }
    
    // Validar longitud razonable (entre 3 y 50 caracteres)
    if (strlen($placa) < 3 || strlen($placa) > 50) {
        return ['valido' => false, 'error' => 'Longitud inválida'];
    }
    
    // Remover caracteres peligrosos
    if (preg_match('/[<>"\']/', $placa)) {
        return ['valido' => false, 'error' => 'Caracteres no permitidos'];
    }
    
    return ['valido' => true, 'valor' => strtoupper($placa)];
}

/**
 * Valida el formato de un hostname
 */
function validar_hostname($hostname) {
    $hostname = limpiar_celda($hostname);
    
    if (empty($hostname)) {
        return ['valido' => true, 'valor' => '']; // Hostname es opcional
    }
    
    // Validar longitud
    if (strlen($hostname) > 63) {
        return ['valido' => false, 'error' => 'Hostname muy largo (máx 63 caracteres)'];
    }
    
    // Validar formato básico (letras, números, guiones)
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $hostname)) {
        return ['valido' => false, 'error' => 'Hostname con caracteres inválidos'];
    }
    
    return ['valido' => true, 'valor' => strtoupper($hostname)];
}

/**
 * Validar archivo antes de procesar
 */
function validar_archivo_subido($file) {
    $errores = [];
    
    // 1. Verificar errores de upload
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "El archivo excede el tamaño máximo permitido (" . (MAX_FILE_SIZE/1024/1024) . "MB)";
            case UPLOAD_ERR_PARTIAL:
                return "El archivo se subió parcialmente. Intente nuevamente.";
            case UPLOAD_ERR_NO_FILE:
                return "No se seleccionó ningún archivo";
            default:
                return "Error en la carga del archivo (código: {$file['error']})";
        }
    }
    
    // 2. Verificar que existe
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return "El archivo no se cargó correctamente";
    }
    
    // 3. Verificar tamaño
    if ($file['size'] > MAX_FILE_SIZE) {
        $size_mb = round(MAX_FILE_SIZE / 1024 / 1024, 1);
        return "Archivo muy grande. Máximo permitido: {$size_mb}MB";
    }
    
    if ($file['size'] == 0) {
        return "El archivo está vacío";
    }
    
    // 4. Verificar extensión
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        return "Solo se permiten archivos .CSV o .TXT";
    }
    
    // 5. Verificar MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = ['text/csv', 'text/plain', 'application/csv', 
                         'application/vnd.ms-excel', 'text/comma-separated-values'];
    
    if (!in_array($mime, $mimes_permitidos)) {
        return "Tipo de archivo no permitido. Solo CSV.";
    }
    
    return true; // Todo OK
}

// ==========================================
// FASE 1: PROCESAMIENTO DEL ARCHIVO
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    
    if (!isset($_FILES['csv_file'])) {
        $msg = "<div class='alert error'>❌ No se recibió ningún archivo</div>";
    } else {
        // Validar archivo
        $validacion_archivo = validar_archivo_subido($_FILES['csv_file']);
        
        if ($validacion_archivo !== true) {
            $msg = "<div class='alert error'>❌ " . $validacion_archivo . "</div>";
        } else {
            // Procesar CSV de forma robusta
            $resultado = procesar_csv_robusto($_FILES['csv_file']['tmp_name']);
            
            if (!$resultado['exito']) {
                $msg = "<div class='alert error'>❌ " . $resultado['error'] . "</div>";
            } else {
                $datos_csv = $resultado['datos'];
                $placas_vistas = [];
                
                // Validar cada fila
                foreach ($datos_csv as $item) {
                    $placa = $item['placa'];
                    $hostname = $item['hostname'];
                    $adic1 = $item['adic1'];
                    $adic2 = $item['adic2'];
                    
                    $status = 'valid';
                    $note = 'OK';
                    $serial = '';
                    
                    // Validar placa
                    $validacion_placa = validar_placa($placa);
                    if (!$validacion_placa['valido']) {
                        $status = 'invalid';
                        $note = 'Placa inválida: ' . $validacion_placa['error'];
                        $csv_errors = true;
                    } else {
                        $placa = $validacion_placa['valor'];
                        
                        // Verificar duplicados
                        if (in_array($placa, $placas_vistas)) {
                            $status = 'duplicated';
                            $note = 'Placa repetida en el archivo';
                            $csv_errors = true;
                        } else {
                            // Buscar en BD
                            try {
                                $stmt = $pdo->prepare("SELECT serial, estado_maestro FROM equipos WHERE placa_ur = ? LIMIT 1");
                                $stmt->execute([$placa]);
                                $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$equipo) {
                                    $status = 'invalid';
                                    $note = 'No existe en Inventario';
                                    $csv_errors = true;
                                } elseif ($equipo['estado_maestro'] === 'Baja') {
                                    $status = 'invalid';
                                    $note = 'Equipo en BAJA';
                                    $csv_errors = true;
                                } else {
                                    $serial = $equipo['serial'];
                                    $placas_vistas[] = $placa;
                                }
                            } catch (PDOException $e) {
                                $status = 'invalid';
                                $note = 'Error de consulta';
                                $csv_errors = true;
                            }
                        }
                    }
                    
                    // Validar hostname si no está vacío
                    if ($status === 'valid' && !empty($hostname)) {
                        $validacion_hostname = validar_hostname($hostname);
                        if (!$validacion_hostname['valido']) {
                            $status = 'invalid';
                            $note = 'Hostname inválido: ' . $validacion_hostname['error'];
                            $csv_errors = true;
                        } else {
                            $hostname = $validacion_hostname['valor'];
                        }
                    }
                    
                    $preview_data[] = [
                        'placa' => $placa,
                        'hostname' => $hostname,
                        'serial' => $serial,
                        'adic1' => substr($adic1, 0, 255), // Limitar longitud
                        'adic2' => substr($adic2, 0, 255),
                        'status' => $status,
                        'note' => $note
                    ];
                }
                
                if (count($preview_data) > 0) {
                    $step = 2;
                } else {
                    $msg = "<div class='alert error'>⚠️ No se encontraron datos válidos en el archivo.</div>";
                }
            }
        }
    }
}

// ==========================================
// FASE 2: GUARDADO EN BASE DE DATOS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_save'])) {
    try {
        $items = json_decode($_POST['items_json'], true);
        
        // Validar JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Datos inválidos. Por favor recargue la página.");
        }
        
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();
        
        if (!$l) {
            throw new Exception("Ubicación no válida");
        }
        
        $pdo->beginTransaction();
        $serials_procesados = [];
        
        $sql = "INSERT INTO bitacora (
            serial_equipo, id_lugar, sede, ubicacion, 
            campo_adic1, campo_adic2, tipo_evento, 
            correo_responsable, responsable_secundario, 
            tecnico_responsable, hostname, fecha_evento, 
            check_dlo, check_antivirus
        ) VALUES (?, ?, ?, ?, ?, ?, 'Asignacion_Masiva', ?, ?, ?, ?, NOW(), ?, ?)";
        
        $stmt = $pdo->prepare($sql);

        // Captura de estados de los switches
        $dlo_status = isset($_POST['check_dlo']) ? 1 : 0;
        $av_status  = isset($_POST['check_antivirus']) ? 1 : 0;

        foreach ($items as $item) {
            if ($item['status'] !== 'valid') continue;
            
            $stmt->execute([
                $item['serial'], 
                $_POST['id_lugar'], 
                $l['sede'], 
                $l['nombre'], 
                $item['adic1'], 
                $item['adic2'],
                $_POST['correo_resp_real'], 
                $_POST['correo_sec_real'] ?: null, 
                $_SESSION['nombre'],
                strtoupper($item['hostname']), 
                $dlo_status, 
                $av_status
            ]);
            
            $serials_procesados[] = $item['serial'];
        }
        
        $pdo->commit();

        if (count($serials_procesados) > 0) {
            $serials_str = implode(',', $serials_procesados);
            header("Location: generar_acta_masiva.php?serials=" . urlencode($serials_str));
            exit;
        } else {
            $msg = "<div class='alert error'>⚠️ No se procesó ningún equipo válido.</div>"; 
            $step = 1;
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log del error (opcional)
        error_log("Error en asignación masiva: " . $e->getMessage());
        
        $msg = "<div class='alert error'>❌ Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>"; 
        $step = 2;
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
            --warning: #f59e0b;
        }
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
        .btn-back:hover { 
            color: var(--primary); 
        }

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
        .btn-primary:hover { 
            background: var(--primary-hover); 
        }

        .alert { 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align:center; 
            font-weight:bold; 
        }
        .error { 
            background: #fee2e2; 
            color: #991b1b; 
        }
        .warning { 
            background: #fef3c7; 
            color: #92400e; 
            font-size: 0.9rem; 
            text-align: left;
        }
        
        .warnings-list {
            margin-top: 10px;
            padding-left: 20px;
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
            font-size: 0.9rem;
        }
        .preview-table td { 
            padding: 10px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 0.85rem;
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
            border-left: 4px solid var(--warning); 
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
        input:checked + .slider { 
            background-color: var(--success); 
        }
        input:checked + .slider:before { 
            transform: translateX(24px); 
        }
        .switch-label { 
            font-weight: bold; 
            color: #166534; 
            font-size: 0.9rem; 
        }
        
        @media (max-width: 768px) {
            .card { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .instructions { flex-direction: column; }
            .compliance-section { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2>🚀 Asignación Masiva v6.0</h2>
        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <?= $msg ?>
    
    <?php if (!empty($csv_warnings)): ?>
        <div class="alert warning">
            ⚠️ <strong>Advertencias del procesamiento:</strong>
            <ul class="warnings-list">
                <?php foreach ($csv_warnings as $warning): ?>
                    <li><?= htmlspecialchars($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="dropzone-container" id="dropzone">
                <input type="file" name="csv_file" id="csv_file" class="file-input" accept=".csv,.txt" required>
                <div class="dropzone-content">
                    <span class="dropzone-icon">☁️</span>
                    <div class="dropzone-title">Arrastra tu archivo CSV aquí</div>
                    <div class="dropzone-desc">o haz clic para explorar en tu equipo</div>
                    <div style="margin-top: 10px; font-size: 0.8rem; color: #94a3b8;">
                        Soporta: CSV con comas (,) punto y coma (;) o tabuladores
                    </div>
                </div>
            </div>

            <div id="fileInfo" class="file-info">
                📄 Archivo seleccionado: <span id="fileName"></span>
            </div>

            <div class="instructions">
                <div class="instruction-box">
                    <h4>📋 Formato Requerido</h4>
                    <p style="font-size:0.9rem; color:#555;">El archivo CSV debe tener las siguientes columnas:</p>
                    <div style="display:flex; gap:5px; flex-wrap:wrap;">
                        <span class="code-pill">PLACA_UR</span>
                        <span class="code-pill">HOSTNAME</span>
                        <span class="code-pill">INFO_ADIC1</span>
                        <span class="code-pill">INFO_ADIC2</span>
                    </div>
                    <p style="font-size:0.85rem; color:#666; margin-top:10px;">
                        <strong>Compatible con Excel:</strong> Exporta como "CSV (delimitado por comas)" o "CSV (MS-DOS)"
                    </p>
                </div>
                <div class="instruction-box">
                    <h4>💡 Tips Anti-Error</h4>
                    <ul style="padding-left:20px; font-size:0.9rem; color:#555; margin-bottom:0;">
                        <li>Máximo 100 filas por carga</li>
                        <li>Se detecta automáticamente: <strong>,</strong> o <strong>;</strong> o <strong>TAB</strong></li>
                        <li>Encoding automático (UTF-8, Latin1, etc.)</li>
                        <li>Espacios vacíos se limpian automáticamente</li>
                        <li>Filas vacías se ignoran</li>
                    </ul>
                </div>
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
                    const ext = file.name.toLowerCase().split('.').pop();
                    
                    if (ext === 'csv' || ext === 'txt') {
                        fileName.textContent = file.name;
                        fileInfo.style.display = 'block';
                        fileInfo.style.background = '#dcfce7';
                        fileInfo.style.color = '#166534';
                        btnUpload.style.display = 'block';
                    } else {
                        fileName.textContent = "❌ Error: El archivo debe ser .CSV o .TXT";
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
            <h3>1. Validación de Datos (<?= count($preview_data) ?> registros procesados)</h3>
            
            <div class="table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Placa</th>
                            <th>Serial</th>
                            <th>Hostname</th>
                            <th>Adicional 1</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $validos = 0; 
                        foreach($preview_data as $row): 
                            if($row['status'] == 'valid') $validos++;
                        ?>
                            <tr class="row-<?= $row['status'] ?>">
                                <td>
                                    <?= $row['status'] == 'valid' ? '✅' : 
                                        ($row['status'] == 'duplicated' ? '⚠️' : '❌') ?>
                                </td>
                                <td><?= htmlspecialchars($row['placa']) ?></td>
                                <td><?= htmlspecialchars($row['serial']) ?></td>
                                <td><?= htmlspecialchars($row['hostname']) ?></td>
                                <td><?= htmlspecialchars(substr($row['adic1'], 0, 30)) ?><?= strlen($row['adic1']) > 30 ? '...' : '' ?></td>
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
                        foreach($sedes as $s) echo "<option value='$s'>$s</option>"; 
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

            <input type="hidden" name="items_json" value='<?= json_encode($preview_data) ?>'>
            
            <div style="display:flex; gap:20px; margin-top:30px;">
                <a href="asignacion_masiva.php" style="flex:1; text-align:center; padding:15px; background:#64748b; color:white; text-decoration:none; border-radius:8px; font-weight:bold;">CANCELAR</a>
                <?php if ($validos > 0): ?>
                    <button type="submit" name="confirm_save" id="btnSubmit" style="flex:2; background:var(--primary); color:white; border:none; padding:15px; border-radius:8px; font-weight:bold; cursor:pointer;" disabled>
                        CONFIRMAR (<?= $validos ?> Equipos)
                    </button>
                <?php else: ?>
                    <div class="alert error" style="flex:2; margin:0;">No hay equipos válidos para procesar</div>
                <?php endif; ?>
            </div>
        </form>
        
        <script>const URTRACK_LUGARES = <?= json_encode($lugares) ?>;</script>
        <script src="js/verificar_ldap.js"></script>
        <script src="js/verificar_ldap_opcional.js"></script>
        <script src="js/asignacion_masiva.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(document.getElementById('ldap_nombre').innerText !== '') {
                    document.getElementById('userCard').style.display='block';
                }
            });
        </script>
    <?php endif; ?>
</div>

</body>
</html>