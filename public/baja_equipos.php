<?php
/**
 * public/baja_equipos.php
 * Módulo de Bajas Masivas con Generación de Acta Consolidada
 * Versión V2.0
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

$results = [];
$bajas_exitosas = []; // Array para guardar seriales procesados OK

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['seriales_raw'])) {
    $motivo = trim($_POST['motivo']);
    $tecnico = $_SESSION['nombre'];
    
    // Convertir texto a array
    $raw_data = $_POST['seriales_raw'];
    $lista_seriales = preg_split('/\r\n|\r|\n/', $raw_data);
    $lista_seriales = array_filter(array_map('trim', $lista_seriales));
    
    if (count($lista_seriales) > 0) {
        // Buscar ID de Bodega de Bajas (o crear lógica de lugar de disposición final)
        // Por defecto mandamos a 'Bodega de Tecnología' pero con estado 'Baja'
        $stmt_lugar = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $lugar_defecto = $stmt_lugar->fetch(PDO::FETCH_ASSOC);
        
        // ID de Lote único para agrupar este evento en el reporte
        $id_lote = date('YmdHis') . '-' . rand(100,999);

        foreach ($lista_seriales as $serial) {
            $serial = strtoupper($serial);
            try {
                $pdo->beginTransaction();
                
                // A. Verificar existencia
                $stmt_check = $pdo->prepare("SELECT estado_maestro, placa_ur, marca, modelo FROM equipos WHERE serial = ?");
                $stmt_check->execute([$serial]);
                $equipo = $stmt_check->fetch();
                
                if (!$equipo) {
                    throw new Exception("Serial no encontrado.");
                }
                if ($equipo['estado_maestro'] === 'Baja') {
                    throw new Exception("Ya estaba de Baja.");
                }

                // B. Actualizar Estado
                $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Baja' WHERE serial = ?");
                $stmt_upd->execute([$serial]);
                
                // C. Bitácora (Guardamos el motivo en 'hostname' o campo libre para referencia)
                $sql_bit = "INSERT INTO bitacora (
                                serial_equipo, id_lugar, sede, ubicacion,
                                tipo_evento, correo_responsable, tecnico_responsable,
                                hostname, fecha_evento
                            ) VALUES (?, ?, ?, ?, 'Baja', ?, ?, ?, NOW())";
                
                $stmt_b = $pdo->prepare($sql_bit);
                $stmt_b->execute([
                    $serial,
                    $lugar_defecto['id'],
                    $lugar_defecto['sede'],
                    'Disposición Final', // Ubicación Lógica
                    'Activos Fijos (Bajas)', // Responsable Lógico
                    $tecnico,
                    "LOTE:$id_lote | $motivo" // Usamos hostname para guardar referencia del lote y motivo
                ]);
                
                $pdo->commit();
                
                $results[] = ['serial' => $serial, 'status' => 'ok', 'msg' => "Baja OK (Placa: {$equipo['placa_ur']})"];
                $bajas_exitosas[] = $serial; // Guardar para el acta
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $results[] = ['serial' => $serial, 'status' => 'error', 'msg' => $e->getMessage()];
            }
        }
        
        // Si hubo al menos una baja exitosa, guardar en sesión para generar acta
        if (count($bajas_exitosas) > 0) {
            $_SESSION['acta_baja_seriales'] = $bajas_exitosas;
            $_SESSION['acta_baja_motivo'] = $motivo;
            $_SESSION['acta_baja_lote'] = $id_lote;
        }

    } else {
        $results[] = ['serial' => 'General', 'status' => 'error', 'msg' => 'Campo vacío.'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Baja de Activos | URTRACK</title>
    <style>
        :root { --danger: #dc3545; --success: #28a745; --bg: #f8f9fa; --white: #fff; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; }
        .main-card { background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid var(--danger); }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        h1 { margin: 0; color: var(--danger); font-size: 1.5rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #555; }
        textarea, input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        textarea { height: 150px; font-family: monospace; }
        .btn-submit { background: var(--danger); color: white; width: 100%; padding: 15px; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; }
        .btn-submit:hover { background: #b02a37; }
        
        /* Botón Acta */
        .btn-acta { 
            display: block; width: 100%; text-align: center; background: #0d6efd; color: white; 
            padding: 15px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 20px; 
        }
        .btn-acta:hover { background: #0b5ed7; }

        .results-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .results-table th { text-align: left; background: #eee; padding: 10px; border-bottom: 2px solid #ddd; }
        .results-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .status-ok { color: var(--success); font-weight: bold; }
        .status-error { color: var(--danger); font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <header>
                <h1>🗑️ Baja y Disposición Final</h1>
                <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
            </header>

            <form method="POST" onsubmit="return confirm('¿Está seguro de procesar estas bajas? Esta acción afecta el inventario contable.');">
                <div class="form-group">
                    <label>Justificación / Concepto Técnico *</label>
                    <input type="text" name="motivo" required placeholder="Ej: Equipo obsoleto - Acta de baja #2026-05" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Listado de Seriales (Uno por línea)</label>
                    <textarea name="seriales_raw" required placeholder="5CD12345X&#10;5CD67890Y"></textarea>
                    <small style="color:#777;">ℹ️ Puede copiar y pegar una columna de Excel.</small>
                </div>
                <button type="submit" class="btn-submit">PROCESAR BAJAS</button>
            </form>

            <?php if (!empty($results)): ?>
                <h3>Resultado de la operación:</h3>
                
                <?php if (!empty($bajas_exitosas)): ?>
                    <div style="background: #e7f1ff; padding: 15px; border-radius: 6px; border-left: 5px solid #0d6efd; margin-bottom: 20px;">
                        <strong>✅ ¡Proceso finalizado!</strong>
                        <p style="margin: 5px 0;">Se han dado de baja <?= count($bajas_exitosas) ?> equipos correctamente.</p>
                        <a href="generar_acta_baja.php" target="_blank" class="btn-acta">📄 DESCARGAR ACTA DE BAJA CONSOLIDADA (PDF)</a>
                    </div>
                <?php endif; ?>

                <table class="results-table">
                    <thead>
                        <tr><th>Serial</th><th>Estado</th><th>Detalle</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $res): ?>
                            <tr>
                                <td><?= htmlspecialchars($res['serial']) ?></td>
                                <td class="<?= $res['status'] == 'ok' ? 'status-ok' : 'status-error' ?>"><?= strtoupper($res['status']) ?></td>
                                <td><?= htmlspecialchars($res['msg']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>