<?php
/**
 * public/reportes.php
 * Módulo de Inteligencia de Negocios y Exportación
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor', 'Recursos'])) {
    header("Location: dashboard.php");
    exit;
}

// 2. LOGICA DE DESCARGA CSV (Si se solicita)
if (isset($_POST['exportar_inventario'])) {
    $filename = "Inventario_URTRACK_" . date('Y-m-d_H-i') . ".csv";
    
    // Headers para forzar descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    // BOM para que Excel reconozca tildes y ñ
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados del CSV
    fputcsv($output, ['PLACA', 'SERIAL', 'MARCA', 'MODELO', 'ESTADO', 'UBICACION ACTUAL', 'RESPONSABLE ACTUAL', 'FECHA ULTIMO MOV']);
    
    // Consulta Maestra: Equipos + Último Movimiento
    // Esta consulta es el corazón del reporte: Cruza equipos con su bitácora más reciente
    $sql = "SELECT e.placa_ur, e.serial, e.marca, e.modelo, e.estado_maestro, 
            b.ubicacion, b.correo_responsable, b.fecha_evento
            FROM equipos e
            LEFT JOIN bitacora b ON e.serial = b.serial_equipo 
            AND b.id_evento = (SELECT MAX(id_evento) FROM bitacora WHERE serial_equipo = e.serial)";
            
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// 3. CÁLCULO DE KPIs (Indicadores Clave)
try {
    // Total Activos
    $total_activos = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Alta'")->fetchColumn();
    
    // Equipos en Bodega (Basado en el último movimiento que sea 'Devolución' o 'Ingreso' y que esté en Bodega)
    // Nota: Simplificamos buscando cuántos tienen ubicación 'Bodega de Tecnología' en su último movimiento
    $sql_bodega = "SELECT COUNT(*) FROM bitacora b 
                   WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo)
                   AND b.ubicacion LIKE '%Bodega%'";
    $en_bodega = $pdo->query($sql_bodega)->fetchColumn();
    
    // Asignados (Total - Bodega)
    $asignados = $total_activos - $en_bodega;
    
    // Movimientos del Mes
    $mes_actual = date('Y-m');
    $movs_mes = $pdo->query("SELECT COUNT(*) FROM bitacora WHERE fecha_evento LIKE '$mes_actual%'")->fetchColumn();
    
    // Distribución por Sede (Para gráfica)
    $sql_sedes = "SELECT sede, COUNT(*) as cantidad FROM bitacora b 
                  WHERE b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = b.serial_equipo)
                  GROUP BY sede";
    $sedes_dist = $pdo->query($sql_sedes)->fetchAll(PDO::FETCH_KEY_PAIR); // Retorna array [Sede => Cantidad]

} catch (PDOException $e) {
    $error_kpi = "Error cargando estadísticas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes e Inteligencia | URTRACK</title>
    <style>
        :root { --primary: #002D72; --bg: #f4f7fa; --card: #fff; --text: #333; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--primary); padding-bottom: 15px; }
        .btn-back { text-decoration: none; color: #666; font-weight: 600; }
        
        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .kpi-card { background: var(--card); padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid var(--primary); }
        .kpi-title { font-size: 0.85rem; color: #666; text-transform: uppercase; font-weight: bold; }
        .kpi-value { font-size: 2rem; font-weight: 700; color: var(--primary); margin: 10px 0; }
        .kpi-sub { font-size: 0.8rem; color: #888; }
        
        /* Secciones Principales */
        .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        
        /* Panel de Descargas */
        .report-panel { background: var(--card); padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .report-panel h3 { margin-top: 0; color: var(--primary); border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .btn-download { 
            background: #22c55e; color: white; border: none; padding: 15px 25px; 
            border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold; 
            display: flex; align-items: center; gap: 10px; transition: 0.2s; width: 100%; justify-content: center;
        }
        .btn-download:hover { background: #16a34a; transform: translateY(-2px); }
        
        /* Gráfica Simple (CSS Bars) */
        .chart-panel { background: var(--card); padding: 20px; border-radius: 8px; }
        .bar-group { margin-bottom: 15px; }
        .bar-label { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; }
        .bar-bg { background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--primary); border-radius: 5px; transition: width 1s; }
        
        @media (max-width: 768px) { .main-grid { grid-template-columns: 1fr; } }
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
            <div class="kpi-sub">En uso por usuarios</div>
        </div>
        <div class="kpi-card" style="border-color: #3b82f6;">
            <div class="kpi-title">Gestión Mensual</div>
            <div class="kpi-value"><?= $movs_mes ?></div>
            <div class="kpi-sub">Movimientos en <?= date('M Y') ?></div>
        </div>
    </div>

    <div class="main-grid">
        <div class="report-panel">
            <h3>📥 Exportación de Datos</h3>
            <p style="color:#666; margin-bottom:25px;">
                Descargue el reporte completo en formato CSV (Compatible con Excel). 
                Incluye ubicación en tiempo real y responsable actual de cada activo.
            </p>
            
            <form method="POST">
                <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px; border:1px solid #e9ecef;">
                    <strong>Contenido del Reporte:</strong>
                    <ul style="font-size:0.9rem; color:#555; margin:10px 0 0 20px;">
                        <li>Inventario Maestro (Placa, Serial, Marca)</li>
                        <li>Ubicación Actualizada (Cruce con Bitácora)</li>
                        <li>Responsable a Cargo (Correo Institucional)</li>
                        <li>Estado Operativo</li>
                    </ul>
                </div>
                
                <button type="submit" name="exportar_inventario" class="btn-download">
                    📄 DESCARGAR INVENTARIO MAESTRO
                </button>
            </form>
        </div>

        <div class="chart-panel">
            <h4 style="margin-top:0; color:#444;">📍 Distribución por Sede</h4>
            <?php 
            if (!empty($sedes_dist)) {
                $max_val = max($sedes_dist); // Para calcular porcentaje relativo
                foreach ($sedes_dist as $sede => $cant) {
                    $porcentaje = ($total_activos > 0) ? round(($cant / $total_activos) * 100) : 0;
                    $ancho_barra = ($max_val > 0) ? ($cant / $max_val) * 100 : 0;
                    if(empty($sede)) $sede = "Sin Asignar / Transito";
                    
                    echo "
                    <div class='bar-group'>
                        <div class='bar-label'>
                            <span>$sede</span>
                            <span>$cant ($porcentaje%)</span>
                        </div>
                        <div class='bar-bg'>
                            <div class='bar-fill' style='width: {$ancho_barra}%'></div>
                        </div>
                    </div>";
                }
            } else {
                echo "<p style='color:#999; text-align:center;'>Sin datos suficientes</p>";
            }
            ?>
        </div>
    </div>
</div>

</body>
</html>