<?php
require_once '../core/db.php';
require_once '../core/session.php';

// Control de Acceso
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

// Lógica de Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO bitacora (serial_equipo, sede, ubicacion, tipo_evento, correo_responsable, tecnico_responsable, hostname, fecha_evento) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], $_POST['sede'], $_POST['ubicacion'], 
            $_POST['tipo_evento'], $_POST['correo_resp'], 
            $_SESSION['nombre'], strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        // Redirigir para imprimir acta
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignación LDAP - URTRACK</title>
    <link rel="stylesheet" href="css/estilos.css"> <style>
        .ldap-verify { font-size: 0.85rem; margin-top: 5px; font-weight: bold; }
        .user-found { color: green; }
        .user-not-found { color: red; }
        .loading { color: orange; }
    </style>
</head>
<body>
<div class="card" style="max-width: 800px; margin: 40px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
    <h2 style="color: #002D72;">🚚 Asignación de Equipo (LDAP)</h2>
    
    <form method="GET" style="display:flex; gap:10px; margin-bottom: 20px;">
        <input type="text" name="criterio" placeholder="Placa o Serial" required style="flex:1; padding:10px;" value="<?= $_GET['criterio'] ?? '' ?>">
        <button type="submit" name="buscar" style="background:#002D72; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">Buscar</button>
    </form>

    <?php 
    if (isset($_GET['buscar'])) {
        $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ?");
        $stmt->execute([$_GET['criterio'], $_GET['criterio']]);
        $equipo = $stmt->fetch();
    }
    ?>

    <?php if ($equipo): ?>
    <form method="POST">
        <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
        
        <div style="background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:20px; border-left: 5px solid #002D72;">
            <strong>Equipo:</strong> <?= $equipo['marca'] ?> <?= $equipo['modelo'] ?> | <strong>Placa:</strong> <?= $equipo['placa_ur'] ?>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div>
                <label>Hostname *</label>
                <input type="text" name="hostname" required placeholder="Ej: PB-INV-01" style="width:100%; padding:10px;">
            </div>
            <div>
                <label>Tipo de Evento</label>
                <select name="tipo_evento" style="width:100%; padding:10px;">
                    <option value="Asignación">Asignación Directa</option>
                    <option value="Traslado">Traslado de Sede</option>
                </select>
            </div>
            <div>
                <label>Sede</label>
                <select name="sede" style="width:100%; padding:10px;">
                    <option value="Sede Norte">Sede Norte</option>
                    <option value="Sede Centro">Sede Centro</option>
                    <option value="Sede Sur">Sede Sur</option>
                </select>
            </div>
            <div>
                <label>Edificio / Ubicación</label>
                <input type="text" name="ubicacion" required placeholder="Ej: Torre A - Piso 4" style="width:100%; padding:10px;">
            </div>
            <div style="grid-column: span 2;">
                <label>Correo del Responsable (LDAP) *</label>
                <input type="email" id="correo_resp" name="correo_resp" required placeholder="usuario@urosario.edu.co" style="width:100%; padding:10px;">
                <div id="ldap_status" class="ldap-verify"></div>
            </div>
        </div>

        <button type="submit" name="confirmar" id="btn_guardar" disabled style="width:100%; padding:15px; margin-top:20px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">
            💾 Registrar Movimiento y Generar Acta
        </button>
    </form>

    <script>
    document.getElementById('correo_resp').addEventListener('blur', function() {
        const email = this.value;
        const statusDiv = document.getElementById('ldap_status');
        const btn = document.getElementById('btn_guardar');

        if(email.includes('@')) {
            statusDiv.innerHTML = '<span class="loading">🔍 Verificando en Directorio Activo...</span>';
            
            fetch(`../core/validar_usuario_ldap.php?email=${email}`)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        statusDiv.innerHTML = `<span class="user-found">✅ Usuario: ${data.nombre} (${data.departamento})</span>`;
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    } else {
                        statusDiv.innerHTML = '<span class="user-not-found">❌ Usuario no existe en LDAP. Verifique el correo.</span>';
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                    }
                });
        }
    });
    </script>
    <?php endif; ?>
</div>
</body>
</html>