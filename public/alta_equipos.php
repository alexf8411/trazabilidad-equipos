<?php
/**
 * public/alta_equipos.php
 * Módulo de Registro Maestro (Recursos)
 * Implementa Transacción Atómica (Equipo + Bitácora)
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO (RBAC)
// Solo permitimos Administrador y Recursos. Soporte es expulsado.
$roles_permitidos = ['Administrador', 'Recursos'];
if (!in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$msg = "";

// 2. PROCESAMIENTO DEL FORMULARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitización básica
    $placa = trim($_POST['placa']);
    $serial = trim($_POST['serial']);
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $modalidad = $_POST['modalidad'];
    $fecha_compra = $_POST['fecha_compra'];
    
    // Datos automáticos para la bitácora
    $usuario_responsable = $_SESSION['correo']; // El técnico logueado es quien hace el ingreso
    $fecha_evento = date('Y-m-d H:i:s'); // Hora actual del servidor (NTP)

    try {
        // INICIO DE TRANSACCIÓN ATÓMICA
        $pdo->beginTransaction();

        // A. Insertar en tabla MAESTRA (equipos)
        $sql_equipo = "INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Alta')";
        $stmt = $pdo->prepare($sql_equipo);
        $stmt->execute([$placa, $serial, $marca, $modelo, $fecha_compra, $modalidad]);
        
        // Obtener el ID del equipo recién creado (necesario si usaramos ID numérico en bitácora, 
        // pero por regla de negocio usamos PLACA o SERIAL como vínculo lógico).
        
        // B. Insertar en tabla TRANSACCIONAL (bitacora)
        // El primer evento siempre es 'Ingreso' y va a 'Bodega de Tecnología' (Sede Única)
        
        // B.1 Buscar ID de la Bodega de Tecnología (Sede Única)
        $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
        $stmt_bodega->execute();
        $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

        if (!$bodega) {
            throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología' en el catálogo de lugares.");
        }

        // B.2 Insertar Evento
        $sql_bitacora = "INSERT INTO bitacora (
                            serial_equipo, id_lugar, sede, ubicacion, 
                            tipo_evento, correo_responsable, fecha_evento, 
                            tecnico_responsable, hostname
                         ) VALUES (?, ?, ?, ?, 'Ingreso', ?, ?, ?, 'PENDIENTE')";
        
        $stmt_b = $pdo->prepare($sql_bitacora);
        $stmt_b->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'],
            'Bodega de TI', // El responsable inicial es el área de TI
            $fecha_evento,
            $_SESSION['nombre'] // Nombre del técnico que registra
        ]);

        // Si todo salió bien, CONFIRMAR CAMBIOS
        $pdo->commit();
        
        // Patrón Post-Redirect-Get
        header("Location: alta_equipos.php?status=success&p=$placa");
        exit;

    } catch (PDOException $e) {
        // Si algo falla, REVERTIR TODO (No se crea ni el equipo ni el log)
        $pdo->rollBack();
        
        if ($e->getCode() == '23000') {
            $msg = "<div class='toast error'>⚠️ Error: La Placa o el Serial ya existen en el sistema.</div>";
        } else {
            $msg = "<div class='toast error'>❌ Error del Sistema: " . $e->getMessage() . "</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='toast error'>❌ " . $e->getMessage() . "</div>";
    }
}

// Mensaje de éxito tras redirección
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $placa_creada = htmlspecialchars($_GET['p']);
    $msg = "<div class='toast success'>✅ Equipo <b>$placa_creada</b> ingresado correctamente a Bodega.</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alta de Equipos - URTRACK</title>
    <style>
        /* Reutilizando estilos de admin_lugares para consistencia */
        :root { --primary: #002D72; --accent: #28a745; --bg: #f0f2f5; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        
        .container { max-width: 800px; margin: 0 auto; background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        header { border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; color: var(--primary); font-size: 1.5rem; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #555; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input:focus, select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(0,45,114,0.1); }
        
        .full-width { grid-column: 1 / -1; }
        
        .btn-submit { background: var(--primary); color: white; width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #001f52; }
        
        .toast { padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid; }
        .success { background: #d4edda; color: #155724; border-color: #28a745; }
        .error { background: #f8d7da; color: #721c24; border-color: #dc3545; }

        .info-box { background: #e9ecef; padding: 10px; border-radius: 4px; font-size: 0.85rem; color: #495057; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>➕ Registro Maestro de Equipos</h1>
        <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    </header>

    <?= $msg ?>

    <form method="POST">
        <div class="form-grid">
            
            <div class="form-group">
                <label>Placa UR *</label>
                <input type="text" name="placa" placeholder="Ej: 123456" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Serial del Fabricante *</label>
                <input type="text" name="serial" placeholder="Ej: 5CD1234X..." required autocomplete="off">
            </div>

            <div class="form-group">
                <label>Marca *</label>
                <select name="marca" required>
                    <option value="">-- Seleccionar --</option>
                    <option value="HP">HP</option>
                    <option value="Lenovo">Lenovo</option>
                    <option value="Dell">Dell</option>
                    <option value="Apple">Apple</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Modelo *</label>
                <input type="text" name="modelo" placeholder="Ej: ProBook 440 G8" required>
            </div>

            <div class="form-group">
                <label>Fecha de Compra *</label>
                <input type="date" name="fecha_compra" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Modalidad de Adquisición *</label>
                <select name="modalidad" required>
                    <option value="Propio">Propio (Compra Directa)</option>
                    <option value="Leasing">Leasing (Arrendamiento)</option>
                    <option value="Proyecto">Proyecto de Investigación</option>
                </select>
            </div>

            <div class="full-width info-box">
                <strong>ℹ️ Nota de Sistema:</strong> Al registrar este equipo, se asignará automáticamente a la 
                <u>Bodega de Tecnología</u> con estado 'Alta'.
            </div>

            <div class="full-width">
                <button type="submit" class="btn-submit">💾 Guardar e Ingresar a Inventario</button>
            </div>
        </div>
    </form>
</div>

</body>
</html>