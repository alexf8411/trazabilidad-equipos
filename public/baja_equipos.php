<?php
/**
 * URTRACK - Baja Masiva de Equipos
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ CSS centralizado en urtrack-styles.css
 * ✅ JavaScript separado en baja_equipos.js
 * ✅ Código limpio y modular
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Control de acceso
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos'])) {
    header('Location: dashboard.php');
    exit;
}

$error_msg = "";

// ============================================================================
// PROCESAMIENTO DEL FORMULARIO
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['seriales_raw'])) {
    $motivo = trim($_POST['motivo']);
    $tecnico = $_SESSION['nombre'];
    
    // Procesar seriales
    $raw_data = $_POST['seriales_raw'];
    $lista_seriales = preg_split('/\r\n|\r|\n/', $raw_data);
    $lista_seriales = array_filter(array_map('trim', $lista_seriales));
    
    $bajas_exitosas = [];
    $errores = [];

    if (count($lista_seriales) > 0) {
        // ID de Lote para agrupación
        $id_lote = date('YmdHis') . '-' . rand(100,999);
        
        // Buscar lugar de destino (Bodega)
        $stmt_lugar = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $lugar_defecto = $stmt_lugar->fetch(PDO::FETCH_ASSOC);

        $id_lugar_final = $lugar_defecto['id'] ?? 1;
        $sede_final = $lugar_defecto['sede'] ?? 'Sede Principal';

        foreach ($lista_seriales as $serial) {
            $serial = strtoupper($serial);
            
            try {
                $pdo->beginTransaction();
                
                // 1. Validar que existe y no está de baja
                $stmt_check = $pdo->prepare("SELECT estado_maestro FROM equipos WHERE serial = ?");
                $stmt_check->execute([$serial]);
                $equipo = $stmt_check->fetch();
                
                if (!$equipo) {
                    throw new Exception("No existe en DB");
                }
                
                if ($equipo['estado_maestro'] === 'Baja') {
                    throw new Exception("Ya estaba de Baja");
                }

                // 2. Actualizar estado maestro
                $pdo->prepare("UPDATE equipos SET estado_maestro = 'Baja' WHERE serial = ?")
                    ->execute([$serial]);
                
                // 3. Insertar en bitácora
                $sql_bit = "INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion,
                    tipo_evento, correo_responsable, tecnico_responsable,
                    hostname, fecha_evento, desc_evento
                ) VALUES (?, ?, ?, 'Disposición Final', 'Baja', 'Activos Fijos', ?, ?, NOW(), ?)";
                
                $pdo->prepare($sql_bit)->execute([
                    $serial, 
                    $id_lugar_final, 
                    $sede_final, 
                    $tecnico, 
                    "LOTE:$id_lote",
                    $motivo
                ]);
                
                $pdo->commit();
                $bajas_exitosas[] = $serial;
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores[] = "$serial: " . $e->getMessage();
            }
        }

        // Redirección si hubo éxito
        if (count($bajas_exitosas) > 0) {
            $_SESSION['acta_baja_seriales'] = $bajas_exitosas;
            $_SESSION['acta_baja_motivo'] = $motivo;
            $_SESSION['acta_baja_lote'] = $id_lote;
            $_SESSION['acta_baja_errores'] = $errores;
            
            header("Location: generar_acta_baja.php");
            exit;
        } else {
            $error_str = implode(", ", array_slice($errores, 0, 5));
            $error_msg = "❌ No se pudo procesar ninguna baja. Errores: $error_str";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baja de Activos - URTRACK</title>
    
    <!-- CSS EXTERNO -->
    <link rel="stylesheet" href="../css/urtrack-styles.css">
</head>
<body>

<div class="container">
    <div class="baja-card">
        <!-- Header -->
        <div class="baja-header">
            <h1>🗑️ Baja Masiva de Equipos</h1>
            <a href="dashboard.php" class="btn btn-outline">⬅ Volver</a>
        </div>

        <!-- Alerta de error -->
        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" id="formBaja">
            
            <!-- Motivo/Justificación -->
            <div class="form-group">
                <label for="motivo">Justificación Técnica (Acta / Ticket de Mesa de Ayuda) *</label>
                <input type="text" 
                       id="motivo"
                       name="motivo" 
                       required 
                       placeholder="Ej: Obsolescencia Tecnológica - Acta #2026-B05" 
                       autocomplete="off">
            </div>

            <!-- Listado de seriales -->
            <div class="form-group">
                <label for="seriales">Listado de Seriales *</label>
                <textarea id="seriales"
                          name="seriales_raw" 
                          required 
                          placeholder="5CD1234X&#10;5CD5678Y&#10;CNU9012Z"></textarea>
                <span class="hint">
                    ℹ️ Copie y pegue la columna de seriales directamente desde Excel. 
                    Los seriales se convertirán automáticamente a mayúsculas.
                </span>
            </div>

            <!-- Botón opcional para limpiar duplicados -->
            <div class="form-group" style="text-align: right; margin-bottom: 10px;">
                <button type="button" 
                        id="btn-limpiar-duplicados" 
                        class="btn btn-outline"
                        style="width: auto; padding: 8px 16px; font-size: 0.9rem;">
                    🧹 Limpiar Duplicados
                </button>
            </div>

            <!-- Botón de envío -->
            <button type="submit" class="btn-danger-submit">
                🚨 EJECUTAR BAJA Y GENERAR ACTA
            </button>
        </form>

        <!-- Advertencia adicional -->
        <div class="alert alert-warning" style="margin-top: 20px;">
            <strong>⚠️ ADVERTENCIA IMPORTANTE:</strong><br>
            Esta acción marcará los equipos como <strong>BAJA DEFINITIVA</strong> en el sistema.
            Solo un Administrador puede revertir esta operación.
        </div>
    </div>
</div>

<!-- JAVASCRIPT EXTERNO -->
<script src="../public/js/baja_equipos.js"></script>

</body>
</html>