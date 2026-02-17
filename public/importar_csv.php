<?php
/**
 * URTRACK - Importador CSV
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES IMPLEMENTADAS:
 * ✅ Carga por lotes (batch insert) - 100 filas a la vez
 * ✅ Detección automática de delimitadores
 * ✅ Validación robusta de datos
 * ✅ Límites de memoria razonables (512MB)
 * ✅ Transacciones agrupadas
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Límites razonables para producción
set_time_limit(300); // 5 minutos máximo
ini_set('memory_limit', '512M'); // 512MB es suficiente

// Verificar permisos
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

/**
 * Detecta el delimitador del CSV automáticamente
 */
function detectDelimitador($archivo) {
    $handle = fopen($archivo, 'r');
    $primera_linea = fgets($handle);
    fclose($handle);
    
    $delimitadores = [',', ';', "\t", '|'];
    $max_count = 0;
    $delimitador = ',';
    
    foreach ($delimitadores as $delim) {
        $count = substr_count($primera_linea, $delim);
        if ($count > $max_count) {
            $max_count = $count;
            $delimitador = $delim;
        }
    }
    
    return $delimitador;
}

/**
 * Limpia y normaliza una fila del CSV
 */
function limpiarFila($data) {
    while (count($data) > 0 && trim(end($data)) === '') {
        array_pop($data);
    }
    
    return array_map(function($celda) {
        $celda = trim($celda);
        $celda = str_replace(["\r", "\n", "\r\n"], ' ', $celda);
        $celda = preg_replace('/\s+/', ' ', $celda);
        $celda = preg_replace('/^\xEF\xBB\xBF/', '', $celda); // Quitar BOM
        return $celda;
    }, $data);
}

/**
 * Valida y normaliza datos de una fila
 */
function validarFila($data, $numero_fila, &$errores) {
    $data = limpiarFila($data);
    
    if (count($data) < 9) {
        $errores[] = "Fila $numero_fila: Faltan columnas (se esperan 9, hay " . count($data) . ")";
        return null;
    }

    $serial = strtoupper(trim($data[0]));
    $placa = strtoupper(trim($data[1]));
    $marca = trim($data[2]);
    $modelo = trim($data[3]);
    $vida_util = (int)trim($data[4]);
    $precio_raw = trim($data[5]);
    $fecha_raw = trim($data[6]);
    $modalidad = ucfirst(strtolower(trim($data[7])));
    $orden_compra = trim($data[8]);

    // Validaciones
    if (empty($serial)) {
        $errores[] = "Fila $numero_fila: Serial vacío";
        return null;
    }
    
    if (empty($placa)) {
        $errores[] = "Fila $numero_fila: Placa vacía";
        return null;
    }
    
    if (empty($orden_compra)) {
        $errores[] = "Fila $numero_fila: Orden de Compra obligatoria";
        return null;
    }

    if ($vida_util < 1 || $vida_util > 50) {
        $errores[] = "Fila $numero_fila: Vida útil inválida ($vida_util). Debe ser 1-50 años";
        return null;
    }

    // Limpiar precio
    $precio_limpio = str_replace(['$', ' ', 'COP', 'USD'], '', $precio_raw);
    if (strpos($precio_limpio, '.') !== false && strpos($precio_limpio, ',') !== false) {
        $precio_limpio = str_replace('.', '', $precio_limpio);
        $precio_limpio = str_replace(',', '.', $precio_limpio);
    } elseif (strpos($precio_limpio, ',') !== false) {
        $precio_limpio = str_replace(',', '.', $precio_limpio);
    }
    $precio = (float)$precio_limpio;

    if ($precio <= 0) {
        $errores[] = "Fila $numero_fila: Precio inválido ($precio_raw)";
        return null;
    }

    // Validar modalidad
    if (!in_array($modalidad, ['Propio', 'Leasing', 'Proyecto'])) {
        $errores[] = "Fila $numero_fila: Modalidad inválida. Debe ser: Propio, Leasing o Proyecto";
        return null;
    }

    // Normalizar fecha
    $fecha_norm = str_replace(['/', '.', '\\'], '-', $fecha_raw);
    $timestamp = strtotime($fecha_norm);
    
    if (!$timestamp) {
        $partes = explode('-', $fecha_norm);
        if (count($partes) == 3 && strlen($partes[2]) == 4) {
            $fecha_norm = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
            $timestamp = strtotime($fecha_norm);
        }
    }

    $fecha_compra = ($timestamp && $timestamp > 0) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    return [
        'serial' => $serial,
        'placa' => $placa,
        'marca' => $marca,
        'modelo' => $modelo,
        'vida_util' => $vida_util,
        'precio' => $precio,
        'fecha_compra' => $fecha_compra,
        'modalidad' => $modalidad,
        'orden_compra' => $orden_compra
    ];
}

