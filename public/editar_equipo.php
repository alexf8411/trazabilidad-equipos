<?php
/**
 * public/editar_equipo.php
 * Edición Maestra de Activos (Admin y Recursos)
 * Incluye lógica de "Actualización en Cascada" para Seriales/Placas.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD: Solo Admin y Recursos
$roles_permitidos = ['Administrador', 'Recursos'];
if (!in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$id_equipo = $_GET['id'] ?? null;
$msg = "";

// 2. VALIDAR QUE LLEGUE UN ID
if (!$id_equipo) {
    header('Location: inventario.php');
    exit;
}

// 3. PROCESAR FORMULARIO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nuevo_serial = trim($_POST['serial']);
    $nueva_placa  = trim($_POST['placa']);
    $marca        = trim($_POST['marca']);
    $modelo       = trim($_POST['modelo']);
    $fecha_compra = $_POST['fecha_compra'];
    $modalidad    = $_POST['modalidad'];
    
    // Capturamos los valores originales (para saber si cambiaron)
    $serial_original = $_POST['serial_original'];
    $placa_original  = $_POST['placa_original'];

    try {
        $pdo->beginTransaction();

        // 1. DETECTOR DE CAMBIOS (Lógica de Auditoría)
        $cambios_detectados = [];
        
        // Comparamos dato por dato
        if ($nuevo_serial !== $serial_original) 
            $cambios_detectados[] = "Serial: '$serial_original' ➝ '$nuevo_serial'";
        
        if ($nueva_placa !== $placa_original) 
            $cambios_detectados[] = "Placa: '$placa_original' ➝ '$nueva_placa'";
            
        // (Podrías agregar más comparaciones aquí si quieres auditar marca/modelo también)

        // 2. ACTUALIZACIÓN MAESTRA (Lo que ya tenías)
        $sql = "UPDATE equipos SET 
                serial = ?, placa_ur = ?, marca = ?, modelo = ?, 
                fecha_compra = ?, modalidad = ? 
                WHERE id_equipo = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_serial, $nueva_placa, $marca, $modelo, $fecha_compra, $modalidad, $id_equipo]);

        // 3. ACTUALIZACIÓN EN CASCADA
        if ($nuevo_serial !== $serial_original) {
            $stmt_bit = $pdo->prepare("UPDATE bitacora SET serial_equipo = ? WHERE serial_equipo = ?");
            $stmt_bit->execute([$nuevo_serial, $serial_original]);
        }

        // 4. GUARDAR LOG DE AUDITORÍA (NUEVO PASO)
        if (count($cambios_detectados) > 0) {
            $resumen_cambios = implode(", ", $cambios_detectados);
            $ip_cliente = $_SERVER['REMOTE_ADDR'];
            $responsable = $_SESSION['usuario_id'] ?? 'Sistema';

            $sql_audit = "INSERT INTO auditoria_cambios (usuario_responsable, tipo_accion, referencia, detalles, ip_origen) 
                          VALUES (?, 'Edición Maestro', ?, ?, ?)";
            $stmt_audit = $pdo->prepare($sql_audit);
            $stmt_audit->execute([$responsable, "Placa: $nueva_placa", $resumen_cambios, $ip_cliente]);
        }

        $pdo->commit();
        $msg = "<div class='toast success'>✅ Datos actualizados y auditoría registrada.</div>";
        
        // Actualizar variables visuales
        $serial_original = $nuevo_serial;
        $placa_original = $nueva_placa;

    } catch (PDOException $e) {
        $pdo->rollBack();
        // ... (Manejo de errores igual que antes) ...
    }
}

// 4. CONSULTAR DATOS ACTUALES
$stmt = $pdo->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
$stmt->execute([$id_equipo]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    die("Equipo no encontrado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Equipo - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f0f2f5; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        h1 { color: var(--primary); border-bottom: 2px solid #ffc107; padding-bottom: 10px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        .btn-submit { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-submit:hover { background: #001f52; }
        .btn-cancel { display:inline-block; margin-top:10px; text-decoration:none; color: #666; font-size:0.9rem; }
        
        .alert-change { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; grid-column: 1 / -1; font-size: 0.85rem; border: 1px solid #ffeeba; }
        
        .toast { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>✏️ Editar Hoja de Vida</h1>
        <a href="inventario.php" class="btn-cancel">Cancelar y Volver</a>
    </div>

    <?= $msg ?>

    <form method="POST">
        <input type="hidden" name="serial_original" value="<?= htmlspecialchars($equipo['serial']) ?>">
        <input type="hidden" name="placa_original" value="<?= htmlspecialchars($equipo['placa_ur']) ?>">

        <div class="alert-change">
            ⚠️ <strong>Atención:</strong> Si modifica el Serial o la Placa, el sistema actualizará automáticamente todo el historial de bitácora asociado para mantener la trazabilidad.
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Placa UR</label>
                <input type="text" name="placa" value="<?= htmlspecialchars($equipo['placa_ur']) ?>" required>
            </div>
            <div class="form-group">
                <label>Serial Fabricante</label>
                <input type="text" name="serial" value="<?= htmlspecialchars($equipo['serial']) ?>" required>
            </div>

            <div class="form-group">
                <label>Marca</label>
                <select name="marca" required>
                    <option value="HP" <?= $equipo['marca']=='HP'?'selected':'' ?>>HP</option>
                    <option value="Lenovo" <?= $equipo['marca']=='Lenovo'?'selected':'' ?>>Lenovo</option>
                    <option value="Dell" <?= $equipo['marca']=='Dell'?'selected':'' ?>>Dell</option>
                    <option value="Apple" <?= $equipo['marca']=='Apple'?'selected':'' ?>>Apple</option>
                    <option value="Otro" <?= $equipo['marca']=='Otro'?'selected':'' ?>>Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Modelo</label>
                <input type="text" name="modelo" value="<?= htmlspecialchars($equipo['modelo']) ?>" required>
            </div>

            <div class="form-group">
                <label>Fecha Compra</label>
                <input type="date" name="fecha_compra" value="<?= $equipo['fecha_compra'] ?>" required>
            </div>
            <div class="form-group">
                <label>Modalidad</label>
                <select name="modalidad" required>
                    <option value="Propio" <?= $equipo['modalidad']=='Propio'?'selected':'' ?>>Propio</option>
                    <option value="Leasing" <?= $equipo['modalidad']=='Leasing'?'selected':'' ?>>Leasing</option>
                    <option value="Proyecto" <?= $equipo['modalidad']=='Proyecto'?'selected':'' ?>>Proyecto</option>
                </select>
            </div>

            <div style="grid-column: 1 / -1;">
                <button type="submit" class="btn-submit">Guardar Cambios</button>
            </div>
        </div>
    </form>
</div>

</body>
</html>