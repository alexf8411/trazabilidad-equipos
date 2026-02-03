<?php
/**
 * public/syscheck.php
 * Monitor de Recursos del Servidor (Capa de Infraestructura)
 */
require_once '../core/session.php';

// Seguridad: Solo el Administrador puede ver las entrañas del servidor
if ($_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado: Se requieren privilegios de Infraestructura.");
}

// --- LÓGICA DE DIAGNÓSTICO ---

// 1. DISCO DURO
$path = "C:"; // Cambia a "/" si estás en Linux
$disco_total = disk_total_space($path);
$disco_libre = disk_free_space($path);
$disco_uso_bytes = $disco_total - $disco_libre;
$disco_uso_pct = round(($disco_uso_bytes / $disco_total) * 100, 1);

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

// 2. MEMORIA RAM (Vía WMIC para Windows)
$free_mem = shell_exec('wmic OS get FreePhysicalMemory /Value');
$total_mem = shell_exec('wmic OS get TotalVisibleMemorySize /Value');
preg_match('/\d+/', $free_mem, $fm);
preg_match('/\d+/', $total_mem, $tm);

$ram_total_kb = $tm[0] ?? 0;
$ram_libre_kb = $fm[0] ?? 0;
$ram_uso_kb = $ram_total_kb - $ram_libre_kb;
$ram_uso_pct = ($ram_total_kb > 0) ? round(($ram_uso_kb / $ram_total_kb) * 100, 1) : 0;

// 3. CARGA DE CPU (Estimación)
$cpu_load = "No disponible";
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cpu_usage = shell_exec('wmic cpu get loadpercentage /Value');
    preg_match('/\d+/', $cpu_usage, $cpu);
    $cpu_load = ($cpu[0] ?? '0') . "%";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>System Check - URTRACK</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a1a; color: #eee; padding: 40px; }
        .monitor-card { max-width: 600px; margin: 0 auto; background: #2d2d2d; padding: 30px; border-radius: 12px; border: 1px solid #444; }
        .stat-row { margin-bottom: 25px; }
        .label-group { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .progress-bg { background: #444; height: 12px; border-radius: 6px; overflow: hidden; }
        .progress-bar { height: 100%; transition: width 0.5s ease; }
        .bar-blue { background: #3498db; }
        .bar-green { background: #2ecc71; }
        .bar-orange { background: #e67e22; }
        .bar-red { background: #e74c3c; }
        .details { font-size: 0.85rem; color: #aaa; margin-top: 5px; }
        .refresh-btn { display: block; width: 100%; padding: 10px; background: transparent; border: 1px solid #3498db; color: #3498db; border-radius: 5px; cursor: pointer; text-decoration: none; text-align: center; margin-top: 20px; }
        .refresh-btn:hover { background: #3498db; color: white; }
    </style>
</head>
<body>

<div class="monitor-card">
    <h2 style="margin-top:0; border-bottom: 1px solid #444; padding-bottom: 10px;">📊 Estado del Servidor</h2>
    
    <div class="stat-row">
        <div class="label-group">
            <span>Procesador (CPU)</span>
            <span><?= $cpu_load ?></span>
        </div>
        <div class="progress-bg">
            <div class="progress-bar bar-blue" style="width: <?= $cpu_load ?>"></div>
        </div>
    </div>

    <div class="stat-row">
        <div class="label-group">
            <span>Memoria RAM</span>
            <span><?= $ram_uso_pct ?>%</span>
        </div>
        <div class="progress-bg">
            <?php 
                $ram_color = 'bar-green';
                if($ram_uso_pct > 70) $ram_color = 'bar-orange';
                if($ram_uso_pct > 90) $ram_color = 'bar-red';
            ?>
            <div class="progress-bar <?= $ram_color ?>" style="width: <?= $ram_uso_pct ?>%"></div>
        </div>
        <div class="details">Uso: <?= formatSize($ram_uso_kb * 1024) ?> de <?= formatSize($ram_total_kb * 1024) ?></div>
    </div>

    <div class="stat-row">
        <div class="label-group">
            <span>Almacenamiento (Disco)</span>
            <span><?= $disco_uso_pct ?>%</span>
        </div>
        <div class="progress-bg">
            <div class="progress-bar bar-blue" style="width: <?= $disco_uso_pct ?>%"></div>
        </div>
        <div class="details">Libre: <?= formatSize($disco_libre) ?> de <?= formatSize($disco_total) ?></div>
    </div>

    <a href="syscheck.php" class="refresh-btn">🔄 Actualizar Diagnóstico</a>
    <a href="dashboard.php" style="display:block; text-align:center; margin-top:15px; color:#777; font-size:0.8rem; text-decoration:none;">Cerrar y volver al sistema</a>
</div>

</body>
</html>