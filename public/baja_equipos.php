<?php
/**
 * public/baja_equipos.php
 * Módulo de Bajas Masivas
 * Actualización V3.0: Redirección automática al Visor de Acta
 */
require_once '../core/db.php';
require_once '../core/session.php';

// CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['seriales_raw'])) {
    $motivo = trim($_POST['motivo']);
    $tecnico = $_SESSION['nombre'];
    
    // Procesar Seriales
    $raw_data = $_POST['seriales_raw'];
    $lista_seriales = preg_split('/\r\n|\r|\n/', $raw_data);
    $lista_seriales = array_filter(array_map('trim', $lista_seriales));
    
    $bajas_exitosas = [];
    $errores = [];

    if (count($lista_seriales) > 0) {
        // ID de Lote para agrupación
        $id_lote = date('YmdHis') . '-' . rand(100,999);
        
        // Buscar lugar de destino (Bodega/Basura)
        $stmt_lugar = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $lugar_defecto = $stmt_lugar->fetch(PDO::FETCH_ASSOC);

        foreach ($lista_seriales as $serial) {
            $serial = strtoupper($serial);
            try {
                $pdo->beginTransaction();
                
                // Validaciones
                $stmt_check = $pdo->prepare("SELECT estado_maestro FROM equipos WHERE serial = ?");
                $stmt_check->execute([$serial]);
                $equipo = $stmt_check->fetch();
                
                if (!$equipo) throw new Exception("No existe");
                if ($equipo['estado_maestro'] === 'Baja') throw new Exception("Ya es Baja");

                // Update
                $pdo->prepare("UPDATE equipos SET estado_maestro = 'Baja' WHERE serial = ?")->execute([$serial]);
                
                // Bitácora
                $sql_bit = "INSERT INTO bitacora (
                                serial_equipo, id_lugar, sede, ubicacion,
                                tipo_evento, correo_responsable, tecnico_responsable,
                                hostname, fecha_evento
                            ) VALUES (?, ?, ?, 'Disposición Final', 'Baja', 'Activos Fijos', ?, ?, NOW())";
                
                $pdo->prepare($sql_bit)->execute([
                    $serial, $lugar_defecto['id'], $lugar_defecto['sede'], 
                    $tecnico, "LOTE:$id_lote"
                ]);
                
                $pdo->commit();
                $bajas_exitosas[] = $serial;
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errores[] = "$serial: " . $e->getMessage();
            }
        }

        // REDIRECCIÓN SI HUBO ÉXITO
        if (count($bajas_exitosas) > 0) {
            $_SESSION['acta_baja_seriales'] = $bajas_exitosas;
            $_SESSION['acta_baja_motivo'] = $motivo;
            $_SESSION['acta_baja_lote'] = $id_lote;
            $_SESSION['acta_baja_errores'] = $errores; // Para mostrar advertencias si las hubo
            
            header("Location: generar_acta_baja.php");
            exit;
        } else {
            $error_msg = "❌ No se pudo procesar ninguna baja. Revise los seriales.";
        }

    } else {
        $error_msg = "❌ El campo de seriales está vacío.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Baja de Activos | URTRACK</title>
    <style>
        :root { --danger: #dc3545; --bg: #f8f9fa; --white: #fff; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .main-card { background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid var(--danger); }
        h1 { color: var(--danger); margin-top: 0; }
        textarea { width: 100%; height: 150px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-family: monospace; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 15px; }
        .btn-submit { background: var(--danger); color: white; width: 100%; padding: 15px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 1.1rem; }
        .btn-submit:hover { background: #b02a37; }
        .alert { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1>🗑️ Baja Masiva de Equipos</h1>
                <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
            </div>

            <?php if ($error_msg): ?>
                <div class="alert"><?= $error_msg ?></div>
            <?php endif; ?>

            <form method="POST" onsubmit="return confirm('⚠️ ¿CONFIRMA LA BAJA DEFINITIVA DE ESTOS EQUIPOS?');">
                <label><strong>Justificación Técnica (Acta / Ticket):</strong></label>
                <input type="text" name="motivo" required placeholder="Ej: Obsolescencia - Acta #2026-B01">

                <label><strong>Seriales (Copiar y Pegar desde Excel):</strong></label>
                <textarea name="seriales_raw" required placeholder="SERIAL1&#10;SERIAL2&#10;SERIAL3"></textarea>

                <br><br>
                <button type="submit" class="btn-submit">🚨 EJECUTAR BAJA Y GENERAR ACTA</button>
            </form>
        </div>
    </div>
</body>
</html>