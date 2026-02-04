<?php
/**
 * public/registro_movimiento.php
 * Módulo de Asignación de Activos con Validación LDAP Flexible
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. Seguridad RBAC
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

// 2. Cargar Catálogo de Lugares (Para selects dinámicos)
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar Equipo (Paso 1 del flujo)
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipo) $msg = "<div class='alert error'>❌ Equipo no localizado en inventario.</div>";
}

// 4. Procesar Asignación (Paso 2 del flujo)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtenemos nombres legibles del lugar para la bitácora histórica
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
            $_POST['correo_resp_real'], // Este es el dato validado por LDAP
            $_SESSION['nombre'], 
            strtoupper($_POST['hostname'])
        ]);

        // Opcional: Actualizar estado maestro del equipo
        $pdo->prepare("UPDATE equipos SET estado_maestro = 'Asignado' WHERE serial = ?")->execute([$_POST['serial']]);

        $pdo->commit();
        
        // Redirigir al PDF
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
            --bg: #f8fafc; 
            --border: #e2e8f0; 
            --text-secondary: #64748b;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); display: flex; justify-content: center; padding: 40px 20px; color: #333; }
        
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid var(--border); width: 100%; max-width: 900px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px; }
        .header h2 { margin: 0; color: var(--primary); }
        .btn-back { text-decoration: none; color: var(--text-secondary); font-weight: 500; font-size: 0.9rem; }
        .btn-back:hover { color: var(--primary); }

        .search-section { display: flex; gap: 10px; margin-bottom: 30px; background: #f1f5f9; padding: 20px; border-radius: 8px; }
        input, select { padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; box-sizing: border-box; font-size: 0.95rem; outline: none; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 45, 114, 0.1); }

        .btn-action { background: var(--primary); color: white; border: none; padding: 0 25px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-action:hover { background: #001f52; }

        .info-pill { background: #e0e7ff; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-left: 5px solid var(--primary); }
        .label-sm { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; display: block; }
        .data-val { font-size: 1rem; font-weight: 600; color: #1e293b; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        /* Estilos específicos para la validación de usuario */
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
        <button type="submit" name="buscar" class="btn-action">BUSCAR ACTIVO</button>
    </form>

    <?php if ($equipo): ?>
        <div class="info-pill">
            <div><span class="label-sm">Modelo del Equipo</span><div class="data-val"><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></div></div>
            <div><span class="label-sm">Placa Institucional</span><div class="data-val"><?= $equipo['placa_ur'] ?></div></div>
            <div><span class="label-sm">Serial</span><div class="data-val"><?= $equipo['serial'] ?></div></div>
            <div><span class="label-sm">Estado Actual</span><div class="data-val" style="color:var(--primary)"><?= $equipo['estado_maestro'] ?></div></div>
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
                    <label class="label-sm">Responsable (Usuario o Correo Institucional)</label>
                    <div class="user-verify-group">
                        <input type="text" id="user_id" placeholder="Ej: guillermo.fonseca (o correo completo)" autocomplete="off">
                        <button type="button" onclick="verificarUsuario()" class="btn-verify">🔍 Verificar Identidad</button>
                    </div>
                    
                    <div id="userCard" class="user-card">
                        <h4 id="ldap_nombre"></h4>
                        <div id="ldap_info" style="font-size:0.9rem; color:#475569; line-height:1.5;"></div>
                    </div>
                </div>
            </div>

            <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>CONFIRMAR MOVIMIENTO Y GENERAR ACTA</button>
        </form>

        <script>
            // Lógica para filtrar lugares según la sede
            const lugaresData = <?= json_encode($lugares) ?>;
            
            function filtrarLugares() {
                const sedeSeleccionada = document.getElementById('selectSede').value;
                const selectLugar = document.getElementById('selectLugar');
                
                // Reiniciar select
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