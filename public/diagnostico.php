<?php
/**
 * URTRACK - Centro de Diagnóstico Seguro
 * Versión 3.0 - Solo para Administradores
 */

require_once '../core/db.php';
require_once '../core/session.php';

// SOLO Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    die('Acceso denegado. Solo Administradores.');
}

// Límites de seguridad
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// Cargar configuración de forma segura
$configFile = '../core/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

$diagnostico = [];
$alertas = [];
$recomendaciones = [];

// ============================================================================
// 1. PRUEBA DE BASE DE DATOS
// ============================================================================
try {
    $start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) FROM equipos");
    $query_time = round((microtime(true) - $start) * 1000, 2);
    
    $diagnostico['db'] = [
        'status' => 'online',
        'response_time' => $query_time . ' ms',
        'connection' => 'PDO MySQL',
    ];
    
    if ($query_time > 100) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensaje' => "La BD responde lento ({$query_time}ms). Verificar carga del servidor."
        ];
    }
} catch (Exception $e) {
    $diagnostico['db'] = [
        'status' => 'error',
        'mensaje' => 'No se pudo conectar a la base de datos'
    ];
    $alertas[] = [
        'tipo' => 'danger',
        'mensaje' => 'Base de datos OFFLINE - Sistema no operativo'
    ];
}

// ============================================================================
// 2. PRUEBA DE LDAP (SIN MOSTRAR CREDENCIALES)
// ============================================================================
if (isset($config['ldap']['host'])) {
    $ldap_host = $config['ldap']['host'];
    $ldap_port = $config['ldap']['port'] ?? 389;
    
    $conn = @ldap_connect($ldap_host, $ldap_port);
    
    if ($conn) {
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3);
        
        $test_bind = @ldap_bind($conn);
        
        if ($test_bind || ldap_errno($conn) == 49) {
            $diagnostico['ldap'] = [
                'status' => 'online',
                'host' => $ldap_host,
                'mensaje' => 'Servidor LDAP responde correctamente'
            ];
        } else {
            $diagnostico['ldap'] = [
                'status' => 'warning',
                'mensaje' => 'Servidor LDAP no responde. Verificar firewall/VPN.'
            ];
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => 'LDAP no disponible - Las búsquedas de usuarios pueden fallar'
            ];
        }
        ldap_close($conn);
    } else {
        $diagnostico['ldap'] = [
            'status' => 'offline',
            'mensaje' => 'No se puede alcanzar el servidor LDAP'
        ];
    }
} else {
    $diagnostico['ldap'] = [
        'status' => 'not_configured',
        'mensaje' => 'LDAP no configurado en config.json'
    ];
}

// ============================================================================
// 3. PRUEBA DE SMTP
// ============================================================================
if (isset($config['mail']['smtp_user'])) {
    $smtp_configured = !empty($config['mail']['smtp_user']) && !empty($config['mail']['smtp_pass']);
    
    if ($smtp_configured) {
        $socket = @fsockopen('smtp.office365.com', 587, $errno, $errstr, 3);
        
        if ($socket) {
            $diagnostico['smtp'] = [
                'status' => 'online',
                'host' => 'smtp.office365.com:587',
                'mensaje' => 'Puerto SMTP accesible'
            ];
            fclose($socket);
        } else {
            $diagnostico['smtp'] = [
                'status' => 'offline',
                'mensaje' => 'Puerto 587 bloqueado. Verificar firewall.'
            ];
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => 'SMTP bloqueado - Los correos de notificación no llegarán'
            ];
        }
    } else {
        $diagnostico['smtp'] = [
            'status' => 'not_configured',
            'mensaje' => 'Credenciales SMTP incompletas'
        ];
    }
} else {
    $diagnostico['smtp'] = [
        'status' => 'not_configured',
        'mensaje' => 'SMTP no configurado'
    ];
}

