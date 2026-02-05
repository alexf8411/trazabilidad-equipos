<?php
/**
 * public/baja_equipos.php
 * Módulo de Bajas (Individuales o Masivas por Serial)
 */
require_once '../core/db.php';
require_once '../core/session.php';
// 1. CONTROL DE ACCESO (Solo Admin y Recursos)
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}
$results = []; // Para almacenar el resultado de cada serial procesado
// 2. PROCESAMIENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['seriales_raw'])) {
    $motivo = trim($_POST['motivo']);
    $tecnico = $_SESSION['nombre'];
    // Convertimos el texto en un array, separando por enter
    // Esto permite pegar una columna de Excel directamente maximo 100 equipos
    $raw_data = $_POST['seriales_raw'];
    $lista_seriales = preg_split('/\r\n|\r|\n/', $raw_data);
    $lista_seriales = array_filter(array_map('trim', $lista_seriales)); // Limpiar vacíos
    if (count($lista_seriales) > 0) {
        // Buscamos un ID de lugar genérico para la baja (o usamos Bodega por defecto)
        $stmt_lugar = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $lugar_defecto = $stmt_lugar->fetch(PDO::FETCH_ASSOC);
        foreach ($lista_seriales as $serial) {
            $serial = strtoupper($serial); // Estandarizar
            try {
                $pdo->beginTransaction();
                // A. Verificar existencia y estado actual
                $stmt_check = $pdo->prepare("SELECT estado_maestro, placa_ur FROM equipos WHERE serial = ?");
                $stmt_check->execute([$serial]);
                $equipo = $stmt_check->fetch();
                if (!$equipo) {
                    throw new Exception("Serial no encontrado en BD.");
                }
                if ($equipo['estado_maestro'] === 'Baja') {
                    throw new Exception("El equipo ya estaba dado de Baja anteriormente.");
                }
                // B. Actualizar Estado Maestro
                $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Baja' WHERE serial = ?");
                $stmt_upd->execute([$serial]);
                // C. Insertar en Bitácora (Evento 'Baja')
                $sql_bit = "INSERT INTO bitacora (
                                serial_equipo, id_lugar, sede, ubicacion,
                                tipo_evento, correo_responsable, tecnico_responsable,
                                hostname, fecha_evento
                            ) VALUES (?, ?, ?, ?, 'Baja', ?, ?, 'BAJA-DEFINITIVA', NOW())";
                $stmt_b = $pdo->prepare($sql_bit);
                $stmt_b->execute([
                    $serial,
                    $lugar_defecto['id'],
                    $lugar_defecto['sede'],
                    'Disposición Final / Residuos', // Ubicación lógica
                    'Activos Fijos (Bajas)',        // Responsable lógico
                    $tecnico
                ]);
                $pdo->commit();
                $results[] = ['serial' => $serial, 'status' => 'ok', 'msg' => "Baja exitosa (Placa: {$equipo['placa_ur']})"];
            } catch (Exception $e) {
                if ($pdo->inTransaction())
                    $pdo->rollBack();
                $results[] = ['serial' => $serial, 'status' => 'error', 'msg' => $e->getMessage()];
            }
        }
    } else {
        $results[] = ['serial' => 'General', 'status' => 'error', 'msg' => 'No se detectaron seriales en el campo de texto.'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Baja de Activos | URTRACK</title>
    <style>
        :root {
            --danger: #dc3545;
            --success: #28a745;
            --bg: #f8f9fa;
            --white: #fff;
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .main-card {
            background: var(--white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border-top: 5px solid var(--danger);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        h1 {
            margin: 0;
            color: var(--danger);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #555;
        }
        textarea {
            width: 100%;
            height: 150px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: monospace;
            font-size: 1rem;
            box-sizing: border-box;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .btn-submit {
            background: var(--danger);
            color: white;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-submit:hover {
            background: #b02a37;
        }
        /* Resultados */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .results-table th {
            text-align: left;
            background: #eee;
            padding: 10px;
            border-bottom: 2px solid #ddd;
        }
        .results-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .status-ok {
            color: var(--success);
            font-weight: bold;
        }
        .status-error {
            color: var(--danger);
            font-weight: bold;
        }
        .hint {
            font-size: 0.85rem;
            color: #777;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <header>
                <h1>🗑️ Baja y Disposición Final</h1>
                <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
            </header>
            <form method="POST"
                onsubmit="return confirm('¿Está seguro de procesar estas bajas? Esta acción afecta el inventario contable.');">
                <div class="form-group">
                    <label>Justificación / Concepto Técnico *</label>
                    <input type="text" name="motivo" required placeholder="Ej: Equipo obsoleto - Acta de baja #2026-05"
                        autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Listado de Seriales (Uno por línea)</label>
                    <textarea name="seriales_raw" required
                        placeholder="5CD12345X&#10;5CD67890Y&#10;CNU12345Z"></textarea>
                    <div class="hint">ℹ️ Puede copiar y pegar una columna directamente desde Excel.</div>
                </div>
                <button type="submit" class="btn-submit">PROCESAR BAJAS</button>
            </form>
            <?php if (!empty($results)): ?>
                <h3>Resultado de la operación:</h3>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $res): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($res['serial']) ?>
                                </td>
                                <td class="<?= $res['status'] == 'ok' ? 'status-ok' : 'status-error' ?>">
                                    <?= strtoupper($res['status']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($res['msg']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>