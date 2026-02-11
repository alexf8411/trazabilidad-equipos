<?php
/**
 * public/importar_csv.php
 * Versión URTRACK V2.2 - Previsualización Integrada
 * Mantiene TODA la lógica original de la V2.0 y añade validación previa.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Mantenemos tus configuraciones de servidor intactas
set_time_limit(600);
ini_set('memory_limit', '2G');

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Función original procesarFila - NO SE TOCA
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos) {
    if (count($data) < 8) return;

    $serial    = strtoupper(trim($data[0]));
    $placa     = trim($data[1]);
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) trim($data[5]);
    $raw_fecha = trim($data[6]);
    $modalidad = trim($data[7]);
    
    if (empty($serial) || empty($placa)) return;
    
    $fecha_evento = date('Y-m-d H:i:s');
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    // INSERTs originales con todas sus columnas
    $stmt_eq->execute([$placa, $serial, $marca, $modelo, $vida_util, $precio, $fecha_compra, $modalidad]);
    $stmt_bit->execute([$serial, $bodega['id'], $bodega['sede'], $bodega['nombre'], $fecha_evento, $_SESSION['nombre'], $serial]);
    
    $exitos++;
}

$errores = [];
$exitos = 0;
$mensaje_exito = "";
$equipos_previa = [];
$mostrar_tabla = false;
$bloqueo_por_error = false;

// PASO 1: Analizar el archivo (Nueva funcionalidad solicitada)
if (isset($_POST['analizar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        $handle = fopen($archivo, "r");
        $fila_n = 0;
        // Preparar validación de duplicados
        $stmt_dup = $pdo->prepare("SELECT serial, placa_ur FROM equipos WHERE serial = ? OR placa_ur = ? LIMIT 1");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $fila_n++;
            // Lógica original para saltar cabecera
            if ($fila_n == 1) {
                $check = strtolower(trim($data[0]));
                $palabras_clave = ['serial', 'sn', 'placa', 'marca', 'modelo'];
                if (in_array($check, $palabras_clave)) continue;
            }
            if (count($data) < 8) continue;

            $s = strtoupper(trim($data[0]));
            $p = trim($data[1]);
            $err_msg = "";

            // Validar existencia real en DB
            $stmt_dup->execute([$s, $p]);
            if ($stmt_dup->fetch()) {
                $err_msg = "ERROR: Serial o Placa ya registrados.";
                $bloqueo_por_error = true;
            }

            $equipos_previa[] = [
                'data' => $data,
                'error' => $err_msg
            ];
        }
        fclose($handle);
        $mostrar_tabla = true;
        // Guardar temporalmente en sesión para no perder el archivo al confirmar
        $_SESSION['csv_temp_data'] = $equipos_previa;
    }
}

// PASO 2: Confirmar carga (Lógica original de procesamiento)
if (isset($_POST['confirmar_importacion'])) {
    if (!isset($_SESSION['csv_temp_data'])) {
        $errores[] = "Sesión expirada o datos no encontrados.";
    } else {
        try {
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología'.");

            $pdo->beginTransaction();

            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, 'Alta', 'Bodega de TI', ?, ?, ?)");

            foreach ($_SESSION['csv_temp_data'] as $item) {
                procesarFila($item['data'], $stmt_eq, $stmt_bit, $bodega, $exitos);
            }
            
            $pdo->commit();
            $mensaje_exito = "✅ ¡Éxito! Se han cargado $exitos equipos al inventario maestro.";
            unset($_SESSION['csv_temp_data']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Equipos | URTRACK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Mantenemos tu CSS original intacto */
        :root { 
            --ur-blue: #002D72; --ur-gold: #FFC72C; --ur-light: #F8F9FA;
            --ur-dark: #1D1D1B; --success: #28a745; --error: #dc3545;
        }
        body { font-family: 'Montserrat', sans-serif; background-color: #e9ecef; margin: 0; padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        .container { width: 100%; max-width: 1000px; background: white; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.15); overflow: hidden; }
        .header { background: var(--ur-blue); color: white; padding: 30px; text-align: center; border-bottom: 5px solid var(--ur-gold); }
        .header h1 { margin: 0; font-size: 1.8rem; text-transform: uppercase; }
        .content { padding: 40px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border-left: 6px solid var(--success); }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 6px solid var(--error); }
        .instruction-card { background: var(--ur-light); border: 1px solid #dee2e6; border-radius: 12px; padding: 25px; margin-bottom: 30px; }
        .table-wrapper { overflow-x: auto; margin-top: 15px; border-radius: 8px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; background: white; min-width: 700px; }
        th { background: #f1f3f5; padding: 12px; text-align: left; font-size: 0.8rem; color: #666; text-transform: uppercase; }
        td { padding: 12px; border-top: 1px solid #eee; font-size: 0.9rem; }
        .fila-error { background-color: #fff5f5; color: #b71c1c; }
        .file-upload-wrapper { position: relative; margin-bottom: 30px; }
        input[type="file"] { width: 100%; padding: 40px 20px; border: 3px dashed #cbd5e0; border-radius: 12px; background: #fafafa; text-align: center; cursor: pointer; box-sizing: border-box; }
        .btn-group { display: flex; flex-direction: column; gap: 15px; }
        .btn-main { background: var(--ur-blue); color: white; border: none; padding: 18px; border-radius: 10px; font-size: 1.1rem; font-weight: bold; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; text-decoration: none; }
        .btn-confirm { background: var(--success); }
        .btn-confirm:disabled { background: #ccc; cursor: not-allowed; }
        .btn-back { text-align: center; text-decoration: none; color: #666; font-size: 0.9rem; padding: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-import"></i> URTRACK</h1>
        <p>Módulo de Carga Masiva - Dirección de Tecnología</p>
    </div>

    <div class="content">
        <?php if ($mensaje_exito): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle fa-lg"></i> <?= $mensaje_exito ?></div>
            <a href="importar_csv.php" class="btn-main">CARGAR OTRO ARCHIVO</a>
        <?php endif; ?>

        <?php foreach ($errores as $error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle fa-lg"></i> <?= $error ?></div>
        <?php endforeach; ?>

        <?php if (!$mostrar_tabla && !$mensaje_exito): ?>
            <div class="instruction-card">
                <h3><i class="fas fa-info-circle"></i> Estructura del Archivo</h3>
                <p>El CSV debe contener: Serial, Placa UR, Marca, Modelo, Vida Útil, Precio, Fecha Compra, Modalidad.</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="file-upload-wrapper">
                    <input type="file" name="archivo_csv" accept=".csv" required>
                </div>
                <div class="btn-group">
                    <button type="submit" name="analizar" class="btn-main">
                        <i class="fas fa-search"></i> ANALIZAR Y PREVISUALIZAR
                    </button>
                    <a href="alta_equipos.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
                </div>
            </form>

        <?php elseif ($mostrar_tabla): ?>
            <h3>Listado de Equipos a Ingresar</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Serial</th><th>Placa UR</th><th>Modelo</th><th>Modalidad</th><th>Estado / Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipos_previa as $item): ?>
                            <tr class="<?= $item['error'] ? 'fila-error' : '' ?>">
                                <td><?= htmlspecialchars($item['data'][0]) ?></td>
                                <td><?= htmlspecialchars($item['data'][1]) ?></td>
                                <td><?= htmlspecialchars($item['data'][3]) ?></td>
                                <td><?= htmlspecialchars($item['data'][7]) ?></td>
                                <td>
                                    <?php if ($item['error']): ?>
                                        <i class="fas fa-times-circle"></i> <?= $item['error'] ?>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle" style="color:green"></i> Listo
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" style="margin-top: 30px;">
                <div class="btn-group">
                    <?php if ($bloqueo_por_error): ?>
                        <div class="alert alert-error">
                            No se puede procesar la carga porque hay equipos duplicados o datos inválidos.
                        </div>
                        <a href="importar_csv.php" class="btn-main" style="background: #666;">CORREGIR ARCHIVO Y REINTENTAR</a>
                    <?php else: ?>
                        <button type="submit" name="confirmar_importacion" class="btn-main btn-confirm">
                            <i class="fas fa-cloud-upload-alt"></i> CONFIRMAR CARGA A BODEGA
                        </button>
                        <a href="importar_csv.php" class="btn-back">Cancelar carga</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>