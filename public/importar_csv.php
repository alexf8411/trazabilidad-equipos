<?php
/**
 * public/importar_csv.php
 * Versión URTRACK V2.0 - Diseño Institucional & Responsive
 */
require_once '../core/db.php';
require_once '../core/session.php';

set_time_limit(600);
ini_set('memory_limit', '2G');

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

function procesarFila($data, $pdo, $bodega, &$exitos, &$errores_fila) {
    if (count($data) < 8) return;

    $serial    = strtoupper(trim($data[0]));
    $placa     = trim($data[1]);
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) str_replace(['$', '.', ','], ['', '', '.'], trim($data[5]));
    $raw_fecha = trim($data[6]);
    $modalidad = trim($data[7]);
    
    // Validaciones
    if (empty($serial) || empty($placa)) return;
    if ($vida_util <= 0 || $precio <= 0) {
        $errores_fila[] = "Fila con serial $serial: datos numéricos inválidos";
        return;
    }
    
    // Fecha más robusta
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp && $timestamp > 0) ? date('Y-m-d', $timestamp) : date('Y-m-d');
    $fecha_evento = date('Y-m-d H:i:s');

    try {
        // Verificar duplicado
        $stmt_check = $pdo->prepare("SELECT id FROM equipos WHERE serial = ? OR placa_ur = ?");
        $stmt_check->execute([$serial, $placa]);
        if ($stmt_check->fetch()) {
            $errores_fila[] = "Serial/Placa duplicado: $serial / $placa";
            return;
        }

        // Insertar en transacción individual
        $pdo->beginTransaction();
        
        $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
        $stmt_eq->execute([$placa, $serial, $marca, $modelo, $vida_util, $precio, $fecha_compra, $modalidad]);
        
        $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, 'Alta', 'Bodega de TI', ?, ?, ?)");
        $stmt_bit->execute([$serial, $bodega['id'], $bodega['sede'], $bodega['nombre'], $fecha_evento, $_SESSION['nombre'], $serial]);
        
        $pdo->commit();
        $exitos++;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores_fila[] = "Error en serial $serial: " . $e->getMessage();
    }
}

