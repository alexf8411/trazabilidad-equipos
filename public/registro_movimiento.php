<?php
/**
 * public/registro_movimiento.php
 * Formulario de Asignación por ID de Usuario LDAP
 */
require_once '../core/db.php';
require_once '../core/session.php';

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipo) $msg = "<div class='alert error'>Equipo no localizado.</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, tecnico_responsable, hostname, fecha_evento) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], $_POST['id_lugar'], $l['sede'], $l['nombre'],
            $_POST['tipo_evento'], $_POST['correo_resp_real'], $_SESSION['nombre'], strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación | URTRACK</title>
    <style>
        :root { --primary: #002D72; --success: #22c55e; --bg: #f8fafc; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; justify-content: center; padding: 40px 20px; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid var(--border); width: 100%; max-width: 800px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .search-section { display: flex; gap: 12px; margin-bottom: 25px; background: #f1f5f9; padding: 15px; border-radius: 12px; }
        input, select { padding: 12px; border: 1px solid var(--border); border-radius: 8px; width: 100%; outline: none; box-sizing: border-box; }
        .info-pill { background: #f1f5f9; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; border-left: 4px solid var(--primary); }
        .user-card { background: #f8fafc; border: 1px dashed var(--primary); padding: 15px; border-radius: 10px; margin-top: 15px; display: none; }
        .btn-submit { background: var(--success); color: white; border: none; padding: 16px; border-radius: 8px; width: 100%; font-weight: 700; cursor: pointer; margin-top: 25px; }
        .btn-submit:disabled { opacity: 0.5; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .error { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2>Asignación de Activo</h2>
        <a href="dashboard.php" style="text-decoration:none; color:#64748b; font-size:0.9rem;">← Dashboard</a>
    </div>

    <?= $msg ?>

    <form method="GET" class="search-section">
        <input type="text" name="criterio" placeholder="Placa UR o Serial..." value="<?= $_GET['criterio'] ?? '' ?>" required autofocus>
        <button type="submit" name="buscar" style="background:var(--primary); color:white; border:none; padding:0 25px; border-radius:8px; cursor:pointer;">Buscar</button>
    </form>

    <?php if ($equipo): ?>
        <div class="info-pill">
            <div><small>ACTIVO</small><br><strong><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></strong></div>
            <div><small>PLACA</small><br><strong><?= $equipo['placa_ur'] ?></strong></div>
        </div>

        <form method="POST">
            <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
            <input type="hidden" name="correo_resp_real" id="correo_resp_real">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div><label>Hostname</label><input type="text" name="hostname" required placeholder="Nombre de red"></div>
                <div><label>Evento</label><select name="tipo_evento"><option value="Asignación">Asignación</option><option value="Traslado">Traslado</option></select></div>
                <div><label>Sede</label><select id="selectSede" required onchange="filtrarLugares()"><option value="">-- Elija Sede --</option>
                    <?php $sedes = array_unique(array_column($lugares, 'sede')); foreach($sedes as $s) echo "<option value='$s'>$s</option>"; ?></select></div>
                <div><label>Ubicación</label><select id="selectLugar" name="id_lugar" required disabled><option value="">-- Elija Ubicación --</option></select></div>

                <div style="grid-column: span 2;">
                    <label>Usuario Institucional Responsable</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="user_id" placeholder="ej. guillermo.fonseca" style="flex:1;">
                        <button type="button" onclick="verificarUsuario()" style="background:#e2e8f0; border:none; padding:10px 15px; border-radius:8px; cursor:pointer; font-weight:600;">Verificar</button>
                    </div>
                    
                    <div id="userCard" class="user-card">
                        <h4 id="ldap_nombre" style="margin:0; color:var(--primary);"></h4>
                        <p id="ldap_info" style="margin:4px 0 0 0; color:#64748b; font-size:0.85rem;"></p>
                    </div>
                </div>
            </div>

            <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>CONFIRMAR Y GENERAR ACTA</button>
        </form>

        <script>
            const lugaresData = <?= json_encode($lugares) ?>;
            function filtrarLugares() {
                const sede = document.getElementById('selectSede').value;
                const el = document.getElementById('selectLugar');
                el.innerHTML = '<option value="">-- Seleccionar --</option>';
                lugaresData.filter(l => l.sede === sede).forEach(l => { el.innerHTML += `<option value="${l.id}">${l.nombre}</option>`; });
                el.disabled = false;
            }

            function verificarUsuario() {
                const user = document.getElementById('user_id').value;
                const card = document.getElementById('userCard');
                const btn = document.getElementById('btnSubmit');

                if (!user) return alert("Ingrese el usuario.");

                fetch(`../core/validar_usuario_ldap.php?usuario=${user}`)
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 'success') {
                            card.style.display = 'block';
                            document.getElementById('ldap_nombre').innerText = data.nombre;
                            document.getElementById('ldap_info').innerText = `${data.correo} | ${data.departamento}`;
                            document.getElementById('correo_resp_real').value = data.correo;
                            btn.disabled = false;
                        } else {
                            alert("❌ Usuario no encontrado.");
                            card.style.display = 'none';
                            btn.disabled = true;
                        }
                    });
            }
        </script>
    <?php endif; ?>
</div>
</body>
</html>