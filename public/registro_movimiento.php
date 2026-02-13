<?php
/**
 * URTRACK - Registro de Movimiento (Asignación y Traslados)
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ Query con LATERAL JOIN (evita subconsulta lenta)
 * ✅ CSS centralizado en urtrack-styles.css
 * ✅ JavaScript en registro_movimiento.js
 * ✅ Campo "No. de Caso" obligatorio
 * ✅ 3 switches de compliance (DLO, Antivirus, SCCM)
 * ✅ Responsive completo
 */

require_once '../core/db.php';
require_once '../core/session.php';

// Control de acceso RBAC
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php');
    exit;
}

$equipo = null;
$msg = "";

// ============================================================================
// CARGAR CATÁLOGO DE LUGARES
// ============================================================================
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// BUSCAR EQUIPO (CON QUERY OPTIMIZADA)
// ============================================================================
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    
    // QUERY OPTIMIZADA CON LATERAL JOIN
    $sql_buscar = "SELECT e.*, 
                   last_event.correo_responsable AS responsable_actual, 
                   last_event.ubicacion AS ubicacion_actual, 
                   last_event.sede AS sede_actual
                   FROM equipos e
                   LEFT JOIN LATERAL (
                       SELECT correo_responsable, ubicacion, sede
                       FROM bitacora
                       WHERE serial_equipo = e.serial
                       ORDER BY id_evento DESC
                       LIMIT 1
                   ) AS last_event ON TRUE
                   WHERE e.placa_ur = ? OR e.serial = ? 
                   LIMIT 1";
                   
    $stmt = $pdo->prepare($sql_buscar);
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipo) {
        $msg = "<div class='alert alert-error'>❌ Equipo no localizado en inventario.</div>";
    } 
    elseif ($equipo['estado_maestro'] === 'Baja') {
        $msg = "<div class='alert alert-error'>🛑 <b>ACCESO DENEGADO:</b> El equipo está en estado de <b>BAJA</b>.</div>";
        $equipo = null;
    }
}

// ============================================================================
// PROCESAR ASIGNACIÓN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del lugar
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $lugar = $stmt_l->fetch();

        // Validar que el lugar existe
        if (!$lugar) {
            throw new Exception("Ubicación destino no válida");
        }

        // Obtener valores de los switches (si no están marcados, no vienen en POST)
        $check_dlo = isset($_POST['check_dlo']) ? 1 : 0;
        $check_antivirus = isset($_POST['check_antivirus']) ? 1 : 0;
        $check_sccm = isset($_POST['check_sccm']) ? 1 : 0;

        // Construir desc_evento con el formato: "Caso: XXXX"
        $no_caso = trim($_POST['no_caso']);
        $desc_evento = "Caso: " . $no_caso;

        // Insertar en bitácora
        $sql = "INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion, 
                    campo_adic1, desc_evento,
                    tipo_evento, correo_responsable, responsable_secundario, tecnico_responsable, 
                    hostname, fecha_evento,
                    check_dlo, check_antivirus, check_sccm
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
        
        $pdo->prepare($sql)->execute([
            $_POST['serial'], 
            $_POST['id_lugar'], 
            $lugar['sede'], 
            $lugar['nombre'],
            $_POST['comentarios'] ?: null,  // Campo opcional
            $desc_evento,                    // "Caso: 12345"
            $_POST['tipo_evento'], 
            $_POST['correo_resp_real'],      // Principal
            $_POST['correo_sec_real'] ?: null, // Secundario (opcional)
            $_SESSION['nombre'], 
            strtoupper($_POST['hostname']),
            $check_dlo,
            $check_antivirus,
            $check_sccm
        ]);

        $pdo->commit();
        
        header("Location: generar_acta.php?serial=" . urlencode($_POST['serial']));
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error en registro_movimiento.php: " . $e->getMessage());
        $msg = "<div class='alert alert-error'>❌ Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación - URTRACK</title>
    
    <!-- CSS EXTERNO -->
    <link rel="stylesheet" href="../css/urtrack-styles.css">
</head>
<body>

