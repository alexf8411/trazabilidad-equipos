<?php
/**
 * public/auditoria.php
 * Visor de Logs de Acceso (Evidencia Forense)
 */
require_once '../core/session.php';
require_once '../core/db.php';

// 1. SEGURIDAD: Solo Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php?error=acceso_no_autorizado");
    exit;
}

// 2. CONSULTA: Traemos los últimos 100 accesos (Ordenados del más reciente al más antiguo)
try {
    $sql = "SELECT * FROM auditoria_acceso ORDER BY fecha DESC, hora DESC LIMIT 100";
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al leer logs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría de Acceso</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { margin: 0; color: #333; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #343a40; color: white; } /* Encabezado oscuro para logs */
        tr:hover { background-color: #f1f1f1; }
        
        .ip-tag { font-family: monospace; background: #e9ecef; padding: 2px 5px; border-radius: 4px; color: #d63384; }
        .date-tag { color: #666; font-size: 0.85rem; }
        
        .btn-back { text-decoration: none; color: #6c757d; font-weight: bold; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; }
        .btn-back:hover { background-color: #e2e6ea; }
    </style>
    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h2>📜 Bitácora de Accesos</h2>
            <small style="color: #666;">Últimos 100 eventos de inicio de sesión</small>
        </div>
        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Fecha y Hora</th>
                    <th>Usuario LDAP</th>
                    <th>Dirección IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color: #999;">#<?php echo $log['id_auditoria']; ?></td>
                    <td>
                        <strong><?php echo $log['fecha']; ?></strong> 
                        <span class="date-tag"><?php echo $log['hora']; ?></span>
                    </td>
                    <td>
                        👤 <?php echo htmlspecialchars($log['usuario_ldap']); ?>
                    </td>
                    <td>
                        <span class="ip-tag"><?php echo htmlspecialchars($log['ip_acceso']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>