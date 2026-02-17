<?php
/**
 * URTRACK - Reportes y Business Intelligence
 * Versión 3.0 FINAL
 * 
 * CAMBIOS APLICADOS:
 * ✅ KPIs clicables (8 totales)
 * ✅ Sin badge de caché
 * ✅ Sin KPI "Valor Inventario"
 * ✅ Todos los reportes desde KPIs funcionando
 * ✅ LATERAL JOIN (no subconsultas)
 * ✅ Caché en sesión (5 minutos)
 * ✅ Límites estrictos en queries
 * ✅ CSS centralizado
 * ✅ Responsive completo
 */

require_once '../core/db.php';
require_once '../core/session.php';

// CONTROL DE ACCESO
if (!in_array($_SESSION['rol'], ['Administrador', 'Auditor', 'Recursos'])) {
    header("Location: dashboard.php");
    exit;
}

// LÍMITES DE SEGURIDAD
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');

// CONFIGURACIÓN DE CACHÉ
$cache_duration = 300; // 5 minutos
$cache_key_kpis = 'reportes_kpis_cache';
$cache_key_charts = 'reportes_charts_cache';
$cache_key_time = 'reportes_cache_time';

// Filtro de tiempo (desde toolbar)
$time_filter = $_GET['filter'] ?? 'all';
$force_refresh = isset($_GET['refresh']);

