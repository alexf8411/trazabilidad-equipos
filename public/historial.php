<?php
/**
 * public/historial.php
 * Hoja de Vida y Trazabilidad del Activo - Versión V2.5
 * Mejoras: 
 * - Corrección campo 'precio'.
 * - Diseño Responsive.
 * - Scroll vertical en historial extenso.
 */
require_once '../core/db.php';
require_once '../core/session.php';

$serial = $_GET['serial'] ?? null;

if (!$serial) {
    header("Location: inventario.php");
    exit;
}

try {
    // 1. Obtener información maestra del equipo
    $stmt_eq = $pdo->prepare("SELECT * FROM equipos WHERE serial = ?");
    $stmt_eq->execute([$serial]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("Error: Equipo no encontrado en la base de datos.");
    }

    // Lógica de Arquitectura: Cálculo de fin de vida útil
    $fecha_compra = new DateTime($equipo['fecha_compra']);
    $vida_util_años = (int)$equipo['vida_util'];
    $fecha_fin_vida = clone $fecha_compra;
    $fecha_fin_vida->modify("+$vida_util_años years");

    // 2. Obtener historial de eventos
    $stmt_hist = $pdo->prepare("SELECT * FROM bitacora WHERE serial_equipo = ? ORDER BY fecha_evento DESC");
    $stmt_hist->execute([$serial]);
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error técnico: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - <?= htmlspecialchars($serial) ?></title>
    <style>
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f4f6f9; --success: #28a745; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* BOTÓN VOLVER */
        .btn-back { display: inline-flex; align-items: center; margin-bottom: 20px; text-decoration: none; color: #576574; font-weight: 600; font-size: 0.9rem; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-back:hover { color: var(--primary); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        /* FICHA TÉCNICA */
        .info-card { 
            background: white; padding: 20px; border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            border-top: 5px solid var(--primary); 
            margin-bottom: 20px; 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); /* 3 Columnas en escritorio */
            gap: 20px; 
        }
        .info-card h2 { grid-column: 1 / -1; margin: 0 0 10px 0; color: var(--primary); font-size: 1.4rem; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .data-point { font-size: 0.95rem; }
        .data-point label { display: block; color: #7f8c8d; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .data-point span { color: #2c3e50; font-weight: 500; word-break: break-word; }
        .price-tag { color: var(--success) !important; font-weight: bold !important; }
        
        /* CONTENEDOR CON SCROLL PARA LA LÍNEA DE TIEMPO */
        .timeline-container {
            max-height: 400px; /* Altura máxima antes de hacer scroll */
            overflow-y: auto;  /* Activa la barra de desplazamiento */
            padding-right: 10px; /* Espacio para que la barra no tape contenido */
            scrollbar-width: thin; /* Firefox: barra delgada */
            scrollbar-color: #cbd5e1 #f4f6f9; /* Firefox: colores */
        }

        /* Estilos para Webkit (Chrome, Safari, Edge) Scrollbar */
        .timeline-container::-webkit-scrollbar { width: 8px; }
        .timeline-container::-webkit-scrollbar-track { background: #f4f6f9; }
        .timeline-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }

        /* TIMELINE UI */
        .timeline { position: relative; padding-left: 30px; margin-top: 10px; }
        .timeline::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 3px; background: #dcdde1; }
        
        .event-card { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; position: relative; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #eee; transition: transform 0.2s; }
        .event-card:hover { transform: translateX(3px); border-color: #b6d4fe; }
        
        /* Punto en la línea de tiempo */
        .event-card::before { content: ''; position: absolute; left: -26px; top: 20px; width: 13px; height: 13px; background: white; border: 3px solid var(--primary); border-radius: 50%; z-index: 1; }
        
        .event-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; flex-wrap: wrap; gap: 5px; }
        .event-type { font-weight: bold; color: var(--primary); text-transform: uppercase; font-size: 0.85rem; }
        .event-date { font-size: 0.8rem; color: #666; background: #eee; padding: 2px 6px; border-radius: 4px; }
        
        .event-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9rem; }
        .badge { background: #ebf3ff; color: #004085; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        
        .obs-box { 
            grid-column: 1 / -1; background: #f8fafc; padding: 12px; border-radius: 6px; 
            border-left: 4px solid #cbd5e1; margin-top: 10px; font-size: 0.85rem;
        }

        /* --- MEDIA QUERIES (RESPONSIVE) --- */
        @media (max-width: 768px) {
            body { padding: 10px; }
            
            /* Ficha técnica en 1 sola columna */
            .info-card { grid-template-columns: 1fr; gap: 15px; }
            
            /* Detalles del evento en 1 sola columna */
            .event-details { grid-template-columns: 1fr; }
            
            .timeline { padding-left: 25px; }
            .event-card::before { left: -21px; width: 10px; height: 10px; border-width: 2px; }
            .timeline::before { left: 9px; width: 2px; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>

    <div class="info-card">
        <h2>📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h2>
        
        <div class="data-point"><label>Serial</label> <span><?= htmlspecialchars($equipo['serial']) ?></span></div>
        <div class="data-point"><label>Marca/Modelo</label> <span><?= htmlspecialchars($equipo['marca']." ".$equipo['modelo']) ?></span></div>
        <div class="data-point"><label>Modalidad</label> <span><?= htmlspecialchars($equipo['modalidad']) ?></span></div>
        
        <div class="data-point"><label>Fecha Compra</label> <span><?= date('d/m/Y', strtotime($equipo['fecha_compra'])) ?></span></div>
        <div class="data-point">
            <label>Vida Útil Contable</label> 
            <span><?= $vida_util_años ?> años (Fin: <?= $fecha_fin_vida->format('m/Y') ?>)</span>
        </div>
        <div class="data-point">
            <label>Valor de Adquisición</label> 
            <span class="price-tag">$<?= number_format($equipo['precio'], 0, ',', '.') ?> COP</span>
        </div>

        <div class="data-point"><label>Estado Maestro</label> <strong><?= $equipo['estado_maestro'] ?></strong></div>
    </div>

    <h3 style="color:#2c3e50; margin-left: 5px; font-size: 1.2rem; border-bottom: 2px solid #eee; padding-bottom: 10px;">
        🕒 Línea de Tiempo de Operaciones
    </h3>
    
    <div class="timeline-container">
        <div class="timeline">
            <?php if (empty($historial)): ?>
                <p style="color:#95a5a6; font-style:italic; padding-left: 10px;">No se registran movimientos para este equipo todavía.</p>
            <?php endif; ?>

            <?php foreach ($historial as $ev): ?>
            <div class="event-card">
                <div class="event-header">
                    <span class="event-type">🔹 <?= htmlspecialchars($ev['tipo_evento']) ?></span>
                    <span class="event-date">📅 <?= date('d/m/Y h:i A', strtotime($ev['fecha_evento'])) ?></span>
                </div>
                <div class="event-details">
                    <div><strong>📍 Ubicación:</strong> <?= htmlspecialchars($ev['sede']." - ".$ev['ubicacion']) ?></div>
                    <div><strong>👤 Responsable:</strong> <span style="word-break: break-all;"><?= htmlspecialchars($ev['correo_responsable']) ?></span></div>
                    <div><strong>🖥️ Hostname:</strong> <span class="badge"><?= htmlspecialchars($ev['hostname'] ?? 'N/A') ?></span></div>
                    <div><strong>🛠️ Asignado por:</strong> <?= htmlspecialchars($ev['tecnico_responsable']) ?></div>

                    <?php if(!empty($ev['responsable_secundario']) || !empty($ev['campo_adic1']) || !empty($ev['campo_adic2'])): ?>
                    <div class="obs-box">
                        <?php if(!empty($ev['responsable_secundario'])): ?>
                            <div><strong>👥 Co-Responsable:</strong> <?= htmlspecialchars($ev['responsable_secundario']) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($ev['campo_adic1'])): ?>
                            <div style="margin-top:5px;"><strong>📝 Nota 1:</strong> <?= htmlspecialchars($ev['campo_adic1']) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($ev['campo_adic2'])): ?>
                            <div><strong>📝 Nota 2:</strong> <?= htmlspecialchars($ev['campo_adic2']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>