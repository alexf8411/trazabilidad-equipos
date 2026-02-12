<?php
/**
 * public/importar_csv.php
 * Versión URTRACK V2.0 - Diseño Institucional & Responsive
 * PROTECCIÓN AVANZADA: Detección automática de delimitadores, limpieza de datos
 */
require_once '../core/db.php';
require_once '../core/session.php';

set_time_limit(600);
ini_set('memory_limit', '2G');

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

/**
 * Detecta automáticamente el delimitador del CSV (coma, punto y coma, tabulador)
 */
function detectarDelimitador($archivo) {
    $handle = fopen($archivo, 'r');
    $primera_linea = fgets($handle);
    fclose($handle);
    
    $delimitadores = [',', ';', "\t", '|'];
    $max_count = 0;
    $delimitador_detectado = ',';
    
    foreach ($delimitadores as $delim) {
        $count = substr_count($primera_linea, $delim);
        if ($count > $max_count) {
            $max_count = $count;
            $delimitador_detectado = $delim;
        }
    }
    
    return $delimitador_detectado;
}

/**
 * Limpia y normaliza una fila del CSV
 */
function limpiarFila($data) {
    // Eliminar columnas vacías al final
    while (count($data) > 0 && trim(end($data)) === '') {
        array_pop($data);
    }
    
    // Limpiar cada celda: trim, eliminar BOM, saltos de línea internos
    return array_map(function($celda) {
        $celda = trim($celda);
        $celda = str_replace(["\r", "\n", "\r\n"], ' ', $celda); // Quitar saltos internos
        $celda = preg_replace('/\s+/', ' ', $celda); // Múltiples espacios -> uno solo
        $celda = preg_replace('/^\xEF\xBB\xBF/', '', $celda); // Eliminar BOM UTF-8
        return $celda;
    }, $data);
}

