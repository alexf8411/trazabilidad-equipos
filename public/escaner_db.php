<?php
/**
 * URTRACK - Escáner de Base de Datos
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ Caché en sesión (evita consultas repetidas)
 * ✅ Límite de timeout y memoria
 * ✅ Botón manual de refresh
 * ✅ CSS centralizado
 * ✅ Queries optimizadas
 */

require_once '../core/db.php';
require_once '../core/session.php';

// SOLO Administradores pueden acceder
if ($_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

// Configuración de límites
ini_set('max_execution_time', 60); // 1 minuto máximo
ini_set('memory_limit', '256M');

$db_name = 'trazabilidad_local';
$cache_key = 'db_scanner_cache';
$cache_time_key = 'db_scanner_time';
$cache_duration = 300; // 5 minutos

// Función para obtener datos (con caché)
function obtenerDatosDB($pdo, $db_name, $force_refresh = false) {
    global $cache_key, $cache_time_key, $cache_duration;
    
    // Verificar si existe caché válido
    if (!$force_refresh && 
        isset($_SESSION[$cache_key]) && 
        isset($_SESSION[$cache_time_key]) &&
        (time() - $_SESSION[$cache_time_key]) < $cache_duration) {
        
        return $_SESSION[$cache_key];
    }
    
    // Si no hay caché o se forzó refresh, consultar BD
    $datos = [];
    
    try {
        // 1. Tamaño de tablas (query única optimizada)
        $stmt = $pdo->prepare("
            SELECT table_name AS tabla, 
                   ROUND((data_length + index_length) / 1024 / 1024, 2) AS tamano_mb, 
                   table_rows AS filas
            FROM information_schema.TABLES 
            WHERE table_schema = ?
            ORDER BY (data_length + index_length) DESC
            LIMIT 20
        ");
        $stmt->execute([$db_name]);
        $datos['tablas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Para cada tabla, obtener estructura
        foreach ($datos['tablas'] as &$tabla) {
            $table_name = $tabla['tabla'];
            
            // Columnas
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME AS nombre, 
                       COLUMN_TYPE AS tipo, 
                       IS_NULLABLE AS nullable,
                       COLUMN_KEY AS llave,
                       COLUMN_DEFAULT AS defecto,
                       EXTRA AS extra
                FROM information_schema.COLUMNS 
                WHERE table_schema = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
                LIMIT 50
            ");
            $stmt->execute([$db_name, $table_name]);
            $tabla['columnas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Índices
            $stmt = $pdo->prepare("
                SELECT INDEX_NAME AS nombre, 
                       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columnas,
                       MAX(NON_UNIQUE) AS no_unico,
                       INDEX_TYPE AS tipo
                FROM information_schema.STATISTICS 
                WHERE table_schema = ? AND TABLE_NAME = ?
                GROUP BY INDEX_NAME, INDEX_TYPE
                LIMIT 20
            ");
            $stmt->execute([$db_name, $table_name]);
            $tabla['indices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
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
    <title>Escáner BD - URTRACK</title>
    
    <!-- CSS EXTERNO -->
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    
    <style>
        /* Estilos específicos del escáner */
        .scanner-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .key-indicator {
            color: #f59e0b;
            font-weight: bold;
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
            
            .data-table th,
            .data-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="scanner-header">
        <h1>🔍 Radiografía de Base de Datos</h1>
        <p style="margin: 5px 0;">Sistema de diagnóstico interno - URTRACK</p>
        <div class="cache-info">
            ⏱️ Datos cacheados hace <?= $cache_age ?> segundos (válido por 5 minutos)
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="action-buttons">
        <a href="escaner_db.php?refresh=1" class="btn btn-primary">
            🔄 Refrescar Datos
        </a>
        <a href="dashboard.php" class="btn btn-outline">
            ⬅ Volver al Dashboard
        </a>
    </div>

    <?php if (isset($datos['error'])): ?>
        <div class="alert alert-error">
            ❌ Error: <?= htmlspecialchars($datos['error']) ?>
        </div>
    <?php else: ?>
        
        <!-- Resumen de tablas -->
        <div class="section-card">
            <h2>📊 Resumen de Tablas</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Tamaño (MB)</th>
                        <th>Filas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos['tablas'] as $tabla): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tabla['tabla']) ?></strong></td>
                            <td><span class="stat-badge size"><?= $tabla['tamano_mb'] ?> MB</span></td>
                            <td><span class="stat-badge rows"><?= number_format($tabla['filas']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Detalle por tabla -->
        <div class="section-card">
            <h2>🔎 Estructura Detallada</h2>
            
            <?php foreach ($datos['tablas'] as $tabla): ?>
                <div class="table-detail">
                    <h3><?= htmlspecialchars($tabla['tabla']) ?></h3>
                    
                    <!-- Columnas -->
                    <h4 style="color: #666; margin-top: 15px;">Columnas:</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Nulo</th>
                                <th>Llave</th>
                                <th>Por Defecto</th>
                                <th>Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabla['columnas'] as $col): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($col['nombre']) ?></strong></td>
                                    <td><?= htmlspecialchars($col['tipo']) ?></td>
                                    <td><?= $col['nullable'] ?></td>
                                    <td>
                                        <?php if ($col['llave']): ?>
                                            <span class="key-indicator"><?= $col['llave'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($col['defecto'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($col['extra']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Índices -->
                    <h4 style="color: #666; margin-top: 15px;">Índices:</h4>
                    <?php if (count($tabla['indices']) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Columnas</th>
                                    <th>Único</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabla['indices'] as $idx): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($idx['nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($idx['columnas']) ?></td>
                                        <td>
                                            <?php if ($idx['no_unico'] == 0): ?>
                                                <span style="color: green; font-weight: bold;">✓ Único</span>
                                            <?php else: ?>
                                                No
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($idx['tipo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #dc3545; font-weight: bold;">⚠️ Esta tabla no tiene índices</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
    
    <!-- Footer info -->
    <div class="alert alert-info" style="margin-top: 30px;">
        ℹ️ <strong>Nota:</strong> Los datos se cachean automáticamente durante 5 minutos para no sobrecargar el servidor.
        Use el botón "Refrescar Datos" solo cuando sea necesario.
    </div>
</div>

</body>
</html>