// ============================================================================
// LÓGICA DE EXPORTACIÓN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $date_now = date('Y-m-d_H-i');
    
    try {
        // ===== REPORTES DESDE KPIs CLICABLES =====
        
        // 1. DESDE KPI: Total Activos
        if (isset($_POST['btn_kpi_activos'])) {
            $_POST['btn_inventario'] = true; // Reutilizar código existente
        }
        
        // 2. DESDE KPI: En Bodega
        if (isset($_POST['btn_kpi_bodega'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Equipos_Bodega_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'MARCA', 'MODELO', 'SEDE', 'UBICACION', 'FECHA_INGRESO', 'TECNICO']);
            
            $sql = "SELECT e.placa_ur, e.serial, e.marca, e.modelo,
                    l.sede, l.nombre AS ubicacion, last_event.fecha_evento, last_event.tecnico_responsable
                    FROM equipos e
                    LEFT JOIN LATERAL (
                        SELECT tipo_evento, id_lugar, fecha_evento, tecnico_responsable
                        FROM bitacora WHERE serial_equipo = e.serial
                        ORDER BY id_evento DESC LIMIT 1
                    ) AS last_event ON TRUE
                    LEFT JOIN lugares l ON last_event.id_lugar = l.id
                    WHERE e.estado_maestro = 'Alta'
                    AND last_event.tipo_evento IN ('Devolución', 'Alta', 'Alistamiento')
                    LIMIT 10000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
                fputcsv($output, array_map(function($v) { return $v ?? ''; }, $row)); 
            }
            fclose($output);
            exit;
        }
        
        // 3. DESDE KPI: Asignados
        if (isset($_POST['btn_kpi_asignados'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Equipos_Asignados_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'MARCA', 'MODELO', 'RESPONSABLE', 'SEDE', 'UBICACION', 'FECHA_ASIGNACION', 'HOSTNAME']);
            
            $sql = "SELECT e.placa_ur, e.serial, e.marca, e.modelo,
                    last_event.correo_responsable, l.sede, l.nombre AS ubicacion, last_event.fecha_evento, last_event.hostname
                    FROM equipos e
                    LEFT JOIN LATERAL (
                        SELECT tipo_evento, correo_responsable, id_lugar, fecha_evento, hostname
                        FROM bitacora WHERE serial_equipo = e.serial
                        ORDER BY id_evento DESC LIMIT 1
                    ) AS last_event ON TRUE
                    LEFT JOIN lugares l ON last_event.id_lugar = l.id
                    WHERE e.estado_maestro = 'Alta'
                    AND last_event.tipo_evento IN ('Asignación', 'Asignacion_Masiva')
                    LIMIT 10000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
                fputcsv($output, array_map(function($v) { return $v ?? ''; }, $row)); 
            }
            fclose($output);
            exit;
        }
        
        // 4. DESDE KPI: Productividad Mes
        if (isset($_POST['btn_kpi_productividad'])) {
            $inicio = date('Y-m-01') . ' 00:00:00';
            $fin = date('Y-m-t') . ' 23:59:59';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Movimientos_Mes_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['ID_EVENTO', 'FECHA', 'TIPO', 'PLACA', 'SERIAL', 'EQUIPO', 'SEDE', 'UBICACION', 'RESPONSABLE', 'REALIZADO_POR']);
            
            $sql = "SELECT b.id_evento, b.fecha_evento, b.tipo_evento, e.placa_ur, b.serial_equipo,
                    CONCAT(e.marca, ' ', e.modelo) as equipo, l.sede, l.nombre AS ubicacion, b.correo_responsable, b.tecnico_responsable
                    FROM bitacora b
                    JOIN equipos e ON b.serial_equipo = e.serial
                    LEFT JOIN lugares l ON b.id_lugar = l.id
                    WHERE b.fecha_evento BETWEEN ? AND ?
                    ORDER BY b.fecha_evento DESC
                    LIMIT 50000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicio, $fin]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // 5. DESDE KPI: Fin de Vida
        if (isset($_POST['btn_kpi_finvida'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Equipos_FinVida_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'EQUIPO', 'FECHA_COMPRA', 'VIDA_UTIL', 'ANTIGUEDAD', 'PORCENTAJE_USO']);
            
            $sql = "SELECT e.placa_ur, e.serial, CONCAT(e.marca, ' ', e.modelo),
                    e.fecha_compra, e.vida_util,
                    TIMESTAMPDIFF(YEAR, e.fecha_compra, NOW()) AS antiguedad,
                    ROUND((TIMESTAMPDIFF(YEAR, e.fecha_compra, NOW()) / e.vida_util) * 100, 1) AS porcentaje
                    FROM equipos e
                    WHERE e.estado_maestro = 'Alta'
                    AND TIMESTAMPDIFF(YEAR, e.fecha_compra, NOW()) >= (e.vida_util * 0.8)
                    ORDER BY porcentaje DESC
                    LIMIT 5000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // 6. DESDE KPI: Sin Movimiento
        if (isset($_POST['btn_kpi_sinmov'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Bodega_SinMovimiento_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'EQUIPO', 'UBICACION', 'ULTIMO_MOVIMIENTO', 'MESES_INACTIVO']);
            
            $sql = "SELECT e.placa_ur, e.serial, CONCAT(e.marca, ' ', e.modelo),
                    l.nombre AS ubicacion, last_event.fecha_evento,
                    TIMESTAMPDIFF(MONTH, last_event.fecha_evento, NOW()) AS meses
                    FROM equipos e
                    LEFT JOIN LATERAL (
                        SELECT tipo_evento, id_lugar, fecha_evento
                        FROM bitacora WHERE serial_equipo = e.serial
                        ORDER BY id_evento DESC LIMIT 1
                    ) AS last_event ON TRUE
                    LEFT JOIN lugares l ON last_event.id_lugar = l.id
                    WHERE e.estado_maestro = 'Alta'
                    AND last_event.tipo_evento IN ('Devolución', 'Alta', 'Alistamiento')
                    AND l.nombre LIKE '%Bodega%'
                    AND last_event.fecha_evento < DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    ORDER BY meses DESC
                    LIMIT 5000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // 7. DESDE KPI: Tasa Siniestralidad
        if (isset($_POST['btn_kpi_siniestralidad'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Todas_Bajas_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['FECHA_BAJA', 'MOTIVO', 'PLACA', 'SERIAL', 'EQUIPO', 'VALOR_PERDIDO', 'TECNICO']);
            
            $sql = "SELECT b.fecha_evento, b.desc_evento, e.placa_ur, e.serial,
                    CONCAT(e.marca, ' ', e.modelo), e.precio, b.tecnico_responsable
                    FROM bitacora b
                    JOIN equipos e ON b.serial_equipo = e.serial
                    WHERE b.tipo_evento = 'Baja'
                    ORDER BY b.fecha_evento DESC
                    LIMIT 10000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // 8. DESDE KPI: Sin Compliance
        if (isset($_POST['btn_kpi_compliance'])) {
            $_POST['btn_compliance'] = true; // Reutilizar código existente
        }
        
        // ===== REPORTES ORIGINALES (del Centro de Descargas) =====
        
        // A. INVENTARIO MAESTRO
        if (isset($_POST['btn_inventario'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Inventario_Maestro_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'MARCA', 'MODELO', 'MODALIDAD', 'PRECIO', 
                              'FECHA_COMPRA', 'VIDA_UTIL', 'ANTIGUEDAD_ANIOS', 'ESTADO_MAESTRO',
                              'TIPO_ULTIMO_EVENTO', 'FECHA_ULTIMO_MOVIMIENTO', 'ESTADO_OPERATIVO',
                              'SEDE', 'UBICACION', 'RESPONSABLE', 'TECNICO']);
            
            $sql = "
                SELECT 
                    e.placa_ur, e.serial, e.marca, e.modelo, e.modalidad, e.precio,
                    e.fecha_compra, e.vida_util,
                    TIMESTAMPDIFF(YEAR, e.fecha_compra, CURDATE()) AS antiguedad,
                    e.estado_maestro,
                    last_event.tipo_evento, last_event.fecha_evento,
                    CASE 
                        WHEN last_event.tipo_evento IN ('Asignación','Asignacion_Masiva') THEN 'Asignado'
                        WHEN last_event.tipo_evento IN ('Devolución','Alta','Alistamiento') THEN 'Bodega'
                        WHEN last_event.tipo_evento = 'Baja' THEN 'Baja'
                        ELSE 'Sin historial'
                    END AS estado_op,
                    l.sede, l.nombre AS ubicacion, last_event.correo_responsable, last_event.tecnico_responsable
                FROM equipos e
                LEFT JOIN LATERAL (
                    SELECT tipo_evento, fecha_evento, id_lugar, correo_responsable, tecnico_responsable
                    FROM bitacora
                    WHERE serial_equipo = e.serial
                    ORDER BY id_evento DESC
                    LIMIT 1
                ) AS last_event ON TRUE
                LEFT JOIN lugares l ON last_event.id_lugar = l.id
                ORDER BY e.placa_ur
                LIMIT 10000
            ";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, array_map(function($v) { return $v ?? ''; }, $row));
            }
            fclose($output);
            exit;
        }
        
        // B. MOVIMIENTOS
        if (isset($_POST['btn_movimientos'])) {
            $inicio = $_POST['f_ini'] . ' 00:00:00';
            $fin = $_POST['f_fin'] . ' 23:59:59';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Movimientos_' . $_POST['f_ini'] . '_a_' . $_POST['f_fin'] . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['ID_EVENTO', 'FECHA', 'TIPO', 'PLACA', 'SERIAL', 'EQUIPO', 'SEDE', 'UBICACION', 'RESPONSABLE', 'REALIZADO_POR']);
            
            $sql = "SELECT b.id_evento, b.fecha_evento, b.tipo_evento, e.placa_ur, b.serial_equipo,
                    CONCAT(e.marca, ' ', e.modelo) as equipo, l.sede, l.nombre AS ubicacion, b.correo_responsable, b.tecnico_responsable
                    FROM bitacora b
                    JOIN equipos e ON b.serial_equipo = e.serial
                    LEFT JOIN lugares l ON b.id_lugar = l.id
                    WHERE b.fecha_evento BETWEEN ? AND ?
                    ORDER BY b.fecha_evento DESC
                    LIMIT 50000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicio, $fin]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // C. ALTAS/COMPRAS
        if (isset($_POST['btn_altas'])) {
            $inicio = $_POST['f_ini_a'] . ' 00:00:00';
            $fin = $_POST['f_fin_a'] . ' 23:59:59';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Altas_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['FECHA_INGRESO', 'ORDEN_COMPRA', 'PLACA', 'SERIAL', 'EQUIPO', 'MODALIDAD', 'VALOR']);
            
            $sql = "SELECT b.fecha_evento, b.desc_evento, e.placa_ur, e.serial,
                    CONCAT(e.marca, ' ', e.modelo), e.modalidad, e.precio
                    FROM bitacora b
                    JOIN equipos e ON b.serial_equipo = e.serial
                    WHERE b.tipo_evento = 'Alta' AND b.fecha_evento BETWEEN ? AND ?
                    ORDER BY b.fecha_evento DESC
                    LIMIT 10000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicio, $fin]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // D. BAJAS/SINIESTROS
        if (isset($_POST['btn_bajas'])) {
            $inicio = $_POST['f_ini_b'] . ' 00:00:00';
            $fin = $_POST['f_fin_b'] . ' 23:59:59';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Bajas_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['FECHA_BAJA', 'MOTIVO', 'PLACA', 'SERIAL', 'EQUIPO', 'VALOR_PERDIDO', 'TECNICO']);
            
            $sql = "SELECT b.fecha_evento, b.desc_evento, e.placa_ur, e.serial,
                    CONCAT(e.marca, ' ', e.modelo), e.precio, b.tecnico_responsable
                    FROM bitacora b
                    JOIN equipos e ON b.serial_equipo = e.serial
                    WHERE b.tipo_evento = 'Baja' AND b.fecha_evento BETWEEN ? AND ?
                    ORDER BY b.fecha_evento DESC
                    LIMIT 10000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicio, $fin]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // E. COMPLIANCE
        if (isset($_POST['btn_compliance'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Compliance_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'EQUIPO', 'RESPONSABLE', 'SEDE', 'UBICACION', 'DLO', 'SCCM', 'ANTIVIRUS']);
            
            $sql = "SELECT e.placa_ur, e.serial, CONCAT(e.marca, ' ', e.modelo),
                    last_event.correo_responsable, l.sede, l.nombre AS ubicacion,
                    CASE WHEN last_event.check_dlo = 1 THEN 'SI' ELSE 'NO' END,
                    CASE WHEN last_event.check_sccm = 1 THEN 'SI' ELSE 'NO' END,
                    CASE WHEN last_event.check_antivirus = 1 THEN 'SI' ELSE 'NO' END
                    FROM equipos e
                    LEFT JOIN LATERAL (
                        SELECT correo_responsable, id_lugar, tipo_evento, check_dlo, check_sccm, check_antivirus
                        FROM bitacora
                        WHERE serial_equipo = e.serial
                        ORDER BY id_evento DESC
                        LIMIT 1
                    ) AS last_event ON TRUE
                    LEFT JOIN lugares l ON last_event.id_lugar = l.id
                    WHERE e.estado_maestro = 'Alta'
                    AND last_event.tipo_evento IN ('Asignación', 'Asignacion_Masiva')
                    AND (last_event.check_dlo = 0 OR last_event.check_sccm = 0 OR last_event.check_antivirus = 0)
                    LIMIT 10000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // F. OBSOLETOS
        if (isset($_POST['btn_obsoletos'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Obsoletos_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['PLACA', 'SERIAL', 'EQUIPO', 'FECHA_COMPRA', 'VIDA_UTIL', 'ANTIGUEDAD', 'ESTADO']);
            
            $sql = "SELECT e.placa_ur, e.serial, CONCAT(e.marca, ' ', e.modelo),
                    e.fecha_compra, e.vida_util,
                    TIMESTAMPDIFF(YEAR, e.fecha_compra, NOW()) AS antiguedad,
                    e.estado_maestro
                    FROM equipos e
                    WHERE TIMESTAMPDIFF(YEAR, e.fecha_compra, NOW()) > e.vida_util
                    ORDER BY antiguedad DESC
                    LIMIT 5000";
            
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
        // G. HOJA DE VIDA
        if (isset($_POST['btn_hoja_vida'])) {
            $serial = strtoupper(trim($_POST['serial_hv']));
            
            if (!preg_match('/^[A-Z0-9\-]{3,30}$/i', $serial)) {
                die("Serial inválido");
            }
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=HojaVida_' . $serial . '_' . $date_now . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($output, ['FECHA', 'TIPO_EVENTO', 'SEDE', 'UBICACION', 'RESPONSABLE', 'HOSTNAME', 'TECNICO', 'DETALLES']);
            
            $sql = "SELECT b.fecha_evento, b.tipo_evento, l.sede, l.nombre AS ubicacion,
                    b.correo_responsable, b.hostname, b.tecnico_responsable, b.desc_evento
                    FROM bitacora b
                    LEFT JOIN lugares l ON b.id_lugar = l.id
                    WHERE b.serial_equipo = ?
                    ORDER BY b.id_evento ASC
                    LIMIT 1000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$serial]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
            fclose($output);
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Error en reportes: " . $e->getMessage());
        http_response_code(500);
        die("Error al generar reporte");
    }
}

// ============================================================================
// OBTENER KPIs Y GRÁFICAS (CON CACHÉ)
// ============================================================================
function obtenerDatosReportes($pdo, $force_refresh = false, $time_filter = 'all') {
    global $cache_key_kpis, $cache_key_charts, $cache_key_time, $cache_duration;
    
    $cache_key = $cache_key_kpis . '_' . $time_filter;
    
    if (!$force_refresh && 
        isset($_SESSION[$cache_key]) && 
        isset($_SESSION[$cache_key_time]) &&
        (time() - $_SESSION[$cache_key_time]) < $cache_duration) {
        return $_SESSION[$cache_key];
    }
    
    $datos = [];
    
    try {
        // KPIs
        $datos['total_activos'] = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Alta'")->fetchColumn();
        $datos['total_bajas'] = $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado_maestro = 'Baja'")->fetchColumn();
        
        // En bodega
        $datos['en_bodega'] = $pdo->query("
            SELECT COUNT(DISTINCT e.serial)
            FROM equipos e
            LEFT JOIN LATERAL (
                SELECT tipo_evento
                FROM bitacora
                WHERE serial_equipo = e.serial
                ORDER BY id_evento DESC
                LIMIT 1
            ) AS last_event ON TRUE
            WHERE e.estado_maestro = 'Alta'
            AND last_event.tipo_evento IN ('Devolución', 'Alta', 'Alistamiento')
        ")->fetchColumn();
        
        $datos['asignados'] = $datos['total_activos'] - $datos['en_bodega'];
        
        // Productividad mes
        $mes_actual = date('Y-m');
        $datos['movs_mes'] = $pdo->query("SELECT COUNT(*) FROM bitacora WHERE fecha_evento LIKE '$mes_actual%' LIMIT 1000")->fetchColumn();
        
        // Próximos a fin de vida (>80% antigüedad)
        $datos['fin_vida'] = $pdo->query("
            SELECT COUNT(*)
            FROM equipos
            WHERE estado_maestro = 'Alta'
            AND TIMESTAMPDIFF(YEAR, fecha_compra, NOW()) >= (vida_util * 0.8)
            LIMIT 1000
        ")->fetchColumn();
        
        // Sin movimiento 6+ meses (SOLO BODEGA)
        $datos['sin_movimiento'] = $pdo->query("
            SELECT COUNT(DISTINCT e.serial)
            FROM equipos e
            LEFT JOIN LATERAL (
                SELECT tipo_evento, fecha_evento, id_lugar
                FROM bitacora
                WHERE serial_equipo = e.serial
                ORDER BY id_evento DESC
                LIMIT 1
            ) AS last_event ON TRUE
            LEFT JOIN lugares l ON last_event.id_lugar = l.id
            WHERE e.estado_maestro = 'Alta'
            AND last_event.tipo_evento IN ('Devolución', 'Alta', 'Alistamiento')
            AND l.nombre LIKE '%Bodega%'
            AND last_event.fecha_evento < DATE_SUB(NOW(), INTERVAL 6 MONTH)
            LIMIT 1000
        ")->fetchColumn();
        
        // Tasa siniestralidad
        $total_equipos = $datos['total_activos'] + $datos['total_bajas'];
        $datos['tasa_siniestralidad'] = $total_equipos > 0 ? round(($datos['total_bajas'] / $total_equipos) * 100, 1) : 0;
        
        // Sin compliance (SOLO ASIGNADOS)
        $datos['sin_compliance'] = $pdo->query("
            SELECT COUNT(DISTINCT e.serial)
            FROM equipos e
            LEFT JOIN LATERAL (
                SELECT tipo_evento, check_dlo, check_sccm, check_antivirus
                FROM bitacora
                WHERE serial_equipo = e.serial
                ORDER BY id_evento DESC
                LIMIT 1
            ) AS last_event ON TRUE
            WHERE e.estado_maestro = 'Alta'
            AND last_event.tipo_evento IN ('Asignación', 'Asignacion_Masiva')
            AND (last_event.check_dlo = 0 OR last_event.check_sccm = 0 OR last_event.check_antivirus = 0)
            LIMIT 1000
        ")->fetchColumn();
        
        // GRÁFICAS
        // Modalidad
        $stmt = $pdo->query("SELECT modalidad, COUNT(*) as cant FROM equipos GROUP BY modalidad LIMIT 20");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $datos['mod_labels'] = array_column($raw, 'modalidad');
        $datos['mod_data'] = array_column($raw, 'cant');
        
        // Sedes
        $stmt = $pdo->query("
            SELECT l.sede, COUNT(DISTINCT e.serial) as cant
            FROM equipos e
            LEFT JOIN LATERAL (
                SELECT id_lugar
                FROM bitacora
                WHERE serial_equipo = e.serial
                ORDER BY id_evento DESC
                LIMIT 1
            ) AS last_event ON TRUE
            LEFT JOIN lugares l ON last_event.id_lugar = l.id
            WHERE e.estado_maestro = 'Alta'
            GROUP BY l.sede
            LIMIT 50
        ");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $datos['sede_labels'] = array_column($raw, 'sede');
        $datos['sede_data'] = array_column($raw, 'cant');
        
        // Top técnicos
        $stmt = $pdo->query("
            SELECT tecnico_responsable, COUNT(*) as total
            FROM bitacora
            WHERE tecnico_responsable IS NOT NULL AND tecnico_responsable != ''
            GROUP BY tecnico_responsable
            ORDER BY total DESC
            LIMIT 10
        ");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $datos['tec_labels'] = array_map(function($t) {
            return explode(' ', trim($t))[0];
        }, array_column($raw, 'tecnico_responsable'));
        $datos['tec_data'] = array_column($raw, 'total');
        
        // Guardar en caché
        $_SESSION[$cache_key] = $datos;
        $_SESSION[$cache_key_time] = time();
        
        return $datos;
        
    } catch (Exception $e) {
        error_log("Error obteniendo datos: " . $e->getMessage());
        return ['error' => true];
    }
}

$datos = obtenerDatosReportes($pdo, $force_refresh, $time_filter);
$cache_age = isset($_SESSION[$cache_key_time]) ? (time() - $_SESSION[$cache_key_time]) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    <script src="js/chart.js"></script>
</head>
<body>

<div class="container">
    
    <div class="d-flex justify-between align-center mb-3" style="border-bottom: 2px solid var(--primary-color); padding-bottom: 15px;">
        <h2 style="margin: 0; color: var(--primary-color);">📊 Inteligencia de Negocio</h2>
        <a href="dashboard.php" class="btn btn-outline">⬅ Volver</a>
    </div>

    <!-- Toolbar (SIN BADGE DE CACHÉ) -->
    <div class="report-toolbar">
        <div class="filter-group">
            <span class="filter-label">Período:</span>
            <select class="filter-select" onchange="window.location.href='reportes.php?filter='+this.value">
                <option value="all" <?= $time_filter == 'all' ? 'selected' : '' ?>>Todo el tiempo</option>
                <option value="year" <?= $time_filter == 'year' ? 'selected' : '' ?>>Último año</option>
                <option value="month" <?= $time_filter == 'month' ? 'selected' : '' ?>>Último mes</option>
                <option value="day" <?= $time_filter == 'day' ? 'selected' : '' ?>>Último día</option>
            </select>
        </div>
        <a href="reportes.php?refresh=1&filter=<?= $time_filter ?>" class="btn btn-primary">
            🔄 Refrescar
        </a>
    </div>

    <!-- KPIs CLICABLES (8 TOTALES - SIN "VALOR INVENTARIO") -->
    <div class="kpi-grid">
        
        <!-- 1. TOTAL ACTIVOS -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_activos" class="kpi-card kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Total Activos</div>
                <div class="kpi-value"><?= number_format($datos['total_activos']) ?></div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 2. EN BODEGA -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_bodega" class="kpi-card kpi-warning kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">En Bodega</div>
                <div class="kpi-value"><?= number_format($datos['en_bodega']) ?></div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 3. ASIGNADOS -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_asignados" class="kpi-card kpi-success kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Asignados</div>
                <div class="kpi-value"><?= number_format($datos['asignados']) ?></div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 4. PRODUCTIVIDAD MES -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_productividad" class="kpi-card kpi-info kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Productividad Mes</div>
                <div class="kpi-value"><?= $datos['movs_mes'] ?></div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 5. FIN DE VIDA -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_finvida" class="kpi-card kpi-warning kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Fin de Vida</div>
                <div class="kpi-value"><?= number_format($datos['fin_vida']) ?></div>
                <div class="kpi-subtitle">>80% antigüedad</div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 6. SIN MOVIMIENTO -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_sinmov" class="kpi-card kpi-danger kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Sin Movimiento</div>
                <div class="kpi-value"><?= number_format($datos['sin_movimiento']) ?></div>
                <div class="kpi-subtitle">Bodega 6+ meses</div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 7. TASA SINIESTRALIDAD -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_siniestralidad" class="kpi-card kpi-danger kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Tasa Siniestralidad</div>
                <div class="kpi-value"><?= $datos['tasa_siniestralidad'] ?>%</div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>
        
        <!-- 8. SIN COMPLIANCE -->
        <form method="POST" style="margin: 0;">
            <button type="submit" name="btn_kpi_compliance" class="kpi-card kpi-warning kpi-card-clickable" style="width: 100%; text-align: left; background: white; border: none;">
                <div class="kpi-title">Sin Compliance</div>
                <div class="kpi-value"><?= number_format($datos['sin_compliance']) ?></div>
                <div class="kpi-subtitle">Asignados sin agentes</div>
                <div class="kpi-action">📥 Click para descargar</div>
            </button>
        </form>

    </div>
    <!-- FIN DE KPIs CLICABLES -->

    <!-- Gráficas -->
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
            <div class="chart-header"><span class="chart-title">♻️ Ciclo Vida</span></div>
            <div class="chart-canvas-container"><canvas id="chartVida"></canvas></div>
        </div>
    </div>

    <!-- Descargas -->
    <h3 class="downloads-title">📥 Centro de Descargas</h3>
    <div class="downloads-grid">
        
        <div class="download-card">
            <div>
                <h4>📦 Inventario Maestro</h4>
                <p>Snapshot actual de equipos, ubicación y estado operativo.</p>
            </div>
            <form method="POST">
                <button type="submit" name="btn_inventario" class="btn-dl btn-dl-blue">
                    📄 Descargar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>🚚 Movimientos</h4>
                <p>Trazabilidad de asignaciones y traslados.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini" class="date-input" required>
                    <input type="date" name="f_fin" class="date-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_movimientos" class="btn-dl btn-dl-green">
                    📅 Exportar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>📥 Altas/Compras</h4>
                <p>Equipos nuevos con valor y OC.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini_a" class="date-input" required>
                    <input type="date" name="f_fin_a" class="date-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_altas" class="btn-dl btn-dl-orange">
                    📥 Descargar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>🚫 Bajas</h4>
                <p>Equipos retirados por daño o robo.</p>
            </div>
            <form method="POST">
                <div class="date-group">
                    <input type="date" name="f_ini_b" class="date-input" required>
                    <input type="date" name="f_fin_b" class="date-input" required value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" name="btn_bajas" class="btn-dl btn-dl-red">
                    📥 Descargar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>🔧 Compliance</h4>
                <p>Asignados sin agentes DLO/SCCM/Antivirus.</p>
            </div>
            <form method="POST">
                <button type="submit" name="btn_compliance" class="btn-dl btn-dl-purple">
                    📥 Descargar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>⏰ Obsoletos</h4>
                <p>Equipos que excedieron vida útil.</p>
            </div>
            <form method="POST">
                <button type="submit" name="btn_obsoletos" class="btn-dl btn-dl-orange">
                    📥 Descargar
                </button>
            </form>
        </div>

        <div class="download-card">
            <div>
                <h4>📋 Hoja de Vida</h4>
                <p>Historial completo de un equipo.</p>
            </div>
            <form method="POST">
                <input type="text" name="serial_hv" class="serial-input" placeholder="SERIAL" required pattern="[A-Za-z0-9\-]{3,30}">
                <button type="submit" name="btn_hoja_vida" class="btn-dl btn-dl-blue">
                    📄 Generar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
Chart.defaults.font.family = "'Segoe UI', sans-serif";
Chart.defaults.color = '#666';

const modL = <?= json_encode($datos['mod_labels']) ?>;
const modD = <?= json_encode($datos['mod_data']) ?>;
new Chart(document.getElementById('chartModalidad'), {
    type: 'pie',
    data: {
        labels: modL,
        datasets: [{
            data: modD,
            backgroundColor: ['#002D72', '#28a745', '#ffc107', '#17a2b8'],
            borderWidth: 1
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'right' } }
    }
});

const sedeL = <?= json_encode($datos['sede_labels']) ?>;
const sedeD = <?= json_encode($datos['sede_data']) ?>;
new Chart(document.getElementById('chartSedes'), {
    type: 'bar',
    data: {
        labels: sedeL,
        datasets: [{
            label: 'Equipos',
            data: sedeD,
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

const tecL = <?= json_encode($datos['tec_labels']) ?>;
const tecD = <?= json_encode($datos['tec_data']) ?>;
new Chart(document.getElementById('chartTecnicos'), {
    type: 'bar',
    data: {
        labels: tecL,
        datasets: [{
            label: 'Movimientos',
            data: tecD,
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

new Chart(document.getElementById('chartVida'), {
    type: 'doughnut',
    data: {
        labels: ['Activos', 'Bajas'],
        datasets: [{
            data: [<?= $datos['total_activos'] ?>, <?= $datos['total_bajas'] ?>],
            backgroundColor: ['#28a745', '#dc3545'],
            hoverOffset: 4
        }]
    },
    options: { maintainAspectRatio: false }
});
</script>

</body>
</html>