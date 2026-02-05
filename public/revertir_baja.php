<?php
/**
 * public/revertir_baja.php
 * Módulo Forense: Reversión de Bajas
 * Exclusivo para Administradores
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD ESTRICTA (Solo Admin)
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado. Este incidente ha sido reportado.");
}

if (isset($_GET['serial'])) {
    $serial = $_GET['serial'];
    $admin = $_SESSION['nombre'];
    $ip = $_SERVER['REMOTE_ADDR'];

    try {
        $pdo->beginTransaction();

        // A. Verificar que el equipo realmente esté en Baja
        $stmt_check = $pdo->prepare("SELECT placa_ur, estado_maestro FROM equipos WHERE serial = ?");
        $stmt_check->execute([$serial]);
        $equipo = $stmt_check->fetch();

        if (!$equipo || $equipo['estado_maestro'] !== 'Baja') {
            throw new Exception("El equipo no existe o no está en estado de Baja.");
        }

        // B. Buscar la Bodega para reingresarlo allí
        $stmt_bodega = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
        $bodega = $stmt_bodega->fetch();
        if (!$bodega) throw new Exception("No se encontró la Bodega de Tecnología.");

        // C. Restaurar Estado Maestro
        $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Alta' WHERE serial = ?");
        $stmt_upd->execute([$serial]);

        // D. Crear evento de 'Ingreso' en Bitácora (Corrección)
        $sql_bit = "INSERT INTO bitacora (
                        serial_equipo, id_lugar, sede, ubicacion, 
                        tipo_evento, correo_responsable, tecnico_responsable, 
                        hostname, fecha_evento
                    ) VALUES (?, ?, ?, ?, 'Ingreso', ?, ?, ?, NOW())";
        
        $pdo->prepare($sql_bit)->execute([
            $serial, $bodega['id'], $bodega['sede'], $bodega['nombre'],
            'Corrección Administrativa', // Responsable lógico
            $admin,
            'CORRECCION'
        ]);

        // E. INSERTAR EVIDENCIA EN AUDITORÍA (Lo que pediste)
        // Asumo que la tabla auditoria_cambios tiene esta estructura basada en tu código anterior
        $sql_audit = "INSERT INTO auditoria_cambios (
                        fecha, usuario_responsable, tipo_accion, 
                        referencia, detalles, ip_origen
                      ) VALUES (NOW(), ?, 'REVERSION_BAJA', ?, ?, ?)";
        
        $detalles = "El administrador revirtió la baja del activo Placa: " . $equipo['placa_ur'];
        
        $pdo->prepare($sql_audit)->execute([
            $admin,
            $serial,
            $detalles,
            $ip
        ]);

        $pdo->commit();
        
        // Redirigir con mensaje de éxito
        header("Location: inventario.php?status=reverted&p=" . $equipo['placa_ur']);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error crítico al revertir baja: " . $e->getMessage());
    }
} else {
    header("Location: inventario.php");
}
?>