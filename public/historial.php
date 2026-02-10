<?php
/**
 * public/historial.php
 * Hoja de Vida y Trazabilidad del Activo - Versión V2.2
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
        die("<div style='padding:20px; font-family:sans-serif;'>❌ Error: Equipo no existe. <a href='inventario.php'>Volver</a></div>");
    }

    // 2. Obtener historial de movimientos
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
    <title>Historial - <?= htmlspecialchars($equipo['placa_ur']) ?></title>
    <style>
        :root { --primary: #002D72; --bg: #f4f6f9; --text: #334155; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 20px; color: var(--text); }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Ficha Maestra */
        .info-card { 
            background: white; padding: 25px; border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 6px solid var(--primary); 
            margin-bottom: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; 
        }
        .info-card h2 { grid-column: 1 / -1; margin: 0; color: var(--primary); border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .data-point label { display: block; color: #64748b; font-weight: bold; font-size: 0.7rem; text-transform: uppercase; margin-bottom: 4px; }
        .data-point span { font-weight: 600; font-size: 1rem; }

        /* Timeline */
        .timeline { position: relative; padding-left: 40px; margin-top: 20px; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 3px; background: #cbd5e1; }
        
        .event-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; position: relative; border: 1px solid #e2e8f0; }
        .event-card::before { content: ''; position: absolute; left: -31px; top: 22px; width: 14px; height: 14px; background: white; border: 3px solid var(--primary); border-radius: 50%; z-index: 1; }
        
        .event-header { display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
        .event-type { font-weight: bold; color: var(--primary); text-transform: uppercase; font-size: 0.85rem; }
        .event-date { font-size: 0.85rem; color: #64748b; }
        
        .event-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9rem; }
        .badge { background: #1e293b; color: white; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        
        /* Cuadro de Notas Especiales */
        .obs-box { 
            grid-column: 1 / -1; background: #f8fafc; padding: 12px; border-radius: 6px; 
            border-left: 4px solid #94a3b8; margin-top: 10px; line-height: 1.6;
        }
        .obs-box div { margin-bottom: 4px; border-bottom: 1px solid #f1f5f9; padding-bottom: 2px; }
        .obs-box div:last-child { border-bottom: none; }

        .btn-back { display: inline-block; margin-bottom: 15px; text-decoration: none; color: #475569; font-weight: bold; padding: 8px 15px; background: white; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>

    <div class="info-card">
        <h2>📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h2>
        <div class="data-point"><label>Serial</label><span><?= htmlspecialchars($equipo['serial']) ?></span></div>
        <div class="data-point"><label>Marca / Modelo</label><span><?= htmlspecialchars($equipo['marca']." ".$equipo['modelo']) ?></span></div>
        <div class="data-point"><label>Estado Actual</label><span><?= htmlspecialchars($equipo['estado_maestro']) ?></span></div>
        <div class="data-point"><label>Valor</label><span style="color:#16a34a;">$ <?= number_format($equipo['precio'], 0, ',', '.') ?></span></div>
    </div>

    <h3 style="color:#475569; margin-left: 10px;">🕒 Historial de Movimientos</h3>
    
    <div class="timeline">
        <?php foreach ($historial as $ev): ?>
        <div class="event-card">
            <div class="event-header">
                <span class="event-type">🔹 <?= htmlspecialchars($ev['tipo_evento']) ?></span>
                <span class="event-date">📅 <?= date('d/m/Y h:i A', strtotime($ev['fecha_evento'])) ?></span>
            </div>
            <div class="event-details">
                <div><strong>📍 Ubicación:</strong> <?= htmlspecialchars($ev['sede']." - ".$ev['ubicacion']) ?></div>
                <div><strong>👤 Responsable:</strong> <?= htmlspecialchars($ev['correo_responsable']) ?></div>
                <div><strong>🖥️ Hostname:</strong> <span class="badge"><?= htmlspecialchars($ev['hostname'] ?? 'N/A') ?></span></div>
                <div><strong>🛠️ Técnico:</strong> <?= htmlspecialchars($ev['tecnico_responsable']) ?></div>

                <?php if(!empty($ev['responsable_secundario']) || !empty($ev['campo_adic1']) || !empty($ev['campo_adic2'])): ?>
                <div class="obs-box">
                    <?php if(!empty($ev['responsable_secundario'])): ?>
                        <div><strong>👥 Responsable secundario:</strong> <?= htmlspecialchars($ev['responsable_secundario']) ?></div>
                    <?php endif; ?>
                    
                    <?php if(!empty($ev['campo_adic1'])): ?>
                        <div><strong>📝 Campo adicional 1:</strong> <?= htmlspecialchars($ev['campo_adic1']) ?></div>
                    <?php endif; ?>
                    
                    <?php if(!empty($ev['campo_adic2'])): ?>
                        <div><strong>📝 Campo adicional 2:</strong> <?= htmlspecialchars($ev['campo_adic2']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>