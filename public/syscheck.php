<?php
/**
 * public/syscheck.php 
 * Monitor Profesional de Infraestructura - URTRACK
 * Versión 3.0 SQL SERVER
 * 
 * Muestra:
 * - Recursos del servidor (CPU, RAM, Disco)
 * - Versiones de software crítico
 * - Conectores y extensiones PHP
 * - Información de red
 */
require_once '../core/session.php';
require_once '../core/db.php';

// SEGURIDAD: Solo Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    die('Acceso denegado. Solo Administradores.');
}

// ============================================================================
// 1. RECURSOS DEL SERVIDOR
// ============================================================================
$disco_total = @disk_total_space("/");
$disco_libre = @disk_free_space("/");
$disco_uso_pct = $disco_total > 0 ? round((($disco_total - $disco_libre) / $disco_total) * 100, 1) : 0;

// Memoria RAM
$memoria = ['total' => 0, 'uso' => 0, 'pct' => 0];
$free = @shell_exec('free -b');
if ($free) {
    $free_arr = explode("\n", trim($free));
    if (isset($free_arr[1])) {
        $mem = preg_split('/\s+/', $free_arr[1]);
        if (isset($mem[1]) && isset($mem[2])) {
            $memoria['total'] = $mem[1];
            $memoria['uso'] = $mem[2];
            $memoria['pct'] = round(($mem[2] / $mem[1]) * 100, 1);
        }
    }
}

// CPU
$load = @sys_getloadavg();
$cpu_cores = 4; // Ajustar según servidor
$cpu_load = $load ? round($load[0] / $cpu_cores * 100, 1) : 0;

// ============================================================================
// 2. VERSIONES DE SOFTWARE
// ============================================================================
$software = [];

// PHP
$software['PHP'] = [
    'version' => PHP_VERSION,
    'sapi' => php_sapi_name(),
    'zend_version' => zend_version()
];

// Apache/Nginx
$webserver = 'Desconocido';
$webserver_version = 'N/A';
if (isset($_SERVER['SERVER_SOFTWARE'])) {
    $webserver_info = $_SERVER['SERVER_SOFTWARE'];
    $software['Servidor Web'] = [
        'info' => $webserver_info,
        'puerto' => $_SERVER['SERVER_PORT'] ?? '80'
    ];
}

// Sistema Operativo
$software['Sistema Operativo'] = [
    'os' => PHP_OS,
    'familia' => PHP_OS_FAMILY,
    'uname' => php_uname('s') . ' ' . php_uname('r')
];

// ============================================================================
// 3. EXTENSIONES PHP CRÍTICAS
// ============================================================================
$extensiones_criticas = [
    'pdo_sqlsrv' => 'SQL Server PDO Driver',
    'sqlsrv' => 'SQL Server Native Driver',
    'ldap' => 'LDAP (Active Directory)',
    'openssl' => 'OpenSSL (Cifrado)',
    'mbstring' => 'Multibyte String',
    'curl' => 'cURL (HTTP requests)',
    'json' => 'JSON',
    'session' => 'Sessions',
    'fileinfo' => 'File Information',
    'gd' => 'GD (Imágenes)',
    'zip' => 'ZIP'
];

$extensiones_estado = [];
foreach ($extensiones_criticas as $ext => $descripcion) {
    $cargada = extension_loaded($ext);
    $version = $cargada ? phpversion($ext) : null;
    
    $extensiones_estado[] = [
        'nombre' => $ext,
        'descripcion' => $descripcion,
        'cargada' => $cargada,
        'version' => $version ?: 'N/A'
    ];
}

