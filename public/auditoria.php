<?php
/**
 * public/auditoria.php
 * Centro de Evidencia Forense - Versión 2.0
 * Actualizado con nueva estructura de tablas
 */
require_once '../core/session.php';
require_once '../core/db.php';

// SEGURIDAD: Solo Administradores y Auditores
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor'])) {
    header("Location: dashboard.php");
    exit;
}

// CONSULTAS DE DATOS
try {
    // A. Logs de Acceso (Últimos 100)
    $stmt_login = $pdo->query("
        SELECT * FROM auditoria_acceso 
        ORDER BY fecha_hora DESC 
        LIMIT 100
    ");
    $logs_login = $stmt_login->fetchAll();

    // B. Logs de Cambios Administrativos (Últimos 100)
    $stmt_cambios = $pdo->query("
        SELECT * FROM auditoria_cambios 
        ORDER BY fecha DESC 
        LIMIT 100
    ");
    $logs_cambios = $stmt_cambios->fetchAll();

} catch (PDOException $e) {
    $error = "Error de conexión: " . $e->getMessage();
}

// Función para badge de resultado
function getBadgeResultado($resultado) {
    $badges = [
        'Login_Exitoso'     => '<span class="badge badge-success">✓ Exitoso</span>',
        'Login_Fallido'     => '<span class="badge badge-danger">✗ Fallido</span>',
        'Acceso_Denegado'   => '<span class="badge badge-warning">⊘ Denegado</span>',
        'Cuenta_Bloqueada'  => '<span class="badge badge-alert">🔒 Bloqueada</span>',
        'Password_Expirado' => '<span class="badge badge-info">⏰ Expirado</span>',
    ];
    return $badges[$resultado] ?? '<span class="badge">' . $resultado . '</span>';
}

// Función para badge de tipo de acción
function getBadgeTipo($tipo) {
    $badges = [
        'EDICION_EQUIPO'        => '<span class="badge badge-primary">✏️ Edición</span>',
        'BAJA_EQUIPO'           => '<span class="badge badge-danger">⬇️ Baja</span>',
        'REVERSION_BAJA'        => '<span class="badge badge-warning">↩️ Reversión</span>',
        'IMPORTACION_CSV'       => '<span class="badge badge-info">📥 Importación</span>',
        'CAMBIO_USUARIO'        => '<span class="badge badge-primary">👤 Usuario</span>',
        'CAMBIO_LUGAR'          => '<span class="badge badge-primary">🏢 Lugar</span>',
        'CAMBIO_CONFIGURACION'  => '<span class="badge badge-alert">⚙️ Config</span>',
    ];
    return $badges[$tipo] ?? '<span class="badge">' . $tipo . '</span>';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría Integral - URTRACK</title>
    <link rel="stylesheet" href="css/urtrack-styles.css">
    <style>
        .audit-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn {
            padding: 12px 24px; cursor: pointer; background: #f1f3f5; border: none;
            border-radius: 8px 8px 0 0; font-weight: 600; color: #495057; transition: all 0.2s;
            font-size: 14px;
        }
        .tab-btn.active { background: white; color: #002D72; border-bottom: 3px solid #002D72; }
        .tab-btn:hover:not(.active) { background: #e9ecef; }
        
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }

        .audit-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 15px; }
        .audit-table th { background: #343a40; color: white; padding: 12px; text-align: left; font-weight: 600; }
        .audit-table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; }
        .audit-table tr:hover { background-color: #f8f9fa; }

        .badge {
            display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; 
            font-weight: 600; text-transform: uppercase; white-space: nowrap;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-alert { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-primary { background: #cfe2ff; color: #084298; }

        .ip-tag { background: #e2e3e5; color: #383d41; padding: 3px 8px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        
        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; font-size: 13px; }
        
        .btn-export { 
            background: #28a745; color: white; padding: 8px 16px; border: none; 
            border-radius: 4px; cursor: pointer; font-weight: 600; text-decoration: none;
            display: inline-block; margin-bottom: 15px;
        }
        .btn-export:hover { background: #218838; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<div class="audit-container">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>📜 Auditoría y Evidencia Forense</h1>
            <p>Sistema de Trazabilidad URTRACK - ISO 27001 Compliant</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline">⬅ Volver al Dashboard</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php else: ?>

        <div class="tabs">
            <div class="tab-btn active" onclick="openTab(event, 'tab-cambios')">🛠️ Cambios Administrativos (<?= count($logs_cambios) ?>)</div>
            <div class="tab-btn" onclick="openTab(event, 'tab-accesos')">🔑 Accesos al Sistema (<?= count($logs_login) ?>)</div>
        </div>

        <!-- TAB 1: CAMBIOS ADMINISTRATIVOS -->
        <div id="tab-cambios" class="tab-content active">
            <div class="info-box">
                ⚠️ <strong>Nota:</strong> Registra todas las modificaciones críticas: equipos, usuarios, lugares y configuración del sistema.
            </div>

            <a href="#" class="btn-export" onclick="exportarCSV('cambios'); return false;">📥 Exportar a CSV</a>

            <table class="audit-table" id="tabla-cambios">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                        <th>Referencia</th>
                        <th>Cambios</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs_cambios) == 0): ?>
                        <tr><td colspan="7" style="text-align:center; color:#999; padding: 40px;">No hay registros de cambios.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($logs_cambios as $log): ?>
                    <tr>
                        <td style="white-space:nowrap; font-size: 12px;">
                            <?= date('d/m/Y H:i', strtotime($log['fecha'])) ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($log['usuario_nombre'] ?? $log['usuario_ldap']) ?></strong><br>
                            <small style="color:#666;"><?= htmlspecialchars($log['usuario_ldap']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($log['usuario_rol']) ?></td>
                        <td><?= getBadgeTipo($log['tipo_accion']) ?></td>
                        <td style="font-size: 12px;">
                            <strong><?= htmlspecialchars($log['referencia']) ?></strong>
                            <?php if ($log['tabla_afectada']): ?>
                                <br><small style="color:#999;">Tabla: <?= htmlspecialchars($log['tabla_afectada']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 300px; font-size: 12px;">
                            <?php if ($log['valor_anterior']): ?>
                                <div style="color: #dc3545;">❌ <?= htmlspecialchars($log['valor_anterior']) ?></div>
                            <?php endif; ?>
                            <?php if ($log['valor_nuevo']): ?>
                                <div style="color: #28a745;">✓ <?= htmlspecialchars($log['valor_nuevo']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="ip-tag"><?= htmlspecialchars($log['ip_origen'] ?? 'N/A') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TAB 2: ACCESOS AL SISTEMA -->
        <div id="tab-accesos" class="tab-content">
            <div class="info-box">
                🔐 <strong>Nota:</strong> Registra todos los intentos de acceso: exitosos, fallidos, denegados y cuentas bloqueadas.
            </div>

            <a href="#" class="btn-export" onclick="exportarCSV('accesos'); return false;">📥 Exportar a CSV</a>

            <table class="audit-table" id="tabla-accesos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Resultado</th>
                        <th>IP Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_login as $log): ?>
                    <tr>
                        <td style="color: #999;">#<?= $log['id_log'] ?></td>
                        <td style="white-space:nowrap; font-size: 12px;">
                            <?= date('d/m/Y H:i:s', strtotime($log['fecha_hora'])) ?>
                        </td>
                        <td><strong><?= htmlspecialchars($log['usuario_ldap']) ?></strong></td>
                        <td><?= htmlspecialchars($log['usuario_nombre'] ?? 'N/A') ?></td>
                        <td><?= getBadgeResultado($log['resultado']) ?></td>
                        <td><span class="ip-tag"><?= htmlspecialchars($log['ip_acceso']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script>
function openTab(evt, tabName) {
    var tabcontent = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    
    var tablinks = document.getElementsByClassName("tab-btn");
    for (var i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

function exportarCSV(tipo) {
    var tabla = tipo === 'cambios' ? 'tabla-cambios' : 'tabla-accesos';
    var csv = [];
    var rows = document.querySelectorAll('#' + tabla + ' tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) {
            var texto = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + texto + '"');
        }
        csv.push(row.join(','));
    }
    
    var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    var downloadLink = document.createElement('a');
    downloadLink.download = 'auditoria_' + tipo + '_' + new Date().toISOString().slice(0,10) + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

</body>
</html>