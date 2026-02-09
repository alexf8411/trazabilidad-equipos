<?php
/**
 * public/registro_movimiento.php
 * Versión Consolidada: Bloqueo Bajas, Campos Adicionales y Dual LDAP
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. Seguridad RBAC
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

// 2. Cargar Catálogo de Lugares
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar Equipo (Con validación de estado y Caja Naranja)
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    
    $sql_buscar = "SELECT e.*, 
                   b.correo_responsable AS responsable_actual, 
                   b.ubicacion AS ubicacion_actual, 
                   b.sede AS sede_actual
                   FROM equipos e
                   LEFT JOIN bitacora b ON e.serial = b.serial_equipo 
                   AND b.id_evento = (SELECT MAX(id_evento) FROM bitacora WHERE serial_equipo = e.serial)
                   WHERE e.placa_ur = ? OR e.serial = ? 
                   LIMIT 1";
                   
    $stmt = $pdo->prepare($sql_buscar);
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipo) {
        $msg = "<div class='alert error'>❌ Equipo no localizado en inventario.</div>";
    } 
    elseif ($equipo['estado_maestro'] === 'Baja') {
        $msg = "<div class='alert error'>🛑 <b>ACCESO DENEGADO:</b> El equipo se encuentra en estado de <b>BAJA</b>. No se permiten movimientos de activos retirados.</div>";
        $equipo = null;
    }
}

// 4. Procesar Asignación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion, 
                    campo_adic1, campo_adic2,
                    tipo_evento, correo_responsable, responsable_secundario, tecnico_responsable, 
                    hostname, fecha_evento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $pdo->prepare($sql)->execute([
            $_POST['serial'], 
            $_POST['id_lugar'], 
            $l['sede'], 
            $l['nombre'],
            $_POST['campo_adic1'], 
            $_POST['campo_adic2'],
            $_POST['tipo_evento'], 
            $_POST['correo_resp_real'],           // Principal
            $_POST['correo_sec_real'] ?: null,    // Secundario
            $_SESSION['nombre'], 
            strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>Error al guardar: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación | URTRACK</title>
    <style>
        :root { --primary: #002D72; --success: #22c55e; --bg: #f8fafc; --border: #e2e8f0; --text-secondary: #64748b; --warning: #f59e0b; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 40px 20px; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width: 900px; margin: auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 30px; }
        .search-section { display: flex; gap: 10px; margin-bottom: 30px; background: #f1f5f9; padding: 20px; border-radius: 8px; }
        input, select { padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; box-sizing: border-box; }
        
        .info-pill { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; border-left: 5px solid var(--primary); }
        .current-status-box { grid-column: span 2; background: #fff7ed; border: 1px solid #fed7aa; padding: 10px; border-radius: 6px; margin-top: 10px; }
        
        .label-sm { display: block; font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .data-val { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
        .btn-submit { background: var(--success); color: white; border: none; padding: 18px; border-radius: 8px; width: 100%; font-weight: 700; cursor: pointer; opacity: 0.5; margin-top: 25px; }
        .user-card { background: #fff; border: 2px solid var(--primary); padding: 15px; border-radius: 8px; margin-top: 15px; display: none; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #fee2e2; color: #991b1b; }
        .ldap-group { background: #fcfcfc; border: 1px solid #eee; padding: 20px; border-radius: 8px; margin-top: 15px; }
    </style>
</head>
<body>

<div class="card">
    <div class="header"><h2>🚚 Asignación y Traslados</h2><a href="dashboard.php" style="text-decoration:none; color:gray;">⬅ Volver</a></div>

    <?= $msg ?>

    <form method="GET" class="search-section">
        <input type="text" name="criterio" placeholder="Escanee Placa UR o Serial..." value="<?= htmlspecialchars($_GET['criterio'] ?? '') ?>" required autofocus>
        <button type="submit" name="buscar" style="background:var(--primary); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">BUSCAR</button>
    </form>

    <?php if ($equipo): ?>
        <div class="info-pill">
            <div><span class="label-sm">Equipo</span><div class="data-val"><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></div></div>
            <div><span class="label-sm">Identificación</span><div class="data-val">Placa: <?= $equipo['placa_ur'] ?> | SN: <?= $equipo['serial'] ?></div></div>
            <div class="current-status-box">
                <div style="display:grid; grid-template-columns: 1fr 1fr;">
                    <div><span class="label-sm" style="color:var(--warning)">📍 Ubicación Actual:</span><div class="data-val"><?= $equipo['sede_actual'] ?> - <?= $equipo['ubicacion_actual'] ?></div></div>
                    <div><span class="label-sm" style="color:var(--warning)">👤 Responsable Actual:</span><div class="data-val"><?= $equipo['responsable_actual'] ?? 'Sin asignar (Bodega)' ?></div></div>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
            
            <input type="hidden" name="correo_resp_real" id="correo_resp_real"> 
            <input type="hidden" name="correo_sec_real" id="correo_sec_real">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div><label class="label-sm">Nuevo Hostname</label><input type="text" name="hostname" required placeholder="Ej: PB-ADM-L01"></div>
                <div>
                    <label class="label-sm">Tipo de Movimiento</label>
                    <select name="tipo_evento">
                        <option value="Asignación">Asignación</option>
                        <option value="Devolución">Devolución</option>
                    </select>
                </div>
                
                <div>
                    <label class="label-sm">Sede Destino</label>
                    <select id="selectSede" required onchange="filtrarLugares()">
                        <option value="">-- Seleccionar Sede --</option>
                        <?php 
                        $sedes = array_unique(array_column($lugares, 'sede')); 
                        foreach($sedes as $s) echo "<option value='$s'>$s</option>"; 
                        ?>
                    </select>
                </div>
                <div>
                    <label class="label-sm">Ubicación Destino</label>
                    <select id="selectLugar" name="id_lugar" required disabled><option value="">-- Elija Sede --</option></select>
                </div>

                <div><label class="label-sm">Campo Adicional 1</label><input type="text" name="campo_adic1" placeholder="Info 1"></div>
                <div><label class="label-sm">Campo Adicional 2</label><input type="text" name="campo_adic2" placeholder="Info 2"></div>

                <div class="ldap-group" style="grid-column: span 2;">
                    <label class="label-sm">👤 Responsable Principal (LDAP)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuario()" style="white-space:nowrap; background:var(--primary); color:white; border:none; padding:0 15px; border-radius:6px; cursor:pointer;">🔍 Verificar</button>
                    </div>
                    <div id="userCard" class="user-card">
                        <h4 id="ldap_nombre" style="margin:0; color:var(--primary);"></h4>
                        <div id="ldap_info" style="font-size:0.85rem;"></div>
                    </div>
                </div>

                <div class="ldap-group" style="grid-column: span 2;">
                    <label class="label-sm">👥 Responsable Secundario (LDAP - Opcional)</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id_sec" placeholder="nombre.apellido">
                        <button type="button" onclick="verificarUsuarioOpcional()" style="white-space:nowrap; background:#64748b; color:white; border:none; padding:0 15px; border-radius:6px; cursor:pointer;">🔍 Verificar Opcional</button>
                    </div>
                    <div id="userCard_sec" class="user-card">
                        <h4 id="ldap_nombre_sec" style="margin:0; color:#444;"></h4>
                        <div id="ldap_info_sec" style="font-size:0.85rem;"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>CONFIRMAR MOVIMIENTO</button>
        </form>

        <script>
            const lugaresData = <?= json_encode($lugares) ?>;
            function filtrarLugares() {
                const sede = document.getElementById('selectSede').value;
                const sl = document.getElementById('selectLugar');
                sl.innerHTML = '<option value="">-- Seleccionar --</option>';
                lugaresData.filter(l => l.sede === sede).forEach(l => { 
                    sl.innerHTML += `<option value="${l.id}">${l.nombre}</option>`; 
                });
                sl.disabled = false;
            }
        </script>
        
        <script src="js/verificar_ldap.js"></script>
        <script src="js/verificar_ldap_opcional.js"></script>
    <?php endif; ?>
</div>
</body>
</html>