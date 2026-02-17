<?php
/**
 * public/revertir_baja.php
 * Módulo Forense: Reversión de Bajas
 * Corregido: Query exacta para bodega + eliminado sede/ubicacion redundantes.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD ESTRICTA (Solo Admin)
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado. Este incidente ha sido reportado.");
}

if (isset($_GET['serial'])) {
    $serial = $_GET['serial'];
    
    // DATOS DEL USUARIO QUE CORRIGE
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

        // 2. Buscar Bodega de Tecnología — query exacta, sin LIKE peligroso
        $stmt_bodega = $pdo->prepare("SELECT id FROM lugares WHERE nombre = ? LIMIT 1");
        $stmt_bodega->execute(['Bodega de Tecnología']);
        $bodega = $stmt_bodega->fetch();

        if (!$bodega) {
            throw new Exception("Error Crítico: No se encontró 'Bodega de Tecnología' en la base de datos.");
        }

        // 3. Restaurar equipo (UPDATE)
        $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Alta' WHERE serial = ?");
        $stmt_upd->execute([$serial]);

        // =========================================================================
        // 4. REGISTRO EN AUDITORÍA DE CAMBIOS (TABLA: auditoria_cambios)
        // =========================================================================
       // AUDITORÍA — Registrar reversión
        try {
            $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
            $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
            $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
            $ip_cliente     = $_SERVER['REMOTE_ADDR'];
            
            $pdo->prepare("INSERT INTO auditoria_cambios 
                (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                VALUES (NOW(), ?, ?, ?, ?, 'REVERSION_BAJA', 'equipos', ?, 'Baja', 'Alta')")
                ->execute([
                    $usuario_ldap,
                    $usuario_nombre,
                    $usuario_rol,
                    $ip_cliente,
                    "Equipo: " . htmlspecialchars($equipo['placa_ur'])
                ]);
        } catch (Exception $e) {
            error_log("Fallo auditoría reversión: " . $e->getMessage());
        }

        // =========================================================================
        // 5. REGISTRO EN BITÁCORA (TABLA: bitacora)
        // =========================================================================
        $sql_bit = "INSERT INTO bitacora (
                        serial_equipo, id_lugar,
                        tipo_evento, correo_responsable, tecnico_responsable, 
                        hostname, fecha_evento, desc_evento
                    ) VALUES (?, ?, 'Alta', ?, ?, ?, NOW(), ?)";
        
        $pdo->prepare($sql_bit)->execute([
            $serial, 
            $bodega['id'],
            $correo_admin,
            $nombre_admin,
            $serial,
            'Reversión de baja administrativa por ' . $nombre_admin
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