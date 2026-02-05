<?php
/**
 * public/inventario.php
 * Inventario General con Opción de Reversión para Admin
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. SEGURIDAD
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

// 2. DETECTOR DE MENSAJES DEL SISTEMA
$mensaje_sistema = "";
if (isset($_GET['status'])) {
    $placa_msg = htmlspecialchars($_GET['p'] ?? 'Equipo');
    
    if ($_GET['status'] == 'updated') {
        $mensaje_sistema = "
        <div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px; border-left:5px solid #28a745;'>
            ✅ <strong>Guardado:</strong> Datos actualizados para <u>$placa_msg</u>.
        </div>";
    }
    // NUEVO MENSAJE PARA LA REVERSIÓN
    if ($_GET['status'] == 'reverted') {
        $mensaje_sistema = "
        <div style='background:#fff3cd; color:#856404; padding:15px; border-radius:5px; margin-bottom:20px; border-left:5px solid #ffc107;'>
            ♻️ <strong>Baja Revertida:</strong> El equipo <u>$placa_msg</u> ha sido restaurado a 'Alta' y movido a Bodega. 
            <br><small>Esta acción ha quedado registrada en la Auditoría Forense.</small>
        </div>";
    }
}

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
    // 1. Contar total
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

    // 2. Obtener datos
    $sql_data = "SELECT e.*, b.sede, b.ubicacion, b.tipo_evento, b.correo_responsable, b.hostname
                 FROM equipos e
                 LEFT JOIN (
                    SELECT b1.* FROM bitacora b1
                    INNER JOIN (SELECT serial_equipo, MAX(id_evento) as max_id FROM bitacora GROUP BY serial_equipo) b2 
                    ON b1.serial_equipo = b2.serial_equipo AND b1.id_evento = b2.max_id
                 ) b ON e.serial = b.serial_equipo
                 WHERE 1=1 $filtro_sql
                 ORDER BY e.creado_en DESC
                 LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql_data);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error crítico de base de datos.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario General - URTRACK</title>
    <style>
        :root { --primary: #002D72; --secondary: #e7f1ff; --text: #333; --border: #e1e4e8; --bg: #f8f9fa; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg); color: var(--text); padding: 20px; margin: 0; }
        .layout-container { max-width: 1400px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-title { margin: 0; color: var(--primary); border-left: 5px solid #ffc107; padding-left: 15px; }
        .search-form { display: flex; gap: 10px; flex-grow: 1; max-width: 500px; }
        .search-input { width: 100%; padding: 10px 15px; border: 1px solid #ccc; border-radius: 20px; outline: none; }
        .btn-search { background: var(--primary); color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 1000px; }
        thead th { background-color: var(--primary); color: white; text-align: left; padding: 12px 15px; }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:hover { background-color: var(--secondary); }
        td { padding: 10px 15px; vertical-align: middle; }
        .badge-modalidad { background: #eee; color: #555; border: 1px solid #ddd; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }
        
        /* Estilos de Estado */
        .status-alta { color: #28a745; font-weight: bold; background: #d4edda; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
        .status-baja { color: #dc3545; font-weight: bold; background: #f8d7da; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }

        .pagination { display: flex; justify-content: center; margin-top: 30px; gap: 5px; }
        .page-link { padding: 8px 12px; border: 1px solid var(--border); text-decoration: none; color: var(--primary); border-radius: 4px; }
        .page-link.active { background: var(--primary); color: white; }
        
        .btn-icon { text-decoration: none; font-size: 1.1rem; padding: 5px; display: inline-block; }
        
        /* Botón Revertir Exclusivo */
        .btn-revert { 
            color: #fff; background: #6c757d; font-size: 0.75rem; padding: 3px 8px; 
            border-radius: 4px; text-decoration: none; margin-left: 5px; vertical-align: middle;
        }
        .btn-revert:hover { background: #5a6268; }
    </style>
</head>
<body>

    <?= $mensaje_sistema ?>

<div class="layout-container">
    
    <div class="top-bar">
        <div>
            <h1 class="page-title">📦 Inventario General</h1>
            <small style="color: #666; margin-left: 20px;">
                Pag <strong><?= $pagina_actual ?></strong> de <strong><?= $total_paginas ?></strong> 
                (Total: <?= number_format($total_registros) ?>)
            </small>
        </div>

        <form class="search-form" method="GET">
            <input type="text" name="q" class="search-input" placeholder="Buscar placa, serial, responsable..." value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit" class="btn-search">🔍</button>
            <?php if($busqueda): ?>
                <a href="inventario.php" style="padding: 10px; color: #dc3545; text-decoration: none;">✕</a>
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
                    <tr style="<?= $eq['estado_maestro'] == 'Baja' ? 'opacity: 0.7; background: #fff5f5;' : '' ?>">
                        <td style="font-weight: bold; color: var(--primary);"><?= htmlspecialchars($eq['placa_ur']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($eq['serial']) ?></div>
                            <?php if(!empty($eq['hostname']) && $eq['hostname'] !== 'PENDIENTE'): ?>
                                <small style="color: #666;">Host: <?= htmlspecialchars($eq['hostname']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($eq['marca']) ?> <span style="color:#666;"><?= htmlspecialchars($eq['modelo']) ?></span></td>
                        <td>
                            <div style="font-size: 0.85rem;"><?= date('d/m/Y', strtotime($eq['fecha_compra'])) ?></div>
                            <span class="badge-modalidad"><?= $eq['modalidad'] ?></span>
                        </td>
                        <td>
                            <?php if ($eq['ubicacion']): ?>
                                <strong><?= htmlspecialchars($eq['sede']) ?></strong><br>
                                <small><?= htmlspecialchars($eq['ubicacion']) ?></small>
                            <?php else: ?>
                                <span style="color: #999;">Sin movimientos</span>
                            <?php endif; ?>
                        </td>
                        <td><div style="font-size:0.9rem;"><?= htmlspecialchars($eq['correo_responsable'] ?? 'N/A') ?></div></td>
                        
                        <td>
                            <span class="<?= $eq['estado_maestro'] == 'Alta' ? 'status-alta' : 'status-baja' ?>">
                                <?= $eq['estado_maestro'] ?>
                            </span>
                        </td>
                        
                        <td style="text-align: center; white-space: nowrap;">
                            <a href="historial.php?serial=<?= $eq['serial'] ?>" class="btn-icon" title="Ver Trazabilidad">👁️</a>
                            
                            <?php if (in_array($_SESSION['rol'], ['Administrador', 'Recursos'])): ?>
                                <a href="editar_equipo.php?id=<?= $eq['id_equipo'] ?>" class="btn-icon" title="Editar" style="color: #d39e00;">✏️</a>
                            <?php endif; ?>

                            <?php if ($_SESSION['rol'] === 'Administrador' && $eq['estado_maestro'] === 'Baja'): ?>
                                <a href="revertir_baja.php?serial=<?= $eq['serial'] ?>" 
                                   class="btn-revert" 
                                   title="Restaurar a estado Activo"
                                   onclick="return confirm('⚠️ ¿Está seguro de revertir la BAJA de este equipo?\n\nEsta acción quedará registrada en la Auditoría Forense.');">
                                   ♻️ Revertir
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No se encontraron resultados.</td></tr>
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
                <a href="?page=<?= $i ?>&q=<?= urlencode($busqueda) ?>" class="page-link <?= $i == $pagina_actual ? 'active' : '' ?>"><?= $i ?></a>
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