<?php
/**
 * public/reportes.php
 * Dashboard BI + Centro de Descargas Avanzado
 * VERSIÓN FINAL: Incluye "Realizado Por" en reportes contables.
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor', 'Recursos'])) {
    header("Location: dashboard.php");
    exit;
}

// 2. LÓGICA DE EXPORTACIÓN (ROUTER DE DESCARGAS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Configuración común
    $date_now = date('Y-m-d_H-i');
    header('Content-Type: text/csv; charset=utf-8');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel

    // --- A. REPORTE INVENTARIO MAESTRO (Snapshot Actual) ---
    if (isset($_POST['btn_inventario'])) {
        
        try {
            // Sanitizar nombre de archivo
            $safe_date = preg_replace('/[^a-zA-Z0-9_-]/', '', $date_now);
            
            // Headers para descarga
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Inventario_Maestro_' . $safe_date . '.csv');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // BOM UTF-8 para compatibilidad con Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados
            fputcsv($output, [
                'PLACA',
                'SERIAL',
                'MARCA',
                'MODELO',
                'MODALIDAD',
                'PRECIO',
                'FECHA_COMPRA',
                'VIDA_UTIL',
                'ANTIGUEDAD_ANIOS',
                'ESTADO_MAESTRO',
                'TIPO_ULTIMO_EVENTO',
                'FECHA_ULTIMO_MOVIMIENTO',
                'ESTADO_OPERATIVO',
                'SEDE',
                'UBICACION',
                'RESPONSABLE',
                'TECNICO_ULTIMO_MOVIMIENTO'
            ]);
            
            // Consulta optimizada (misma lógica, mejor sintaxis)
            $sql = "
                SELECT 
                    e.placa_ur AS PLACA,
                    e.serial AS SERIAL,
                    e.marca AS MARCA,
                    e.modelo AS MODELO,
                    e.modalidad AS MODALIDAD,
                    e.precio AS PRECIO,
                    e.fecha_compra AS FECHA_COMPRA,
                    e.vida_util AS VIDA_UTIL,
                    TIMESTAMPDIFF(YEAR, e.fecha_compra, CURDATE()) AS ANTIGUEDAD_ANIOS,
                    e.estado_maestro AS ESTADO_MAESTRO,
                    b.tipo_evento AS TIPO_ULTIMO_EVENTO,
                    b.fecha_evento AS FECHA_ULTIMO_MOVIMIENTO,
                    CASE 
                        WHEN b.tipo_evento IN ('Asignación','Asignacion_Masiva') THEN 'Asignado'
                        WHEN b.tipo_evento IN ('Devolución','Alta','Alistamiento') THEN 'Bodega'
                        WHEN b.tipo_evento = 'Baja' THEN 'Baja'
                        WHEN b.tipo_evento IS NULL THEN 'Sin historial'
                        ELSE 'Revisar'
                    END AS ESTADO_OPERATIVO,
                    b.sede AS SEDE,
                    b.ubicacion AS UBICACION,
                    b.correo_responsable AS RESPONSABLE,
                    b.tecnico_responsable AS TECNICO_ULTIMO_MOVIMIENTO
                FROM equipos e
                LEFT JOIN bitacora b 
                    ON e.serial = b.serial_equipo
                    AND b.fecha_evento = (
                        SELECT MAX(b2.fecha_evento)
                        FROM bitacora b2
                        WHERE b2.serial_equipo = e.serial
                    )
                ORDER BY e.placa_ur
            ";
            
            $stmt = $pdo->query($sql);
            
            // Verificar que hay resultados
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Normalizar valores nulos para el CSV
                    foreach ($row as $key => $value) {
                        if ($value === null) {
                            $row[$key] = '';
                        }
                    }
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            
        } catch (PDOException $e) {
            // Log del error (no mostrar al usuario información sensible)
            error_log("Error en reporte inventario: " . $e->getMessage());
            
            // Mensaje genérico al usuario
            http_response_code(500);
            die("Error al generar el reporte. Por favor, contacte al administrador.");
        }
        
        exit;
    }

    // --- B. REPORTE DE MOVIMIENTOS (Trazabilidad por Fechas) ---
    if (isset($_POST['btn_movimientos'])) {
        $inicio = $_POST['f_ini'] . ' 00:00:00';
        $fin    = $_POST['f_fin'] . ' 23:59:59';
        
        header('Content-Disposition: attachment; filename=Movimientos_' . $_POST['f_ini'] . '_a_' . $_POST['f_fin'] . '.csv');
        fputcsv($output, ['ID EVENTO', 'FECHA', 'TIPO', 'PLACA', 'SERIAL', 'EQUIPO', 'ORIGEN/SEDE', 'UBICACION DESTINO', 'RESPONSABLE', 'REALIZADO POR']);

        $sql = "SELECT b.id_evento, b.fecha_evento, b.tipo_evento, e.placa_ur, b.serial_equipo, 
                CONCAT(e.marca, ' ', e.modelo) as equipo, b.sede, b.ubicacion, b.correo_responsable, b.tecnico_responsable
                FROM bitacora b
                JOIN equipos e ON b.serial_equipo = e.serial
                WHERE b.fecha_evento BETWEEN ? AND ?
                ORDER BY b.fecha_evento DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inicio, $fin]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
        fclose($output); exit;
    }

    // --- REPORTE DE ALTAS (Compras y Valor Inicial) ---
if (isset($_POST['btn_altas_compras'])) {
    $inicio = $_POST['f_ini_a'] . ' 00:00:00';
    $fin    = $_POST['f_fin_a'] . ' 23:59:59';

    header('Content-Disposition: attachment; filename=Reporte_Altas_URTRACK_' . date('Ymd') . '.csv');
    fputcsv($output, ['FECHA INGRESO', 'ORDEN DE COMPRA', 'PLACA UR', 'SERIAL', 'EQUIPO', 'MODALIDAD', 'VALOR COMPRA']);

    // Unimos bitácora con equipos para traer el precio y la OC
    $sql = "SELECT b.fecha_evento, b.desc_evento, e.placa_ur, e.serial, 
                   CONCAT(e.marca, ' ', e.modelo) as equipo, e.modalidad, e.precio
            FROM bitacora b
            JOIN equipos e ON b.serial_equipo = e.serial
            WHERE b.tipo_evento = 'Alta' 
            AND b.fecha_evento BETWEEN ? AND ?
            ORDER BY b.fecha_evento DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$inicio, $fin]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
        fputcsv($output, $row); 
    }
    fclose($output); exit;
}

// --- REPORTE DE BAJAS Y SINIESTRALIDAD ---
if (isset($_POST['btn_bajas_siniestros'])) {
    $inicio = $_POST['f_ini_b'] . ' 00:00:00';
    $fin    = $_POST['f_fin_b'] . ' 23:59:59';

    header('Content-Disposition: attachment; filename=Reporte_Bajas_Siniestros_' . date('Ymd') . '.csv');
    fputcsv($output, ['FECHA BAJA', 'MOTIVO/DETALLE', 'PLACA UR', 'SERIAL', 'EQUIPO', 'VALOR PERDIDO', 'TECNICO']);

    // Buscamos los eventos de tipo 'Baja'
    $sql = "SELECT b.fecha_evento, b.desc_evento, e.placa_ur, e.serial, 
                   CONCAT(e.marca, ' ', e.modelo) as equipo, e.precio, b.tecnico_responsable
            FROM bitacora b
            JOIN equipos e ON b.serial_equipo = e.serial
            WHERE b.tipo_evento = 'Baja' 
            AND b.fecha_evento BETWEEN ? AND ?
            ORDER BY b.fecha_evento DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$inicio, $fin]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
        fputcsv($output, $row); 
    }
    fclose($output); exit;
}
}

// 3. RECOLECCIÓN DE DATOS (GRÁFICAS Y KPIs)
try {
    $total_activos = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Alta'")->fetchColumn();
    $total_bajas   = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Baja'")->fetchColumn();
    
    $en_bodega = $pdo->query("SELECT COUNT(*) FROM bitacora b 
                              WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo)
                              AND b.ubicacion LIKE '%Bodega%'")->fetchColumn();
    $asignados = $total_activos - $en_bodega;

    $mes_actual = date('Y-m');
    $movs_mes = $pdo->query("SELECT COUNT(*) FROM bitacora WHERE fecha_evento LIKE '$mes_actual%'")->fetchColumn();

    // Arrays para Gráficas
    $sql_sedes = "SELECT sede, COUNT(*) as cant FROM bitacora b WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo) GROUP BY sede";
    $raw_sedes = $pdo->query($sql_sedes)->fetchAll(PDO::FETCH_ASSOC);
    $sedes_labels = []; $sedes_data = []; foreach($raw_sedes as $r) { $sedes_labels[] = $r['sede']?:'Sin Asignar'; $sedes_data[] = $r['cant']; }

    $sql_tec = "SELECT tecnico_responsable, COUNT(*) as total FROM bitacora WHERE tecnico_responsable IS NOT NULL AND tecnico_responsable != '' GROUP BY tecnico_responsable ORDER BY total DESC LIMIT 5";
    $raw_tec = $pdo->query($sql_tec)->fetchAll(PDO::FETCH_ASSOC);
    $tec_labels = []; $tec_data = []; foreach($raw_tec as $r) { $tec_labels[] = explode(' ', trim($r['tecnico_responsable']))[0]; $tec_data[] = $r['total']; }

    $sql_mod = "SELECT modalidad, COUNT(*) as total FROM equipos GROUP BY modalidad";
    $raw_mod = $pdo->query($sql_mod)->fetchAll(PDO::FETCH_ASSOC);
    $mod_labels = []; $mod_data = []; foreach($raw_mod as $r) { $mod_labels[] = $r['modalidad']; $mod_data[] = $r['total']; }

} catch (PDOException $e) { $error = $e->getMessage(); }
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
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px; }
        .header h2 { margin: 0; color: var(--primary); font-size: 1.8rem; }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; }

        /* KPI GRID */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .kpi-card { background: var(--card); padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .kpi-title { font-size: 0.85rem; color: #666; text-transform: uppercase; font-weight: bold; }
        .kpi-value { font-size: 2.2rem; font-weight: 700; color: var(--primary); margin: 10px 0; }

        /* CHARTS GRID */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: var(--card); padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .chart-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .chart-title { font-weight: bold; color: #444; font-size: 1.1rem; }
        .chart-canvas-container { position: relative; height: 250px; width: 100%; }

        /* ZONA DE DESCARGAS */
        .downloads-title { font-size: 1.2rem; color: var(--primary); margin-bottom: 20px; font-weight: bold; border-left: 5px solid #22c55e; padding-left: 15px; }
        .downloads-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        
        .download-card { background: var(--card); padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; }
        .download-card h4 { margin: 0 0 10px 0; color: #333; }
        .download-card p { font-size: 0.85rem; color: #666; margin-bottom: 20px; flex-grow: 1; }
        
        .date-group { display: flex; gap: 10px; margin-bottom: 15px; }
        .date-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: sans-serif; }
        
        .btn-dl { width: 100%; padding: 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s; }
        .btn-dl-blue { background: #002D72; color: white; }
        .btn-dl-green { background: #198754; color: white; }
        .btn-dl-orange { background: #fd7e14; color: white; }
        .btn-dl:hover { opacity: 0.9; transform: translateY(-2px); }

    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2>📊 Inteligencia de Negocio</h2>
        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-title">Total Activos</div><div class="kpi-value"><?= number_format($total_activos) ?></div></div>
        <div class="kpi-card" style="border-color: #f59e0b;"><div class="kpi-title">En Bodega</div><div class="kpi-value"><?= number_format($en_bodega) ?></div></div>
        <div class="kpi-card" style="border-color: #22c55e;"><div class="kpi-title">Asignados</div><div class="kpi-value"><?= number_format($asignados) ?></div></div>
        <div class="kpi-card" style="border-color: #3b82f6;"><div class="kpi-title">Productividad</div><div class="kpi-value"><?= $movs_mes ?></div></div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header"><span class="chart-title">💰 Modalidad</span></div>
            <div class="chart-canvas-container"><canvas id="chartModalidad"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><span class="chart-title">📍 Sedes</span></div>
            <div class="chart-canvas-container"><canvas id="chartSedes"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><span class="chart-title">🏆 Top Técnicos</span></div>
            <div class="chart-canvas-container"><canvas id="chartTecnicos"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><span class="chart-title">♻️ Ciclo de Vida</span></div>
            <div class="chart-canvas-container"><canvas id="chartVida"></canvas></div>
        </div>
    </div>

    <h3 class="downloads-title">📥 Centro de Descargas</h3>
    <div class="downloads-grid">
        
        <div class="download-card">
            <div>
                <h4>📦 Inventario Maestro</h4>
                <p>Foto actual de todos los equipos, ubicación en tiempo real y estado operativo.</p>
            </div>
            <form method="POST">
                <button type="submit" name="btn_inventario" class="btn-dl btn-dl-blue">
                    📄 Descargar Actual
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>🚚 Trazabilidad / Movimientos</h4>
                <p>Historial detallado de asignaciones, devoluciones y traslados por rango de fecha.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini" class="date-input" required title="Desde">
                    <input type="date" name="f_fin" class="date-input" required title="Hasta" value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_movimientos" class="btn-dl btn-dl-green">
                    📅 Exportar Movimientos
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>📦 Relación de Compras (Altas)</h4>
                <p>Listado de equipos nuevos, sus costos y el número de Orden de Compra asociada.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini_a" class="date-input" required>
                    <input type="date" name="f_fin_a" class="date-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_altas_compras" class="btn-dl btn-dl-orange">
                    📥 Descargar Compras
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>⚠️ Bajas y Siniestralidad</h4>
                <p>Reporte de equipos retirados del inventario por daño, robo o fin de vida útil.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini_b" class="date-input" required>
                    <input type="date" name="f_fin_b" class="date-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_bajas_siniestros" class="btn-dl" style="background: #dc3545; color: white;">
                    🚫 Descargar Bajas
                </button>
            </form>
        </div>

    </div>
</div>

<script>
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#666';

    const jsonModL = <?= json_encode($mod_labels) ?>; const jsonModD = <?= json_encode($mod_data) ?>;
    new Chart(document.getElementById('chartModalidad'), { type: 'pie', data: { labels: jsonModL, datasets: [{ data: jsonModD, backgroundColor: ['#002D72', '#28a745', '#ffc107', '#17a2b8'], borderWidth: 1 }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } } });

    const jsonSedeL = <?= json_encode($sedes_labels) ?>; const jsonSedeD = <?= json_encode($sedes_data) ?>;
    new Chart(document.getElementById('chartSedes'), { type: 'bar', data: { labels: jsonSedeL, datasets: [{ label: 'Equipos', data: jsonSedeD, backgroundColor: '#002D72', borderRadius: 4 }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } } });

    const jsonTecL = <?= json_encode($tec_labels) ?>; const jsonTecD = <?= json_encode($tec_data) ?>;
    new Chart(document.getElementById('chartTecnicos'), { type: 'bar', data: { labels: jsonTecL, datasets: [{ label: 'Movs', data: jsonTecD, backgroundColor: '#17a2b8', borderRadius: 4 }] }, options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } } } });

    new Chart(document.getElementById('chartVida'), { type: 'doughnut', data: { labels: ['Activos', 'Bajas'], datasets: [{ data: [<?= $total_activos ?>, <?= $total_bajas ?>], backgroundColor: ['#28a745', '#dc3545'], hoverOffset: 4 }] }, options: { maintainAspectRatio: false } });
</script>

</body>
</html>