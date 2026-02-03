<?php
/**
 * public/auditoria.php
 * Centro de Evidencia Forense (Accesos + Cambios de Datos)
 */
require_once '../core/session.php';
require_once '../core/db.php';

// 1. SEGURIDAD: Solo Administradores y Auditores
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor'])) {
    header("Location: dashboard.php");
    exit;
}

// 2. CONSULTAS DE DATOS
try {
    // A. Logs de Acceso (Últimos 50)
    $stmt_login = $pdo->query("SELECT * FROM auditoria_acceso ORDER BY fecha DESC, hora DESC LIMIT 50");
    $logs_login = $stmt_login->fetchAll();

    // B. Logs de Cambios Administrativos (Últimos 50)
    $stmt_cambios = $pdo->query("SELECT * FROM auditoria_cambios ORDER BY fecha DESC LIMIT 50");
    $logs_cambios = $stmt_cambios->fetchAll();

} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría Integral - URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; --tab-active: #fff; --tab-inactive: #e9ecef; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        h2 { margin: 0; color: var(--primary); }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; }

        /* Estilos de Pestañas (Tabs) */
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid #ccc; }
        .tab-btn {
            padding: 10px 20px; cursor: pointer; background: var(--tab-inactive); border: 1px solid #ccc; border-bottom: none;
            border-radius: 5px 5px 0 0; font-weight: bold; color: #666; transition: all 0.2s;
        }
        .tab-btn.active { background: var(--tab-active); color: var(--primary); border-top: 3px solid var(--primary); border-bottom: 1px solid white; margin-bottom: -1px; }
        
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }

        /* Tablas */
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #343a40; color: white; }
        tr:hover { background-color: #f8f9fa; }

        /* Etiquetas */
        .tag { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; font-family: monospace; }
        .tag-ip { background: #e2e3e5; color: #333; }
        .tag-accion { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .diff-text { font-family: monospace; color: #d63384; background: #fff0f6; padding: 2px 5px; border-radius: 3px; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h2>📜 Auditoría y Evidencia Forense</h2>
            <small style="color: #666;">Sistema de Trazabilidad URTRACK</small>
        </div>
        <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;"><?= $error ?></div>
    <?php else: ?>

        <div class="tabs">
            <div class="tab-btn active" onclick="openTab(event, 'tab-cambios')">🛠️ Cambios Administrativos</div>
            <div class="tab-btn" onclick="openTab(event, 'tab-accesos')">🔑 Accesos al Sistema</div>
        </div>

        <div id="tab-cambios" class="tab-content active">
            <div style="background: #fff3cd; padding: 10px; margin-bottom: 15px; border-left: 4px solid #ffc107; font-size: 0.9rem;">
                ⚠️ <strong>Nota:</strong> Esta sección registra modificaciones críticas en la información maestra de los equipos (Placas, Seriales, etc).
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Responsable</th>
                        <th>Acción / Referencia</th>
                        <th>Detalle del Cambio</th>
                        <th>IP Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs_cambios) == 0): ?>
                        <tr><td colspan="5" style="text-align:center; color:#999;">No hay registros de cambios recientes.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($logs_cambios as $log): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= $log['fecha'] ?></td>
                        <td><strong><?= htmlspecialchars($log['usuario_responsable']) ?></strong></td>
                        <td>
                            <span class="tag tag-accion"><?= $log['tipo_accion'] ?></span><br>
                            <small><?= htmlspecialchars($log['referencia']) ?></small>
                        </td>
                        <td style="color: #555;">
                            <?= htmlspecialchars($log['detalles']) ?>
                        </td>
                        <td><span class="tag tag-ip"><?= $log['ip_origen'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="tab-accesos" class="tab-content">
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
                    <?php foreach ($logs_login as $log): ?>
                    <tr>
                        <td style="color: #999;">#<?= $log['id_auditoria'] ?></td>
                        <td>
                            <strong><?= $log['fecha'] ?></strong> 
                            <small style="color:#666; margin-left:5px;"><?= $log['hora'] ?></small>
                        </td>
                        <td>👤 <?= htmlspecialchars($log['usuario_ldap']) ?></td>
                        <td><span class="tag tag-ip"><?= htmlspecialchars($log['ip_acceso']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script>
    function openTab(evt, tabName) {
        // 1. Ocultar todos los contenidos
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        
        // 2. Desactivar todos los botones
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        
        // 3. Mostrar el actual y activar botón
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
</script>

</body>
</html>