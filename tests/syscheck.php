<?php
/**
 * public/syscheck.php
 * Monitor de Recursos del Servidor (Versión SEGURA)
 * SOLO Administradores
 */
require_once '../core/session.php';

// VERIFICAR ROL
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    die('Acceso denegado. Solo Administradores.');
}

// DISCO DURO
$disco_total = disk_total_space("/");
$disco_libre = disk_free_space("/");
$disco_uso_pct = round((($disco_total - $disco_libre) / $disco_total) * 100, 1);

// MEMORIA RAM
$free = shell_exec('free -b');
$free = (string)trim($free);
$free_arr = explode("\n", $free);
$mem = explode(" ", preg_replace('/\s+/', ' ', $free_arr[1]));

$ram_total = $mem[1];
$ram_uso = $mem[2];
$ram_uso_pct = round(($ram_uso / $ram_total) * 100, 1);

// CARGA DE CPU
$load = sys_getloadavg();
$cpu_load = round($load[0] * 25, 1);
$cpu_load_str = $cpu_load . "%";

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Check - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    <style>
        body { background: #121212; color: #e0e0e0; padding: 40px; }
        .monitor-card { 
            max-width: 550px; 
            margin: 0 auto; 
            background: #1e1e1e; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
            border: 1px solid #333; 
        }
        .stat-row { margin-bottom: 25px; }
        .label-group { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px; 
            font-weight: 500; 
        }
        .progress-bg { 
            background: #333; 
            height: 10px; 
            border-radius: 5px; 
            overflow: hidden; 
        }
        .progress-bar { 
            height: 100%; 
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .bar-blue { background: #0d6efd; }
        .bar-green { background: #198754; }
        .bar-orange { background: #fd7e14; }
        .bar-red { background: #dc3545; }
        .details { 
            font-size: 0.8rem; 
            color: #888; 
            margin-top: 6px; 
        }
        .btn { 
            display: block; 
            width: 100%; 
            padding: 12px; 
            border-radius: 6px; 
            text-align: center; 
            text-decoration: none; 
            margin-top: 15px; 
            font-weight: bold; 
            transition: 0.3s; 
        }
        .btn-refresh { 
            border: 1px solid #0d6efd; 
            color: #0d6efd; 
        }
        .btn-refresh:hover { 
            background: #0d6efd; 
            color: white; 
        }
    </style>
</head>
<body>

<div class="monitor-card">
    <h2 style="margin: 0 0 20px 0; color: #fff; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
        🐧 Servidor Ubuntu: Status
    </h2>
    
    <div class="stat-row">
        <div class="label-group"><span>Carga CPU</span><span><?= $cpu_load_str ?></span></div>
        <div class="progress-bg"><div class="progress-bar bar-blue" style="width: <?= $cpu_load ?>%"></div></div>
    </div>

    <div class="stat-row">
        <div class="label-group"><span>Memoria RAM</span><span><?= $ram_uso_pct ?>%</span></div>
        <div class="progress-bg">
            <?php 
                $ram_color = 'bar-green';
                if($ram_uso_pct > 75) $ram_color = 'bar-orange';
                if($ram_uso_pct > 90) $ram_color = 'bar-red';
            ?>
            <div class="progress-bar <?= $ram_color ?>" style="width: <?= $ram_uso_pct ?>%"></div>
        </div>
        <div class="details">Uso: <?= formatSize($ram_uso) ?> / <?= formatSize($ram_total) ?></div>
    </div>

    <div class="stat-row">
        <div class="label-group"><span>Disco Duro</span><span><?= $disco_uso_pct ?>%</span></div>
        <div class="progress-bg"><div class="progress-bar bar-blue" style="width: <?= $disco_uso_pct ?>%"></div></div>
        <div class="details">Libre: <?= formatSize($disco_libre) ?> de <?= formatSize($disco_total) ?></div>
    </div>

    <a href="syscheck.php" class="btn btn-refresh">🔄 Re-escanear Sistema</a>
    <a href="diagnostico.php" class="btn btn-refresh" style="margin-top:10px;">🏥 Diagnóstico</a>
    <a href="escaner_db.php" class="btn btn-refresh" style="margin-top:10px;">🔍 Escáner BD</a>
    <a href="configuracion.php" style="display:block; text-align:center; margin-top:15px; color:#888; font-size:0.9rem; text-decoration:none;">⬅ Volver a Configuración</a>
</div>

</body>
</html>