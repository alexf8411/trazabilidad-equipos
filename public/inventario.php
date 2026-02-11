<?php
ob_start();

/**
 * public/inventario.php
 * Versión V1.9: Inventario Responsivo y Dinámico
 * Mejoras:
 * - Ordenamiento por columnas (Sortable headers).
 * - Paginación inteligente (Range view).
 * - Diseño móvil "Card View".
 */

require_once '../core/db.php';
require_once '../core/session.php';

// SEGURIDAD
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 1. CONFIGURACIÓN DE ORDENAMIENTO (SORTING)
// Definimos qué columnas se pueden ordenar para evitar SQL Injection
$columnas_permitidas = [
    'placa_ur'           => 'e.placa_ur',
    'serial'             => 'e.serial',
    'marca'              => 'e.marca',
    'fecha_compra'       => 'e.fecha_compra',
    'ubicacion'          => 'b.ubicacion',
    'correo_responsable' => 'b.correo_responsable',
    'estado_maestro'     => 'e.estado_maestro'
];

// Capturamos parámetros de URL o usamos valores por defecto
$columna_orden = isset($_GET['col']) && array_key_exists($_GET['col'], $columnas_permitidas) ? $_GET['col'] : 'fecha_compra';
$orden_dir     = isset($_GET['dir']) && in_array(strtoupper($_GET['dir']), ['ASC', 'DESC']) ? strtoupper($_GET['dir']) : 'DESC';

// Mapeamos al nombre real de la columna SQL
$campo_sql_orden = $columnas_permitidas[$columna_orden];

// Función para generar links de ordenamiento
function sortLink($col, $label, $currentCol, $currentDir, $busqueda) {
    $newDir = ($currentCol == $col && $currentDir == 'ASC') ? 'DESC' : 'ASC';
    $icon   = ($currentCol == $col) ? ($currentDir == 'ASC' ? ' ▲' : ' ▼') : '';
    $active_class = ($currentCol == $col) ? 'active-sort' : '';
    
    // Construimos la URL manteniendo la búsqueda y paginación base
    $url = "?col=$col&dir=$newDir&q=" . urlencode($busqueda);
    
    return "<a href='$url' class='sort-link $active_class'>$label $icon</a>";
}

// 2. CONFIGURACIÓN DE PAGINACIÓN Y BÚSQUEDA
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_sql = "";
$params = [];

if ($busqueda != '') {
    $filtro_sql = "AND (
        e.placa_ur LIKE :p1 OR 
        e.serial LIKE :p2 OR 
        e.modelo LIKE :p3 OR 
        e.marca LIKE :p4 OR
        b.correo_responsable LIKE :p5
    )";
    $term = "%$busqueda%";
    $params[':p1'] = $term; $params[':p2'] = $term; $params[':p3'] = $term; 
    $params[':p4'] = $term; $params[':p5'] = $term;
}

try {
    // A. Contar total
    $sql_count = "SELECT COUNT(*) FROM equipos e 
                  LEFT JOIN (
                      SELECT b1.serial_equipo, b1.correo_responsable 
                      FROM bitacora b1
                      INNER JOIN (SELECT serial_equipo, MAX(id_evento) as max_id FROM bitacora GROUP BY serial_equipo) b2 
                      ON b1.id_evento = b2.max_id
                  ) b ON e.serial = b.serial_equipo
                  WHERE 1=1 $filtro_sql";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    if ($total_paginas < 1) $total_paginas = 1;

    // B. Obtener datos con Ordenamiento Dinámico
    $sql_data = "SELECT e.*, b.sede, b.ubicacion, b.tipo_evento, b.correo_responsable, b.hostname
                 FROM equipos e
                 LEFT JOIN (
                    SELECT b1.* FROM bitacora b1
                    INNER JOIN (SELECT serial_equipo, MAX(id_evento) as max_id FROM bitacora GROUP BY serial_equipo) b2 
                    ON b1.serial_equipo = b2.serial_equipo AND b1.id_evento = b2.max_id
                 ) b ON e.serial = b.serial_equipo
                 WHERE 1=1 $filtro_sql
                 ORDER BY $campo_sql_orden $orden_dir
                 LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql_data);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error crítico de base de datos: " . $e->getMessage());
}

