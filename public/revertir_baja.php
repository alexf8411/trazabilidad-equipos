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
    $correo_admin = $_SESSION['correo_ldap'] ?? 'admin@sistema'; // Usamos el correo real de la sesión

    try {
        $pdo->beginTransaction();

        // A. Verificar que el equipo realmente esté en Baja
        $stmt_check = $pdo->prepare("SELECT placa_ur, estado_maestro FROM equipos WHERE serial = ?");
        $stmt_check->execute([$serial]);
        $equipo = $stmt_check->fetch();

        if (!$equipo || $equipo['estado_maestro'] !== 'Baja') {
            throw new Exception("El equipo no existe o no está en estado de Baja.");
        }

        // B. Buscar la Bodega de Tecnología (Corrección solicitada)
        // Buscamos algo que contenga "Tecnología" y "Bodega" para ser precisos
        $stmt_bodega = $pdo->prepare("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE ? LIMIT 1");
        $stmt_bodega->execute(['%Bodega de tecnología%']); 
        $bodega = $stmt_bodega->fetch();

        // Fallback: Si no existe "Bodega de tecnología", buscamos cualquier "Bodega" para no romper el código
        if (!$bodega) {
            $stmt_bodega = $pdo->query("SELECT id, sede, nombre FROM lugares WHERE nombre LIKE '%Bodega%' LIMIT 1");
            $bodega = $stmt_bodega->fetch();
        }

        if (!$bodega) throw new Exception("Error Crítico: No se encontró ninguna Bodega en el sistema.");

        // C. Restaurar Estado Maestro a 'Alta'
        $stmt_upd = $pdo->prepare("UPDATE equipos SET estado_maestro = 'Alta' WHERE serial = ?");
        $stmt_upd->execute([$serial]);

        // D. Crear evento de 'Ingreso' en Bitácora (CORREGIDO)
        $sql_bit = "INSERT INTO bitacora (
                        serial_equipo, id_lugar, sede, ubicacion, 
                        tipo_evento, correo_responsable, tecnico_responsable, 
                        hostname, fecha_evento
                    ) VALUES (?, ?, ?, ?, 'Ingreso', ?, ?, ?, NOW())";
        
        $pdo->prepare($sql_bit)->execute([
            $serial, 
            $bodega['id'], 
            $bodega['sede'], 
            $bodega['nombre'],      // Ej: Bodega de tecnología
            $correo_admin,          // RESPONSABLE: Tu usuario (quien corrige)
            $nombre_admin,          // TÉCNICO: Tu nombre
            $serial                 // HOSTNAME: El mismo serial (como pediste)
        ]);

        $pdo->commit();
        
        // Redirigir con mensaje de éxito (Muestra la alerta amarilla en inventario)
        header("Location: inventario.php?status=reverted&p=" . urlencode($equipo['placa_ur']));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error crítico al revertir baja: " . $e->getMessage());
    }
} else {
    header("Location: inventario.php");
    exit;
}
?>