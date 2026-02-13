<?php
/**
 * public/importar_csv.php
 * URTRACK Fase 3: Importación Masiva Inteligente y Responsiva
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Configuración de recursos
set_time_limit(600); 
ini_set('memory_limit', '2G'); 

// Validación de Rol
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    die("No tienes permisos para esta acción.");
}

/**
 * Procesa la fila. Retorna TRUE si insertó, FALSE si la ignoró.
 */
function procesarFila($data, $stmt_eq, $stmt_bit, $bodega, &$exitos) {
    // Validación estricta: Mínimo placa y serial requeridos
    if (count($data) < 2 || empty(trim($data[0])) || empty(trim($data[1]))) {
        return false;
    }

    // Normalización
    $placa     = strtoupper(trim($data[0]));
    $serial    = strtoupper(trim($data[1]));
    $marca     = trim($data[2]);
    $modelo    = trim($data[3]);
    $raw_fecha = trim($data[4]);
    $modalidad = trim($data[5]);
    
    // Parseo de Fechas
    $fecha_normalizada = str_replace(['/', '.'], '-', $raw_fecha);
    $timestamp = strtotime($fecha_normalizada);
    $fecha_compra = ($timestamp) ? date('Y-m-d', $timestamp) : date('Y-m-d');

    // 1. Insertar en Equipos
    $stmt_eq->execute([$placa, $serial, $marca, $modelo, $fecha_compra, $modalidad]);
    
    // 2. Insertar en Bitácora (Ingreso inicial)
    $stmt_bit->execute([
        $serial, 
        $bodega['id'], 
        $bodega['sede'], 
        $bodega['nombre'], // Ubicación física
        date('Y-m-d H:i:s'), 
        $_SESSION['nombre']
    ]);
    
    $exitos++;
    return true;
}

$errores = [];
$mensaje_exito = "";