// ============================================================================
// PROCESAMIENTO DEL ARCHIVO
// ============================================================================
$errores = [];
$exitos = 0;
$mensaje = '';
$delimitador_usado = ',';

//if (isset($_POST['importar']) && isset($_FILES['archivo_csv'])) {
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv']['tmp_name'];
    
    if (empty($archivo) || !is_uploaded_file($archivo)) {
        $errores[] = "Error: No se cargó ningún archivo";
    } else {
        try {
            // Obtener bodega
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                throw new Exception("ERROR: No existe la ubicación 'Bodega de Tecnología'");
            }

            $delimitador_usado = detectDelimitador($archivo);
            $handle = fopen($archivo, "r");
            $numero_fila = 0;
            $batch_equipos = [];
            $batch_bitacora = [];
            $BATCH_SIZE = 100; // Procesar de 100 en 100

            // Saltar encabezado
            $primera_fila = fgetcsv($handle, 10000, $delimitador_usado);
            $numero_fila++;
            
            if ($primera_fila) {
                $primera_fila = limpiarFila($primera_fila);
                $check = strtolower(trim($primera_fila[0]));
                if (!in_array($check, ['serial', 'sn', 'placa', 'marca'])) {
                    $fila_validada = validarFila($primera_fila, $numero_fila, $errores);
                    if ($fila_validada) {
                        $batch_equipos[] = $fila_validada;
                    }
                }
            }

            // Procesar todas las filas
            while (($data = fgetcsv($handle, 10000, $delimitador_usado)) !== FALSE) {
                $numero_fila++;
                
                $data_limpia = limpiarFila($data);
                if (count(array_filter($data_limpia)) == 0) continue;
                
                $fila_validada = validarFila($data, $numero_fila, $errores);
                
                if ($fila_validada) {
                    $batch_equipos[] = $fila_validada;
                    
                    // Cuando llegamos al tamaño del batch, insertamos
                    if (count($batch_equipos) >= $BATCH_SIZE) {
                        procesarBatch($pdo, $batch_equipos, $bodega, $exitos, $errores);
                        $batch_equipos = []; // Vaciar batch
                    }
                }
            }

            fclose($handle);

            // Procesar equipos restantes
            if (count($batch_equipos) > 0) {
                procesarBatch($pdo, $batch_equipos, $bodega, $exitos, $errores);
            }

            $delim_nombre = ($delimitador_usado == ',') ? 'coma' : 
                           (($delimitador_usado == ';') ? 'punto y coma' : 'tabulador');
            
            if ($exitos > 0) {
                $mensaje = '<div class="alert alert-success">✅ Éxito! Se cargaron <strong>' . $exitos . '</strong> equipos (Delimitador: ' . $delim_nombre . ')</div>';
            }
            
            if (count($errores) > 0) {
                $errores = array_slice($errores, 0, 20); // Mostrar primeros 20
            }

        } catch (Exception $e) {
            $errores[] = "Error crítico: " . $e->getMessage();
        }
    }
}

/**
 * Procesa un lote de equipos (BATCH INSERT)
 */
