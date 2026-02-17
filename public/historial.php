<?php
/**
 * URTRACK - Historial del Equipo
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ Query simple y directa
 * ✅ Código limpio y modular
 * ✅ Estilos en archivo externo
 * ✅ Scroll en timeline para mejor UX
 */

require_once '../core/db.php';
require_once '../core/session.php';

$serial = $_GET['serial'] ?? null;

if (!$serial) {
    header("Location: inventario.php");
    exit;
}

try {
    // Obtener equipo
    $stmt_eq = $pdo->prepare("SELECT * FROM equipos WHERE serial = ?");
    $stmt_eq->execute([$serial]);
    $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        die("Error: Equipo no encontrado");
    }

    // Calcular fin de vida útil
    $fecha_compra = new DateTime($equipo['fecha_compra']);
    $vida_util = (int)$equipo['vida_util'];
    $fecha_fin = clone $fecha_compra;
    $fecha_fin->modify("+$vida_util years");

    // Obtener historial — sede y nombre vienen de tabla lugares via JOIN
    $stmt_hist = $pdo->prepare("
        SELECT b.*,
               l.sede AS sede_lugar,
               l.nombre AS nombre_lugar
        FROM bitacora b
        LEFT JOIN lugares l ON b.id_lugar = l.id
        WHERE b.serial_equipo = ? 
        ORDER BY b.fecha_evento DESC
    ");
    $stmt_hist->execute([$serial]);
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error historial.php: " . $e->getMessage());
    die("Error de base de datos");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - <?= htmlspecialchars($serial) ?></title>
    <link rel="stylesheet" href="../css/urtrack-styles.css">

    <style>
        /* Estilos específicos para el historial */
        .timeline-scroll {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .timeline-scroll::-webkit-scrollbar {
            width: 8px;
        }
        
        .timeline-scroll::-webkit-scrollbar-track {
            background: var(--bg-light);
        }
        
        .timeline-scroll::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        
        .timeline-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--text-secondary);
        }

        .timeline-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .compliance-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }

        .comp-badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .comp-ok {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .comp-fail {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .obs-box {
            grid-column: 1 / -1;
            background: var(--bg-light);
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid var(--border-color);
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .price-highlight {
            color: var(--success);
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .timeline-details {
                grid-template-columns: 1fr;
            }
            
            .timeline-scroll {
                max-height: 500px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="inventario.php" class="btn btn-outline mb-2">⬅ Volver al Inventario</a>

    <div class="card fade-in">
        <div class="card-header">
            <h1>📄 Hoja de Vida: <?= htmlspecialchars($equipo['placa_ur']) ?></h1>
            <p>Serial: <?= htmlspecialchars($equipo['serial']) ?></p>
        </div>

        <div class="card-body">
            <!-- Información del equipo -->
            <div class="info-grid mb-3">
                <div class="info-item">
                    <label>Serial</label>
                    <span><?= htmlspecialchars($equipo['serial']) ?></span>
                </div>

                <div class="info-item">
                    <label>Marca / Modelo</label>
                    <span><?= htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']) ?></span>
                </div>

                <div class="info-item">
                    <label>Modalidad</label>
                    <span class="badge badge-secondary"><?= $equipo['modalidad'] ?></span>
                </div>

                <div class="info-item">
                    <label>Fecha de Compra</label>
                    <span><?= date('d/m/Y', strtotime($equipo['fecha_compra'])) ?></span>
                </div>

                <div class="info-item">
                    <label>Vida Útil</label>
                    <span><?= $vida_util ?> años (Fin: <?= $fecha_fin->format('m/Y') ?>)</span>
                </div>

                <div class="info-item">
                    <label>Valor de Adquisición</label>
                    <span class="price-highlight">$<?= number_format($equipo['precio'], 0, ',', '.') ?> COP</span>
                </div>

                <div class="info-item">
                    <label>Estado Maestro</label>
                    <span class="badge badge-<?= $equipo['estado_maestro'] == 'Alta' ? 'success' : 'danger' ?>">
                        <?= $equipo['estado_maestro'] ?>
                    </span>
                </div>
            </div>

            <hr style="border: 1px solid var(--border-color); margin: 25px 0;">

            <h3 class="mb-2">🕒 Línea de Tiempo de Operaciones</h3>

            <div class="timeline-scroll">
                <?php if (empty($historial)): ?>
                    <div class="alert alert-info">
                        No se registran movimientos para este equipo
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($historial as $ev): ?>
                        <div class="timeline-item">
                            <div class="timeline-header">
                                <span class="timeline-type">🔹 <?= htmlspecialchars($ev['tipo_evento']) ?></span>
                                <span class="timeline-date">
                                    📅 <?= date('d/m/Y h:i A', strtotime($ev['fecha_evento'])) ?>
                                </span>
                            </div>

                            <div class="timeline-details">
                                <div>
                                    <strong>📍 Ubicación:</strong><br>
                                    <?= htmlspecialchars(($ev['sede_lugar'] ?? 'Sin sede') . ' - ' . ($ev['nombre_lugar'] ?? 'Sin ubicación')) ?>
                                </div>

                                <div>
                                    <strong>👤 Responsable:</strong><br>
                                    <small style="word-break:break-all;">
                                        <?= htmlspecialchars($ev['correo_responsable']) ?>
                                    </small>
                                </div>

                                <div>
                                    <strong>🖥️ Hostname:</strong>
                                    <span class="badge badge-info">
                                        <?= htmlspecialchars($ev['hostname'] ?? 'N/A') ?>
                                    </span>
                                </div>

                                <div>
                                    <strong>🛠️ Asignado por:</strong><br>
                                    <?= htmlspecialchars($ev['tecnico_responsable']) ?>
                                </div>

                                <!-- Badges de compliance -->
                                <div class="compliance-badges" style="grid-column: 1 / -1;">
                                    <?php if ($ev['check_dlo']): ?>
                                        <span class="comp-badge comp-ok">✅ DLO OK</span>
                                    <?php else: ?>
                                        <span class="comp-badge comp-fail">⚠️ Sin DLO</span>
                                    <?php endif; ?>

                                    <?php if ($ev['check_antivirus']): ?>
                                        <span class="comp-badge comp-ok">✅ Antivirus OK</span>
                                    <?php else: ?>
                                        <span class="comp-badge comp-fail">⚠️ Sin Antivirus</span>
                                    <?php endif; ?>

                                    <?php if ($ev['check_sccm']): ?>
                                        <span class="comp-badge comp-ok">✅ SCCM OK</span>
                                    <?php else: ?>
                                        <span class="comp-badge comp-fail">⚠️ Sin SCCM</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Observaciones -->
                                <div class="obs-box">
                                    <strong>📝 Observaciones:</strong><br>
                                    <?php if (!empty($ev['desc_evento'])): ?>
                                        <?= nl2br(htmlspecialchars($ev['desc_evento'])) ?>
                                    <?php else: ?>
                                        <em class="text-muted">Sin observaciones registradas</em>
                                    <?php endif; ?>

                                    <?php if (!empty($ev['responsable_secundario'])): ?>
                                        <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--border-color);">
                                            <strong>👥 Co-Responsable:</strong> <?= htmlspecialchars($ev['responsable_secundario']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($ev['campo_adic1'])): ?>
                                        <div style="margin-top:8px;">
                                            <strong>ℹ️ Nota Adicional:</strong> <?= htmlspecialchars($ev['campo_adic1']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../public/js/app.js"></script>
</body>
</html>