function procesarFila($data, $pdo, $bodega, &$exitos, &$errores_fila, $numero_fila) {
    // Limpiar la fila primero
    $data = limpiarFila($data);
    
    // Validar que tenga al menos 8 columnas con datos
    if (count($data) < 9) {
        $errores_fila[] = "Fila $numero_fila: Datos insuficientes (se esperan 9 columnas, se encontraron " . count($data) . ")";
        return;
    }

    $serial    = strtoupper(trim($data[0]));
    $placa     = strtoupper(trim($data[1])); // Placas también en mayúsculas
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio_raw = trim($data[5]);
    $raw_fecha = trim($data[6]);
    $modalidad = trim($data[7]);
    $orden_compra = trim($data[8]);
    
    // Validaciones básicas
    if (empty($serial)) {
        $errores_fila[] = "Fila $numero_fila: Serial vacío";
        return;
    }
    if (empty($placa)) {
        $errores_fila[] = "Fila $numero_fila: Placa vacía";
        return;
    }
    if (empty($orden_compra)) {
        $errores_fila[] = "Fila $numero_fila: La Orden de Compra es obligatoria";
        return;
    }
    
    // Limpiar precio: eliminar símbolos, puntos de miles, convertir coma decimal a punto
    $precio_limpio = str_replace(['$', ' ', 'COP', 'USD'], '', $precio_raw);
    // Si tiene punto Y coma, asumimos punto=miles, coma=decimal
    if (strpos($precio_limpio, '.') !== false && strpos($precio_limpio, ',') !== false) {
        $precio_limpio = str_replace('.', '', $precio_limpio); // Quitar miles
        $precio_limpio = str_replace(',', '.', $precio_limpio); // Coma a punto
    } 
    // Si solo tiene coma, puede ser decimal
    elseif (strpos($precio_limpio, ',') !== false) {
        $precio_limpio = str_replace(',', '.', $precio_limpio);
    }
    // Si solo tiene punto, puede ser miles o decimal - asumimos decimal si hay 2 dígitos después
    elseif (preg_match('/\.(\d{3,})$/', $precio_limpio)) {
        $precio_limpio = str_replace('.', '', $precio_limpio); // Es separador de miles
    }
    
    $precio = (float) $precio_limpio;
    
    if ($vida_util <= 0 || $vida_util > 50) {
        $errores_fila[] = "Fila $numero_fila (Serial: $serial): Vida útil inválida ($vida_util). Debe ser entre 1 y 50 años";
        return;
    }
    
    if ($precio <= 0) {
        $errores_fila[] = "Fila $numero_fila (Serial: $serial): Precio inválido ($precio_raw)";
        return;
    }
    
    // Normalizar modalidad (case-insensitive)
    $modalidad_normalizada = ucfirst(strtolower($modalidad));
    $modalidades_validas = ['Propio', 'Leasing', 'Proyecto'];
    if (!in_array($modalidad_normalizada, $modalidades_validas)) {
        $errores_fila[] = "Fila $numero_fila (Serial: $serial): Modalidad '$modalidad' no válida. Debe ser: Propio, Leasing o Proyecto";
        return;
    }
    
    // Fecha más robusta - soporta múltiples formatos
    $fecha_normalizada = str_replace(['/', '.', '\\'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    
    // Si falla, intentar formato DD-MM-YYYY
    if (!$timestamp || $timestamp <= 0) {
        $partes = explode('-', $fecha_normalizada);
        if (count($partes) == 3 && strlen($partes[2]) == 4) {
            // DD-MM-YYYY
            $fecha_normalizada = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
            $timestamp = strtotime($fecha_normalizada);
        }
    }
    
    $fecha_compra = ($timestamp && $timestamp > 0) ? date('Y-m-d', $timestamp) : date('Y-m-d');
    $fecha_evento = date('Y-m-d H:i:s');
    $usuario_responsable = $_SESSION['usuario_id'] ?? $_SESSION['nombre'];
    $desc_evento_final   = "OC: " . $orden_compra;

    try {
        // Verificar duplicado - CORREGIDO: id_equipo en lugar de id
        $stmt_check = $pdo->prepare("SELECT id_equipo FROM equipos WHERE serial = ? OR placa_ur = ?");
        $stmt_check->execute([$serial, $placa]);
        if ($stmt_check->fetch()) {
            $errores_fila[] = "Fila $numero_fila: Serial/Placa duplicado ($serial / $placa)";
            return;
        }

        // Insertar en transacción individual
        $pdo->beginTransaction();
        
        $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
        $stmt_eq->execute([$placa, $serial, $marca, $modelo, $vida_util, $precio, $fecha_compra, $modalidad_normalizada]);
        
        // Insertar en bitacora - hostname usa el serial por defecto
        // CAMBIO: Insertar con la descripción de la Orden de Compra y el usuario real
        $stmt_bit = $pdo->prepare("INSERT INTO bitacora (
                                    serial_equipo, id_lugar, sede, ubicacion, 
                                    tipo_evento, correo_responsable, fecha_evento, 
                                    tecnico_responsable, hostname, desc_evento
                                   ) VALUES (?, ?, ?, ?, 'Alta', ?, ?, ?, ?, ?)");
        
        $stmt_bit->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'], 
            $usuario_responsable, // Usuario autenticado
            $fecha_evento, 
            $_SESSION['nombre'], 
            $serial, //hostname = Serial
            $desc_evento_final    // Valor "OC: 12345"
        ]);
        
        $pdo->commit();
        $exitos++;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores_fila[] = "Fila $numero_fila (Serial: $serial): " . $e->getMessage();
    }
}

$errores = [];
$errores_fila = [];
$exitos = 0;
$mensaje_exito = "";
$delimitador_usado = ",";