function procesarBatch($pdo, $equipos, $bodega, &$exitos, &$errores) {
    try {
        $pdo->beginTransaction();

        foreach ($equipos as $eq) {
            // Verificar duplicados
            $stmt_dup = $pdo->prepare("SELECT id_equipo FROM equipos WHERE serial = ? OR placa_ur = ?");
            $stmt_dup->execute([$eq['serial'], $eq['placa']]);
            
            if ($stmt_dup->fetch()) {
                $errores[] = "Serial/Placa duplicado: {$eq['serial']} / {$eq['placa']}";
                continue;
            }

            // Insertar equipo
            $stmt_eq = $pdo->prepare("
                INSERT INTO equipos (placa_ur, serial, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')
            ");
            $stmt_eq->execute([
                $eq['placa'], $eq['serial'], $eq['marca'], $eq['modelo'], 
                $eq['vida_util'], $eq['precio'], $eq['fecha_compra'], $eq['modalidad']
            ]);

            // Insertar en bitácora
            $stmt_bit = $pdo->prepare("
                INSERT INTO bitacora (
                    serial_equipo, id_lugar, tipo_evento, 
                    correo_responsable, fecha_evento, tecnico_responsable, hostname, desc_evento
                ) VALUES (?, ?, 'Alta', ?, NOW(), ?, ?, ?)
            ");
            $stmt_bit->execute([
                $eq['serial'],
                $bodega['id'],
                $_SESSION['usuario_id'] ?? $_SESSION['nombre'],
                $_SESSION['nombre'],
                $eq['serial'],
                'OC: ' . $eq['orden_compra']
            ]);

            $exitos++;
        }

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errores[] = "Error en lote: " . $e->getMessage();
    }
}

// AUDITORÍA — Registrar importación masiva (solo si hubo éxitos)
if ($exitos > 0) {
    try {
        require_once '../core/db.php';
        $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
        $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
        $usuario_rol    = $_SESSION['rol']        ?? 'Recursos';
        $ip_cliente     = $_SERVER['REMOTE_ADDR'];
        
        $pdo->prepare("INSERT INTO auditoria_cambios 
            (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
             tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
            VALUES (NOW(), ?, ?, ?, ?, 'IMPORTACION_CSV', 'equipos', ?, NULL, ?)")
            ->execute([
                $usuario_ldap,
                $usuario_nombre,
                $usuario_rol,
                $ip_cliente,
                "Importación masiva: $exitos equipos",
                "Total: $exitos equipos procesados"
            ]);
    } catch (Exception $e) {
        error_log("Fallo auditoría importación: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Equipos - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">

</head>
<body>

<div class="container">
    <div class="card fade-in">
        <div class="card-header">
            <h1>📥 Carga Masiva de Equipos</h1>
            <p>Dirección de Tecnología - Universidad del Rosario</p>
        </div>

        <div class="card-body">
            <?php if ($mensaje) echo $mensaje; ?>

            <?php foreach ($errores as $error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <div class="alert alert-info">
                <strong>📋 Estructura del CSV (9 columnas obligatorias):</strong><br>
                Serial | Placa UR | Marca | Modelo | Vida Útil | Precio | Fecha Compra | Modalidad | Orden Compra
            </div>

            <div class="table-wrapper mt-2 mb-3">
                <table>
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Placa UR</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Vida Útil</th>
                            <th>Precio</th>
                            <th>Fecha</th>
                            <th>Modalidad</th>
                            <th style="background:#e3f2fd;">Orden Compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>SN882233</td>
                            <td>004589</td>
                            <td>HP</td>
                            <td>ProBook 440</td>
                            <td>5</td>
                            <td>4500000</td>
                            <td>25/10/2023</td>
                            <td>Leasing</td>
                            <td style="background:#f1f8e9;"><strong>2026-9988-OC</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-warning">
                <strong>⚡ Recomendaciones:</strong><br>
                ✅ Formato: CSV (.csv)<br>
                ✅ Sin filas vacías ni duplicados<br>
                ✅ Modalidades válidas: Propio, Leasing, Proyecto<br>
                ✅ Vida útil: 1-50 años<br>
                ✅ Máximo recomendado: 500 filas por archivo
            </div>

            <form method="POST" enctype="multipart/form-data" data-validate>
                <div class="form-group">
                    <input type="file" name="archivo_csv" accept=".csv" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="importar" class="btn btn-primary btn-block">
                        📤 PROCESAR CARGA MASIVA
                    </button>
                </div>

                <div class="text-center mt-3">
                    <a href="alta_equipos.php" class="btn btn-outline">⬅ Volver al registro manual</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../public/js/app.js"></script>
</body>
</html>