// ============================================================================
// 4. INFORMACIÓN DE BASE DE DATOS
// ============================================================================
$db_info = ['status' => 'error', 'mensaje' => 'No conectado'];
try {
    // Versión de SQL Server
    $stmt = $pdo->query("SELECT @@VERSION AS version");
    $version_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Información adicional
    $stmt = $pdo->query("SELECT SERVERPROPERTY('ProductVersion') AS version, 
                                SERVERPROPERTY('ProductLevel') AS nivel,
                                SERVERPROPERTY('Edition') AS edicion");
    $server_props = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $db_info = [
        'status' => 'online',
        'motor' => 'Microsoft SQL Server',
        'version' => $server_props['version'],
        'nivel' => $server_props['nivel'],
        'edicion' => $server_props['edicion'],
        'driver_pdo' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'driver_version' => $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION)
    ];
    
} catch (Exception $e) {
    $db_info['mensaje'] = 'Error: ' . $e->getMessage();
}

// ============================================================================
// 5. CONFIGURACIÓN PHP RELEVANTE
// ============================================================================
$php_config = [
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
    'error_reporting' => error_reporting(),
    'timezone' => date_default_timezone_get()
];

// ============================================================================
// 6. INFORMACIÓN DE RED
// ============================================================================
$network = [
    'hostname' => gethostname(),
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
    'client_ip' => $_SERVER['REMOTE_ADDR'],
    'protocolo' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
    'puerto' => $_SERVER['SERVER_PORT'] ?? '80'
];

// Funciones auxiliares
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

