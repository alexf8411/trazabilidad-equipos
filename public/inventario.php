<?php
/**
 * public/inventario.php
 * Inventario General con Paginación y Búsqueda
 * Optimizado para alto volumen de datos (4,500+ registros)
 */
require_once '../core/db.php';
require_once '../core/session.php';

// --- CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- CONFIGURACIÓN DE BÚSQUEDA ---
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_sql = "";
$params = [];

if ($busqueda != '') {
    // Usamos marcadores únicos para evitar error HY093
    $filtro_sql = "AND (
        e.placa_ur LIKE :p1 OR 
        e.serial LIKE :p2 OR 
        e.modelo LIKE :p3 OR 
        e.marca LIKE :p4 OR
        b.correo_responsable LIKE :p5
    )";
    
    // Asignamos el mismo valor de búsqueda a cada marcador
    $term = "%$busqueda%";
    $params[':p1'] = $term;
    $params[':p2'] = $term;
    $params[':p3'] = $term;
    $params[':p4'] = $term;
    $params[':p5'] = $term;
}

// --- CONSULTA SQL MAESTRA (JOIN ÓPTIMO) ---
// Explicación: Seleccionamos el equipo y hacemos JOIN con el ÚLTIMO evento registrado en bitácora
// para saber su ubicación actual real sin subconsultas lentas fila por fila.

