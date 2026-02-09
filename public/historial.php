<?php
/**
 * public/historial.php
 * Hoja de Vida y Trazabilidad del Activo - Versión V2.1
 * Actualización: Inclusión de campos adicionales y responsable secundario
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
        die("<div style='padding:20px; font-family:sans-serif;'>❌ Error: El equipo con serial <b>$serial</b> no existe. <a href='inventario.php'>Volver</a></div>");
    }

    // 2. Obtener historial (Bitácora) - La consulta hereda automáticamente los nuevos campos
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
    <title>Hoja de Vida - <?= htmlspecialchars($equipo['placa_ur']) ?></title>
    <style>
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f4f6f9; --text-muted: #6c757d; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: #333; }
        .container { max-width: 950px; margin: 0 auto; }
        
        /* Ficha Técnica */
        .info-card { 
            background: white; padding: 25px; border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 6px solid var(--primary); 
            margin-bottom: 30px; 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
        }
        .info-card h2 { grid-column: 1 / -1; margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        
        .data-point label { display: block; color: var(--text-muted); font-weight: bold; font-size: 0.7rem; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }
        .data-point span { font-size: 1rem; font-weight: 600; }
        
        .money { font-family: 'Courier New', monospace; color: #15803d; font-weight: bold; }
        .status-alta { color: #166534; background: #dcfce7; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; }
        .status-baja { color: #991b1b; background: #fee2e2; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; }

        /* Timeline / Bitácora */
        .timeline { position: relative; padding-left: 45px; margin-top: 30px; }
        .timeline::before { content: ''; position: absolute; left: 18px; top: 0; bottom: 0; width: 4px; background: #e2e8f0; border-radius: 2px; }
        
        .event-card { 
            background: white; padding: 20px; border-radius: 10px; margin-bottom: 25px; 
            position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #edf2f7; 
        }
        .event-card::before { 
            content: ''; position: absolute; left: -34px; top: 22px; width: 14px; height: 14px; 
            background: white; border: 4px solid var(--primary); border-radius: 50%; z-index: 1; 
        }
        
        .event-header { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 10px; }
        .event-type { font-weight: 800; color: var(--primary); font-size: 0.9rem; }
        .event-date { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        
        .event-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; font-size: 0.95rem; }
        .detail-item { display: flex; gap: 8px; }
        .detail-item strong { color: #4a5568; min-width: 100px; }
        
        .obs-box { 
            grid-column: 1 / -1; background: #f8fafc; padding: 10px 15px; 
            border-radius: 6px; border-left: 4px solid #cbd5e1; margin-top: 5px; font-size: 0.85rem; color: #475569;
        }

        .badge { background: #1e293b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; text-decoration: none; color: #475569; font-weight: 700; font-size: 0.9rem; transition: 0.2s; }
        .btn-back:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>

    <div class="info-card">
        <h2>📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h2>
        
        <div class="data-point"><label>Serial</label><span><?= htmlspecialchars($equipo['serial']) ?></span></div>
        <div class="data-point"><label>Marca / Modelo</label><span><?= htmlspecialchars($equipo['marca'] . " " . $equipo['modelo']) ?></span></div>
        <div class="data-point"><label>Fecha Compra</label><span><?= date('d/m/Y', strtotime($equipo['fecha_compra'])) ?></span></div>
        <div class="data-point"><label>Valor Activo</label><span class="money">$ <?= number_format($equipo['precio'], 0, ',', '.') ?></span></div>
        <div class="data-point">
            <label>Estado Maestro</label> 
            <span class="<?= $equipo['estado_maestro'] == 'Alta' ? 'status-alta' : 'status-baja' ?>">
                <?= strtoupper($equipo['estado_maestro']) ?>
            </span>
        </div>
    </div>

    <h3 style="color:var(--primary); margin-left: 10px; display:flex; align-items:center; gap:10px;">
        🕒 Línea de Tiempo de Movimientos
    </h3>
    
    <div class="timeline">
        <?php if (empty($historial)): ?>
            <p style="color:var(--text-muted); font-style:italic; padding: 20px; background:white; border-radius:8px;">No se registran movimientos para este equipo.</p>
        <?php endif; ?>

        <?php foreach ($historial as $ev): ?>
        <div class="event-card">
            <div class="event-header">
                <span class="event-type">📦 <?= htmlspecialchars($ev['tipo_evento']) ?></span>
                <span class="event-date">📅 <?= date('d/m/Y - h:i A', strtotime($ev['fecha_evento'])) ?></span>
            </div>
            
            <div class="event-details">
                <div class="detail-item"><strong>📍 Ubicación:</strong> <?= htmlspecialchars($ev['sede'] . " - " . $ev['ubicacion']) ?></div>
                <div class="detail-item"><strong>👤 Responsable:</strong> <?= htmlspecialchars($ev['correo_responsable']) ?></div>
                
                <?php if(!empty($ev['responsable_secundario'])): ?>
                <div class="detail-item"><strong>👥 Secundario:</strong> <?= htmlspecialchars($ev['responsable_secundario']) ?></div>
                <?php endif; ?>

                <div class="detail-item"><strong>🖥️ Hostname:</strong> <span class="badge"><?= htmlspecialchars($ev['hostname'] ?? 'N/A') ?></span></div>
                <div class="detail-item"><strong>🛠️ Técnico:</strong> <?= htmlspecialchars($ev['tecnico_responsable']) ?></div>

                <?php if(!empty($ev['campo_adic1']) || !empty($ev['campo_adic2'])): ?>
                <div class="obs-box">
                    <strong>📝 Observaciones:</strong><br>
                    <?= htmlspecialchars(trim($ev['campo_adic1'] . " " . $ev['campo_adic2'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>