function getStatusColor($pct) {
    if ($pct < 60) return 'success';
    if ($pct < 80) return 'warning';
    return 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Infraestructura - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    <style>
        .syscheck-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .syscheck-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .grid-2col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary-color);
        }
        
        .info-card h2 {
            color: var(--primary-color);
            margin: 0 0 20px 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .stat-row:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-weight: 600;
            color: #555;
        }
        
        .stat-value {
            font-family: 'Courier New', monospace;
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .progress-bar-container {
            background: #e9ecef;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-bar {
            height: 100%;
            transition: width 0.8s ease;
            border-radius: 6px;
        }
        
        .progress-bar.success { background: linear-gradient(90deg, #28a745, #20c997); }
        .progress-bar.warning { background: linear-gradient(90deg, #ffc107, #ff9800); }
        .progress-bar.danger { background: linear-gradient(90deg, #dc3545, #c82333); }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .extension-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .extension-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 0.9rem;
        }
        
        .extension-item.loaded {
            border-color: #28a745;
            background: #d4edda;
        }
        
        .extension-item.missing {
            border-color: #dc3545;
            background: #f8d7da;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .grid-2col {
                grid-template-columns: 1fr;
            }
            
            .extension-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="syscheck-header">
        <h1>🖥️ Monitor de Infraestructura</h1>
        <p style="margin: 0; opacity: 0.9;">Estado del servidor y componentes críticos de URTRACK</p>
    </div>
    
    <!-- Botones de navegación -->
    <div class="action-buttons">
        <a href="syscheck.php" class="btn btn-primary">🔄 Refrescar</a>
        <a href="diagnostico.php" class="btn btn-outline">🏥 Diagnóstico</a>
        <a href="escaner_db.php" class="btn btn-outline">🔍 Escáner BD</a>
        <a href="dashboard.php" class="btn btn-outline">⬅ Dashboard</a>
    </div>

    <!-- Grid de 2 columnas -->
    <div class="grid-2col">
        
        <!-- RECURSOS DEL SERVIDOR -->
        <div class="info-card">
            <h2>📊 Recursos del Servidor</h2>
            
            <div class="stat-row">
                <span class="stat-label">CPU (<?= $cpu_cores ?> cores)</span>
                <span class="stat-value"><?= $cpu_load ?>%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar <?= getStatusColor($cpu_load) ?>" style="width: <?= $cpu_load ?>%"></div>
            </div>
            
            <div class="stat-row">
                <span class="stat-label">Memoria RAM</span>
                <span class="stat-value"><?= $memoria['pct'] ?>%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar <?= getStatusColor($memoria['pct']) ?>" style="width: <?= $memoria['pct'] ?>%"></div>
            </div>
            <small style="color: #666;">Uso: <?= formatBytes($memoria['uso']) ?> / <?= formatBytes($memoria['total']) ?></small>
            
            <div class="stat-row" style="margin-top: 15px;">
                <span class="stat-label">Disco Duro</span>
                <span class="stat-value"><?= $disco_uso_pct ?>%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar <?= getStatusColor($disco_uso_pct) ?>" style="width: <?= $disco_uso_pct ?>%"></div>
            </div>
            <small style="color: #666;">Libre: <?= formatBytes($disco_libre) ?> de <?= formatBytes($disco_total) ?></small>
        </div>
        
        <!-- INFORMACIÓN DE RED -->
        <div class="info-card">
            <h2>🌐 Red y Conectividad</h2>
            <div class="stat-row">
                <span class="stat-label">Hostname:</span>
                <span class="stat-value"><?= htmlspecialchars($network['hostname']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">IP Servidor:</span>
                <span class="stat-value"><?= htmlspecialchars($network['server_ip']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">IP Cliente:</span>
                <span class="stat-value"><?= htmlspecialchars($network['client_ip']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Puerto:</span>
                <span class="stat-value"><?= htmlspecialchars($network['puerto']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Protocolo:</span>
                <span class="stat-value"><?= htmlspecialchars($network['protocolo']) ?></span>
            </div>
        </div>
        
    </div>

    <!-- SOFTWARE INSTALADO -->
    <div class="info-card">
        <h2>💿 Software Instalado</h2>
        
        <div class="stat-row">
            <span class="stat-label">PHP:</span>
            <span class="stat-value"><?= $software['PHP']['version'] ?> (<?= $software['PHP']['sapi'] ?>)</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Zend Engine:</span>
            <span class="stat-value"><?= $software['PHP']['zend_version'] ?></span>
        </div>
        
        <?php if (isset($software['Servidor Web'])): ?>
        <div class="stat-row">
            <span class="stat-label">Servidor Web:</span>
            <span class="stat-value"><?= htmlspecialchars($software['Servidor Web']['info']) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="stat-row">
            <span class="stat-label">Sistema Operativo:</span>
            <span class="stat-value"><?= $software['Sistema Operativo']['uname'] ?></span>
        </div>
    </div>

    <!-- BASE DE DATOS -->
    <div class="info-card">
        <h2>🗄️ Base de Datos SQL Server</h2>
        
        <?php if ($db_info['status'] == 'online'): ?>
            <div class="stat-row">
                <span class="stat-label">Estado:</span>
                <span class="badge badge-success">✅ Conectado</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Motor:</span>
                <span class="stat-value"><?= $db_info['motor'] ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Versión:</span>
                <span class="stat-value"><?= htmlspecialchars($db_info['version']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Nivel:</span>
                <span class="stat-value"><?= htmlspecialchars($db_info['nivel']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Edición:</span>
                <span class="stat-value"><?= htmlspecialchars($db_info['edicion']) ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Driver PDO:</span>
                <span class="stat-value"><?= $db_info['driver_pdo'] ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Versión Driver:</span>
                <span class="stat-value"><?= is_array($db_info['driver_version']) ? implode(', ', $db_info['driver_version']) : $db_info['driver_version'] ?></span>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($db_info['mensaje']) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- EXTENSIONES PHP -->
    <div class="info-card">
        <h2>🧩 Extensiones PHP Críticas</h2>
        <div class="extension-grid">
            <?php foreach ($extensiones_estado as $ext): ?>
                <div class="extension-item <?= $ext['cargada'] ? 'loaded' : 'missing' ?>">
                    <strong><?= $ext['cargada'] ? '✅' : '❌' ?> <?= htmlspecialchars($ext['nombre']) ?></strong><br>
                    <small style="opacity: 0.8;"><?= htmlspecialchars($ext['descripcion']) ?></small><br>
                    <small style="opacity: 0.7;"><?= $ext['version'] ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CONFIGURACIÓN PHP -->
    <div class="info-card">
        <h2>⚙️ Configuración PHP</h2>
        <?php foreach ($php_config as $key => $value): ?>
            <div class="stat-row">
                <span class="stat-label"><?= htmlspecialchars($key) ?>:</span>
                <span class="stat-value"><?= htmlspecialchars($value) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

</div>

</body>
</html>