try {
    // 1. Contar total de resultados (para calcular páginas)
    $sql_count = "SELECT COUNT(*) FROM equipos e 
                  LEFT JOIN (
                      SELECT serial_equipo, correo_responsable FROM bitacora 
                      WHERE id_evento IN (SELECT MAX(id_evento) FROM bitacora GROUP BY serial_equipo)
                  ) b ON e.serial = b.serial_equipo
                  WHERE 1=1 $filtro_sql";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // 2. Obtener los datos paginados
    // Nota: El sub-join complejo es para obtener UBICACIÓN y RESPONSABLE actuales.
    $sql_data = "SELECT 
                    e.*,
                    b.sede,
                    b.ubicacion,
                    b.tipo_evento,
                    b.correo_responsable,
                    b.hostname
                 FROM equipos e
                 LEFT JOIN (
                    SELECT b1.*
                    FROM bitacora b1
                    INNER JOIN (
                        SELECT serial_equipo, MAX(id_evento) as max_id
                        FROM bitacora
                        GROUP BY serial_equipo
                    ) b2 ON b1.serial_equipo = b2.serial_equipo AND b1.id_evento = b2.max_id
                 ) b ON e.serial = b.serial_equipo
                 WHERE 1=1 $filtro_sql
                 ORDER BY e.creado_en DESC
                 LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql_data);
    
    // Bindear parámetros de búsqueda
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    // Bindear parámetros de paginación (deben ser enteros)
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de carga: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario General - URTRACK</title>
    <style>
        :root {
            --primary: #002D72;
            --secondary: #e7f1ff;
            --text: #333;
            --border: #e1e4e8;
            --bg: #f8f9fa;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        
        .layout-container { max-width: 1400px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }

        /* Header y Buscador */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-title { margin: 0; color: var(--primary); border-left: 5px solid #ffc107; padding-left: 15px; }
        
        .search-form { display: flex; gap: 10px; flex-grow: 1; max-width: 500px; }
        .search-input { width: 100%; padding: 10px 15px; border: 1px solid #ccc; border-radius: 20px; outline: none; transition: border 0.3s; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,45,114,0.1); }
        .btn-search { background: var(--primary); color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; }

        /* Tabla de Datos */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 1000px; }
        
        thead th { background-color: var(--primary); color: white; text-align: left; padding: 12px 15px; font-weight: 600; letter-spacing: 0.5px; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        tbody tr:hover { background-color: var(--secondary); }
        td { padding: 10px 15px; vertical-align: middle; }

        /* Badges y Estados */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-modalidad { background: #eee; color: #555; border: 1px solid #ddd; }
        .status-alta { color: #28a745; font-weight: bold; }
        .status-baja { color: #dc3545; font-weight: bold; }

        /* Paginación */
        .pagination { display: flex; justify-content: center; margin-top: 30px; gap: 5px; }
        .page-link { padding: 8px 12px; border: 1px solid var(--border); text-decoration: none; color: var(--primary); border-radius: 4px; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-link:hover:not(.active) { background: #eee; }

        /* Botones de Acción */
        .btn-icon { text-decoration: none; font-size: 1.1rem; padding: 5px; border-radius: 4px; display: inline-block; }
        .btn-icon:hover { background-color: rgba(0,0,0,0.1); }
        
        .empty-state { text-align: center; padding: 40px; color: #777; font-style: italic; }
    </style>
</head>
<body>

<div class="layout-container">
    
    <div class="top-bar">
        <div>
            <h1 class="page-title">📦 Inventario General</h1>
            <small style="color: #666; margin-left: 20px;">
                Total Equipos: <strong><?= number_format($total_registros) ?></strong>
                <?php if($busqueda): ?> (Filtrado por: "<?= htmlspecialchars($busqueda) ?>") <?php endif; ?>
            </small>
        </div>

        <form class="search-form" method="GET">
            <input type="text" name="q" class="search-input" placeholder="Buscar por Placa, Serial, Modelo o Responsable..." value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit" class="btn-search">🔍</button>
            <?php if($busqueda): ?>
                <a href="inventario.php" style="padding: 10px; color: #dc3545; text-decoration: none;" title="Limpiar filtro">✕</a>
            <?php endif; ?>
        </form>

        <a href="dashboard.php" class="btn-back">⬅ Volver</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Placa UR</th>
                    <th>Serial / Hostname</th>
                    <th>Equipo (Marca/Modelo)</th>
                    <th>Adquisición</th>
                    <th>Ubicación Actual</th>
                    <th>Responsable</th>
                    <th>Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($equipos) > 0): ?>
                    <?php foreach ($equipos as $eq): ?>
                    <tr>
                        <td style="font-weight: bold; color: var(--primary);">
                            <?= htmlspecialchars($eq['placa_ur']) ?>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($eq['serial']) ?></div>
                            <?php if(!empty($eq['hostname']) && $eq['hostname'] !== 'PENDIENTE'): ?>
                                <small style="color: #666;">Host: <?= htmlspecialchars($eq['hostname']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($eq['marca']) ?> 
                            <span style="color:#666;"><?= htmlspecialchars($eq['modelo']) ?></span>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem;"><?= date('d/m/Y', strtotime($eq['fecha_compra'])) ?></div>
                            <span class="badge badge-modalidad"><?= $eq['modalidad'] ?></span>
                        </td>
                        <td>
                            <?php if ($eq['ubicacion']): ?>
                                <strong><?= htmlspecialchars($eq['sede']) ?></strong><br>
                                <small><?= htmlspecialchars($eq['ubicacion']) ?></small>
                            <?php else: ?>
                                <span style="color: #999;">Sin movimientos</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:0.9rem;"><?= htmlspecialchars($eq['correo_responsable'] ?? 'N/A') ?></div>
                        </td>
                        <td>
                            <span class="<?= $eq['estado_maestro'] == 'Alta' ? 'status-alta' : 'status-baja' ?>">
                                <?= $eq['estado_maestro'] ?>
                            </span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            
                            <a href="historial.php?serial=<?= $eq['serial'] ?>" class="btn-icon" title="Ver Trazabilidad Completa">
                                👁️
                            </a>

                            <?php if (in_array($_SESSION['rol'], ['Administrador', 'Recursos'])): ?>
                                <a href="editar_equipo.php?id=<?= $eq['id_equipo'] ?>" class="btn-icon" title="Editar Maestro" style="color: #d39e00;">
                                    ✏️
                                </a>
                            <?php endif; ?>

                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            No se encontraron equipos registrados con esos criterios.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina_actual > 1): ?>
            <a href="?page=<?= $pagina_actual - 1 ?>&q=<?= urlencode($busqueda) ?>" class="page-link">« Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($busqueda) ?>" class="page-link <?= $i == $pagina_actual ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                <span style="padding: 8px;">...</span>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?page=<?= $pagina_actual + 1 ?>&q=<?= urlencode($busqueda) ?>" class="page-link">Siguiente »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>