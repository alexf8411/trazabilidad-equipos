<?php
/**
 * public/alta_equipos.php
 * Módulo de Registro Maestro (Recursos) - Versión V3.2
 * Ajustes Solicitados:
 * 1. El responsable inicial es el usuario autenticado (no la bodega).
 * 2. Campo 'desc_evento' se captura como 'Orden de Compra' (Prefijo OD:).
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
$roles_permitidos = ['Administrador', 'Recursos'];
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$msg = "";

// 2. PROCESAMIENTO DEL FORMULARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitización de Inputs
    $serial = trim($_POST['serial']);
    $placa  = trim($_POST['placa']); 
    
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $vida_util = (int) $_POST['vida_util'];
    $precio = (float) $_POST['precio'];
    $modalidad = $_POST['modalidad'];
    $fecha_compra = $_POST['fecha_compra'];
    $fecha_evento = date('Y-m-d H:i:s');

    // --- LÓGICA DE NEGOCIO PERSONALIZADA ---
    
    // 1. Capturar Orden de Compra para la descripción obligatoria
    $orden_compra_input = trim($_POST['orden_compra']);
    $desc_evento_final  = "OD: " . $orden_compra_input; 

    // 2. Identificar al Usuario Autenticado
    // Usamos 'usuario_id' (login) o 'nombre' como fallback
    $usuario_autenticado = $_SESSION['usuario_id'] ?? $_SESSION['nombre'];
    $tecnico_nombre      = $_SESSION['nombre'];

    try {
        $pdo->beginTransaction();

        // A. INSERTAR EN EQUIPOS (Registro Maestro)
        $sql_equipo = "INSERT INTO equipos (
                            placa_ur, serial, marca, modelo, 
                            vida_util, precio, 
                            fecha_compra, modalidad, estado_maestro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')";
        
        $stmt = $pdo->prepare($sql_equipo);
        $stmt->execute([
            $placa, $serial, $marca, $modelo, 
            $vida_util, $precio, 
            $fecha_compra, $modalidad
        ]);
        
        // B. OBTENER UBICACIÓN FÍSICA (Bodega)
        $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $stmt_bodega->execute();
        $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

        if (!$bodega) {
            // Fallback de seguridad por si no existe la bodega en DB
            $bodega = ['id' => 1, 'sede' => 'Centro', 'nombre' => 'Bodega General'];
        }

        // C. INSERTAR EN BITÁCORA (Evento de Alta)
        $sql_bitacora = "INSERT INTO bitacora (
                            serial_equipo, id_lugar, sede, ubicacion, 
                            tipo_evento, correo_responsable, fecha_evento, 
                            tecnico_responsable, hostname, desc_evento, check_sccm
                          ) VALUES (?, ?, ?, ?, 'Alta', ?, ?, ?, ?, ?, 0)";
        
        $stmt_b = $pdo->prepare($sql_bitacora);
        $stmt_b->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'],
            // REQUERIMIENTO 1: Responsable es el usuario autenticado
            $usuario_autenticado, 
            $fecha_evento,
            $tecnico_nombre,
            $serial, // Hostname inicial = Serial
            // REQUERIMIENTO 2: Descripción con formato "OD: valor"
            $desc_evento_final, 
            0 // check_sccm default
        ]);

        $pdo->commit();
        header("Location: alta_equipos.php?status=success&p=$placa");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e->getCode() == '23000') {
            $msg = "<div class='toast error'>⚠️ Error: El <b>Serial</b> o la <b>Placa UR</b> ya están registrados.</div>";
        } else {
            $msg = "<div class='toast error'>❌ Error SQL: " . $e->getMessage() . "</div>";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='toast error'>❌ " . $e->getMessage() . "</div>";
    }
}

// Mensaje de éxito visual
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $placa_creada = htmlspecialchars($_GET['p']);
    $msg = "<div class='toast success'>✅ Equipo <b>$placa_creada</b> ingresado correctamente a tu cargo.</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de Equipos - URTRACK</title>
    <style>
        :root { --primary: #002D72; --accent: #28a745; --bg: #f0f2f5; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; margin: 0; }
        
        .container { max-width: 850px; margin: 0 auto; width: 100%; }
        
        .main-card { background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        header { border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        
        h1 { margin: 0; color: var(--primary); font-size: 1.5rem; }
        
        .bulk-banner { background: #e7f1ff; border: 1px solid #b6d4fe; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        
        .btn-bulk { background: #0d6efd; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; font-size: 0.9rem; white-space: nowrap; }
        .btn-bulk:hover { background: #0b5ed7; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #555; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        
        .full-width { grid-column: 1 / -1; }
        
        .btn-submit { background: var(--primary); color: white; width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 1rem; font-weight: bold; cursor: pointer; margin-top: 10px; }
        
        .toast { padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid; }
        .success { background: #d4edda; color: #155724; border-color: #28a745; }
        .error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .info-box { background: #e9ecef; padding: 10px; border-radius: 4px; font-size: 0.85rem; }

        @media (max-width: 768px) {
            body { padding: 15px; }
            .main-card { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .bulk-banner { flex-direction: column; text-align: center; }
            .btn-bulk { width: 100%; text-align: center; box-sizing: border-box; }
            header { flex-direction: column; align-items: flex-start; }
            header a { align-self: flex-end; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="bulk-banner">
        <div>
            <strong>¿Tienes muchos equipos?</strong>
            <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">Sube un archivo CSV con las Placas y Seriales.</p>
        </div>
        <a href="importar_csv.php" class="btn-bulk">📥 Importación Masiva</a>
    </div>

    <div class="main-card">
        <header>
            <h1>➕ Registro Individual</h1>
            <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
        </header>

        <?= $msg ?>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Serial Fabricante *</label>
                    <input type="text" name="serial" required placeholder="Ej: 5CD2340JL" autofocus>
                </div>
                
                <div class="form-group">
                    <label>Placa Inventario UR *</label>
                    <input type="text" name="placa" required placeholder="Ej: 004589">
                </div>

                <div class="form-group">
                    <label>Marca *</label>
                    <select name="marca" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="HP">HP</option>
                        <option value="Lenovo">Lenovo</option>
                        <option value="Dell">Dell</option>
                        <option value="Apple">Apple</option>
                        <option value="Asus">Asus</option>
                        <option value="Microsoft">Microsoft</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Modelo *</label>
                    <input type="text" name="modelo" required placeholder="Ej: ProBook 440">
                </div>

                <div class="form-group">
                    <label>Orden de Compra *</label>
                    <input type="text" name="orden_compra" required placeholder="Ej: 2026-9988-OC">
                    <small style="color:#666; font-size:0.8rem;">Se guardará con prefijo OD:</small>
                </div>

                <div class="form-group">
                    <label>Fecha de Compra *</label>
                    <input type="date" name="fecha_compra" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Vida Útil (Años) *</label>
                    <input type="number" name="vida_util" min="1" max="20" required placeholder="Ej: 5" value="5">
                </div>
                <div class="form-group">
                    <label>Precio (COP) *</label>
                    <input type="number" name="precio" min="0" step="0.01" required placeholder="Ej: 4500000">
                </div>
                
                <div class="form-group full-width">
                    <label>Modalidad *</label>
                    <select name="modalidad" required>
                        <option value="Propio">Propio</option>
                        <option value="Leasing">Leasing</option>
                        <option value="Proyecto">Proyecto</option>
                    </select>
                </div>

                <div class="full-width info-box">
                    ℹ️ <strong>Nota:</strong> El equipo ingresará a <strong>Bodega de Tecnología</strong> bajo tu responsabilidad (<?= htmlspecialchars($_SESSION['usuario_id'] ?? $_SESSION['nombre']) ?>).
                </div>
                
                <div class="full-width">
                    <button type="submit" class="btn-submit">💾 Guardar Equipo</button>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>