$errores = [];
$errores_fila = [];
$exitos = 0;
$mensaje_exito = "";

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        try {
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología'.");

            $handle = fopen($archivo, "r");
            
            // Detectar y saltar encabezado
            $primera_fila = fgetcsv($handle, 1000, ",");
            if ($primera_fila) {
                $check = strtolower(trim($primera_fila[0]));
                if (!in_array($check, ['serial', 'sn', 'placa', 'marca', 'modelo'])) {
                    procesarFila($primera_fila, $pdo, $bodega, $exitos, $errores_fila);
                }
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                procesarFila($data, $pdo, $bodega, $exitos, $errores_fila);
            }
            
            fclose($handle);
            
            if ($exitos > 0) {
                $mensaje_exito = "✅ ¡Éxito! Se han cargado $exitos equipos al inventario maestro.";
            }
            if (!empty($errores_fila)) {
                $errores = array_merge($errores, array_slice($errores_fila, 0, 10)); // Mostrar solo primeros 10
                if (count($errores_fila) > 10) {
                    $errores[] = "... y " . (count($errores_fila) - 10) . " errores más.";
                }
            }
            
        } catch (Exception $e) {
            $errores[] = "Error crítico: " . $e->getMessage();
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
        :root { 
            --ur-blue: #002D72; 
            --ur-gold: #FFC72C; 
            --ur-light: #F8F9FA;
            --ur-dark: #1D1D1B;
            --success: #28a745;
            --error: #dc3545;
        }

        * {
            box-sizing: border-box;
        }

        body { 
            font-family: 'Montserrat', 'Segoe UI', sans-serif; 
            background-color: #e9ecef; 
            margin: 0; 
            padding: 20px;
            display: flex; 
            justify-content: center; 
            align-items: flex-start;
            min-height: 100vh;
        }

        .container { 
            width: 100%; 
            max-width: 1000px; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); 
            overflow: hidden; 
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        /* Header Institucional */
        .header { 
            background: var(--ur-blue); 
            color: white; 
            padding: 30px; 
            text-align: center; 
            border-bottom: 5px solid var(--ur-gold);
        }
        .header h1 { 
            margin: 0; 
            font-size: 1.8rem; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
        }
        .header p { 
            margin: 10px 0 0; 
            opacity: 0.8; 
            font-size: 0.9rem; 
        }

        .content { 
            padding: 40px; 
        }

        /* Alertas */
        .alert { 
            padding: 15px 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            font-weight: 500;
            animation: slideIn 0.4s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 6px solid var(--success); 
        }
        .alert-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 6px solid var(--error); 
        }

        /* Box de Instrucciones */
        .instruction-card { 
            background: var(--ur-light); 
            border: 1px solid #dee2e6; 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 30px;
        }
        .instruction-card h3 { 
            color: var(--ur-blue); 
            margin-top: 0; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Tabla Responsive */
        .table-wrapper { 
            overflow-x: auto; 
            margin-top: 15px; 
            border-radius: 8px; 
            border: 1px solid #ddd; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            background: white; 
            min-width: 700px; 
        }
        th { 
            background: #f1f3f5; 
            padding: 12px; 
            text-align: left; 
            font-size: 0.8rem; 
            color: #666; 
            text-transform: uppercase;
            white-space: nowrap;
        }
        td { 
            padding: 12px; 
            border-top: 1px solid #eee; 
            font-size: 0.9rem; 
        }

        /* Dropzone / Input */
        .file-upload-wrapper {
            position: relative; 
            margin-bottom: 30px;
        }
        input[type="file"] {
            width: 100%; 
            padding: 40px 20px;
            border: 3px dashed #cbd5e0; 
            border-radius: 12px;
            background: #fafafa; 
            text-align: center; 
            cursor: pointer;
            transition: all 0.3s; 
            box-sizing: border-box;
            font-size: 0.95rem;
        }
        input[type="file"]:hover { 
            border-color: var(--ur-blue); 
            background: #f0f4f8; 
        }

        /* Botones */
        .btn-group { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
        }
        .btn-main { 
            background: var(--ur-blue); 
            color: white; 
            border: none; 
            padding: 18px; 
            border-radius: 10px; 
            font-size: 1.1rem; 
            font-weight: bold; 
            cursor: pointer; 
            transition: 0.3s;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            gap: 10px;
        }
        .btn-main:hover { 
            background: #001f52; 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,45,114,0.3); 
        }
        .btn-main:active {
            transform: translateY(0);
        }
        
        .btn-back { 
            text-align: center; 
            text-decoration: none; 
            color: #666; 
            font-size: 0.9rem; 
            padding: 10px; 
            transition: 0.3s;
            display: inline-block;
        }
        .btn-back:hover { 
            color: var(--ur-blue); 
        }

        /* Responsividad */
        @media (max-width: 768px) {
            body { 
                padding: 10px; 
            }
            .content { 
                padding: 20px; 
            }
            .header { 
                padding: 20px; 
            }
            .header h1 { 
                font-size: 1.4rem; 
            }
            .header p {
                font-size: 0.8rem;
            }
            .instruction-card { 
                padding: 15px; 
            }
            .instruction-card h3 {
                font-size: 1rem;
            }
            .instruction-card p {
                font-size: 0.85rem;
            }
            .btn-main {
                padding: 15px;
                font-size: 1rem;
            }
            input[type="file"] {
                padding: 30px 15px;
                font-size: 0.85rem;
            }
            .alert {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            th, td {
                padding: 8px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.2rem;
            }
            .btn-main {
                font-size: 0.95rem;
                padding: 14px;
            }
            .instruction-card h3 {
                font-size: 0.9rem;
            }
        }
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
            <div class="alert alert-success">
                <i class="fas fa-check-circle fa-lg"></i> 
                <span><?= $mensaje_exito ?></span>
            </div>
        <?php endif; ?>

        <?php foreach ($errores as $error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle fa-lg"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="instruction-card">
            <h3><i class="fas fa-info-circle"></i> Estructura del Archivo</h3>
            <p style="font-size: 0.9rem; color: #555;">Para garantizar la integridad, el CSV debe contener 8 columnas estrictas:</p>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Placa UR</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Vida Útil</th>
                            <th>Precio</th>
                            <th>Fecha Compra</th>
                            <th>Modalidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>SN882233</strong></td>
                            <td>004589</td>
                            <td>HP</td>
                            <td>ProBook 440</td>
                            <td>5</td>
                            <td>$4.500.000</td>
                            <td>25/10/2023</td>
                            <td>Leasing</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top: 15px; font-size: 0.85rem; color: var(--ur-blue); font-weight: bold;">
                <i class="fas fa-robot"></i> El sistema asignará automáticamente el Hostname basado en el Serial.
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="file-upload-wrapper">
                <input type="file" name="archivo_csv" accept=".csv" required title="Seleccione su archivo .csv">
            </div>

            <div class="btn-group">
                <button type="submit" name="importar" class="btn-main">
                    <i class="fas fa-cloud-upload-alt"></i> PROCESAR CARGA A BODEGA
                </button>
                <a href="alta_equipos.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Volver al registro manual
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>