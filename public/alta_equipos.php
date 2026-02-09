<?php
/**
 * public/alta_equipos.php
 * Módulo de Registro Maestro (Recursos) - Versión V1.3
 * Ajuste: Placa UR y Hostname heredan el valor del Serial automáticamente.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
$roles_permitidos = ['Administrador', 'Recursos'];
if (!in_array($_SESSION['rol'], $roles_permitidos)) {
    header('Location: dashboard.php');
    exit;
}

$msg = "";

// 2. PROCESAMIENTO DEL FORMULARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitización
    $serial = trim($_POST['serial']);
    
    // LÓGICA DE NEGOCIO: Placa y Hostname = Serial
    $placa = $serial; 
    
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $vida_util = (int) $_POST['vida_util'];
    $precio = (float) $_POST['precio'];
    $modalidad = $_POST['modalidad'];
    $fecha_compra = $_POST['fecha_compra'];
    $fecha_evento = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // A. INSERTAR EN EQUIPOS
        // Placa UR recibe el mismo valor que el Serial
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
        
        // B. OBTENER BODEGA
        $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
        $stmt_bodega->execute();
        $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

        if (!$bodega) {
            throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología' en el catálogo de lugares.");
        }

        // C. INSERTAR EN BITÁCORA
        // Hostname recibe el mismo valor que el Serial
        $sql_bitacora = "INSERT INTO bitacora (
                            serial_equipo, id_lugar, sede, ubicacion, 
                            tipo_evento, correo_responsable, fecha_evento, 
                            tecnico_responsable, hostname
                          ) VALUES (?, ?, ?, ?, 'Ingreso', ?, ?, ?, ?)";
        
        $stmt_b = $pdo->prepare($sql_bitacora);
        $stmt_b->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'],
            'Bodega de TI',
            $fecha_evento,
            $_SESSION['nombre'],
            $serial 
        ]);

        $pdo->commit();
        // Redireccionamos mostrando el Serial como referencia
        header("Location: alta_equipos.php?status=success&s=$serial");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == '23000') {
            $msg = "<div class='toast error'>⚠️ Error: El Serial ya está registrado en el sistema.</div>";
        } else {
            $msg = "<div class='toast error'>❌ Error SQL: " . $e->getMessage() . "</div>";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='toast error'>❌ " . $e->getMessage() . "</div>";
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $serial_creado = htmlspecialchars($_GET['s']);
    $msg = "<div class='toast success'>✅ Equipo con Serial <b>$serial_creado</b> ingresado correctamente.</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alta de Equipos - URTRACK</title>
    <style>
        :root { --primary: #002D72; --accent: #28a745; --bg: #f0f2f5; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 850px; margin: 0 auto; }
        .main-card { background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        header { border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; color: var(--primary); font-size: 1.5rem; }
        .bulk-banner { background: #e7f1ff; border: 1px solid #b6d4fe; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .btn-bulk { background: #0d6efd; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; font-size: 0.9rem; }
        .btn-bulk:hover { background: #0b5ed7; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #555; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .full-width { grid-column: 1 / -1; }
        .btn-submit { background: var(--primary); color: white; width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 1rem; font-weight: bold; cursor: pointer; }
        .toast { padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 5px solid; }
        .success { background: #d4edda; color: #155724; border-color: #28a745; }
        .error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .info-box { background: #e9ecef; padding: 10px; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="bulk-banner">
        <div>
            <strong>¿Tienes muchos equipos?</strong>
            <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">Sube un archivo CSV y registra cientos de activos en segundos.</p>
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
                    <input type="text" name="serial" required placeholder="Serial S/N">
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
                    <input type="text" name="modelo" required placeholder="Ej: ProBook 440">
                </div>
                <div class="form-group">
                    <label>Fecha de Compra *</label>
                    <input type="date" name="fecha_compra" required>
                </div>

                <div class="form-group">
                    <label>Vida Útil (Años) *</label>
                    <input type="number" name="vida_util" min="1" max="20" required placeholder="Ej: 5">
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
                    ℹ️ <strong>Nota:</strong> La Placa UR y el Hostname se asignan automáticamente; el equipo ingresará a <strong>Bodega de Tecnología</strong>.
                </div>
                
                <div class="full-width">
                    <button type="submit" class="btn-submit">💾 Guardar Equipo Único</button>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>