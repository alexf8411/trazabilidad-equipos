<?php
/**
 * public/editar_equipo.php
 * Edición Maestra de Activos y Corrección de Último Evento
 * V3.0 - Soporte para desc_evento obligatorio
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD
$roles_permitidos = ['Administrador', 'Recursos'];
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$id_equipo = $_GET['id'] ?? null;
$msg = "";

if (!$id_equipo) {
    header('Location: inventario.php');
    exit;
}

// 2. PROCESAR FORMULARIO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Datos de la tabla EQUIPOS
    $nuevo_serial = trim($_POST['serial']);
    $nueva_placa  = trim($_POST['placa']);
    $marca        = trim($_POST['marca']);
    $modelo       = trim($_POST['modelo']);
    $fecha_compra = $_POST['fecha_compra'];
    $modalidad    = $_POST['modalidad'];
    
    // Datos de la tabla BITACORA (Último evento)
    $desc_evento  = trim($_POST['desc_evento']);
    $id_ultimo_evento = $_POST['id_ultimo_evento'] ?? null;

    // Valores originales para comparación
    $serial_original = $_POST['serial_original'];
    $placa_original  = $_POST['placa_original'];

    try {
        $pdo->beginTransaction();

        // A. DETECTOR DE CAMBIOS (Equipos)
        $cambios_detectados = [];
        
        if ($nuevo_serial !== $serial_original) 
            $cambios_detectados[] = "Serial: '$serial_original' ➝ '$nuevo_serial'";
        if ($nueva_placa !== $placa_original) 
            $cambios_detectados[] = "Placa: '$placa_original' ➝ '$nueva_placa'";
        if ($marca !== ($_POST['marca_original'] ?? ''))
            $cambios_detectados[] = "Marca: cambiada";
        if ($modelo !== ($_POST['modelo_original'] ?? ''))
            $cambios_detectados[] = "Modelo: cambiado";
            
        // B. ACTUALIZAR TABLA EQUIPOS
        $sql = "UPDATE equipos SET 
                serial = ?, placa_ur = ?, marca = ?, modelo = ?, 
                fecha_compra = ?, modalidad = ? 
                WHERE id_equipo = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_serial, $nueva_placa, $marca, $modelo, $fecha_compra, $modalidad, $id_equipo]);

        // C. ACTUALIZAR DESCRIPCIÓN DEL ÚLTIMO EVENTO (Si existe y cambió)
        if ($id_ultimo_evento) {
            // Verificar si la descripción cambió
            $desc_original = $_POST['desc_evento_original'] ?? '';
            if ($desc_evento !== $desc_original) {
                $sql_bit = "UPDATE bitacora SET desc_evento = ? WHERE id_evento = ?";
                $pdo->prepare($sql_bit)->execute([$desc_evento, $id_ultimo_evento]);
                $cambios_detectados[] = "Nota Bitácora: Editada";
            }
        }

        // D. GUARDAR LOG DE AUDITORÍA
        if (!empty($cambios_detectados)) {
            $ip_cliente = $_SERVER['REMOTE_ADDR'];
            
            // Capturar datos del usuario desde sesión
            $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
            $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
            $usuario_rol    = $_SESSION['rol']        ?? 'Recursos';
            
            // Construir valores anterior y nuevo estructurados
            $cambios_array = [];
            foreach ($cambios_detectados as $cambio) {
                $cambios_array[] = $cambio;
            }
            
            $valor_anterior = "Placa: $placa_original, Serial: $serial_original";
            $valor_nuevo    = "Placa: $nueva_placa, Serial: $nuevo_serial";

            $sql_audit = "INSERT INTO auditoria_cambios 
                (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                 tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                VALUES (NOW(), ?, ?, ?, ?, 'EDICION_EQUIPO', 'equipos', ?, ?, ?)";
            
            $stmt_audit = $pdo->prepare($sql_audit);
            $stmt_audit->execute([
                $usuario_ldap,
                $usuario_nombre,
                $usuario_rol,
                $ip_cliente,
                "Equipo: $nueva_placa",
                $valor_anterior,
                $valor_nuevo
            ]);
        }

        $pdo->commit();
        header("Location: inventario.php?status=updated&p=" . urlencode($nueva_placa));
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() == '23000') {
            $msg = "<div class='alert error'>⚠️ Error: La placa o serial ya existen.</div>";
        } else {
            $msg = "<div class='alert error'>❌ Error técnico: " . $e->getMessage() . "</div>";
        }
    }
}

// 3. CONSULTAR DATOS (JOIN para traer el último evento)
// Usamos una subconsulta simple para obtener el último evento de este equipo
$sql_load = "SELECT e.*, 
             (SELECT id_evento FROM bitacora WHERE serial_equipo = e.serial ORDER BY id_evento DESC LIMIT 1) as id_ultimo_evento,
             (SELECT desc_evento FROM bitacora WHERE serial_equipo = e.serial ORDER BY id_evento DESC LIMIT 1) as ultimo_desc_evento
             FROM equipos e 
             WHERE e.id_equipo = ?";

$stmt = $pdo->prepare($sql_load);
$stmt->execute([$id_equipo]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) die("Equipo no encontrado.");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Equipo - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f0f2f5; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1 { color: var(--primary); border-bottom: 2px solid #ffc107; padding-bottom: 10px; margin-top: 0; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .full-width { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        textarea { resize: vertical; min-height: 80px; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; font-size: 1rem; }
        .btn-submit:hover { background: #001f52; }
        .btn-cancel { text-decoration: none; color: #666; font-size: 0.9rem; font-weight: 500; }
        .alert-change { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 10px; border: 1px solid #ffeeba; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid; }
        .error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .section-title { margin-top: 20px; margin-bottom: 10px; color: #666; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1>✏️ Editar Hoja de Vida</h1>
        <a href="inventario.php" class="btn-cancel">⬅ Volver al Inventario</a>
    </div>

    <?= $msg ?>

    <form method="POST">
        <input type="hidden" name="serial_original" value="<?= htmlspecialchars($equipo['serial']) ?>">
        <input type="hidden" name="placa_original" value="<?= htmlspecialchars($equipo['placa_ur']) ?>">
        <input type="hidden" name="marca_original" value="<?= htmlspecialchars($equipo['marca']) ?>">
        <input type="hidden" name="modelo_original" value="<?= htmlspecialchars($equipo['modelo']) ?>">
        
        <input type="hidden" name="id_ultimo_evento" value="<?= $equipo['id_ultimo_evento'] ?>">
        <input type="hidden" name="desc_evento_original" value="<?= htmlspecialchars($equipo['ultimo_desc_evento'] ?? '') ?>">

        <div class="alert-change full-width">
            ⚠️ <strong>Control de Cambios:</strong> Cualquier modificación quedará registrada en la auditoría con tu usuario y dirección IP.
        </div>

        <div class="form-grid">
            <div class="full-width section-title">Datos del Activo</div>

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
                    <?php 
                    $marcas = ['HP', 'Lenovo', 'Dell', 'Apple', 'Asus', 'Microsoft', 'Otro'];
                    foreach($marcas as $m): ?>
                        <option value="<?= $m ?>" <?= $equipo['marca']==$m?'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
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

            <div class="full-width section-title">Corrección de Último Evento (Bitácora)</div>
            
            <div class="form-group full-width">
                <label>Descripción / Observaciones del último movimiento</label>
                <textarea name="desc_evento" required placeholder="Detalle del evento..."><?= htmlspecialchars($equipo['ultimo_desc_evento'] ?? 'Sin observaciones') ?></textarea>
                <small style="color:#666;">ℹ️ Modifique esto solo si necesita corregir información del último estado registrado.</small>
            </div>

            <div class="full-width">
                <button type="submit" class="btn-submit">💾 Guardar Cambios y Registrar Auditoría</button>
            </div>
        </div>
    </form>
</div>

</body>
</html>