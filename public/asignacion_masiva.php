<?php
/**
 * URTRACK - Asignación Masiva  
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ CSS centralizado en urtrack-styles.css
 * ✅ JavaScript en asignacion_masiva.js
 * ✅ CSV solo 3 columnas: PLACA_UR, HOSTNAME, COMENTARIOS
 * ✅ Campo "No. de Caso" obligatorio (global)
 * ✅ 3 switches: DLO, Antivirus, SCCM
 * ✅ desc_evento = "Caso: XXX"
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Configuración
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 300);

define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('MAX_CSV_ROWS', 100);

if ($_SESSION['rol'] === 'Auditor') {
    header('Location: dashboard.php');
    exit;
}

$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
$step = 1;
$preview_data = [];
$csv_errors = false;
$csv_warnings = [];

// Funciones auxiliares (incluir todas las del documento original)
function detectar_delimitador($filepath, $check_lines = 5) {
    $delimitadores = [',', ';', "\t", '|'];
    $handle = fopen($filepath, 'r');
    if (!$handle) return ',';
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
        $min = min($conteos);
        $max = max($conteos);
        if ($max > 0 && $min == $max && $max > $max_consistencia) {
            $max_consistencia = $max;
            $mejor_delimitador = $delim;
        }
    }
    return $mejor_delimitador;
}

function normalizar_encoding($filepath) {
    $contenido = file_get_contents($filepath);
    if ($contenido === false) throw new Exception("No se pudo leer el archivo");
    $bom_utf8 = pack('H*', 'EFBBBF');
    $bom_utf16_be = pack('H*', 'FEFF');
    $bom_utf16_le = pack('H*', 'FFFE');
    $contenido = preg_replace("/^($bom_utf8|$bom_utf16_be|$bom_utf16_le)/", '', $contenido);
    $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];
    $encoding_actual = mb_detect_encoding($contenido, $encodings, true);
    if ($encoding_actual && $encoding_actual !== 'UTF-8') {
        $contenido = mb_convert_encoding($contenido, 'UTF-8', $encoding_actual);
    }
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_utf8_');
    file_put_contents($temp_file, $contenido);
    return $temp_file;
}

function limpiar_celda($valor) {
    if ($valor === null || $valor === false) return '';
    $valor = (string)$valor;
    $valor = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $valor);
    $valor = trim($valor);
    $valor = trim($valor, '"\'');
    $valor = preg_replace('/\s+/', ' ', $valor);
    return $valor;
}

function validar_estructura_csv($primera_fila) {
    $headers = array_map('limpiar_celda', $primera_fila);
    $headers = array_map('strtoupper', $headers);
    if (count($headers) < 2) {
        return ['valido' => false, 'error' => 'El CSV debe tener al menos 2 columnas'];
    }
    if (stripos($headers[0], 'PLACA') === false) {
        return ['valido' => false, 'error' => 'La primera columna debe ser PLACA_UR'];
    }
    return ['valido' => true, 'headers' => $headers];
}

function procesar_csv_robusto($filepath) {
    global $csv_warnings;
    try {
        $archivo_utf8 = normalizar_encoding($filepath);
    } catch (Exception $e) {
        return ['exito' => false, 'error' => 'Error de encoding: ' . $e->getMessage()];
    }
    $delimitador = detectar_delimitador($archivo_utf8);
    $csv_warnings[] = "Delimitador: " . ($delimitador === "\t" ? 'TAB' : ($delimitador === ',' ? 'COMA' : 'PUNTO Y COMA'));
    $handle = fopen($archivo_utf8, 'r');
    if (!$handle) {
        if (file_exists($archivo_utf8)) unlink($archivo_utf8);
        return ['exito' => false, 'error' => 'No se pudo abrir'];
    }
    $datos = [];
    $row_num = 0;
    $es_header = true;
    while (($fila = fgetcsv($handle, 10000, $delimitador)) !== false) {
        $row_num++;
        if (empty(array_filter($fila, 'strlen'))) continue;
        $fila = array_map('limpiar_celda', $fila);
        if ($es_header) {
            $validacion = validar_estructura_csv($fila);
            if (!$validacion['valido']) {
                fclose($handle);
                if (file_exists($archivo_utf8)) unlink($archivo_utf8);
                return ['exito' => false, 'error' => $validacion['error']];
            }
            $es_header = false;
            continue;
        }
        if (count($datos) >= MAX_CSV_ROWS) break;
        if (count($fila) < 2) continue;
        while (count($fila) < 3) $fila[] = '';
        $datos[] = [
            'placa' => $fila[0],
            'hostname' => $fila[1],
            'comentarios' => $fila[2],
            'fila_original' => $row_num
        ];
    }
    fclose($handle);
    if (file_exists($archivo_utf8)) unlink($archivo_utf8);
    if (empty($datos)) return ['exito' => false, 'error' => 'Sin datos válidos'];
    return ['exito' => true, 'datos' => $datos];
}

function validar_placa($placa) {
    $placa = limpiar_celda($placa);
    if (empty($placa)) return ['valido' => false, 'error' => 'Placa vacía'];
    if (strlen($placa) < 3 || strlen($placa) > 50) return ['valido' => false, 'error' => 'Longitud inválida'];
    if (preg_match('/[<>"\']/', $placa)) return ['valido' => false, 'error' => 'Caracteres no permitidos'];
    return ['valido' => true, 'valor' => strtoupper($placa)];
}

function validar_hostname($hostname) {
    $hostname = limpiar_celda($hostname);
    if (empty($hostname)) return ['valido' => true, 'valor' => ''];
    if (strlen($hostname) > 63) return ['valido' => false, 'error' => 'Muy largo'];
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $hostname)) return ['valido' => false, 'error' => 'Caracteres inválidos'];
    return ['valido' => true, 'valor' => strtoupper($hostname)];
}

function validar_archivo_subido($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return "Error en carga";
    if ($file['size'] > MAX_FILE_SIZE) return "Archivo muy grande";
    if ($file['size'] == 0) return "Archivo vacío";
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) return "Solo CSV o TXT";
    return true;
}

// FASE 1: Procesar archivo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    if (!isset($_FILES['csv_file'])) {
        $msg = "<div class='alert alert-error'>❌ No se recibió archivo</div>";
    } else {
        $validacion = validar_archivo_subido($_FILES['csv_file']);
        if ($validacion !== true) {
            $msg = "<div class='alert alert-error'>❌ $validacion</div>";
        } else {
            $resultado = procesar_csv_robusto($_FILES['csv_file']['tmp_name']);
            if (!$resultado['exito']) {
                $msg = "<div class='alert alert-error'>❌ " . $resultado['error'] . "</div>";
            } else {
                $datos_csv = $resultado['datos'];
                $placas_vistas = [];
                foreach ($datos_csv as $item) {
                    $placa = $item['placa'];
                    $hostname = $item['hostname'];
                    $comentarios = $item['comentarios'];
                    $status = 'valid';
                    $note = 'OK';
                    $serial = '';
                    $val_placa = validar_placa($placa);
                    if (!$val_placa['valido']) {
                        $status = 'invalid';
                        $note = 'Placa inválida';
                        $csv_errors = true;
                    } else {
                        $placa = $val_placa['valor'];
                        if (in_array($placa, $placas_vistas)) {
                            $status = 'duplicated';
                            $note = 'Duplicada';
                            $csv_errors = true;
                        } else {
                            try {
                                $stmt = $pdo->prepare("SELECT serial, estado_maestro FROM equipos WHERE placa_ur = ?");
                                $stmt->execute([$placa]);
                                $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
                                if (!$equipo) {
                                    $status = 'invalid';
                                    $note = 'No existe';
                                    $csv_errors = true;
                                } elseif ($equipo['estado_maestro'] === 'Baja') {
                                    $status = 'invalid';
                                    $note = 'En BAJA';
                                    $csv_errors = true;
                                } else {
                                    $serial = $equipo['serial'];
                                    $placas_vistas[] = $placa;
                                }
                            } catch (PDOException $e) {
                                $status = 'invalid';
                                $note = 'Error BD';
                                $csv_errors = true;
                            }
                        }
                    }
                    if ($status === 'valid' && !empty($hostname)) {
                        $val_host = validar_hostname($hostname);
                        if (!$val_host['valido']) {
                            $status = 'invalid';
                            $note = 'Hostname inválido';
                            $csv_errors = true;
                        } else {
                            $hostname = $val_host['valor'];
                        }
                    }
                    $preview_data[] = [
                        'placa' => $placa,
                        'hostname' => $hostname,
                        'serial' => $serial,
                        'comentarios' => substr($comentarios, 0, 255),
                        'status' => $status,
                        'note' => $note
                    ];
                }
                if (count($preview_data) > 0) {
                    $step = 2;
                } else {
                    $msg = "<div class='alert alert-error'>⚠️ Sin datos válidos</div>";
                }
            }
        }
    }
}

// FASE 2: Guardar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_save'])) {
    try {
        $items = json_decode($_POST['items_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Datos del CSV inválidos o corruptos al enviar.");
        
        // Validar que el lugar existe
        $stmt_l = $pdo->prepare("SELECT id FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();
        if (!$l) throw new Exception("La ubicación seleccionada es inválida.");
        
        // Obtener No. de Caso
        $no_caso = trim($_POST['no_caso']);
        if (empty($no_caso)) throw new Exception("Falta el No. de Caso.");
        $desc_evento = "Caso: " . $no_caso;
        
        $pdo->beginTransaction();
        $serials_procesados = [];
        
        // sede y ubicacion ya no se guardan, se leen de tabla lugares via JOIN
        $sql = "INSERT INTO bitacora (
            serial_equipo, id_lugar,
            campo_adic1, desc_evento, tipo_evento,
            correo_responsable, responsable_secundario,
            tecnico_responsable, hostname, fecha_evento,
            check_dlo, check_antivirus, check_sccm
        ) VALUES (?, ?, ?, ?, 'Asignacion_Masiva', ?, ?, ?, ?, NOW(), ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        $dlo = isset($_POST['check_dlo']) ? 1 : 0;
        $av = isset($_POST['check_antivirus']) ? 1 : 0;
        $sccm = isset($_POST['check_sccm']) ? 1 : 0;
        
        foreach ($items as $item) {
            if ($item['status'] !== 'valid') continue;
            
            if (empty($item['serial'])) {
                throw new Exception("El equipo con placa " . $item['placa'] . " no tiene un serial válido asociado en la base de datos.");
            }

            $stmt->execute([
                $item['serial'], 
                $_POST['id_lugar'],
                $item['comentarios'], 
                $desc_evento,
                $_POST['correo_resp_real'], 
                $_POST['correo_sec_real'] ?: null,
                $_SESSION['nombre'], 
                strtoupper($item['hostname']),
                $dlo, 
                $av, 
                $sccm
            ]);
            $serials_procesados[] = $item['serial'];
        }
        
        $pdo->commit();
        
        if (count($serials_procesados) > 0) {
            header("Location: generar_acta_masiva.php?serials=" . urlencode(implode(',', $serials_procesados)));
            exit;
        } else {
            $msg = "<div class='alert alert-error'>⚠️ No se procesó ningún equipo. Revisa que estén en estado 'valid'.</div>";
            $step = 1;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // ESTE MENSAJE ES CLAVE: Te mostrará si es un error de Foreign Key u otra cosa de MySQL
        error_log("Error SQL en asignación masiva: " . $e->getMessage());
        $msg = "<div class='alert alert-error'>❌ Error de Base de Datos: " . htmlspecialchars($e->getMessage()) . "</div>";
        $step = 2; // Mantener en el paso 2 para ver el error
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error de lógica en asignación masiva: " . $e->getMessage());
        $msg = "<div class='alert alert-error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $step = 2; // Mantener en el paso 2 para ver el error
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación Masiva - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
</head>
<body>

<div class="container">
    <div class="card">
        <div class="d-flex justify-between align-center mb-3" style="border-bottom: 2px solid var(--primary-color); padding-bottom: 15px;">
            <h2 style="margin: 0; color: var(--primary-color);">🚀 Asignación Masiva</h2>
            <a href="dashboard.php" class="btn btn-outline">⬅ Volver</a>
        </div>

        <?= $msg ?>
        
        <?php if (!empty($csv_warnings)): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Advertencias:</strong>
                <ul class="warnings-list">
                    <?php foreach ($csv_warnings as $w): ?>
                        <li><?= htmlspecialchars($w) ?></li>
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
                        <div class="dropzone-desc">o haz clic para explorar</div>
                    </div>
                </div>

                <div id="fileInfo" class="file-info">
                    📄 Archivo: <span id="fileName"></span>
                </div>

                <div class="instructions">
                    <div class="instruction-box">
                        <h4>📋 Formato CSV</h4>
                        <p style="font-size:0.9rem;">El archivo debe tener estas columnas:</p>
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <span class="code-pill">PLACA_UR</span>
                            <span class="code-pill">HOSTNAME</span>
                            <span class="code-pill">COMENTARIOS</span>
                        </div>
                    </div>
                    <div class="instruction-box">
                        <h4>💡 Tips</h4>
                        <ul style="padding-left:20px; font-size:0.9rem;">
                            <li>Máximo 100 filas</li>
                            <li>Delimitador automático</li>
                            <li>Encoding automático</li>
                        </ul>
                    </div>
                </div>

                <button type="submit" name="upload_csv" id="btnUpload" class="btn btn-primary btn-block" style="display:none; margin-top: 20px;">
                    📂 Analizar Archivo
                </button>
            </form>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <form method="POST">
                <h3>1. Validación (<?= count($preview_data) ?> registros)</h3>
                
                <div class="table-container">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Placa</th>
                                <th>Serial</th>
                                <th>Hostname</th>
                                <th>Comentarios</th>
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
                                    <td><?= $row['status'] == 'valid' ? '✅' : ($row['status'] == 'duplicated' ? '⚠️' : '❌') ?></td>
                                    <td><?= htmlspecialchars($row['placa']) ?></td>
                                    <td><?= htmlspecialchars($row['serial']) ?></td>
                                    <td><?= htmlspecialchars($row['hostname']) ?></td>
                                    <td><?= htmlspecialchars(substr($row['comentarios'], 0, 30)) ?><?= strlen($row['comentarios']) > 30 ? '...' : '' ?></td>
                                    <td><?= htmlspecialchars($row['note']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="margin-top: 30px;">2. Configuración Global</h3>
                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="selectSede">Sede Destino *</label>
                        <select id="selectSede" required>
                            <option value="">-- Seleccionar --</option>
                            <?php 
                            $sedes = array_unique(array_column($lugares, 'sede'));
                            foreach($sedes as $s) echo "<option value='$s'>$s</option>";
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selectLugar">Ubicación *</label>
                        <select id="selectLugar" name="id_lugar" required disabled>
                            <option value="">-- Elija Sede --</option>
                        </select>
                    </div>

                    <!-- NO. DE CASO (OBLIGATORIO - GLOBAL) -->
                    <div class="form-group">
                        <label for="no_caso">No. de Caso *</label>
                        <input type="text" id="no_caso" name="no_caso" required placeholder="Ej: 12345, INC0045678">
                        <small class="hint">Aplica a todos los equipos</small>
                    </div>

                    <div class="form-group">
                        <!-- Espacio vacío para mantener grid -->
                    </div>

                    <!-- COMPLIANCE SECTION - 3 SWITCHES -->
                    <div class="compliance-section">
                        <span>🛡️ Verificación de Seguridad (aplica a todos)</span>
                        
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

                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="check_sccm" value="1">
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Agente SCCM</span>
                        </div>
                    </div>

                    <div class="ldap-group" style="grid-column: span 2;">
                        <label>👤 Responsable Principal (LDAP) *</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" id="user_id" placeholder="nombre.apellido">
                            <button type="button" onclick="verificarUsuario()">🔍 Verificar</button>
                        </div>
                        <div id="userCard" class="user-card">
                            <h4 id="ldap_nombre"></h4>
                            <div id="ldap_info"></div>
                        </div>
                        <input type="hidden" name="correo_resp_real" id="correo_resp_real" required>
                    </div>

                    <div class="ldap-group" style="grid-column: span 2;">
                        <label>👥 Responsable Secundario (Opcional)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" id="user_id_sec" placeholder="nombre.apellido">
                            <button type="button" onclick="verificarUsuarioOpcional()">🔍 Verificar</button>
                        </div>
                        <div id="userCard_sec" class="user-card">
                            <h4 id="ldap_nombre_sec"></h4>
                            <div id="ldap_info_sec"></div>
                        </div>
                        <input type="hidden" name="correo_sec_real" id="correo_sec_real">
                    </div>
                </div>

                <input type="hidden" name="items_json" value='<?= json_encode($preview_data) ?>'>
                
                <div style="display:flex; gap:20px; margin-top:30px;">
                    <a href="asignacion_masiva.php" class="btn-cancel">CANCELAR</a>
                    <?php if ($validos > 0): ?>
                        <button type="submit" name="confirm_save" id="btnSubmit" class="btn btn-success" style="flex:2;" disabled>
                            CONFIRMAR (<?= $validos ?> Equipos)
                        </button>
                    <?php else: ?>
                        <div class="alert alert-error" style="flex:2; margin:0;">Sin equipos válidos</div>
                    <?php endif; ?>
                </div>
            </form>
            
            <script>const URTRACK_LUGARES = <?= json_encode($lugares) ?>;</script>
            <script src="js/verificar_ldap.js"></script>
            <script src="js/verificar_ldap_opcional.js"></script>
        <?php endif; ?> <script src="js/asignacion_masiva.js"></script>
    </div>
</div>

</body>
</html>