// ============================================================================
// 4. MÉTRICAS DE BASE DE DATOS
// ============================================================================
try {
    $total_equipos = $pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn();
    $activos = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Alta'")->fetchColumn();
    $total_bitacora = $pdo->query("SELECT COUNT(*) FROM bitacora")->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT INDEX_NAME) as total_indices
        FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE()
        AND TABLE_NAME = 'bitacora'
    ");
    $indices_bitacora = $stmt->fetchColumn();
    
    $diagnostico['metricas'] = [
        'total_equipos' => number_format($total_equipos),
        'equipos_activos' => number_format($activos),
        'registros_bitacora' => number_format($total_bitacora),
        'indices_bitacora' => $indices_bitacora
    ];
    
    if ($total_bitacora > 100000) {
        $recomendaciones[] = [
            'tipo' => 'info',
            'mensaje' => "La bitácora tiene {$total_bitacora} registros. Considerar archivar eventos antiguos (>2 años)."
        ];
    }
    
    if ($indices_bitacora < 4) {
        $recomendaciones[] = [
            'tipo' => 'warning',
            'mensaje' => "La tabla bitácora tiene pocos índices ({$indices_bitacora}). Podría afectar rendimiento."
        ];
    }
    
} catch (Exception $e) {
    $diagnostico['metricas'] = ['error' => 'No se pudieron obtener métricas'];
}

// ============================================================================
// 5. ALERTAS DE SEGURIDAD
// ============================================================================
try {
    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bitacora WHERE DATE(fecha_evento) = ?");
    $stmt->execute([$hoy]);
    $movimientos_hoy = $stmt->fetchColumn();
    
    $diagnostico['actividad_hoy'] = $movimientos_hoy;
    
    if ($movimientos_hoy == 0) {
        $recomendaciones[] = [
            'tipo' => 'info',
            'mensaje' => 'No ha habido movimientos hoy. Sistema en espera.'
        ];
    }
    
} catch (Exception $e) {
    // Silenciar errores de alertas
}

// ============================================================================
// 6. ESTADO DE ARCHIVOS CRÍTICOS
// ============================================================================
$archivos_criticos = [
    '../core/config.json' => 'Configuración',
    '../core/db.php' => 'Conexión BD',
    '../core/session.php' => 'Seguridad',
    '../core/config_crypto.php' => 'Módulo de Cifrado',
];

