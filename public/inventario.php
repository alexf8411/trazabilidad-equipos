<?php
/**
 * public/historial.php
 * Hoja de Vida Completa y Trazabilidad (Bitácora)
 * Versión V1.5: Agregados campos 'Vida Útil' y 'Precio'
 */
require_once '../core/db.php';
require_once '../core/session.php';

if (!isset($_GET['serial'])) {
    header('Location: inventario.php');
    exit;
}

$serial = $_GET['serial'];

// 1. Obtener Datos Maestros (Hoja de Vida)
// Se agregan vida_util y precio a la consulta
$stmt = $pdo->prepare("SELECT * FROM equipos WHERE serial = ?");
$stmt->execute([$serial]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    die("❌ Equipo no encontrado.");
}

// 2. Obtener Historial de Movimientos (Bitácora)
$stmt_b = $pdo->prepare("
    SELECT * FROM bitacora 
    WHERE serial_equipo = ? 
    ORDER BY fecha_evento DESC, id_evento DESC
");
$stmt_b->execute([$serial]);
$historial = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial - <?= htmlspecialchars($equipo['placa_ur']) ?></title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; --white: #fff; --gray: #6c757d; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .card { background: var(--white); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .card-header { background: var(--primary); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; }
        
        /* Grid para la Hoja de Vida */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .info-item label { display: block; font-size: 0.8rem; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .info-item span { font-size: 1.1rem; font-weight: 500; display: block; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; }
        .alta { background: #d4edda; color: #155724; }
        .baja { background: #f8d7da; color: #721c24; }

        /* Estilos de la Línea de Tiempo (Timeline) */
        .timeline { list-style: none; padding: 0; margin: 0; position: relative; }
        .timeline:before { content: ''; position: absolute; top: 0; bottom: 0; left: 24px; width: 2px; background: #e9ecef; }
        
        .timeline-item { position: relative; padding-left: 60px; margin-bottom: 25px; }
        .timeline-icon { 
            position: absolute; left: 10px; top: 0; width: 30px; height: 30px; 
            border-radius: 50%; background: var(--white); border: 2px solid var(--primary); 
            text-align: center; line-height: 26px; font-size: 0.9rem; z-index: 2;
        }
        
        .timeline-content { background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid var(--primary); }
        .time-date { font-size: 0.85rem; color: var(--gray); margin-bottom: 5px; display: flex; justify-content: space-between; }
        .event-title { font-weight: bold; font-size: 1rem; color: var(--primary); margin-bottom: 5px; }
        .event-details { font-size: 0.95rem; line-height: 1.5; }
        .user-tag { background: #e2e6ea; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px; }

        .btn-back { color: white; text-decoration: none; border: 1px solid rgba(255,255,255,0.5); padding: 5px 12px; border-radius: 4px; font-size: 0.9rem; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }
        
        .money-format { font-family: monospace; font-weight: bold; color: #28a745; }
    </style>
</head>
<body>

<div class="container">

    <div class="card">
        <div class="card-header">
            <h2 style="margin:0;">📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h2>
            <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Serial / S/N</label>
                    <span><?= htmlspecialchars($equipo['serial']) ?></span>
                </div>
                <div class="info-item">
                    <label>Marca y Modelo</label>
                    <span><?= htmlspecialchars($equipo['marca']) ?> - <?= htmlspecialchars($equipo['modelo']) ?></span>
                </div>
                <div class="info-item">
                    <label>Estado Maestro</label>
                    <span class="status-badge <?= $equipo['estado_maestro'] == 'Alta' ? 'alta' : 'baja' ?>">
                        <?= $equipo['estado_maestro'] ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Modalidad</label>
                    <span><?= htmlspecialchars($equipo['modalidad']) ?></span>
                </div>
                <div class="info-item">
                    <label>Fecha de Compra</label>
                    <span><?= date('d/m/Y', strtotime($equipo['fecha_compra'])) ?></span>
                </div>
                
                <div class="info-item">
                    <label>Vida Útil</label>
                    <span><?= htmlspecialchars($equipo['vida_util']) ?> Años</span>
                </div>
                <div class="info-item">
                    <label>Precio de Activo</label>
                    <span class="money-format">$ <?= number_format($equipo['precio'], 0, ',', '.') ?> COP</span>
                </div>
                </div>
        </div>
    </div>

    <h3 style="color: var(--primary); border-bottom: 2px solid #ddd; padding-bottom: 10px;">🕒 Trazabilidad de Movimientos</h3>
    
    <ul class="timeline">
        <?php foreach ($historial as $evento): ?>
            <?php
            // Lógica visual para íconos según el tipo de evento
            $icono = '📌';
            $color_borde = '#002D72';
            
            if ($evento['tipo_evento'] == 'Ingreso') { $icono = '✨'; $color_borde = '#28a745'; }
            if ($evento['tipo_evento'] == 'Asignación') { $icono = '👤'; $color_borde = '#007bff'; }
            if ($evento['tipo_evento'] == 'Devolución') { $icono = '🔙'; $color_borde = '#ffc107'; }
            if ($evento['tipo_evento'] == 'Baja') { $icono = '💀'; $color_borde = '#dc3545'; }
            ?>
            
            <li class="timeline-item">
                <div class="timeline-icon" style="border-color: <?= $color_borde ?>;"><?= $icono ?></div>
                <div class="timeline-content" style="border-left-color: <?= $color_borde ?>;">
                    <div class="time-date">
                        <span>📅 <?= date('d/m/Y h:i A', strtotime($evento['fecha_evento'])) ?></span>
                        <span style="font-size:0.8rem;">ID Evento: #<?= $evento['id_evento'] ?></span>
                    </div>
                    
                    <div class="event-title"><?= htmlspecialchars($evento['tipo_evento']) ?></div>
                    
                    <div class="event-details">
                        <strong>Ubicación:</strong> <?= htmlspecialchars($evento['ubicacion']) ?> (<?= htmlspecialchars($evento['sede']) ?>)<br>
                        <strong>Responsable:</strong> <?= htmlspecialchars($evento['correo_responsable']) ?><br>
                        <strong>Hostname:</strong> <span style="font-family:monospace;"><?= htmlspecialchars($evento['hostname']) ?></span><br>
                        
                        <div style="margin-top: 8px; font-size: 0.85rem; color: #555; border-top: 1px dashed #ccc; padding-top: 5px;">
                            Gestionado por: <span class="user-tag">🔧 <?= htmlspecialchars($evento['tecnico_responsable']) ?></span>
                        </div>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
        
        <?php if (empty($historial)): ?>
            <li style="padding: 20px; color: #666; font-style: italic;">No hay registros de movimientos para este equipo.</li>
        <?php endif; ?>
    </ul>

</div>

</body>
</html>