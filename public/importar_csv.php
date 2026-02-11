<?php
/**
 * public/importar_csv.php
 * Versión URTRACK V2.1 - Previsualización y Validación Atómica
 */
require_once '../core/db.php';
require_once '../core/session.php';

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

$errores_globales = [];
$equipos_previa = [];
$hay_errores = false;
$procesado = false;

// 1. FASE DE PREVISUALIZACIÓN (Al cargar el CSV)
if (isset($_POST['analizar_csv'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    if (!empty($archivo)) {
        $handle = fopen($archivo, "r");
        $fila_num = 0;
        
        // Preparar consultas de validación de existencia
        $stmt_check = $pdo->prepare("SELECT serial, placa_ur FROM equipos WHERE serial = ? OR placa_ur = ?");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $fila_num++;
            // Saltar cabecera si contiene palabras clave
            if ($fila_num == 1 && in_array(strtolower($data[0]), ['serial', 'sn', 'placa'])) continue;
            if (count($data) < 8) continue;

            $serial = strtoupper(trim($data[0]));
            $placa  = trim($data[1]);
            $error_fila = "";

            // Validar si ya existe
            $stmt_check->execute([$serial, $placa]);
            if ($stmt_check->fetch()) {
                $error_fila = "El Serial o Placa ya existen en el sistema.";
                $hay_errores = true;
            }

            $equipos_previa[] = [
                'serial'    => $serial,
                'placa'     => $placa,
                'marca'     => trim($data[2]),
                'modelo'    => trim($data[3]),
                'vida'      => trim($data[4]),
                'precio'    => trim($data[5]),
                'fecha'     => trim($data[6]),
                'modalidad' => trim($data[7]),
                'error'     => $error_fila
            ];
        }
        fclose($handle);
        $procesado = true;
        // Guardar en sesión para la confirmación final
        $_SESSION['import_data'] = $equipos_previa;
    }
}

// 2. FASE DE CONFIRMACIÓN FINAL (Escritura en BD)
if (isset($_POST['confirmar_carga'])) {
    if (!empty($_SESSION['import_data'])) {
        try {
            $pdo->beginTransaction();
            
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, 'Alta')");
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, sede, ubicacion, tipo_evento, correo_resp, fecha_evento, resp_evento, hostname) VALUES (?, ?, ?, 'Ingreso', 'Bodega de TI', NOW(), ?, ?)");

            foreach ($_SESSION['import_data'] as $eq) {
                $fecha_norm = date('Y-m-d', strtotime(str_replace('/', '-', $eq['fecha'])));
                
                $stmt_eq->execute([$eq['placa'], $eq['serial'], $eq['marca'], $eq['modelo'], $fecha_norm, $eq['modalidad']]);
                $stmt_bit->execute([$eq['serial'], $bodega['sede'], $bodega['nombre'], $_SESSION['usuario_ldap'], $eq['serial']]);
            }

            $pdo->commit();
            unset($_SESSION['import_data']);
            header("Location: dashboard.php?msg=Carga+Masiva+Exitosa");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores_globales[] = "Error crítico: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Equipos | URTRACK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --ur-blue: #002D72; --ur-gold: #FFC72C; --error: #dc3545; --success: #28a745; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: var(--ur-blue); color: white; padding: 20px; text-align: center; border-bottom: 4px solid var(--ur-gold); }
        .content { padding: 30px; }
        .status-badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .status-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9rem; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-confirm { background: var(--success); color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        .btn-analyze { background: var(--ur-blue); color: white; width: 100%; justify-content: center; }
        tr.row-error { background-color: #fff5f5; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-import"></i> URTRACK - Carga Masiva</h1>
    </div>

    <div class="content">
        <?php if (!$procesado): ?>
            <form method="POST" enctype="multipart/form-data">
                <div style="border: 3px dashed #ccc; padding: 40px; text-align: center; border-radius: 10px;">
                    <i class="fas fa-cloud-upload-alt fa-3x" style="color: #ccc; margin-bottom: 15px;"></i><br>
                    <input type="file" name="archivo_csv" accept=".csv" required>
                </div>
                <button type="submit" name="analizar_csv" class="btn btn-analyze" style="margin-top: 20px;">
                    <i class="fas fa-search"></i> ANALIZAR ARCHIVO
                </button>
            </form>
        <?php else: ?>
            <h3>Previsualización de Equipos</h3>
            <p>Se encontraron <?= count($equipos_previa) ?> registros. Por favor revise antes de confirmar.</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Placa</th>
                        <th>Modelo</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos_previa as $eq): ?>
                        <tr class="<?= !empty($eq['error']) ? 'row-error' : '' ?>">
                            <td><?= htmlspecialchars($eq['serial']) ?></td>
                            <td><?= htmlspecialchars($eq['placa']) ?></td>
                            <td><?= htmlspecialchars($eq['modelo']) ?></td>
                            <td><?= htmlspecialchars($eq['modalidad']) ?></td>
                            <td>
                                <?php if (!empty($eq['error'])): ?>
                                    <span class="status-badge status-error"><i class="fas fa-times"></i> <?= $eq['error'] ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-ok"><i class="fas fa-check"></i> Listo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="btn-group">
                <?php if (!$hay_errores): ?>
                    <form method="POST">
                        <button type="submit" name="confirmar_carga" class="btn btn-confirm">
                            <i class="fas fa-database"></i> CONFIRMAR E INGRESAR A BODEGA
                        </button>
                    </form>
                <?php else: ?>
                    <div class="status-badge status-error" style="flex-grow: 1; text-align: center; padding: 15px;">
                        <i class="fas fa-exclamation-circle"></i> Debe corregir los errores en su archivo CSV antes de procesar la carga.
                    </div>
                <?php endif; ?>
                <a href="importar_csv.php" class="btn btn-cancel">CANCELAR</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>