<div class="container">
    <div class="card">
        <!-- Header -->
        <div class="d-flex justify-between align-center mb-3" style="border-bottom: 2px solid var(--primary-color); padding-bottom: 15px;">
            <h2 style="margin: 0; color: var(--primary-color);">🚚 Asignación y Traslados</h2>
            <a href="dashboard.php" class="btn btn-outline">⬅ Volver</a>
        </div>

        <!-- Alertas -->
        <?= $msg ?>

        <!-- Sección de búsqueda -->
        <form method="GET" class="search-section">
            <input type="text" 
                   name="criterio" 
                   placeholder="Buscar por Placa UR o Serial..." 
                   value="<?= htmlspecialchars($_GET['criterio'] ?? '') ?>" 
                   required 
                   autofocus>
            <button type="submit" name="buscar">🔍 BUSCAR</button>
        </form>

        <?php if ($equipo): ?>
            <!-- Información del equipo encontrado -->
            <div class="info-pill">
                <div>
                    <span class="label-sm">Equipo</span>
                    <div class="data-val"><?= htmlspecialchars($equipo['marca']) ?> <?= htmlspecialchars($equipo['modelo']) ?></div>
                </div>
                
                <div>
                    <span class="label-sm">Identificación</span>
                    <div class="data-val">
                        Placa: <?= htmlspecialchars($equipo['placa_ur']) ?> | 
                        SN: <?= htmlspecialchars($equipo['serial']) ?>
                    </div>
                </div>
                
                <!-- Status actual -->
                <div class="current-status-box">
                    <div>
                        <div>
                            <span class="label-sm" style="color: var(--warning);">📍 Ubicación Actual</span>
                            <div class="data-val">
                                <?= htmlspecialchars($equipo['sede_actual'] ?? 'Sin asignar') ?> - 
                                <?= htmlspecialchars($equipo['ubicacion_actual'] ?? 'Bodega') ?>
                            </div>
                        </div>
                        
                        <div>
                            <span class="label-sm" style="color: var(--warning);">👤 Responsable Actual</span>
                            <div class="data-val"><?= htmlspecialchars($equipo['responsable_actual'] ?? 'Sin asignar (Bodega)') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de asignación -->
            <form method="POST">
                <input type="hidden" name="serial" value="<?= htmlspecialchars($equipo['serial']) ?>">
                <input type="hidden" name="correo_resp_real" id="correo_resp_real"> 
                <input type="hidden" name="correo_sec_real" id="correo_sec_real">

                <div class="form-grid-2col">
                    <!-- Hostname -->
                    <div class="form-group">
                        <label for="hostname" class="label-sm">Nuevo Hostname *</label>
                        <input type="text" 
                               id="hostname"
                               name="hostname" 
                               required 
                               placeholder="Ej: PB-ADM-L01">
                    </div>
                    
                    <!-- Tipo de evento -->
                    <div class="form-group">
                        <label for="tipo_evento" class="label-sm">Tipo de Movimiento *</label>
                        <select id="tipo_evento" name="tipo_evento" required>
                            <option value="Asignación">Asignación</option>
                            <option value="Devolución">Devolución</option>
                        </select>
                    </div>
                    
                    <!-- COMPLIANCE SECTION - 3 SWITCHES -->
                    <div class="compliance-section">
                        <span>🛡️ Verificación de Seguridad y Compliance</span>
                        
                        <!-- Switch 1: DLO -->
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="check_dlo" value="1">
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Agente DLO/Backup</span>
                        </div>

                        <!-- Switch 2: Antivirus -->
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="check_antivirus" value="1">
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Antivirus Corp.</span>
                        </div>

                        <!-- Switch 3: SCCM (NUEVO) -->
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="check_sccm" value="1">
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Agente SCCM</span>
                        </div>
                    </div>
                    
                    <!-- Sede destino -->
                    <div class="form-group">
                        <label for="selectSede" class="label-sm">Sede Destino *</label>
                        <select id="selectSede" required onchange="filtrarLugares()">
                            <option value="">-- Seleccionar Sede --</option>
                            <?php 
                            $sedes = array_unique(array_column($lugares, 'sede')); 
                            foreach($sedes as $sede) {
                                echo "<option value='" . htmlspecialchars($sede) . "'>" . htmlspecialchars($sede) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Ubicación destino -->
                    <div class="form-group">
                        <label for="selectLugar" class="label-sm">Ubicación Destino *</label>
                        <select id="selectLugar" name="id_lugar" required disabled>
                            <option value="">-- Primero elija Sede --</option>
                        </select>
                    </div>

                    <!-- No. de Caso (OBLIGATORIO) -->
                    <div class="form-group">
                        <label for="no_caso" class="label-sm">No. de Caso *</label>
                        <input type="text" 
                               id="no_caso"
                               name="no_caso" 
                               required 
                               placeholder="Ej: 12345, INC0045678">
                        <small class="hint">Número de caso o ticket que justifica el movimiento</small>
                    </div>

                    <!-- Comentarios (OPCIONAL) -->
                    <div class="form-group">
                        <label for="comentarios" class="label-sm">Comentarios u Observaciones</label>
                        <input type="text" 
                               id="comentarios"
                               name="comentarios" 
                               placeholder="Información adicional (opcional)">
                    </div>

                    <!-- LDAP Responsable Principal -->
                    <div class="ldap-group" style="grid-column: span 2;">
                        <label class="label-sm">👤 Responsable Principal (LDAP) *</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" 
                                   id="user_id" 
                                   placeholder="nombre.apellido">
                            <button type="button" 
                                    onclick="verificarUsuario()">
                                🔍 Verificar
                            </button>
                        </div>
                        <div id="userCard" class="user-card">
                            <h4 id="ldap_nombre" style="margin:0; color:var(--primary-color);"></h4>
                            <div id="ldap_info" style="font-size:0.85rem;"></div>
                        </div>
                    </div>

                    <!-- LDAP Responsable Secundario (Opcional) -->
                    <div class="ldap-group" style="grid-column: span 2;">
                        <label class="label-sm">👥 Responsable Secundario (LDAP - Opcional)</label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" 
                                   id="user_id_sec" 
                                   placeholder="nombre.apellido">
                            <button type="button" 
                                    onclick="verificarUsuarioOpcional()"
                                    style="background: #64748b;">
                                🔍 Verificar Opcional
                            </button>
                        </div>
                        <div id="userCard_sec" class="user-card">
                            <h4 id="ldap_nombre_sec" style="margin:0; color:#444;"></h4>
                            <div id="ldap_info_sec" style="font-size:0.85rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- Botón de envío -->
                <button type="submit" 
                        name="confirmar" 
                        id="btnSubmit" 
                        class="btn btn-success btn-block btn-submit-disabled" 
                        disabled>
                    ✅ CONFIRMAR MOVIMIENTO
                </button>
            </form>

            <!-- JavaScript para lugares (data embebida) -->
            <script>
                const lugaresData = <?= json_encode($lugares) ?>;
            </script>
            
            <!-- Scripts externos -->
            <script src="../public/js/registro_movimiento.js"></script>
            <script src="js/verificar_ldap.js"></script>
            <script src="js/verificar_ldap_opcional.js"></script>
        <?php endif; ?>
    </div>
</div>

</body>
</html>