// MENSAJES DE SISTEMA
$mensaje_sistema = "";
if (isset($_GET['status'])) {
    $placa_msg = htmlspecialchars($_GET['p'] ?? 'Equipo');
    if ($_GET['status'] == 'updated') {
        $mensaje_sistema = "<div class='alert alert-success'>✅ Datos actualizados para <u>$placa_msg</u>.</div>";
    }
    if ($_GET['status'] == 'reverted') {
        $mensaje_sistema = "<div class='alert alert-warning'>♻️ Baja Revertida para <u>$placa_msg</u>.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario General - URTRACK</title>
    <style>
        :root { --primary: #002D72; --secondary: #e7f1ff; --text: #333; --border: #e1e4e8; --bg: #f8f9fa; --white: #fff; }
        
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        
        .layout-container { max-width: 1400px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        
        /* HEADER Y BUSCADOR */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-title { margin: 0; color: var(--primary); border-left: 5px solid #ffc107; padding-left: 15px; font-size: 1.5rem; }
        .search-form { display: flex; gap: 10px; flex-grow: 1; max-width: 500px; }
        .search-input { width: 100%; padding: 10px 15px; border: 1px solid #ccc; border-radius: 20px; outline: none; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; }

        /* ALERTAS */
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid; }
        .alert-success { background:#d4edda; color:#155724; border-color:#28a745; }
        .alert-warning { background:#fff3cd; color:#856404; border-color:#ffc107; }

        /* TABLA Y RESPONSIVE */
        .table-responsive { width: 100%; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        
        /* Encabezados Ordenables */
        thead th { background-color: var(--primary); color: white; text-align: left; padding: 12px 15px; user-select: none; }
        .sort-link { color: white; text-decoration: none; display: block; width: 100%; height: 100%; font-weight: bold; }
        .sort-link:hover { color: #ffc107; }
        .active-sort { color: #ffc107; text-decoration: underline; }

        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:hover { background-color: var(--secondary); }
        td { padding: 10px 15px; vertical-align: middle; }

        /* Estilos de Estado */
        .badge-modalidad { background: #eee; color: #555; border: 1px solid #ddd; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }
        .status-alta { color: #28a745; font-weight: bold; background: #d4edda; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        .status-baja { color: #dc3545; font-weight: bold; background: #f8d7da; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        
        .btn-icon { text-decoration: none; font-size: 1.1rem; padding: 5px; display: inline-block; }
        .btn-revert { color: #fff; background: #6c757d; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; text-decoration: none; }

        /* PAGINACIÓN */
        .pagination { display: flex; justify-content: center; flex-wrap: wrap; margin-top: 30px; gap: 5px; }
        .page-link { padding: 8px 12px; border: 1px solid var(--border); text-decoration: none; color: var(--primary); border-radius: 4px; min-width: 35px; text-align: center; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); font-weight: bold; }
        .page-link.dots { border: none; color: #666; pointer-events: none; }
        .page-link:hover:not(.active):not(.dots) { background-color: #e9ecef; }

        /* --- MEDIA QUERIES PARA CELULARES (Card View) --- */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .layout-container { padding: 15px; }
            
            /* Buscador en columna */
            .top-bar { flex-direction: column; align-items: stretch; }
            .search-form { max-width: 100%; order: 2; }
            .page-title { order: 1; font-size: 1.2rem; }
            .btn-back { order: 3; text-align: center; display: block; margin-top: 10px; }

            /* Transformar Tabla en Tarjetas */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; } /* Ocultar encabezados visualmente pero mantener accesibilidad */
            
            tbody tr { 
                margin-bottom: 15px; 
                border: 1px solid #ccc; 
                border-radius: 8px; 
                background: #fff;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                padding: 10px;
            }
            
            td { 
                border: none; 
                border-bottom: 1px solid #eee; 
                position: relative; 
                padding-left: 45%; /* Espacio para la etiqueta */
                text-align: right;
                min-height: 25px;
            }
            
            td:last-child { border-bottom: none; }

            /* Etiquetas simuladas (data-label) */
            td::before { 
                content: attr(data-label); 
                position: absolute; 
                left: 10px; 
                top: 10px;
                width: 40%; 
                padding-right: 10px; 
                white-space: nowrap; 
                font-weight: bold; 
                color: #555;
                text-align: left;
            }

            /* Ajustes visuales específicos para móvil */
            .badge-modalidad { display: inline-block; margin-top: 5px; }
            .pagination { gap: 3px; }
            .page-link { padding: 6px 10px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <?= $mensaje_sistema ?>

<div class="layout-container">
    <div class="top-bar">
        <div>
            <h1 class="page-title">📦 Inventario General</h1>
            <small style="color: #666; margin-left: 15px; display:inline-block; margin-top:5px;">
                Total: <strong><?= number_format($total_registros) ?></strong> equipos
            </small>
        </div>

        <form class="search-form" method="GET">
            <input type="hidden" name="col" value="<?= htmlspecialchars($columna_orden) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($orden_dir) ?>">
            
            <input type="text" name="q" class="search-input" placeholder="Buscar placa, serial, responsable..." value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit" class="btn-search">🔍</button>
            <?php if($busqueda): ?>
                <a href="inventario.php" style="padding: 10px; color: #dc3545; text-decoration: none; display:flex; align-items:center;">✕</a>
            <?php endif; ?>
        </form>

        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= sortLink('placa_ur', 'Placa UR', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('serial', 'Serial / Host', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('marca', 'Equipo', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('fecha_compra', 'Adquisición', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('ubicacion', 'Ubicación', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('correo_responsable', 'Responsable', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th><?= sortLink('estado_maestro', 'Estado', $columna_orden, $orden_dir, $busqueda) ?></th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($equipos) > 0): ?>
                    <?php foreach ($equipos as $eq): ?>
                    <tr style="<?= $eq['estado_maestro'] == 'Baja' ? 'opacity: 0.7; background: #fff5f5;' : '' ?>">
                        
                        <td data-label="Placa UR" style="font-weight: bold; color: var(--primary);">
                            <?= htmlspecialchars($eq['placa_ur']) ?>
                        </td>

                        <td data-label="Serial / Host">
                            <div><?= htmlspecialchars($eq['serial']) ?></div>
                            <?php if(!empty($eq['hostname']) && $eq['hostname'] !== 'PENDIENTE'): ?>
                                <small style="color: #666;">Host: <?= htmlspecialchars($eq['hostname']) ?></small>
                            <?php endif; ?>
                        </td>

                        <td data-label="Equipo">
                            <?= htmlspecialchars($eq['marca']) ?>
                            <div style="font-size:0.85rem; color:#666;"><?= htmlspecialchars($eq['modelo']) ?></div>
                        </td>

                        <td data-label="Adquisición">
                            <div style="font-size: 0.85rem;"><?= date('d/m/Y', strtotime($eq['fecha_compra'])) ?></div>
                            <span class="badge-modalidad"><?= $eq['modalidad'] ?></span>
                        </td>

                        <td data-label="Ubicación">
                            <?php if ($eq['ubicacion']): ?>
                                <strong><?= htmlspecialchars($eq['sede']) ?></strong><br>
                                <small><?= htmlspecialchars($eq['ubicacion']) ?></small>
                            <?php else: ?>
                                <span style="color: #999;">Sin movimientos</span>
                            <?php endif; ?>
                        </td>

                        <td data-label="Responsable">
                            <div style="font-size:0.9rem; word-break: break-word;">
                                <?= htmlspecialchars($eq['correo_responsable'] ?? 'N/A') ?>
                            </div>
                        </td>
                        
                        <td data-label="Estado">
                            <span class="<?= $eq['estado_maestro'] == 'Alta' ? 'status-alta' : 'status-baja' ?>">
                                <?= $eq['estado_maestro'] ?>
                            </span>
                        </td>
                        
                        <td data-label="Acciones" style="text-align: center; white-space: nowrap;">
                            <a href="historial.php?serial=<?= $eq['serial'] ?>" class="btn-icon" title="Ver Hoja de Vida Completa">👁️</a>
                            
                            <?php if (in_array($_SESSION['rol'], ['Administrador', 'Recursos'])): ?>
                                <a href="editar_equipo.php?id=<?= $eq['id_equipo'] ?>" class="btn-icon" title="Editar" style="color: #d39e00;">✏️</a>
                            <?php endif; ?>

                            <?php if ($_SESSION['rol'] === 'Administrador' && $eq['estado_maestro'] === 'Baja'): ?>
                                <a href="revertir_baja.php?serial=<?= $eq['serial'] ?>" 
                                   class="btn-revert" 
                                   title="Restaurar a estado Activo"
                                   onclick="return confirm('⚠️ ¿Está seguro de revertir la BAJA?');">
                                   ♻️ Revertir
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state" style="text-align:center; padding:20px;">No se encontraron resultados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <?php 
            $rango = 2; // Número de páginas a mostrar alrededor de la actual
            
            // Botón Anterior
            if ($pagina_actual > 1) {
                echo '<a href="?page='.($pagina_actual-1).'&q='.urlencode($busqueda).'&col='.$columna_orden.'&dir='.$orden_dir.'" class="page-link">«</a>';
            }

            // Página 1 siempre visible
            echo '<a href="?page=1&q='.urlencode($busqueda).'&col='.$columna_orden.'&dir='.$orden_dir.'" class="page-link '.($pagina_actual == 1 ? 'active' : '').'">1</a>';

            // Puntos suspensivos inicio
            if ($pagina_actual - $rango > 2) {
                echo '<span class="page-link dots">...</span>';
            }

            // Rango central
            for ($i = 2; $i < $total_paginas; $i++) {
                if ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango) {
                    echo '<a href="?page='.$i.'&q='.urlencode($busqueda).'&col='.$columna_orden.'&dir='.$orden_dir.'" class="page-link '.($pagina_actual == $i ? 'active' : '').'">'.$i.'</a>';
                }
            }

            // Puntos suspensivos fin
            if ($pagina_actual + $rango < $total_paginas - 1) {
                echo '<span class="page-link dots">...</span>';
            }

            // Última página siempre visible
            echo '<a href="?page='.$total_paginas.'&q='.urlencode($busqueda).'&col='.$columna_orden.'&dir='.$orden_dir.'" class="page-link '.($pagina_actual == $total_paginas ? 'active' : '').'">'.$total_paginas.'</a>';

            // Botón Siguiente
            if ($pagina_actual < $total_paginas) {
                echo '<a href="?page='.($pagina_actual+1).'&q='.urlencode($busqueda).'&col='.$columna_orden.'&dir='.$orden_dir.'" class="page-link">»</a>';
            }
        ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php ob_end_flush(); ?>