if (isset($_POST['importar'])) {
    // Validación de archivo vacío
    if (empty($_FILES['archivo_csv']['tmp_name']) || $_FILES['archivo_csv']['size'] === 0) {
        $errores[] = "❌ El archivo está vacío o no se ha subido correctamente.";
    } else {
        $archivo = $_FILES['archivo_csv']['tmp_name'];

        try {
            // Validar existencia de la Bodega
            $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre = 'Bodega de Tecnología' LIMIT 1");
            $stmt_bodega->execute();
            $bodega = $stmt_bodega->fetch(PDO::FETCH_ASSOC);

            if (!$bodega) {
                throw new Exception("Error de Configuración: No existe la 'Bodega de Tecnología' en la base de datos.");
            }

            $handle = fopen($archivo, "r");
            $pdo->beginTransaction();

            $stmt_eq = $pdo->prepare("INSERT INTO equipos (placa_ur, serial, marca, modelo, fecha_compra, modalidad, estado_maestro) VALUES (?, ?, ?, ?, ?, ?, 'Alta')");
            // Ajustado para coincidir con tu tabla bitacora real
            $stmt_bit = $pdo->prepare("INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, fecha_evento, tecnico_responsable, hostname) VALUES (?, ?, ?, ?, 'Ingreso', 'Bodega de TI', ?, ?, 'PENDIENTE')");

            $exitos = 0;
            
            // Detección de encabezado
            $primera_fila = fgetcsv($handle, 1000, ",");
            if ($primera_fila) {
                $check = strtolower(trim($primera_fila[0]));
                // Si NO parece un encabezado, procesarlo como dato
                if (!in_array($check, ['placa', 'id', 'ur', 'placa_ur', 'equipo'])) {
                    procesarFila($primera_fila, $stmt_eq, $stmt_bit, $bodega, $exitos);
                }
            }

            // Procesar resto
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                procesarFila($data, $stmt_eq, $stmt_bit, $bodega, $exitos);
            }
            
            // LÓGICA CORREGIDA: Solo commitear si hubo inserciones reales
            if ($exitos > 0) {
                $pdo->commit();
                $mensaje_exito = "✅ ¡Proceso Terminado! Se importaron <b>$exitos</b> equipos a la Bodega.";
            } else {
                $pdo->rollBack();
                $errores[] = "⚠️ No se encontraron datos válidos en el archivo. Verifica el formato.";
            }
            
            fclose($handle);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == '23000') {
                $errores[] = "⚠️ <b>Error de Duplicado:</b> Detectamos placas o seriales que ya existen en el sistema. Operación cancelada.";
            } else {
                $errores[] = "❌ Error de Base de Datos: " . $e->getMessage();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = "❌ Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Importar Equipos - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; --warning: #ffc107; --danger: #dc3545; --success: #28a745; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); padding: 20px; margin: 0; }
        
        /* Card Responsive */
        .import-card { 
            width: 100%; 
            max-width: 750px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            box-sizing: border-box; /* Evita desbordes */
        }

        .instruction-box { background: #fff8e1; border-left: 5px solid var(--warning); padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 0.95rem; }
        
        /* Tabla con Scroll Horizontal para Móviles */
        .table-responsive { overflow-x: auto; margin: 15px 0; border: 1px solid #ddd; }
        .csv-table { width: 100%; border-collapse: collapse; min-width: 500px; /* Fuerza el scroll en móviles */ }
        .csv-table th { background: #eee; padding: 10px; text-align: left; font-size: 0.85rem; color: #555; }
        .csv-table td { border-top: 1px solid #ddd; padding: 8px; font-size: 0.9rem; }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: 500; display: block; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* Input File estilizado */
        .file-upload-wrapper { position: relative; margin: 25px 0; }
        input[type="file"] { 
            width: 100%; 
            padding: 20px; 
            background: #f8f9fa; 
            border: 2px dashed var(--primary); 
            border-radius: 8px; 
            cursor: pointer; 
            box-sizing: border-box;
            transition: all 0.3s;
        }
        input[type="file"]:hover { background: #e9ecef; border-color: #0040a0; }

        .btn-import { 
            background: var(--primary); color: white; border: none; 
            padding: 15px; border-radius: 6px; cursor: pointer; width: 100%; 
            font-size: 1.1rem; font-weight: bold; transition: background 0.3s; 
        }
        .btn-import:hover { background: #001a42; }

        .btn-secondary { display: block; text-align: center; text-decoration: none; color: var(--primary); padding: 15px; margin-top: 10px; font-weight: 500; }

        /* Media Queries para Pantallas Pequeñas */
        @media (max-width: 600px) {
            body { padding: 10px; }
            .import-card { padding: 20px; }
            h2 { font-size: 1.5rem; }
            .csv-table th, .csv-table td { font-size: 0.8rem; padding: 6px; }
        }
    </style>
</head>
<body>

<div class="import-card">
    <h2 style="color:var(--primary); margin-top:0; text-align:center;">📥 Carga Masiva de Inventario</h2>
    
    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success"><?= $mensaje_exito ?></div>
    <?php endif; ?>

    <?php foreach ($errores as $error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endforeach; ?>

    <div class="instruction-box">
        <h3 style="margin-top:0; color: #856404; font-size:1.1rem;">⚠️ Instrucciones del CSV</h3>
        <p style="margin: 5px 0;">Sube un archivo <strong>.csv</strong> (separado por comas) con las siguientes columnas en orden exacto:</p>
        
        <div class="table-responsive">
            <table class="csv-table">
                <thead>
                    <tr>
                        <th>Placa</th><th>Serial</th><th>Marca</th><th>Modelo</th><th>Fecha</th><th>Modalidad</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>UR-1001</td><td>SN8822</td><td>HP</td><td>ProBook</td><td>25/10/2023</td><td>Leasing</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="font-size: 0.9rem; margin-top:10px;">
            <strong>📅 Formato Fecha:</strong> DIA/MES/AÑO (Ej: 31/12/2025). <br>
            El sistema convierte automáticamente a formato base de datos.
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="file-upload-wrapper">
            <label style="font-weight:bold; color:#444; margin-bottom:5px; display:block;">Selecciona tu archivo:</label>
            <input type="file" name="archivo_csv" accept=".csv" required>
        </div>
        <button type="submit" name="importar" class="btn-import">🚀 CARGAR INVENTARIO</button>
    </form>

    <a href="alta_equipos.php" class="btn-secondary">⬅ Volver al Registro Individual</a>
</div>

</body>
</html>