<?php
/**
 * public/syscheck.php
 * Monitor de Recursos del Servidor (Versión Linux/Ubuntu)
 */
require_once '../core/session.php';

// 1. SEGURIDAD
if ($_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado.");
}

// --- LÓGICA DE DIAGNÓSTICO PARA LINUX ---

// 1. DISCO DURO
$disco_total = disk_total_space("/");
$disco_libre = disk_free_space("/");
$disco_uso_pct = round((($disco_total - $disco_libre) / $disco_total) * 100, 1);

// 2. MEMORIA RAM (Lectura de /proc/meminfo)
$free = shell_exec('free -b');
$free = (string)trim($free);
$free_arr = explode("\n", $free);
$mem = explode(" ", preg_replace('/\s+/', ' ', $free_arr[1]));

$ram_total = $mem[1];
$ram_uso = $mem[2];
$ram_uso_pct = round(($ram_uso / $ram_total) * 100, 1);

// 3. CARGA DE CPU (Lectura de /proc/loadavg)
$load = sys_getloadavg();
$cpu_load = $load[0] * 100 / 4; // Asumiendo 4 núcleos, ajuste aproximado
$cpu_load = round($cpu_load, 1) . "%";

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
    <title>System Check - URTRACK (Linux)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #121212; color: #e0e0e0; padding: 40px; }
        .monitor-card { max-width: 550px; margin: 0 auto; background: #1e1e1e; padding: 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333; }
        .stat-row { margin-bottom: 25px; }
        .label-group { display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 500; }
        .progress-bg { background: #333; height: 10px; border-radius: 5px; overflow: hidden; }
        .progress-bar { height: 100%; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .bar-blue { background: #0d6efd; }
        .bar-green { background: #198754; }
        .bar-orange { background: #fd7e14; }
        .bar-red { background: #dc3545; }
        .details { font-size: 0.8rem; color: #888; margin-top: 6px; }
        .btn { display: block; width: 100%; padding: 12px; border-radius: 6px; text-align: center; text-decoration: none; margin-top: 15px; font-weight: bold; transition: 0.3s; }
        .btn-refresh { border: 1px solid #0d6efd; color: #0d6efd; }
        .btn-refresh:hover { background: #0d6efd; color: white; }
    </style>
</head>
<body>

<div class="monitor-card">
    <h2 style="margin: 0 0 20px 0; color: #fff; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
        🐧 Servidor Ubuntu: Status
    </h2>
    
    <div class="stat-row">
        <div class="label-group"><span>Carga CPU</span><span><?= $cpu_load ?></span></div>
        <div class="progress-bg"><div class="progress-bar bar-blue" style="width: <?= $cpu_load ?>"></div></div>
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
    <a href="dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#555; font-size:0.8rem; text-decoration:none;">Volver al Panel</a>
</div>

</body>
</html>