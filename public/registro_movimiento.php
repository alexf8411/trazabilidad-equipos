<?php
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO (Admin, Recursos, Soporte)
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

// 2. OBTENER CATÁLOGO DE LUGARES PARA EL FORMULARIO
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// 3. BUSCADOR
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipo) $msg = "<div class='alert error'>❌ Equipo no encontrado.</div>";
}

// 4. GUARDAR MOVIMIENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtenemos los nombres del lugar seleccionado
        $id_lugar = $_POST['id_lugar'];
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$id_lugar]);
        $lugar_info = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, tecnico_responsable, hostname, fecha_evento) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], $id_lugar, $lugar_info['sede'], $lugar_info['nombre'],
            $_POST['tipo_evento'], $_POST['correo_resp'], $_SESSION['nombre'], strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>❌ Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 20px; }
        .card { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        input, select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; width: 100%; box-sizing: border-box; }
        label { font-weight: bold; font-size: 0.9rem; color: #555; display: block; margin-bottom: 5px; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .btn-save { background: #28a745; color: white; border: none; padding: 15px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 20px; opacity: 0.6; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; }
        .ldap-status { font-size: 0.8rem; font-weight: bold; margin-top: 5px; }
    </style>
</head>
<body>

<div class="card">
    <h2 style="color: var(--primary); margin-top:0;">🚚 Asignación de Activo</h2>
    <?= $msg ?>

    <form method="GET" style="display:flex; gap:10px;">
        <input type="text" name="criterio" placeholder="Escriba Placa o Serial..." value="<?= $_GET['criterio'] ?? '' ?>" required autofocus>
        <button type="submit" name="buscar" class="btn-search">Buscar</button>
    </form>

    <?php if ($equipo): ?>
    <form method="POST" id="formAsignacion">
        <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
        
        <div style="background:#eef2f7; padding:15px; border-radius:8px; margin-top:20px; border-left:5px solid var(--primary);">
            <strong>Equipo:</strong> <?= $equipo['marca'] ?> <?= $equipo['modelo'] ?> | <strong>Placa:</strong> <?= $equipo['placa_ur'] ?>
        </div>

        <div class="form-grid">
            <div>
                <label>Hostname del Equipo *</label>
                <input type="text" name="hostname" required placeholder="Ej: PB-SOP-L01">
            </div>
            <div>
                <label>Tipo de Evento</label>
                <select name="tipo_evento">
                    <option value="Asignación">Asignación Directa</option>
                    <option value="Traslado">Traslado Interno</option>
                    <option value="Préstamo">Préstamo</option>
                </select>
            </div>
            
            <div>
                <label>Sede Principal *</label>
                <select id="selectSede" required onchange="filtrarLugares()">
                    <option value="">-- Seleccionar --</option>
                    <?php 
                    $sedes_unicas = array_unique(array_column($lugares, 'sede'));
                    foreach($sedes_unicas as $s) echo "<option value='$s'>$s</option>";
                    ?>
                </select>
            </div>
            <div>
                <label>Edificio / Ubicación Exacta *</label>
                <select id="selectLugar" name="id_lugar" required disabled>
                    <option value="">-- Primero elija sede --</option>
                </select>
            </div>

            <div style="grid-column: span 2;">
                <label>Correo Responsable (Validación LDAP) *</label>
                <input type="email" id="correo_resp" name="correo_resp" required placeholder="nombre.apellido@urosario.edu.co">
                <div id="ldap_msg" class="ldap-status"></div>
            </div>
        </div>

        <button type="submit" name="confirmar" id="btnSubmit" class="btn-save" disabled>💾 Registrar y Generar Acta PDF</button>
    </form>

    <script>
    // 1. LÓGICA DE LUGARES DINÁMICOS
    const lugaresData = <?= json_encode($lugares) ?>;
    
    function filtrarLugares() {
        const sedeSel = document.getElementById('selectSede').value;
        const lugarSel = document.getElementById('selectLugar');
        
        lugarSel.innerHTML = '<option value="">-- Seleccionar Edificio --</option>';
        const filtrados = lugaresData.filter(l => l.sede === sedeSel);
        
        filtrados.forEach(l => {
            lugarSel.innerHTML += `<option value="${l.id}">${l.nombre}</option>`;
        });
        lugarSel.disabled = false;
    }

    // 2. LÓGICA DE VALIDACIÓN LDAP
    document.getElementById('correo_resp').addEventListener('blur', function() {
        const email = this.value;
        const msg = document.getElementById('ldap_msg');
        const btn = document.getElementById('btnSubmit');

        if(email.includes('@')) {
            msg.innerHTML = "🔍 Validando en Directorio Activo...";
            msg.style.color = "orange";

            fetch(`../core/validar_usuario_ldap.php?email=${email}`)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        msg.innerHTML = `✅ Usuario: ${data.nombre} (${data.departamento})`;
                        msg.style.color = "green";
                        btn.disabled = false;
                        btn.style.opacity = "1";
                    } else {
                        msg.innerHTML = "❌ El usuario no existe en el sistema de la Universidad.";
                        msg.style.color = "red";
                        btn.disabled = true;
                        btn.style.opacity = "0.6";
                    }
                });
        }
    });
    </script>
    <?php endif; ?>
</div>

</body>
</html>