if (isset($_POST['importar'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    if (empty($archivo)) {
        $errores[] = "Por favor, selecciona un archivo CSV.";
    } else {
        try {
            // Detectar automáticamente el delimitador
            $delimitador_usado = detectarDelimitador($archivo);
            
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) throw new Exception("Error Crítico: No existe la 'Bodega de Tecnología'.");

            $handle = fopen($archivo, "r");
            $numero_fila = 0;
            
            // Detectar y saltar encabezado
            $primera_fila = fgetcsv($handle, 10000, $delimitador_usado);
            $numero_fila++;
            
            if ($primera_fila) {
                $primera_fila = limpiarFila($primera_fila);
                $check = strtolower(trim($primera_fila[0]));
                // Si no parece encabezado, procesarla
                if (!in_array($check, ['serial', 'sn', 'placa', 'marca', 'modelo', 'número de serie'])) {
                    procesarFila($primera_fila, $pdo, $bodega, $exitos, $errores_fila, $numero_fila);
                }
            }

            while (($data = fgetcsv($handle, 10000, $delimitador_usado)) !== FALSE) {
                $numero_fila++;
                
                // Saltar filas completamente vacías
                $data_limpia = limpiarFila($data);
                if (count(array_filter($data_limpia)) == 0) {
                    continue;
                }
                
                procesarFila($data, $pdo, $bodega, $exitos, $errores_fila, $numero_fila);
            }
            
            fclose($handle);
            
            $delimitador_nombre = ($delimitador_usado == ',') ? 'coma (,)' : 
                                  (($delimitador_usado == ';') ? 'punto y coma (;)' : 
                                  (($delimitador_usado == "\t") ? 'tabulador' : 'otro'));
            
            if ($exitos > 0) {
                $mensaje_exito = "✅ ¡Éxito! Se cargaron $exitos equipos. Delimitador detectado: $delimitador_nombre";
            }
            if (!empty($errores_fila)) {
                $errores = array_merge($errores, array_slice($errores_fila, 0, 15)); // Mostrar primeros 15
                if (count($errores_fila) > 15) {
                    $errores[] = "... y " . (count($errores_fila) - 15) . " errores más.";
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
            --warning: #ffc107;
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
            align-items: flex-start; 
            gap: 15px; 
            font-weight: 500;
            animation: slideIn 0.4s ease;
            line-height: 1.5;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 6px solid var(--warning);
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
        
        .tips-box {
            background: #e7f3ff;
            border-left: 4px solid var(--ur-blue);
            padding: 15px;
            margin-top: 20px;
            border-radius: 6px;
        }
        
        .tips-box h4 {
            margin: 0 0 10px 0;
            color: var(--ur-blue);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tips-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.85rem;
            color: #333;
        }
        
        .tips-box li {
            margin-bottom: 5px;
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
                font-size: 0.85rem;
            }
            th, td {
                padding: 8px;
                font-size: 0.75rem;
            }
            .tips-box {
                padding: 12px;
            }
            .tips-box h4 {
                font-size: 0.85rem;
            }
            .tips-box ul {
                font-size: 0.8rem;
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
                            <th style="background:#e3f2fd; border-left:2px solid #2196f3;">Orden Compra</th> ```
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>SN882233</strong></td>
                            <td>004589</td>
                            <td>HP</td>
                            <td>ProBook 440</td>
                            <td>5</td>
                            <td>4500000</td>
                            <td>25/10/2023</td>
                            <td>Leasing</td>
                            <td style="background:#f1f8e9; font-weight:bold;">2026-9988-OC</td> ```
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="tips-box">
                <h4><i class="fas fa-shield-alt"></i> Protecciones Automáticas</h4>
                <ul>
                    <li>✅ <strong>Detección automática de delimitador</strong>: Soporta coma (,), punto y coma (;), tabulador</li>
                    <li>✅ <strong>Limpieza de espacios</strong>: Elimina espacios adicionales y saltos de línea internos</li>
                    <li>✅ <strong>Columnas vacías</strong>: Ignora columnas extras vacías al final</li>
                    <li>✅ <strong>Formatos de precio</strong>: Acepta $4.500.000 / 4500000 / 4,500,000</li>
                    <li>✅ <strong>Formatos de fecha</strong>: Acepta DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD</li>
                    <li>✅ <strong>Modalidad flexible</strong>: Acepta "leasing", "LEASING", "Leasing"</li>
                    <li>✅ <strong>Validación de duplicados</strong>: Previene cargas repetidas</li>
                </ul>
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