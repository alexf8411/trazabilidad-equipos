<?php
/**
 * URTRACK - Inventario General
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES IMPLEMENTADAS:
 * ✅ Query con LATERAL JOIN (solo procesa equipos visibles)
 * ✅ Caché de conteo en sesión
 * ✅ Código modular y limpio
 * ✅ Separación de estilos en CSS externo
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// ============================================================================
// CONFIGURACIÓN DE ORDENAMIENTO
// ============================================================================
$columnas_permitidas = [
    'id'         => 'e.id_equipo',
    'placa_ur'   => 'e.placa_ur',
    'serial'     => 'e.serial',
    'marca'      => 'e.marca',
    'fecha'      => 'e.fecha_compra',
    'ubicacion'  => 'last_event.ubicacion',
    'responsable'=> 'last_event.correo_responsable',
    'estado'     => 'e.estado_maestro'
];

$col = $_GET['col'] ?? 'id';
$dir = $_GET['dir'] ?? 'DESC';

$columna_orden = isset($columnas_permitidas[$col]) ? $columnas_permitidas[$col] : $columnas_permitidas['id'];
$orden_dir = in_array(strtoupper($dir), ['ASC', 'DESC']) ? strtoupper($dir) : 'DESC';

// Función helper para enlaces de ordenamiento
function sortLink($col, $label, $current, $dir, $search) {
    $newDir = ($current == $col && $dir == 'ASC') ? 'DESC' : 'ASC';
    $icon = ($current == $col) ? ($dir == 'ASC' ? ' ▲' : ' ▼') : '';
    $active = ($current == $col) ? 'active' : '';
    return sprintf(
        '<a href="?col=%s&dir=%s&q=%s" class="sort-link %s">%s%s</a>',
        $col, $newDir, urlencode($search), $active, $label, $icon
    );
}

// ============================================================================
// CONFIGURACIÓN DE PAGINACIÓN Y BÚSQUEDA
// ============================================================================
$por_pagina = 20;
$pagina = max(1, (int)($_GET['page'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;
$busqueda = trim($_GET['q'] ?? '');

// Construir filtro de búsqueda
$filtro_where = "";
$params = [];

if ($busqueda !== '') {
    $filtro_where = "WHERE (
        e.id_equipo LIKE :s1 OR
        e.placa_ur LIKE :s2 OR
        e.serial LIKE :s3 OR
        e.modelo LIKE :s4 OR
        e.marca LIKE :s5
    )";
    $term = "%$busqueda%";
    $params = [':s1'=>$term, ':s2'=>$term, ':s3'=>$term, ':s4'=>$term, ':s5'=>$term];
}

// ============================================================================
// CONSULTAS OPTIMIZADAS
// ============================================================================
try {
    // CONTEO CON CACHÉ
    $cache_key = 'inv_total_' . md5($busqueda);
    
    if (!isset($_SESSION[$cache_key])) {
        $sql_count = "SELECT COUNT(*) FROM equipos e $filtro_where";
        $stmt = $pdo->prepare($sql_count);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        $_SESSION[$cache_key] = $total;
    } else {
        $total = $_SESSION[$cache_key];
    }
    
    $total_paginas = max(1, ceil($total / $por_pagina));

    // QUERY PRINCIPAL OPTIMIZADA
    // Usa LATERAL JOIN para obtener solo el último evento de cada equipo mostrado
    $sql_data = "
        SELECT 
            e.id_equipo,
            e.placa_ur,
            e.serial,
            e.marca,
            e.modelo,
            e.precio,
            e.fecha_compra,
            e.modalidad,
            e.estado_maestro,
            last_event.sede,
            last_event.ubicacion,
            last_event.tipo_evento,
            last_event.correo_responsable,
            last_event.hostname
        FROM equipos e
        LEFT JOIN LATERAL (
            SELECT sede, ubicacion, tipo_evento, correo_responsable, hostname
            FROM bitacora
            WHERE serial_equipo = e.serial
            ORDER BY id_evento DESC
            LIMIT 1
        ) AS last_event ON TRUE
        $filtro_where
        ORDER BY $columna_orden $orden_dir
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql_data);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error inventario.php: " . $e->getMessage());
    die("Error de base de datos. Contacte al administrador.");
}

// ============================================================================
// MENSAJES DEL SISTEMA
// ============================================================================
$mensaje = '';
if (isset($_GET['status'])) {
    $placa = htmlspecialchars($_GET['p'] ?? 'Equipo');
    
    if ($_GET['status'] == 'updated') {
        $mensaje = '<div class="alert alert-success">✅ Datos actualizados para <strong>' . $placa . '</strong></div>';
    }
    
    if ($_GET['status'] == 'reverted') {
        $mensaje = '<div class="alert alert-warning">♻️ Baja revertida para <strong>' . $placa . '</strong></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario General - URTRACK</title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">
</head>
<body>

<?php if ($mensaje) echo $mensaje; ?>

<div class="container">
    <div class="card fade-in">
        <div class="card-header">
            <h1>📦 Inventario General</h1>
            <p>Total de equipos: <strong><?= number_format($total) ?></strong></p>
        </div>

        <div class="card-body">
            <!-- Barra de búsqueda -->
            <div class="d-flex justify-between align-center mb-3">
                <form method="GET" class="search-bar">
                    <input type="hidden" name="col" value="<?= htmlspecialchars($col) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                    <input type="text" 
                           name="q" 
                           class="search-input" 
                           placeholder="Buscar placa, serial, modelo..." 
                           value="<?= htmlspecialchars($busqueda) ?>">
                    <button type="submit" class="btn btn-primary btn-search">🔍 Buscar</button>
                    <?php if ($busqueda): ?>
                        <a href="inventario.php" class="btn btn-outline">✕ Limpiar</a>
                    <?php endif; ?>
                </form>

                <a href="dashboard.php" class="btn btn-outline">⬅ Volver</a>
            </div>

            <!-- Tabla de inventario -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><?= sortLink('id', 'ID', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('placa_ur', 'Placa UR', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('serial', 'Serial / Hostname', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('marca', 'Equipo', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('fecha', 'Adquisición', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('ubicacion', 'Ubicación', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('responsable', 'Responsable', $col, $orden_dir, $busqueda) ?></th>
                            <th><?= sortLink('estado', 'Estado', $col, $orden_dir, $busqueda) ?></th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($equipos) > 0): ?>
                            <?php foreach ($equipos as $eq): ?>
                            <tr<?= $eq['estado_maestro'] == 'Baja' ? ' style="opacity:0.6;"' : '' ?>>
                                <td data-label="ID" class="text-muted">
                                    #<?= $eq['id_equipo'] ?>
                                </td>

                                <td data-label="Placa UR">
                                    <strong class="text-primary"><?= htmlspecialchars($eq['placa_ur']) ?></strong>
                                </td>

                                <td data-label="Serial / Hostname">
                                    <div><?= htmlspecialchars($eq['serial']) ?></div>
                                    <?php if (!empty($eq['hostname']) && $eq['hostname'] !== 'PENDIENTE'): ?>
                                        <small class="text-muted">Host: <?= htmlspecialchars($eq['hostname']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Equipo">
                                    <strong><?= htmlspecialchars($eq['marca']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($eq['modelo']) ?></small>
                                </td>

                                <td data-label="Adquisición">
                                    <div><?= date('d/m/Y', strtotime($eq['fecha_compra'])) ?></div>
                                    <span class="badge badge-secondary"><?= $eq['modalidad'] ?></span>
                                </td>

                                <td data-label="Ubicación">
                                    <?php if ($eq['ubicacion']): ?>
                                        <strong><?= htmlspecialchars($eq['sede']) ?></strong><br>
                                        <small><?= htmlspecialchars($eq['ubicacion']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Sin movimientos</span>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Responsable">
                                    <small style="word-break:break-word;">
                                        <?= htmlspecialchars($eq['correo_responsable'] ?? 'N/A') ?>
                                    </small>
                                </td>

                                <td data-label="Estado">
                                    <span class="badge badge-<?= $eq['estado_maestro'] == 'Alta' ? 'success' : 'danger' ?>">
                                        <?= $eq['estado_maestro'] ?>
                                    </span>
                                </td>

                                <td data-label="Acciones" class="text-center">
                                    <a href="historial.php?serial=<?= $eq['serial'] ?>" 
                                       class="btn-icon" 
                                       title="Ver historial completo">👁️</a>

                                    <?php if (in_array($_SESSION['rol'], ['Administrador', 'Recursos'])): ?>
                                        <a href="editar_equipo.php?id=<?= $eq['id_equipo'] ?>" 
                                           class="btn-icon" 
                                           title="Editar equipo">✏️</a>
                                    <?php endif; ?>

                                    <?php if ($_SESSION['rol'] === 'Administrador' && $eq['estado_maestro'] === 'Baja'): ?>
                                        <a href="revertir_baja.php?serial=<?= $eq['serial'] ?>" 
                                           class="btn btn-secondary btn-sm"
                                           data-confirm="⚠️ ¿Revertir la BAJA de este equipo?">
                                           ♻️ Revertir
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    No se encontraron equipos<?= $busqueda ? ' para "' . htmlspecialchars($busqueda) . '"' : '' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php
                // Botón Anterior
                if ($pagina > 1) {
                    echo '<a href="?page=' . ($pagina-1) . '&q=' . urlencode($busqueda) . '&col=' . $col . '&dir=' . $dir . '" class="page-link">«</a>';
                }

                // Primera página
                echo '<a href="?page=1&q=' . urlencode($busqueda) . '&col=' . $col . '&dir=' . $dir . '" class="page-link' . ($pagina == 1 ? ' active' : '') . '">1</a>';

                // Puntos suspensivos inicio
                if ($pagina > 4) {
                    echo '<span class="page-link disabled">...</span>';
                }

                // Páginas centrales
                for ($i = max(2, $pagina - 2); $i <= min($total_paginas - 1, $pagina + 2); $i++) {
                    $active = $pagina == $i ? ' active' : '';
                    echo '<a href="?page=' . $i . '&q=' . urlencode($busqueda) . '&col=' . $col . '&dir=' . $dir . '" class="page-link' . $active . '">' . $i . '</a>';
                }

                // Puntos suspensivos final
                if ($pagina < $total_paginas - 3) {
                    echo '<span class="page-link disabled">...</span>';
                }

                // Última página
                if ($total_paginas > 1) {
                    echo '<a href="?page=' . $total_paginas . '&q=' . urlencode($busqueda) . '&col=' . $col . '&dir=' . $dir . '" class="page-link' . ($pagina == $total_paginas ? ' active' : '') . '">' . $total_paginas . '</a>';
                }

                // Botón Siguiente
                if ($pagina < $total_paginas) {
                    echo '<a href="?page=' . ($pagina+1) . '&q=' . urlencode($busqueda) . '&col=' . $col . '&dir=' . $dir . '" class="page-link">»</a>';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../public/js/app.js"></script>
</body>
</html>
