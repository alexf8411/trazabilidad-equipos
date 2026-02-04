<?php
/**
 * public/registro_movimiento.php
 * Módulo de Asignación con Validación LDAP y Auditoría de Estado Actual
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. Seguridad RBAC
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$ultimo_mov = null; // Variable para almacenar el estado real
$msg = "";

// 2. Cargar Catálogo de Lugares
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar Equipo + Historial Reciente
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    // A. Buscar datos del activo
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($equipo) {
        // B. MEJORA: Buscar quién lo tiene actualmente (último movimiento en bitácora)
        $stmt_hist = $pdo->prepare("SELECT * FROM bitacora WHERE serial_equipo = ? ORDER BY id DESC LIMIT 1");
        $stmt_hist->execute([$equipo['serial']]);
        $ultimo_mov = $stmt_hist->fetch(PDO::FETCH_ASSOC);
    } else {
        $msg = "<div class='alert error'>❌ Equipo no localizado en inventario.</div>";
    }
}

// 4. Procesar Nueva Asignación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion, 
                    tipo_evento, correo_responsable, tecnico_responsable, 
                    hostname, fecha_evento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], 
            $_POST['id_lugar'], 
            $l['sede'], 
            $l['nombre'],
            $_POST['tipo_evento'], 
            $_POST['correo_resp_real'], 
            $_SESSION['nombre'], 
            strtoupper($_POST['hostname'])
        ]);

        // Actualizamos el estado maestro en la tabla equipos
        // Si es Retorno -> En Bodega, Si es Asignación -> Asignado
        $nuevo_estado = ($_POST['tipo_evento'] === 'Retorno') ? 'En Bodega' : 'Asignado';
        $pdo->prepare("UPDATE equipos SET estado_maestro = ? WHERE serial = ?")->execute([$nuevo_estado, $_POST['serial']]);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación | URTRACK</title>
    <style>
        :root { 
            --primary: #002D72; 
            --success: #22c55e; 
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc; 
            --border: #e2e8f0; 
            --text-secondary: #64748b;
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); display: flex; justify-content: center; padding: 40px 20px; color: #333; }
        
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid var(--border); width: 100%; max-width: 900px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px; }
        .header h2 { margin: 0; color: var(--primary); }
        .btn-back { text-decoration: none; color: var(--text-secondary); font-weight: 500; font-size: 0.9rem; }
        .btn-back:hover { color: var(--primary); }

        .search-section { display: flex; gap: 10px; margin-bottom: 25px; background: #f1f5f9; padding: 20px; border-radius: 8px; }
        input, select { padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; box-sizing: border-box; font-size: 0.95rem; outline: none; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 45, 114, 0.1); }

        .btn-action { background: var(--primary); color: white; border: none; padding: 0 25px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-action:hover { background: #001f52; }

        /* --- NUEVO LAYOUT DE ESTADO --- */
        .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        
        .info-pill { background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .info-pill h3 { margin-top: 0; font-size: 1rem; color: var(--primary); border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 15px; }
        
        /* Caja de Estado Dinámica */
        .status-box { padding: 20px; border-radius: 8px; border: 1px solid transparent; }
        .status-free { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .status-busy { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        
        .label-sm { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 3px; display: block; }
        .data-val { font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom: 12px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .user-verify-group { display: flex; gap: 8px; }
        .btn-verify { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; padding: 0 15px; border-radius: 6px; cursor: pointer; font-weight: 600; white-space: nowrap; }
        .btn-verify:hover { background: #cbd5e1; }

        .user-card { background: #ffffff; border: 2px solid var(--primary); padding: 15px; border-radius: 8px; margin-top: 15px; display: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .user-card h4 { margin: 0 0 5px 0; color: var(--primary); border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }

        .btn-submit { background: var(--success); color: white; border: none; padding: 18px; border-radius: 8px; width: 100%; font-weight: 700; font-size: 1rem; margin-top: 30px; cursor: pointer; transition: 0.3s; opacity: 0.5; }
        .btn-submit:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2>🚚 Asignación y Traslados</h2>
        <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    </div>

    <?= $msg ?>

    <form method="GET" class="search-section">
        <input type="text" name="criterio" placeholder="Escanee Placa UR o Serial..." value="<?= $_GET['criterio'] ?? '' ?>" required autofocus>
        <button type="submit" name="buscar" class="btn-action">BUSCAR</button>
    </form>

    <?php if ($equipo): ?>
        
        <div class="status-grid">
            
            <div class="info-pill">
                <h3>📦 Datos del Activo</h3>
                <div><span class="label-sm">Modelo</span><div class="data-val"><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></div></div>
                <div><span class="label-sm">Placa</span><div class="data-val"><?= $equipo['placa_ur'] ?></div></div>
                <div><span class="label-sm">Serial</span><div class="data-val"><?= $equipo['serial'] ?></div></div>
            </div>

            <?php 
                // Determinamos si está libre u ocupado
                $esta_asignado = ($ultimo_mov && $ultimo_mov['tipo_evento'] !== 'Retorno');
                $clase_estado = $esta_asignado ? 'status-busy' : 'status-free';
                $titulo_estado = $esta_asignado ? '⚠️ ASIGNADO ACTUALMENTE' : '✅ DISPONIBLE / EN BODEGA';
            ?>
            <div class="status-box <?= $clase_estado ?>">
                <h3 style="color: inherit; border-color: rgba(0,0,0,0.1);"><?= $titulo_estado ?></h3>
                
                <?php if ($esta_asignado): ?>
                    <div><span class="label-sm">Responsable Actual</span><div class="data-val"><?= $ultimo_mov['correo_responsable'] ?></div></div>
                    <div><span class="label-sm">Ubicación</span><div class="data-val"><?= $ultimo_mov['sede'] ?> - <?= $ultimo_mov['ubicacion'] ?></div></div>
                    <div><span class="label-sm">Fecha Asignación</span><div class="data-val"><?= date('d/m/Y', strtotime($ultimo_mov['fecha_evento'])) ?></div></div>
                    <small>Verifique antes de reasignar.</small>
                <?php else: ?>
                    <p style="margin: 10px 0;">Este equipo figura como disponible o retornado en el sistema.</p>
                    <?php if ($ultimo_mov): ?>
                        <div style="font-size: 0.85rem; opacity: 0.8;">Último evento: <?= $ultimo_mov['tipo_evento'] ?> (<?= date('d/m/Y', strtotime($ultimo_mov['fecha_evento'])) ?>)</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
            <input type="hidden" name="correo_resp_real" id="correo_resp_real">

            <div class="form-grid">
                <div>
                    <label class="label-sm">Nuevo Hostname</label>
                    <input type="text" name="hostname" required placeholder="Ej: PB-ADM-L01" autocomplete="off">
                </div>
                <div>
                    <label class="label-sm">Tipo de Movimiento</label>
                    <select name="tipo_evento">
                        <option value="Asignación">Asignación (Entrega)</option>
                        <option value="Traslado">Traslado (Cambio de Sede)</option>
                        <option value="Retorno">Retorno (Devolución a Bodega)</option>
                        <option value="Préstamo">Préstamo Temporal</option>
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
                    <label class="label-sm">Ubicación / Edificio</label>
                    <select id="selectLugar" name="id_lugar" required disabled>
                        <option value="">-- Primero elija Sede --</option>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label class="label-sm">Nuevo Responsable (Usuario o Correo)</label>
                    <div class="user-verify-group">
                        <input type="text" id="user_id" placeholder="Ej: guillermo.fonseca (o pegar correo)" autocomplete="off">
                        <button type="button" onclick="verificarUsuario()" class="btn-verify">🔍 Verificar Identidad</button>
                    </div>
                    
                    <div id="userCard" class="user-card">
                        <h4 id="ldap_nombre"></h4>
                        <div id="ldap_info" style="font-size:0.9rem; color:#475569; line-height:1.5;"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>CONFIRMAR Y GENERAR ACTA</button>
        </form>

        <script>
            // Lógica para filtrar lugares
            const lugaresData = <?= json_encode($lugares) ?>;
            
            function filtrarLugares() {
                const sedeSeleccionada = document.getElementById('selectSede').value;
                const selectLugar = document.getElementById('selectLugar');
                
                selectLugar.innerHTML = '<option value="">-- Seleccionar Ubicación --</option>';
                selectLugar.disabled = true;

                if (sedeSeleccionada) {
                    const filtrados = lugaresData.filter(l => l.sede === sedeSeleccionada);
                    if (filtrados.length > 0) {
                        filtrados.forEach(l => {
                            selectLugar.innerHTML += `<option value="${l.id}">${l.nombre}</option>`;
                        });
                        selectLugar.disabled = false;
                    } else {
                        selectLugar.innerHTML = '<option value="">Sin ubicaciones registradas</option>';
                    }
                }
            }
        </script>
        <script src="js/verificar_ldap.js"></script>
    <?php endif; ?>
</div>

</body>
</html>