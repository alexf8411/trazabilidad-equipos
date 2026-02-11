<?php
/**
 * PROYECTO URTRACK - SISTEMA DE TRAZABILIDAD
 * Archivo: public/importar_csv.php
 * Función: Carga Masiva de Activos (Fase 3: Registro Maestro)
 * Estado: Revisión Profesional V2.1
 */

// 1. CONFIGURACIÓN DE ENTORNO Y SEGURIDAD
ini_set('auto_detect_line_endings', true); // CRÍTICO: Para que Ubuntu lea archivos creados en Windows
error_reporting(E_ALL);                    // ACTIVAR: Para ver exactamente qué falla
ini_set('display_errors', 1);

require_once '../core/db.php';
require_once '../core/session.php';

// Aumentar límites para archivos grandes
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

// Verificación de Roles
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php?error=acceso_denegado');
    exit;
}

/**
 * Función: procesarFila
 * Propósito: Inserta de forma atómica en Equipos y Bitácora
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos, &$errores_filas) {
    // Validar que la fila tenga al menos las 8 columnas requeridas
    if (count($data) < 8) {
        $errores_filas[] = "Fila con formato inválido (se esperaban 8 columnas).";
        return;
    }

    // --- LIMPIEZA DE DATOS ---
    // Eliminar el BOM de UTF-8 y espacios en blanco del Serial (primera columna)
    $serial    = strtoupper(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($data[0]))); 
    $placa     = trim($data[1]); 
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $vida_util = (int) trim($data[4]);
    $precio    = (float) trim($data[5]);
    $raw_fecha = trim($data[6]);
    
    // Normalizar Modalidad para cumplir con el ENUM de la DB ('Propio','Leasing','Proyecto')
    $modalidad_input = strtolower(trim($data[7]));
    $modalidad = ucfirst($modalidad_input); 

    // Saltamos filas vacías
    if (empty($serial) || empty($placa)) return;
    
    // --- GESTIÓN DE FECHAS ---
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    try {
        // TRANSACCIÓN PARTE A: Crear el registro maestro del equipo
        $stmt_eq->execute([
            $serial, 
            $placa, 
            $marca, 
            $modelo, 
            $vida_util, 
            $precio, 
            $fecha_compra, 
            $modalidad
        ]);
        
        // TRANSACCIÓN PARTE B: Registrar el primer evento de vida (Ingreso a Bodega)
        // Mapeo exacto según DESCRIBE bitacora:
        // serial_equipo, id_lugar, hostname, sede, ubicacion, correo_responsable, equipo_adic, tipo_evento, tecnico_responsable
        $stmt_bit->execute([
            $serial,                        // serial_equipo
            $bodega['id'],                  // id_lugar
            'PENDIENTE',                    // hostname (Se asigna en Fase 4)
            $bodega['sede'],                // sede
            $bodega['nombre'],              // ubicacion (Ej: Bodega de Tecnología)
            ($_SESSION['usuario'] ?? 'N/A'), // correo_responsable (El que carga el archivo)
            0,                              // equipo_adic (0 = False)
            'Alta',                         // tipo_evento (ENUM)
            ($_SESSION['usuario'] ?? 'N/A')  // tecnico_responsable
        ]);
        
        $exitos++;
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $errores_filas[] = "Registro Duplicado: El Serial <strong>$serial</strong> o Placa <strong>$placa</strong> ya existen.";
        } else {
            $errores_filas[] = "Error en registro $serial: " . $e->getMessage();
        }
    }
}

// Variables de estado
$errores = [];
$mensaje_exito = "";
$exitos = 0;

// --- LÓGICA PRINCIPAL DE IMPORTACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar'])) {
    
    if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error crítico: No se pudo cargar el archivo al servidor.";
    } else {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        
        try {
            // 1. Validar que la Bodega de Tecnología exista (Punto de anclaje)
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                throw new Exception("<strong>Error de Configuración:</strong> No se encontró el lugar 'Bodega de Tecnología' en la base de datos. Por favor, créelo en la tabla 'lugares' antes de continuar.");
            }

            // 2. Iniciar Proceso de Lectura
            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            // Preparar consultas SQL (Optimización de velocidad)
            $stmt_eq = $pdo->prepare("INSERT INTO equipos (serial, placa_ur, marca, modelo, vida_util, precio, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Alta')");
            
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, hostname, sede, ubicacion, correo_responsable, equipo_adic, tipo_evento, tecnico_responsable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // 3. Procesar Cabecera e identificar delimitador
            $fila_numero = 0;
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $fila_numero++;
                
                // Saltar cabecera si la primera columna no parece un Serial (no tiene números)
                if ($fila_numero === 1 && !preg_match('/[0-9]/', $data[0])) {
                    continue; 
                }

                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos, $errores);
            }

            $pdo->commit();
            fclose($handle);

            if ($exitos > 0) {
                $mensaje_exito = "Operación Exitosa: <strong>$exitos</strong> equipos ingresados correctamente a la Bodega de Tecnología.";
            } else if (empty($errores)) {
                $errores[] = "El archivo fue procesado pero no se encontró ningún dato válido para insertar.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "Error Catastrófico: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva - URTRACK</title>
    <style>
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f4f7f6; --success: #28a745; --danger: #dc3545; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; line-height: 1.6; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Header Institucional */
        .header-main { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px; border-left: 8px solid var(--primary); display: flex; justify-content: space-between; align-items: center; }
        .header-main h1 { margin: 0; font-size: 1.8rem; color: var(--primary); letter-spacing: -0.5px; }

        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        /* Banners Informativos */
        .info-box { background: #eef4ff; border: 1px solid #d0e1ff; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .info-box h3 { margin-top: 0; color: #0044cc; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        
        .table-preview { overflow-x: auto; margin: 20px 0; border-radius: 8px; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #f8f9fa; padding: 14px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #666; border-bottom: 2px solid #eee; }
        td { padding: 14px; font-size: 0.9rem; border-bottom: 1px solid #f1f1f1; }

        /* Alertas */
        .alert { padding: 18px; border-radius: 8px; margin-bottom: 25px; border-left: 6px solid; animation: fadeIn 0.4s ease; }
        .alert-success { background: #d4edda; color: #155724; border-color: #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        
        /* Area de Carga */
        .upload-zone { border: 3px dashed #cbd5e0; padding: 50px; text-align: center; border-radius: 15px; cursor: pointer; transition: all 0.3s; background: #fafafa; margin-bottom: 30px; position: relative; }
        .upload-zone:hover { border-color: var(--primary); background: #f0f7ff; }
        .upload-zone input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .upload-icon { font-size: 3rem; color: #a0aec0; margin-bottom: 15px; }
        .upload-text { font-size: 1.2rem; color: #4a5568; }
        .upload-text strong { color: var(--primary); }

        .btn-submit { background: var(--primary); color: white; border: none; padding: 18px 40px; border-radius: 8px; font-size: 1.2rem; font-weight: bold; width: 100%; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,45,114,0.3); }
        .btn-submit:hover { background: #001a45; transform: translateY(-2px); }
        .btn-submit:disabled { background: #a0aec0; cursor: not-allowed; transform: none; }
        
        .back-link { text-decoration: none; color: #718096; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .back-link:hover { color: var(--primary); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .header-main { flex-direction: column; text-align: center; gap: 15px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-main">
        <h1>📦 Carga Masiva de Activos (Fase 3)</h1>
        <a href="dashboard.php" class="back-link">← Volver al Panel de Control</a>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success">
            <strong>¡Excelente!</strong><br><?= $mensaje_exito ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
            <strong>Atención: Se encontraron incidencias</strong>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <?php foreach ($errores as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="info-box">
            <h3>📝 Guía de Formato Requerido</h3>
            <p>Para cargar los equipos correctamente, el archivo CSV debe usar <strong>Punto y Coma (;)</strong> como separador y mantener el siguiente orden de columnas:</p>
            
            <div class="table-preview">
                <table>
                    <thead>
                        <tr>
                            <th>1. Serial</th>
                            <th>2. Placa UR</th>
                            <th>3. Marca</th>
                            <th>4. Modelo</th>
                            <th>5. Vida Útil</th>
                            <th>6. Precio</th>
                            <th>7. Fecha Compra</th>
                            <th>8. Modalidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>5CD240JL01</td>
                            <td>004589</td>
                            <td>HP</td>
                            <td>ProBook 440</td>
                            <td>5</td>
                            <td>3850000</td>
                            <td>15/05/2024</td>
                            <td>Leasing</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size: 0.85rem; color: #666;">
                * Formatos de fecha soportados: DD/MM/YYYY o YYYY-MM-DD.<br>
                * Modalidades válidas: Propio, Leasing o Proyecto.
            </p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="mainUploadForm">
            <div class="upload-zone">
                <div class="upload-icon">📄</div>
                <div class="upload-text" id="fileNameDisplay">
                    Arrastra aquí tu archivo CSV o <strong>haz clic para buscarlo</strong>
                </div>
                <input type="file" name="archivo_csv" id="archivo_csv" accept=".csv" required onchange="displayFileName(this)">
            </div>
            
            <button type="submit" name="importar" class="btn-submit" id="btnSubmit">
                🚀 INICIAR PROCESAMIENTO E INGRESO A BODEGA
            </button>
        </form>
    </div>
</div>

<script>
function displayFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : "Arrastra aquí tu archivo o haz clic para buscarlo";
    document.getElementById('fileNameDisplay').innerHTML = "Archivo seleccionado: <strong style='color: #002D72;'>" + fileName + "</strong>";
}

// Bloqueo de UI para evitar doble envío
document.getElementById('mainUploadForm').onsubmit = function() {
    const btn = document.getElementById('btnSubmit');
    btn.innerHTML = "⌛ Procesando registros... No cierre la página";
    btn.disabled = true;
    btn.style.opacity = "0.8";
};
</script>

</body>
</html>