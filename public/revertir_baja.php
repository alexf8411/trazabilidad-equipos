<?php
/**
 * public/revertir_baja.php
 * Módulo Forense: Reversión de Bajas
 * Corregido: Asignación dinámica de responsable y ubicación.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD ESTRICTA (Solo Admin)
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado. Este incidente ha sido reportado.");
}

if (isset($_GET['serial'])) {
    $serial = $_GET['serial'];
    
    // DATOS DEL USUARIO QUE CORRIGE (TÚ)
    $nombre_admin = $_SESSION['nombre']; 
    $correo_admin = $_SESSION['usuario_id'] ?? $_SESSION['nombre'] ?? 'Administrador';

    try {
        $pdo->beginTransaction();

        // 1. Verificar estado actual
        $stmt_check = $pdo->prepare("SELECT placa_ur, estado_maestro FROM equipos WHERE serial = ?");
        $stmt_check->execute([$serial]);
        $equipo = $stmt_check->fetch();

        if (!$equipo || $equipo['estado_maestro'] !== 'Baja') {
            throw new Exception("El equipo no existe o no está en estado de Baja.");
        }

        // 2. Buscar Bodega de Tecnología
        $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE ? LIMIT 1");
        $stmt_bodega->execute(['%Bodega de tecnología%']); 
        $bodega = $stmt_bodega->fetch();
        
        if (!$bodega) { // Fallback de seguridad
            $stmt_bodega = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
            $bodega = $stmt_bodega->fetch();
        }
        if (!$bodega) throw new Exception("Error Crítico: No se encontró la Bodega de Tecnología.");

        // 3. Restaurar equipo (UPDATE)
        $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Alta' WHERE serial = ?");
        $stmt_upd->execute([$serial]);

        // =================================================================================
        // 4. REGISTRO EN AUDITORÍA DE CAMBIOS (TABLA: auditoria_cambios)
        // =================================================================================
        $sql_audit = "INSERT INTO auditoria_cambios (
                        usuario_responsable, 
                        tipo_accion, 
                        referencia, 
                        detalles, 
                        ip_origen, 
                        fecha
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
        
        $pdo->prepare($sql_audit)->execute([
            $correo_admin,                      // usuario_responsable
            'UPDATE REVERT',                    // tipo_accion
            "Equipo: $serial",                  // referencia (Serial afectado)
            "Reversión administrativa de Baja a Alta. Placa: " . $equipo['placa_ur'], // detalles
            $_SERVER['REMOTE_ADDR']             // ip_origen
        ]);

        // =================================================================================
        // 5. REGISTRO EN BITÁCORA (TABLA: bitacora)
        // =================================================================================
        $sql_bit = "INSERT INTO bitacora (
                        serial_equipo, id_lugar, sede, ubicacion, 
                        tipo_evento, correo_responsable, tecnico_responsable, 
                        hostname, fecha_evento, desc_evento
                    ) VALUES (?, ?, ?, ?, 'Alta', ?, ?, ?, NOW(), ?)";
        
        $pdo->prepare($sql_bit)->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'],
            $correo_admin,      // Responsable
            $nombre_admin,      // Técnico
            $serial,            // Hostname
            'Reversión de baja administrativa por ' . $nombre_admin // desc_evento (OBLIGATORIO)
        ]);

        $pdo->commit();
        
        // Redirigir
        header("Location: inventario.php?status=reverted&p=" . urlencode($equipo['placa_ur']));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error crítico: " . $e->getMessage());
    }
} else {
    header("Location: inventario.php");
    exit;
}
?>