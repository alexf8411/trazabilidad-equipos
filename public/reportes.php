<?php
/**
 * public/reportes.php
 * Dashboard de Inteligencia de Negocios (BI)
 * VERSIÓN OFFLINE (Carga local de Chart.js)
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor', 'Recursos'])) {
    header("Location: dashboard.php");
    exit;
}

// 2. LÓGICA DE EXPORTACIÓN CSV
if (isset($_POST['exportar_inventario'])) {
    $filename = "Inventario_URTRACK_" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel
    fputcsv($output, ['PLACA', 'SERIAL', 'MARCA', 'MODELO', 'ESTADO', 'UBICACION ACTUAL', 'RESPONSABLE', 'FECHA ULTIMO MOV']);
    
    // Consulta Maestra: Equipos + Último Movimiento
    $sql = "SELECT e.placa_ur, e.serial, e.marca, e.modelo, e.estado_maestro, 
            b.ubicacion, b.correo_responsable, b.fecha_evento
            FROM equipos e
            LEFT JOIN bitacora b ON e.serial = b.serial_equipo 
            AND b.id_evento = (SELECT MAX(id_evento) FROM bitacora WHERE serial_equipo = e.serial)";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
    fclose($output); exit;
}

// 3. RECOLECCIÓN DE DATOS
try {
    // A. KPIs Principales
    $total_activos = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Alta'")->fetchColumn();
    $total_bajas   = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Baja'")->fetchColumn();
    
    // B. Equipos en Bodega (Lógica: Último movimiento en sitio que contenga 'Bodega')
    $en_bodega = $pdo->query("SELECT COUNT(*) FROM bitacora b 
                              WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo)
                              AND b.ubicacion LIKE '%Bodega%'")->fetchColumn();
    $asignados = $total_activos - $en_bodega; // El resto se asume asignado

    // C. Movimientos del Mes
    $mes_actual = date('Y-m');
    $movs_mes = $pdo->query("SELECT COUNT(*) FROM bitacora WHERE fecha_evento LIKE '$mes_actual%'")->fetchColumn();

    // D. DATOS PARA GRÁFICAS (Arrays para JS)
    
    // 1. Sedes (Barras Verticales)
    $sql_sedes = "SELECT sede, COUNT(*) as cant FROM bitacora b 
                  WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo)
                  GROUP BY sede";
    $raw_sedes = $pdo->query($sql_sedes)->fetchAll(PDO::FETCH_ASSOC);
    $sedes_labels = []; $sedes_data = [];
    foreach($raw_sedes as $r) { 
        $sedes_labels[] = $r['sede'] ?: 'Sin Asignar'; 
        $sedes_data[] = $r['cant']; 
    }

    // 2. Técnicos (Top 5 - Barras Horizontales)
    $sql_tec = "SELECT tecnico_responsable, COUNT(*) as total FROM bitacora 
                WHERE tecnico_responsable IS NOT NULL AND tecnico_responsable != ''
                GROUP BY tecnico_responsable ORDER BY total DESC LIMIT 5";
    $raw_tec = $pdo->query($sql_tec)->fetchAll(PDO::FETCH_ASSOC);
    $tec_labels = []; $tec_data = [];
    foreach($raw_tec as $r) {
        $tec_labels[] = explode(' ', trim($r['tecnico_responsable']))[0]; // Solo primer nombre
        $tec_data[] = $r['total'];
    }

    // 3. Modalidad de Adquisición (Torta)
    $sql_mod = "SELECT modalidad, COUNT(*) as total FROM equipos GROUP BY modalidad";
    $raw_mod = $pdo->query($sql_mod)->fetchAll(PDO::FETCH_ASSOC);
    $mod_labels = []; $mod_data = [];
    foreach($raw_mod as $r) {
        $mod_labels[] = $r['modalidad'];
        $mod_data[] = $r['total'];
    }

} catch (PDOException $e) {
    $error = "Error DB: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Gerenciales | URTRACK</title>
    
    <script src="js/chart.js"></script>

    <style>
        :root { --primary: #002D72; --bg: #f4f7fa; --card: #ffffff; --text: #333; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* HEADER */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px; }
        .header h2 { margin: 0; color: var(--primary); font-size: 1.8rem; }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .btn-back:hover { color: var(--primary); }

        /* KPI GRID */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .kpi-card { background: var(--card); padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); transition: transform 0.2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-title { font-size: 0.85rem; color: #666; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; }
        .kpi-value { font-size: 2.2rem; font-weight: 700; color: var(--primary); margin: 10px 0; }
        .kpi-sub { font-size: 0.85rem; color: #888; }

        /* GRID DE GRÁFICOS */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: var(--card); padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .chart-header { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .chart-title { font-weight: bold; color: #444; font-size: 1.1rem; }
        .chart-canvas-container { position: relative; height: 250px; width: 100%; }

        /* PANEL DE DESCARGA */
        .report-panel { background: var(--card); padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .report-text h3 { margin: 0 0 10px 0; color: var(--primary); }
        .report-text p { margin: 0; color: #666; max-width: 600px; }
        .btn-download { background: #22c55e; color: white; border: none; padding: 15px 30px; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .btn-download:hover { background: #16a34a; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3); }

        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📊 Inteligencia de Negocio</h2>
        <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Total Activos</div>
            <div class="kpi-value"><?= number_format($total_activos) ?></div>
            <div class="kpi-sub">Equipos en Alta</div>
        </div>
        <div class="kpi-card" style="border-color: #f59e0b;">
            <div class="kpi-title">En Bodega</div>
            <div class="kpi-value"><?= number_format($en_bodega) ?></div>
            <div class="kpi-sub">Disponibles para asignar</div>
        </div>
        <div class="kpi-card" style="border-color: #22c55e;">
            <div class="kpi-title">Asignados</div>
            <div class="kpi-value"><?= number_format($asignados) ?></div>
            <div class="kpi-sub">En manos de usuarios</div>
        </div>
        <div class="kpi-card" style="border-color: #3b82f6;">
            <div class="kpi-title">Productividad</div>
            <div class="kpi-value"><?= $movs_mes ?></div>
            <div class="kpi-sub">Movimientos este mes</div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">💰 Modalidad de Adquisición</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="chartModalidad"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">📍 Distribución por Sede</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="chartSedes"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">🏆 Top Técnicos (Movimientos)</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="chartTecnicos"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">♻️ Ciclo de Vida (Alta vs Baja)</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="chartVida"></canvas>
            </div>
        </div>
    </div>

    <div class="report-panel">
        <div class="report-text">
            <h3>📥 Exportación de Inventario Maestro</h3>
            <p>Genera un archivo CSV compatible con Excel que cruza la base de datos de equipos con su última ubicación registrada en la bitácora.</p>
        </div>
        <form method="POST">
            <button type="submit" name="exportar_inventario" class="btn-download">
                📄 DESCARGAR REPORTE COMPLETO
            </button>
        </form>
    </div>
</div>

<script>
    // Configuración Global de colores
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#666';

    // 1. MODALIDAD (PIE)
    new Chart(document.getElementById('chartModalidad'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($mod_labels) ?>,
            datasets: [{
                data: <?= json_encode($mod_data) ?>,
                backgroundColor: ['#002D72', '#28a745', '#ffc107', '#17a2b8'],
                borderWidth: 1
            }]
        },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    // 2. SEDES (BAR)
    new Chart(document.getElementById('chartSedes'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($sedes_labels) ?>,
            datasets: [{
                label: 'Equipos',
                data: <?= json_encode($sedes_data) ?>,
                backgroundColor: '#002D72',
                borderRadius: 4
            }]
        },
        options: { 
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    // 3. TÉCNICOS (HORIZONTAL BAR)
    new Chart(document.getElementById('chartTecnicos'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($tec_labels) ?>,
            datasets: [{
                label: 'Movimientos',
                data: <?= json_encode($tec_data) ?>,
                backgroundColor: '#17a2b8',
                borderRadius: 4
            }]
        },
        options: { 
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // 4. VIDA UTIL (DOUGHNUT)
    new Chart(document.getElementById('chartVida'), {
        type: 'doughnut',
        data: {
            labels: ['Activos (Alta)', 'De Baja'],
            datasets: [{
                data: [<?= $total_activos ?>, <?= $total_bajas ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                hoverOffset: 4
            }]
        },
        options: { maintainAspectRatio: false }
    });
</script>

</body>
</html>