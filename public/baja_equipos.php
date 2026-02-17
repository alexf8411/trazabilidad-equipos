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
    $motivo_baja = trim($_POST['motivo_baja']); // Select con el motivo
    $justificacion = trim($_POST['justificacion']); // Acta/Ticket
    $tecnico = $_SESSION['nombre'];
    
    // Combinar para el campo desc_evento
    $desc_evento_completo = "Motivo Baja: " . trim($motivo_baja);
    
    // Procesar seriales
    $raw_data = $_POST['seriales_raw'];
    $lista_seriales = preg_split('/\r\n|\r|\n/', $raw_data);
    $lista_seriales = array_filter(array_map('trim', $lista_seriales));
    
    $bajas_exitosas = [];
    $errores = [];

    if (count($lista_seriales) > 0) {
        // ID de Lote para agrupación
        $id_lote = date('YmdHis') . '-' . rand(100,999);
        
        // Buscar lugar de destino (Bodega) — query exacta, sin LIKE peligroso
        $stmt_lugar = $pdo->prepare("SELECT id FROM lugares WHERE nombre = ? LIMIT 1");
        $stmt_lugar->execute(['Bodega de Tecnología']);
        $lugar_defecto = $stmt_lugar->fetch(PDO::FETCH_ASSOC);

        if (!$lugar_defecto) {
            throw new Exception("ERROR CRÍTICO: No existe 'Bodega de Tecnología' en la base de datos");
        }

        $id_lugar_final = $lugar_defecto['id'];

        foreach ($lista_seriales as $serial) {
            $serial = strtoupper($serial);
            
            try {
                $pdo->beginTransaction();
                
                // 1. Validar que existe y no está de baja
                $stmt_check = $pdo->prepare("SELECT placa_ur, estado_maestro FROM equipos WHERE serial = ?");
                $stmt_check->execute([$serial]);
                $equipo = $stmt_check->fetch();

                $placa_ur = $equipo['placa_ur'] ?? $serial; // Extraer placa para auditoría
                
                if (!$equipo) {
                    throw new Exception("No existe en DB");
                }
                
                if ($equipo['estado_maestro'] === 'Baja') {
                    throw new Exception("Ya estaba de Baja");
                }

                // 2. Actualizar estado maestro
                $pdo->prepare("UPDATE equipos SET estado_maestro = 'Baja' WHERE serial = ?")
                    ->execute([$serial]);
                
                // AUDITORÍA — Registrar baja del equipo
                try {
                    $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
                    $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
                    $usuario_rol    = $_SESSION['rol']        ?? 'Soporte';
                    $ip_cliente     = $_SERVER['REMOTE_ADDR'];
                    
                    $pdo->prepare("INSERT INTO auditoria_cambios 
                        (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                        tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                        VALUES (NOW(), ?, ?, ?, ?, 'BAJA_EQUIPO', 'equipos', ?, 'Alta', 'Baja')")
                        ->execute([
                            $usuario_ldap,
                            $usuario_nombre,
                            $usuario_rol,
                            $ip_cliente,
                            "Equipo: $placa_ur (Motivo: $motivo_baja)"
                        ]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría baja equipo: " . $e->getMessage());
                }
                
                // 3. Insertar en bitácora con desc_evento completo
                $sql_bit = "INSERT INTO bitacora (
                    serial_equipo, id_lugar,
                    tipo_evento, correo_responsable, tecnico_responsable,
                    hostname, fecha_evento, desc_evento
                ) VALUES (?, ?, 'Baja', 'Activos Fijos', ?, ?, NOW(), ?)";
                
                $pdo->prepare($sql_bit)->execute([
                    $serial, 
                    $id_lugar_final,
                    $tecnico, 
                    "LOTE:$id_lote",
                    $desc_evento_completo
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
            $_SESSION['acta_baja_motivo'] = $motivo_baja;
            $_SESSION['acta_baja_justificacion'] = $justificacion;
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
            
            <!-- Motivo de la Baja (Select) -->
            <div class="form-group">
                <label for="motivo_baja">Motivo de la Baja *</label>
                <select id="motivo_baja" name="motivo_baja" required>
                    <option value="">-- Seleccionar Motivo --</option>
                    <option value="Robo o pérdida del equipo">Robo o pérdida del equipo</option>
                    <option value="Daño del equipo">Daño del equipo</option>
                    <option value="Obsolescencia del equipo">Obsolescencia del equipo</option>
                </select>
            </div>

            <!-- Justificación Técnica (Acta/Ticket) -->
            <div class="form-group">
                <label for="justificacion">Justificación Técnica (Acta / Ticket) *</label>
                <input type="text" 
                       id="justificacion"
                       name="justificacion" 
                       required 
                       placeholder="Ej: Acta #2026-B05, Ticket #12345" 
                       autocomplete="off">
                <span class="hint">
                    ℹ️ Ingrese el número de acta o ticket que respalda esta baja
                </span>
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