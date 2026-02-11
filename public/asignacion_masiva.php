<?php
/**
 * public/asignacion_masiva.php
 * Versión Corregida: Estructura HTML compatible con verificar_ldap.js
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Seguridad: Bloquear Auditor
if ($_SESSION['rol'] === 'Auditor') {
    header('Location: dashboard.php'); exit;
}

// Cargar Lugares
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

$msg = "";
$step = 1; 
$preview_data = [];
$csv_errors = false;

// --- PROCESAMIENTO PHP (Igual que antes) ---
// FASE 1: Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $ext = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'csv') {
            $msg = "<div class='alert error'>❌ Solo se permiten archivos .csv</div>";
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
            $row_count = 0;
            $placas_vistas = [];
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                if ($row_count === 1 && (stripos($data[0], 'PLACA') !== false)) continue;
                if ($row_count > 101) break;

                $placa = trim($data[0] ?? '');
                $hostname = trim($data[1] ?? '');
                $adic1 = trim($data[2] ?? '');
                $adic2 = trim($data[3] ?? '');

                if (empty($placa)) continue;

                $stmt = $pdo->prepare("SELECT serial, estado_maestro FROM equipos WHERE placa_ur = ? LIMIT 1");
                $stmt->execute([$placa]);
                $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

                $status = 'valid'; $note = 'OK'; $serial = '';

                if (!$equipo) { $status = 'invalid'; $note = 'No existe en Inventario'; $csv_errors = true; } 
                elseif ($equipo['estado_maestro'] === 'Baja') { $status = 'invalid'; $note = 'Equipo en BAJA'; $csv_errors = true; } 
                elseif (in_array($placa, $placas_vistas)) { $status = 'duplicated'; $note = 'Placa repetida'; $csv_errors = true; } 
                else { $serial = $equipo['serial']; $placas_vistas[] = $placa; }

                $preview_data[] = ['placa'=>$placa, 'hostname'=>$hostname, 'serial'=>$serial, 'adic1'=>$adic1, 'adic2'=>$adic2, 'status'=>$status, 'note'=>$note];
            }
            fclose($handle);
            if (count($preview_data) > 0) $step = 2;
            else $msg = "<div class='alert error'>⚠️ Archivo vacío o inválido.</div>";
        }
    }
}

// FASE 2: Guardar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_save'])) {
    try {
        $items = json_decode($_POST['items_json'], true);
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();
        
        $pdo->beginTransaction();
        $count = 0;
        $sql = "INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, campo_adic1, campo_adic2, tipo_evento, correo_responsable, responsable_secundario, tecnico_responsable, hostname, fecha_evento, check_dlo, check_antivirus) VALUES (?, ?, ?, ?, ?, ?, 'Asignacion_Masiva', ?, ?, ?, ?, NOW(), ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($items as $item) {
            if ($item['status'] !== 'valid') continue;
            $stmt->execute([
                $item['serial'], $_POST['id_lugar'], $l['sede'], $l['nombre'], $item['adic1'], $item['adic2'],
                $_POST['correo_resp_real'], $_POST['correo_sec_real'] ?: null, $_SESSION['nombre'],
                strtoupper($item['hostname']), (isset($_POST['check_dlo'])?1:0), (isset($_POST['check_antivirus'])?1:0)
            ]);
            $count++;
        }
        $pdo->commit();
        $msg = "<div class='alert success'>✅ Procesados <b>$count</b> equipos.</div>";
        $step = 1;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>❌ Error: " . $e->getMessage() . "</div>";
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
        :root { --primary: #4f46e5; --success: #22c55e; --error: #ef4444; --warning: #f59e0b; --bg: #f8fafc; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 20px; margin: 0; }
        
        .card { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 1000px; 
            margin: auto; width: 100%; box-sizing: border-box;
        }

        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 25px; }
        .header h2 { font-size: 1.5rem; margin: 0; color: var(--primary); }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align:center; font-weight:bold; }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }

        .table-container { width: 100%; overflow-x: auto; border: 1px solid #e2e8f0; margin-top: 20px; border-radius: 6px; }
        .preview-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .preview-table th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 0.9rem; }
        .preview-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        .row-valid { border-left: 4px solid var(--success); background: #f0fdf4; }
        .row-invalid { border-left: 4px solid var(--error); background: #fef2f2; }
        .row-duplicated { border-left: 4px solid var(--warning); background: #fffbeb; }

        .form-grid { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 20px; 
            background: #f8fafc; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; 
        }

        input[type="text"], select, input[type="file"] { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .label-sm { display: block; font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 8px; }

        .action-buttons { display: flex; gap: 20px; margin-top: 25px; }
        .btn-primary { background: var(--primary); color: white; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1rem; opacity: 0.5; cursor: not-allowed; transition: all 0.3s; }
        .btn-cancel { background: #64748b; color: white; padding: 15px; border-radius: 6px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .switch-container { display: flex; align-items: center; gap: 10px; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(20px); }

        @media (max-width: 768px) {
            .card { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; padding: 15px; }
            .ldap-group, .checks-group { grid-column: span 1 !important; }
            .action-buttons { flex-direction: column; gap: 10px; }
            .btn-cancel, .btn-primary { width: 100%; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2>🚀 Asignación Masiva</h2>
        <a href="dashboard.php" style="text-decoration:none; color:#64748b; font-weight:500;">⬅ Volver</a>
    </div>

    <?= $msg ?>

    <?php if ($step === 1): ?>
        <div style="text-align:center; padding: 40px 20px; border: 2px dashed #cbd5e1; border-radius: 12px; background: #fafafa;">
            <p style="color:#475569; margin-bottom:20px;">Sube un archivo CSV (máx 100 filas) con las columnas:<br>
            <code style="background:#e2e8f0; padding:2px 5px; border-radius:4px;">PLACA_UR, HOSTNAME, [INFO_ADIC1], [INFO_ADIC2]</code></p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required style="max-width:300px;">
                <br><br>
                <button type="submit" name="upload_csv" class="btn-primary" style="width: auto; padding: 12px 30px; opacity: 1; cursor: pointer;">📂 Analizar Archivo</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($step === 2): ?>
        <form method="POST">
            <h3>1. Validación de Datos (<?= count($preview_data) ?> registros)</h3>
            
            <div class="table-container">
                <table class="preview-table">
                    <thead><tr><th>Estado</th><th>Placa</th><th>Serial</th><th>Hostname</th><th>Nota</th></tr></thead>
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
                                <td><?= htmlspecialchars($row['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top: 30px;">2. Configuración Global</h3>
            <p style="font-size: 0.9rem; color: #666;">Se aplicará a los <b><?= $validos ?></b> equipos válidos.</p>

            <div class="form-grid">
                <div>
                    <label class="label-sm">Sede Destino</label>
                    <select id="selectSede" required>
                        <option value="">-- Seleccionar --</option>
                        <?php 
                        $sedes = array_unique(array_column($lugares, 'sede')); 
                        foreach($sedes as $s) echo "<option value='$s'>$s</option>"; 
                        ?>
                    </select>
                </div>
                <div>
                    <label class="label-sm">Ubicación Específica</label>
                    <select id="selectLugar" name="id_lugar" required disabled><option value="">-- Elija Sede --</option></select>
                </div>

                <div class="ldap-group" style="grid-column: span 2;">
                    <label class="label-sm">👤 Responsable Principal (LDAP)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuario()" class="btn-primary" style="width:auto; padding: 0 15px; opacity:1; cursor:pointer;">🔍</button>
                    </div>
                    
                    <div id="userCard" class="user-card" style="margin-top:10px;">
                        <h4 id="ldap_nombre" style="margin:0; color:var(--primary);"></h4>
                        <div id="ldap_info" style="font-size:0.85rem; color:#666;"></div>
                    </div>
                    <input type="hidden" name="correo_resp_real" id="correo_resp_real" required>
                </div>

                <div class="ldap-group" style="grid-column: span 2;">
                    <label class="label-sm">👥 Responsable Secundario (Opcional)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id_sec" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuarioOpcional()" style="background:#64748b; color:white; border:none; border-radius:6px; cursor:pointer; padding:0 15px;">🔍</button>
                    </div>
                    
                    <div id="userCard_sec" class="user-card" style="margin-top:10px;">
                        <h4 id="ldap_nombre_sec" style="margin:0; color:#444;"></h4>
                        <div id="ldap_info_sec" style="font-size:0.85rem; color:#666;"></div>
                    </div>
                    <input type="hidden" name="correo_sec_real" id="correo_sec_real">
                </div>

                <div class="checks-group" style="grid-column: span 2; display: flex; gap: 30px; background: white; padding: 15px; border-radius: 6px;">
                    <div class="switch-container">
                        <label class="switch"><input type="checkbox" name="check_dlo" value="1" checked><span class="slider"></span></label>
                        <span style="font-size:0.9rem;">Agente DLO</span>
                    </div>
                    <div class="switch-container">
                        <label class="switch"><input type="checkbox" name="check_antivirus" value="1" checked><span class="slider"></span></label>
                        <span style="font-size:0.9rem;">Antivirus Corp.</span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="items_json" value='<?= json_encode($preview_data) ?>'>
            
            <div class="action-buttons">
                <a href="asignacion_masiva.php" class="btn-cancel" style="flex:1;">CANCELAR</a>
                <?php if ($validos > 0): ?>
                    <button type="submit" name="confirm_save" id="btnSubmit" class="btn-primary" style="flex:2;" disabled>
                        CONFIRMAR (<?= $validos ?> Equipos) 🔒
                    </button>
                <?php else: ?>
                    <div class="alert error" style="flex:2; margin:0;">No hay equipos válidos</div>
                <?php endif; ?>
            </div>
        </form>

        <script>const URTRACK_LUGARES = <?= json_encode($lugares) ?>;</script>
        
        <script src="js/verificar_ldap.js"></script>
        <script src="js/verificar_ldap_opcional.js"></script>
        <script src="js/asignacion_masiva.js"></script>
    <?php endif; ?>
</div>

</body>
</html>