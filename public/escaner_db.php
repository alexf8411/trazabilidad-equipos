<?php
/**
 * URTRACK - Escáner Profesional de Base de Datos 
 * Versión 4.0 SQL SERVER - Optimizado para DBAs
 * 
 * CARACTERÍSTICAS:
 * ✅ Migrado a SQL Server 2019+
 * ✅ Fragmentación de índices
 * ✅ Foreign Keys y relaciones
 * ✅ Estadísticas de uso de índices
 * ✅ Tamaño real vs asignado
 * ✅ Plan de mantenimiento recomendado
 * ✅ Caché de 5 minutos
 */

require_once '../core/db.php';
require_once '../core/session.php';

// SOLO Administradores
if ($_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Configuración de límites
ini_set('max_execution_time', 90);
ini_set('memory_limit', '512M');

$db_name = 'trazabilidad_local'; // Ajustar según tu BD
$cache_key = 'db_scanner_cache_v4';
$cache_time_key = 'db_scanner_time_v4';
$cache_duration = 300; // 5 minutos

// ============================================================================
// FUNCIÓN PRINCIPAL DE ESCANEO
// ============================================================================
function obtenerDatosDB($pdo, $db_name, $force_refresh = false) {
    global $cache_key, $cache_time_key, $cache_duration;
    
    // Verificar caché
    if (!$force_refresh && 
        isset($_SESSION[$cache_key]) && 
        isset($_SESSION[$cache_time_key]) &&
        (time() - $_SESSION[$cache_time_key]) < $cache_duration) {
        return $_SESSION[$cache_key];
    }
    
    $datos = [];
    
    try {
        // ================================================================
        // 1. INFORMACIÓN GENERAL DE LA BASE DE DATOS
        // ================================================================
        $stmt = $pdo->query("
            SELECT 
                DB_NAME() AS nombre_bd,
                SERVERPROPERTY('ProductVersion') AS version_sql,
                SERVERPROPERTY('ProductLevel') AS nivel,
                SERVERPROPERTY('Edition') AS edicion
        ");
        $datos['info_bd'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Tamaño total de la BD
        $stmt = $pdo->query("
            SELECT 
                SUM(size * 8.0 / 1024) AS tamano_mb
            FROM sys.database_files
        ");
        $size = $stmt->fetch(PDO::FETCH_ASSOC);
        $datos['info_bd']['tamano_total_mb'] = round($size['tamano_mb'], 2);
        
        // ================================================================
        // 2. RESUMEN DE TABLAS (TOP 20 por tamaño)
        // ================================================================
        $stmt = $pdo->query("
            SELECT TOP 20
                t.name AS tabla,
                SUM(a.total_pages) * 8 / 1024.0 AS tamano_mb,
                SUM(a.used_pages) * 8 / 1024.0 AS usado_mb,
                SUM(a.data_pages) * 8 / 1024.0 AS datos_mb,
                p.rows AS filas
            FROM sys.tables t
            INNER JOIN sys.indexes i ON t.object_id = i.object_id
            INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id
            INNER JOIN sys.allocation_units a ON p.partition_id = a.container_id
            WHERE t.is_ms_shipped = 0
            GROUP BY t.name, p.rows
            ORDER BY SUM(a.total_pages) DESC
        ");
        $datos['tablas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ================================================================
        // 3. PARA CADA TABLA: DETALLES COMPLETOS
        // ================================================================
        foreach ($datos['tablas'] as &$tabla) {
            $table_name = $tabla['tabla'];
            
            // 3.1 COLUMNAS
            $stmt = $pdo->prepare("
                SELECT 
                    c.name AS nombre,
                    TYPE_NAME(c.user_type_id) AS tipo,
                    c.max_length,
                    c.precision,
                    c.scale,
                    c.is_nullable,
                    c.is_identity,
                    ISNULL(dc.definition, '') AS valor_default
                FROM sys.columns c
                LEFT JOIN sys.default_constraints dc ON c.default_object_id = dc.object_id
                WHERE c.object_id = OBJECT_ID(?)
                ORDER BY c.column_id
            ");
            $stmt->execute([$table_name]);
            $tabla['columnas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3.2 ÍNDICES CON FRAGMENTACIÓN Y ESTADÍSTICAS DE USO
            $stmt = $pdo->prepare("
                SELECT 
                    i.name AS nombre,
                    i.type_desc AS tipo,
                    i.is_primary_key,
                    i.is_unique,
                    STUFF((
                        SELECT ', ' + c.name
                        FROM sys.index_columns ic
                        INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
                        WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                        ORDER BY ic.key_ordinal
                        FOR XML PATH('')
                    ), 1, 2, '') AS columnas,
                    ips.avg_fragmentation_in_percent AS fragmentacion,
                    ips.page_count AS paginas,
                    ius.user_seeks AS busquedas,
                    ius.user_scans AS escaneos,
                    ius.user_lookups AS lookups,
                    ius.user_updates AS actualizaciones,
                    ius.last_user_seek AS ultima_busqueda,
                    ius.last_user_scan AS ultimo_escaneo
                FROM sys.indexes i
                LEFT JOIN sys.dm_db_index_physical_stats(DB_ID(), OBJECT_ID(?), NULL, NULL, 'LIMITED') ips 
                    ON i.object_id = ips.object_id AND i.index_id = ips.index_id
                LEFT JOIN sys.dm_db_index_usage_stats ius 
                    ON i.object_id = ius.object_id AND i.index_id = ius.index_id AND ius.database_id = DB_ID()
                WHERE i.object_id = OBJECT_ID(?)
                ORDER BY i.index_id
            ");
            $stmt->execute([$table_name, $table_name]);
            $tabla['indices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3.3 FOREIGN KEYS (Relaciones)
            $stmt = $pdo->prepare("
                SELECT 
                    fk.name AS nombre_fk,
                    OBJECT_NAME(fk.parent_object_id) AS tabla_origen,
                    OBJECT_NAME(fk.referenced_object_id) AS tabla_destino,
                    STUFF((
                        SELECT ', ' + COL_NAME(fc.parent_object_id, fc.parent_column_id)
                        FROM sys.foreign_key_columns fc
                        WHERE fc.constraint_object_id = fk.object_id
                        FOR XML PATH('')
                    ), 1, 2, '') AS columnas_origen,
                    STUFF((
                        SELECT ', ' + COL_NAME(fc.referenced_object_id, fc.referenced_column_id)
                        FROM sys.foreign_key_columns fc
                        WHERE fc.constraint_object_id = fk.object_id
                        FOR XML PATH('')
                    ), 1, 2, '') AS columnas_destino,
                    fk.delete_referential_action_desc AS on_delete,
                    fk.update_referential_action_desc AS on_update
                FROM sys.foreign_keys fk
                WHERE fk.parent_object_id = OBJECT_ID(?) OR fk.referenced_object_id = OBJECT_ID(?)
            ");
            $stmt->execute([$table_name, $table_name]);
            $tabla['foreign_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3.4 ESTADÍSTICAS DE LA TABLA
            $stmt = $pdo->prepare("
                SELECT 
                    s.name AS nombre_stat,
                    STATS_DATE(s.object_id, s.stats_id) AS ultima_actualizacion,
                    STUFF((
                        SELECT ', ' + c.name
                        FROM sys.stats_columns sc
                        INNER JOIN sys.columns c ON sc.object_id = c.object_id AND sc.column_id = c.column_id
                        WHERE sc.object_id = s.object_id AND sc.stats_id = s.stats_id
                        ORDER BY sc.stats_column_id
                        FOR XML PATH('')
                    ), 1, 2, '') AS columnas
                FROM sys.stats s
                WHERE s.object_id = OBJECT_ID(?)
            ");
            $stmt->execute([$table_name]);
            $tabla['estadisticas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // ================================================================
        // 4. ANÁLISIS DE FRAGMENTACIÓN GENERAL
        // ================================================================
        $stmt = $pdo->query("
            SELECT 
                OBJECT_NAME(ips.object_id) AS tabla,
                i.name AS indice,
                ips.avg_fragmentation_in_percent AS fragmentacion,
                ips.page_count AS paginas
            FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ips
            INNER JOIN sys.indexes i ON ips.object_id = i.object_id AND ips.index_id = i.index_id
            WHERE ips.avg_fragmentation_in_percent > 30
                AND ips.page_count > 100
                AND i.name IS NOT NULL
            ORDER BY ips.avg_fragmentation_in_percent DESC
        ");
        $datos['indices_fragmentados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ================================================================
        // 5. ÍNDICES NO UTILIZADOS
        // ================================================================
        $stmt = $pdo->query("
            SELECT 
                OBJECT_NAME(i.object_id) AS tabla,
                i.name AS indice,
                i.type_desc AS tipo,
                (SELECT SUM(used_pages) * 8 / 1024.0 
                 FROM sys.dm_db_partition_stats 
                 WHERE object_id = i.object_id AND index_id = i.index_id) AS tamano_mb
            FROM sys.indexes i
            WHERE i.object_id > 100
                AND i.type_desc <> 'HEAP'
                AND i.is_primary_key = 0
                AND i.is_unique_constraint = 0
                AND NOT EXISTS (
                    SELECT 1 
                    FROM sys.dm_db_index_usage_stats ius
                    WHERE ius.object_id = i.object_id 
                        AND ius.index_id = i.index_id
                        AND ius.database_id = DB_ID()
                )
            ORDER BY tamano_mb DESC
        ");
        $datos['indices_no_usados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ================================================================
        // 6. ESTADÍSTICAS DESACTUALIZADAS
        // ================================================================
        $stmt = $pdo->query("
            SELECT 
                OBJECT_NAME(s.object_id) AS tabla,
                s.name AS estadistica,
                STATS_DATE(s.object_id, s.stats_id) AS ultima_actualizacion,
                DATEDIFF(DAY, STATS_DATE(s.object_id, s.stats_id), GETDATE()) AS dias_sin_actualizar
            FROM sys.stats s
            WHERE STATS_DATE(s.object_id, s.stats_id) < DATEADD(DAY, -30, GETDATE())
                AND OBJECTPROPERTY(s.object_id, 'IsUserTable') = 1
            ORDER BY dias_sin_actualizar DESC
        ");
        $datos['estadisticas_viejas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Guardar en caché
        $_SESSION[$cache_key] = $datos;
        $_SESSION[$cache_time_key] = time();
        
        return $datos;
        
    } catch (PDOException $e) {
        error_log("Error en escaner_db: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

// Manejar refresh manual
$force_refresh = isset($_GET['refresh']);
$datos = obtenerDatosDB($pdo, $db_name, $force_refresh);
$cache_age = isset($_SESSION[$cache_time_key]) ? (time() - $_SESSION[$cache_time_key]) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escáner BD Profesional - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    <style>
        .scanner-header {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .scanner-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .cache-info {
            background: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-block;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .section-card h2 {
            color: var(--primary-color);
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .db-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .db-info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .db-info-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .db-info-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: monospace;
        }
        
        .table-detail {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .table-detail h3 {
            color: var(--primary-color);
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9rem;
        }
        
        .data-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .data-table tr:hover {
            background: #f0f4f8;
        }
        
        .stat-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .stat-badge.size {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .stat-badge.rows {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .stat-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-badge.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .stat-badge.danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .fragmentation-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .fragmentation-fill {
            height: 100%;
            transition: width 0.3s;
        }
        
        .frag-low { background: #28a745; }
        .frag-medium { background: #ffc107; }
        .frag-high { background: #dc3545; }
        
        .accordion {
            background: #fff;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .accordion-header {
            padding: 15px 20px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .accordion-header:hover {
            background: #e9ecef;
        }
        
        .accordion-content {
            padding: 20px;
            display: none;
        }
        
        .accordion.active .accordion-content {
            display: block;
        }
        
        .accordion.active .accordion-header {
            border-bottom: 1px solid #e1e4e8;
            border-radius: 8px 8px 0 0;
        }
        
        @media (max-width: 768px) {
            .scanner-header h1 {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="scanner-header">
        <h1>🔬 Escáner Profesional de Base de Datos</h1>
        <p style="margin: 5px 0;">Análisis completo para DBAs - SQL Server</p>
        <div class="cache-info">
            ⏱️ Datos cacheados hace <?= $cache_age ?> segundos (válido por 5 minutos)
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="action-buttons">
        <a href="escaner_db.php?refresh=1" class="btn btn-primary">🔄 Refrescar Datos</a>
        <a href="diagnostico.php" class="btn btn-outline">🏥 Diagnóstico</a>
        <a href="syscheck.php" class="btn btn-outline">🖥️ Infraestructura</a>
        <a href="dashboard.php" class="btn btn-outline">⬅ Dashboard</a>
    </div>

    <?php if (isset($datos['error'])): ?>
        <div class="alert alert-error">
            ❌ Error: <?= htmlspecialchars($datos['error']) ?>
        </div>
    <?php else: ?>
        
        <!-- INFORMACIÓN GENERAL DE LA BD -->
        <div class="section-card">
            <h2>📊 Información General</h2>
            <div class="db-info-grid">
                <div class="db-info-item">
                    <div class="db-info-label">Base de Datos</div>
                    <div class="db-info-value"><?= htmlspecialchars($datos['info_bd']['nombre_bd']) ?></div>
                </div>
                <div class="db-info-item">
                    <div class="db-info-label">Versión SQL Server</div>
                    <div class="db-info-value"><?= htmlspecialchars($datos['info_bd']['version_sql']) ?></div>
                </div>
                <div class="db-info-item">
                    <div class="db-info-label">Nivel</div>
                    <div class="db-info-value"><?= htmlspecialchars($datos['info_bd']['nivel']) ?></div>
                </div>
                <div class="db-info-item">
                    <div class="db-info-label">Edición</div>
                    <div class="db-info-value"><?= htmlspecialchars($datos['info_bd']['edicion']) ?></div>
                </div>
                <div class="db-info-item">
                    <div class="db-info-label">Tamaño Total</div>
                    <div class="db-info-value"><?= number_format($datos['info_bd']['tamano_total_mb'], 2) ?> MB</div>
                </div>
            </div>
        </div>

        <!-- ALERTAS DE MANTENIMIENTO -->
        <?php if (count($datos['indices_fragmentados']) > 0 || count($datos['indices_no_usados']) > 0 || count($datos['estadisticas_viejas']) > 0): ?>
        <div class="section-card">
            <h2>⚠️ Alertas de Mantenimiento</h2>
            
            <?php if (count($datos['indices_fragmentados']) > 0): ?>
                <div class="alert alert-warning" style="margin-bottom: 15px;">
                    <strong>🔧 Índices Fragmentados:</strong> <?= count($datos['indices_fragmentados']) ?> índices requieren mantenimiento (>30% fragmentación)
                </div>
            <?php endif; ?>
            
            <?php if (count($datos['indices_no_usados']) > 0): ?>
                <div class="alert alert-info" style="margin-bottom: 15px;">
                    <strong>📦 Índices No Utilizados:</strong> <?= count($datos['indices_no_usados']) ?> índices sin uso detectados (candidatos a eliminar)
                </div>
            <?php endif; ?>
            
            <?php if (count($datos['estadisticas_viejas']) > 0): ?>
                <div class="alert alert-warning">
                    <strong>📉 Estadísticas Desactualizadas:</strong> <?= count($datos['estadisticas_viejas']) ?> estadísticas con más de 30 días sin actualizar
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- RESUMEN DE TABLAS -->
        <div class="section-card">
            <h2>📋 Resumen de Tablas (Top 20 por tamaño)</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Tamaño Total</th>
                        <th>Datos</th>
                        <th>Usado</th>
                        <th>Filas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos['tablas'] as $tabla): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tabla['tabla']) ?></strong></td>
                            <td><span class="stat-badge size"><?= number_format($tabla['tamano_mb'], 2) ?> MB</span></td>
                            <td><?= number_format($tabla['datos_mb'], 2) ?> MB</td>
                            <td><?= number_format($tabla['usado_mb'], 2) ?> MB</td>
                            <td><span class="stat-badge rows"><?= number_format($tabla['filas']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- DETALLE POR TABLA (Acordeón) -->
        <div class="section-card">
            <h2>🔎 Análisis Detallado por Tabla</h2>
            
            <?php foreach ($datos['tablas'] as $index => $tabla): ?>
                <div class="accordion" id="tabla-<?= $index ?>">
                    <div class="accordion-header" onclick="toggleAccordion('tabla-<?= $index ?>')">
                        <span><strong><?= htmlspecialchars($tabla['tabla']) ?></strong> - <?= number_format($tabla['filas']) ?> filas</span>
                        <span>▼</span>
                    </div>
                    <div class="accordion-content">
                        
                        <!-- Columnas -->
                        <h4 style="color: #666; margin: 15px 0 10px 0;">📝 Columnas (<?= count($tabla['columnas']) ?>)</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Tamaño</th>
                                    <th>Null</th>
                                    <th>Identity</th>
                                    <th>Default</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabla['columnas'] as $col): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($col['nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($col['tipo']) ?></td>
                                        <td>
                                            <?php 
                                            if ($col['max_length'] > 0) {
                                                echo $col['max_length'];
                                            } elseif ($col['precision'] > 0) {
                                                echo "({$col['precision']},{$col['scale']})";
                                            }
                                            ?>
                                        </td>
                                        <td><?= $col['is_nullable'] ? '✓' : '' ?></td>
                                        <td><?= $col['is_identity'] ? '<span class="stat-badge warning">ID</span>' : '' ?></td>
                                        <td><?= htmlspecialchars($col['valor_default']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Índices -->
                        <h4 style="color: #666; margin: 25px 0 10px 0;">🗂️ Índices (<?= count($tabla['indices']) ?>)</h4>
                        <?php if (count($tabla['indices']) > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Columnas</th>
                                        <th>Fragmentación</th>
                                        <th>Uso</th>
                                        <th>Última Actividad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tabla['indices'] as $idx): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($idx['nombre']) ?></strong>
                                                <?php if ($idx['is_primary_key']): ?>
                                                    <span class="stat-badge success">PK</span>
                                                <?php endif; ?>
                                                <?php if ($idx['is_unique']): ?>
                                                    <span class="stat-badge warning">UNIQUE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($idx['tipo']) ?></td>
                                            <td><?= htmlspecialchars($idx['columnas']) ?></td>
                                            <td>
                                                <?php if ($idx['fragmentacion'] !== null): ?>
                                                    <?= number_format($idx['fragmentacion'], 1) ?>%
                                                    <div class="fragmentation-bar">
                                                        <?php 
                                                        $frag_class = 'frag-low';
                                                        if ($idx['fragmentacion'] > 30) $frag_class = 'frag-medium';
                                                        if ($idx['fragmentacion'] > 60) $frag_class = 'frag-high';
                                                        ?>
                                                        <div class="fragmentation-fill <?= $frag_class ?>" style="width: <?= min($idx['fragmentacion'], 100) ?>%"></div>
                                                    </div>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($idx['busquedas'] || $idx['escaneos'] || $idx['lookups']): ?>
                                                    Seeks: <?= number_format($idx['busquedas'] ?? 0) ?><br>
                                                    Scans: <?= number_format($idx['escaneos'] ?? 0) ?><br>
                                                    Lookups: <?= number_format($idx['lookups'] ?? 0) ?>
                                                <?php else: ?>
                                                    <span class="stat-badge danger">Sin uso</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $ultima_actividad = $idx['ultima_busqueda'] ?? $idx['ultimo_escaneo'];
                                                if ($ultima_actividad) {
                                                    echo date('Y-m-d H:i', strtotime($ultima_actividad));
                                                } else {
                                                    echo 'Nunca';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color: #dc3545; font-weight: bold;">⚠️ Esta tabla no tiene índices</p>
                        <?php endif; ?>

                        <!-- Foreign Keys -->
                        <?php if (count($tabla['foreign_keys']) > 0): ?>
                            <h4 style="color: #666; margin: 25px 0 10px 0;">🔗 Relaciones (<?= count($tabla['foreign_keys']) ?>)</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre FK</th>
                                        <th>De → A</th>
                                        <th>Columnas</th>
                                        <th>On Delete</th>
                                        <th>On Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tabla['foreign_keys'] as $fk): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($fk['nombre_fk']) ?></td>
                                            <td><?= htmlspecialchars($fk['tabla_origen']) ?> → <?= htmlspecialchars($fk['tabla_destino']) ?></td>
                                            <td><?= htmlspecialchars($fk['columnas_origen']) ?> → <?= htmlspecialchars($fk['columnas_destino']) ?></td>
                                            <td><span class="stat-badge info"><?= htmlspecialchars($fk['on_delete']) ?></span></td>
                                            <td><span class="stat-badge info"><?= htmlspecialchars($fk['on_update']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <!-- Estadísticas -->
                        <?php if (count($tabla['estadisticas']) > 0): ?>
                            <h4 style="color: #666; margin: 25px 0 10px 0;">📈 Estadísticas (<?= count($tabla['estadisticas']) ?>)</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Columnas</th>
                                        <th>Última Actualización</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tabla['estadisticas'] as $stat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($stat['nombre_stat']) ?></td>
                                            <td><?= htmlspecialchars($stat['columnas']) ?></td>
                                            <td>
                                                <?php 
                                                if ($stat['ultima_actualizacion']) {
                                                    echo date('Y-m-d H:i', strtotime($stat['ultima_actualizacion']));
                                                    $dias = (time() - strtotime($stat['ultima_actualizacion'])) / 86400;
                                                    if ($dias > 30) {
                                                        echo ' <span class="stat-badge warning">Antigua</span>';
                                                    }
                                                } else {
                                                    echo 'Nunca';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PLAN DE MANTENIMIENTO -->
        <?php if (count($datos['indices_fragmentados']) > 0): ?>
        <div class="section-card">
            <h2>🔧 Plan de Mantenimiento Recomendado</h2>
            <h4 style="margin-top: 0;">Índices Fragmentados (>30%)</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Índice</th>
                        <th>Fragmentación</th>
                        <th>Páginas</th>
                        <th>Acción Recomendada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos['indices_fragmentados'] as $idx): ?>
                        <tr>
                            <td><?= htmlspecialchars($idx['tabla']) ?></td>
                            <td><?= htmlspecialchars($idx['indice']) ?></td>
                            <td>
                                <span class="stat-badge <?= $idx['fragmentacion'] > 60 ? 'danger' : 'warning' ?>">
                                    <?= number_format($idx['fragmentacion'], 1) ?>%
                                </span>
                            </td>
                            <td><?= number_format($idx['paginas']) ?></td>
                            <td>
                                <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">
                                    <?php if ($idx['fragmentacion'] > 60): ?>
                                        ALTER INDEX <?= htmlspecialchars($idx['indice']) ?> ON <?= htmlspecialchars($idx['tabla']) ?> REBUILD
                                    <?php else: ?>
                                        ALTER INDEX <?= htmlspecialchars($idx['indice']) ?> ON <?= htmlspecialchars($idx['tabla']) ?> REORGANIZE
                                    <?php endif; ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    
    <!-- Footer info -->
    <div class="alert alert-info" style="margin-top: 30px;">
        ℹ️ <strong>Nota:</strong> Los datos se cachean durante 5 minutos. Use "Refrescar Datos" para análisis en tiempo real.
    </div>
</div>

<script>
function toggleAccordion(id) {
    const accordion = document.getElementById(id);
    accordion.classList.toggle('active');
}

console.log('✅ Escáner DB Profesional cargado correctamente');
</script>

</body>
</html>