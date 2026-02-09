<?php
/**
 * public/historial.php
 * Hoja de Vida y Trazabilidad del Activo
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

    // 2. Obtener historial de eventos (Bitácora) ordenado por fecha descendente
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
    <title>Historial - <?= htmlspecialchars($serial) ?></title>
    <style>
        :root { --primary: #002D72; --accent: #ffc107; --bg: #f4f6f9; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Ficha Técnica */
        .info-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 5px solid var(--primary); margin-bottom: 30px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        .info-card h2 { grid-column: 1 / -1; margin: 0 0 10px 0; color: var(--primary); font-size: 1.4rem; }
        .data-point { font-size: 0.9rem; }
        .data-point label { display: block; color: #666; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; }
        
        /* Timeline */
        .timeline { position: relative; padding-left: 40px; margin-top: 20px; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 3px; background: #ddd; }
        
        .event-card { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; position: relative; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .event-card::before { content: ''; position: absolute; left: -31px; top: 20px; width: 15px; height: 15px; background: white; border: 3px solid var(--primary); border-radius: 50%; z-index: 1; }
        
        .event-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; }
        .event-type { font-weight: bold; color: var(--primary); text-transform: uppercase; font-size: 0.85rem; }
        .event-date { font-size: 0.85rem; color: #888; }
        
        .event-details { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.9rem; }
        .badge { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        
        .btn-back { display: inline-block; margin-bottom: 15px; text-decoration: none; color: #666; font-weight: bold; }
        .btn-back:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="container">
    <a href="inventario.php" class="btn-back">⬅ Volver al Inventario</a>

    <div class="info-card">
        <h2>📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h2>
        <div class="data-point"><label>Serial</label> <?= htmlspecialchars($equipo['serial']) ?></div>
        <div class="data-point"><label>Marca/Modelo</label> <?= htmlspecialchars($equipo['marca']." ".$equipo['modelo']) ?></div>
        <div class="data-point"><label>Modalidad</label> <?= htmlspecialchars($equipo['modalidad']) ?></div>
        <div class="data-point"><label>Fecha Compra</label> <?= date('d/m/Y', strtotime($equipo['fecha_compra'])) ?></div>
        <div class="data-point"><label>Estado Maestro</label> <strong><?= $equipo['estado_maestro'] ?></strong></div>
        <div class="data-point"><label>ID Técnico</label> #<?= $equipo['id_equipo'] ?></div>
    </div>

    <h3 style="color:#555; margin-left: 10px;">🕒 Historial de Movimientos</h3>
    
    <div class="timeline">
        <?php if (empty($historial)): ?>
            <p style="color:#888; font-style:italic;">No se registran movimientos para este equipo todavía.</p>
        <?php endif; ?>

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
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>