$diagnostico['archivos'] = [];
foreach ($archivos_criticos as $path => $nombre) {
    $existe = file_exists($path);
    $size = $existe ? filesize($path) : 0;
    
    $diagnostico['archivos'][] = [
        'nombre' => $nombre,
        'status' => $existe ? 'ok' : 'missing',
        'size' => $size > 0 ? round($size / 1024, 2) . ' KB' : 'N/A'
    ];
    
    if (!$existe) {
        $alertas[] = [
            'tipo' => 'danger',
            'mensaje' => "Archivo crítico faltante: {$nombre}"
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Diagnóstico - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    <style>
        .diag-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid;
        }
        
        .status-card.online { border-color: #28a745; }
        .status-card.warning { border-color: #ffc107; }
        .status-card.offline { border-color: #dc3545; }
        .status-card.not_configured { border-color: #6c757d; }
        
        .status-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .status-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .status-detail {
            font-size: 0.85rem;
            color: #666;
        }
        
        .alert-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-box.danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-box.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .alert-box.info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .metric-label {
            font-weight: 600;
            color: #555;
        }
        
        .metric-value {
            font-family: monospace;
            color: var(--primary-color);
            font-weight: bold;
        }

        .section-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .section-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="diag-header">
        <h1 style="margin: 0 0 10px 0;">🏥 Centro de Diagnóstico</h1>
        <p style="margin: 0; opacity: 0.9;">Monitoreo de salud del sistema URTRACK</p>
    </div>
    
    <!-- Botones -->
    <div style="display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap;">
        <a href="diagnostico.php" class="btn btn-primary">🔄 Refrescar</a>
        <a href="escaner_db.php" class="btn btn-outline">🔍 Escáner BD</a>
        <a href="syscheck.php" class="btn btn-outline">🐧 System Check</a>
        <a href="configuracion.php" class="btn btn-outline">⬅ Volver a Configuración</a>
    </div>
    
    <!-- ALERTAS CRÍTICAS -->
    <?php if (count($alertas) > 0): ?>
        <div class="section-card">
            <h2 style="color: #dc3545; margin-bottom: 20px;">⚠️ Alertas Activas</h2>
            <?php foreach ($alertas as $alerta): ?>
                <div class="alert-box <?= $alerta['tipo'] ?>">
                    <strong><?= $alerta['tipo'] == 'danger' ? '🔴' : '⚠️' ?></strong>
                    <span><?= htmlspecialchars($alerta['mensaje']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- ESTADO DE SERVICIOS -->
    <div class="section-card">
        <h2>🔌 Estado de Servicios</h2>
        <div class="status-grid">
            
            <!-- Base de Datos -->
            <div class="status-card <?= $diagnostico['db']['status'] ?>">
                <div class="status-icon"><?= $diagnostico['db']['status'] == 'online' ? '✅' : '❌' ?></div>
                <div class="status-title">Base de Datos</div>
                <div class="status-detail">
                    <?php if ($diagnostico['db']['status'] == 'online'): ?>
                        Tiempo de respuesta: <?= $diagnostico['db']['response_time'] ?>
                    <?php else: ?>
                        <?= $diagnostico['db']['mensaje'] ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- LDAP -->
            <div class="status-card <?= $diagnostico['ldap']['status'] ?>">
                <div class="status-icon">
                    <?php
                    if ($diagnostico['ldap']['status'] == 'online') echo '✅';
                    elseif ($diagnostico['ldap']['status'] == 'warning') echo '⚠️';
                    elseif ($diagnostico['ldap']['status'] == 'offline') echo '❌';
                    else echo '➖';
                    ?>
                </div>
                <div class="status-title">Directorio Activo (LDAP)</div>
                <div class="status-detail"><?= htmlspecialchars($diagnostico['ldap']['mensaje']) ?></div>
            </div>
            
            <!-- SMTP -->
            <div class="status-card <?= $diagnostico['smtp']['status'] ?>">
                <div class="status-icon">
                    <?php
                    if ($diagnostico['smtp']['status'] == 'online') echo '✅';
                    elseif ($diagnostico['smtp']['status'] == 'offline') echo '❌';
                    else echo '➖';
                    ?>
                </div>
                <div class="status-title">Correo SMTP</div>
                <div class="status-detail"><?= htmlspecialchars($diagnostico['smtp']['mensaje']) ?></div>
            </div>
            
        </div>
    </div>
    
    <!-- MÉTRICAS DEL SISTEMA -->
    <?php if (isset($diagnostico['metricas'])): ?>
        <div class="section-card">
            <h2>📊 Métricas del Sistema</h2>
            <div class="metric-row">
                <span class="metric-label">Total Equipos:</span>
                <span class="metric-value"><?= $diagnostico['metricas']['total_equipos'] ?></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Equipos Activos:</span>
                <span class="metric-value"><?= $diagnostico['metricas']['equipos_activos'] ?></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Registros en Bitácora:</span>
                <span class="metric-value"><?= $diagnostico['metricas']['registros_bitacora'] ?></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Índices en Bitácora:</span>
                <span class="metric-value"><?= $diagnostico['metricas']['indices_bitacora'] ?></span>
            </div>
            <div class="metric-row">
                <span class="metric-label">Movimientos Hoy:</span>
                <span class="metric-value"><?= $diagnostico['actividad_hoy'] ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- ARCHIVOS CRÍTICOS -->
    <div class="section-card">
        <h2>📁 Archivos Críticos</h2>
        <?php foreach ($diagnostico['archivos'] as $archivo): ?>
            <div class="metric-row">
                <span class="metric-label"><?= htmlspecialchars($archivo['nombre']) ?>:</span>
                <span class="metric-value">
                    <?= $archivo['status'] == 'ok' ? '✅ ' . $archivo['size'] : '❌ Faltante' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- RECOMENDACIONES -->
    <?php if (count($recomendaciones) > 0): ?>
        <div class="section-card">
            <h2>💡 Recomendaciones</h2>
            <?php foreach ($recomendaciones as $rec): ?>
                <div class="alert-box <?= $rec['tipo'] ?>">
                    <strong>💡</strong>
                    <span><?= htmlspecialchars($rec['mensaje']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="alert-box info" style="margin-top: 30px;">
        <strong>ℹ️</strong>
        <span><strong>Nota:</strong> Este diagnóstico no muestra credenciales ni información sensible. Solo verifica el estado de los servicios.</span>
    </div>

</div>

</body>
</html>