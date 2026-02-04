<?php
/**
 * public/registro_movimiento.php
 * Formulario de Asignación Minimalista con Verificación LDAP Proactiva
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. CONTROL DE ACCESO (Admin, Recursos, Soporte)
if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); 
    exit;
}

$equipo = null;
$msg = "";

// 2. CARGAR CATÁLOGO DE LUGARES (Para los selects dinámicos)
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

// 3. LÓGICA DE BÚSQUEDA DE ACTIVO
if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipo) {
        $msg = "<div class='alert error'>❌ Activo no localizado. Verifique Placa o Serial.</div>";
    }
}

// 4. PROCESAR GUARDADO DE MOVIMIENTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        
        // Obtener datos del lugar para el registro histórico
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (
                    serial_equipo, id_lugar, sede, ubicacion, 
                    tipo_evento, correo_responsable, tecnico_responsable, 
                    hostname, fecha_evento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], 
            $_POST['id_lugar'], 
            $l['sede'], 
            $l['nombre'],
            $_POST['tipo_evento'], 
            $_POST['correo_resp'], 
            $_SESSION['nombre'], 
            strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        // Redirección al generador de PDF
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>❌ Error al guardar: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación de Activos | URTRACK</title>
    <style>
        :root {
            --primary: #002D72;
            --secondary: #64748b;
            --success: #22c55e;
            --error: #ef4444;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --input-bg: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            background-color: var(--bg);
            color: #1e293b;
            margin: 0;
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }

        .container { width: 100%; max-width: 800px; }

        .card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h2 { margin: 0; color: var(--primary); font-size: 1.5rem; letter-spacing: -0.025em; }

        .btn-back {
            text-decoration: none;
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .btn-back:hover { color: var(--primary); }

        .search-section {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
        }

        input, select {
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 45, 114, 0.1);
        }

        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .info-pill {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            border: 1px solid var(--border);
            border-left: 5px solid var(--primary);
        }

        .label-small { font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 4px; display: block; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }

        .ldap-group { display: flex; gap: 10px; align-items: flex-end; }
        
        .btn-verify {
            background: #f1f5f9;
            color: var(--primary);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            white-space: nowrap;
        }

        .user-card {
            background: #f8fafc;
            border: 1px dashed var(--secondary);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            display: none;
        }

        .btn-submit {
            background: var(--success);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            margin-top: 30px;
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
        }

        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; }
        .error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h2>Asignación de Activo</h2>
            <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
        </div>

        <?= $msg ?>

        <form method="GET" class="search-section">
            <input type="text" name="criterio" placeholder="Ingrese Placa UR o Serial..." value="<?= $_GET['criterio'] ?? '' ?>" required autofocus>
            <button type="submit" name="buscar" class="btn-search">Buscar</button>
        </form>

        <?php if ($equipo): ?>
            <div class="info-pill">
                <div><span class="label-small">Activo</span><strong><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></strong></div>
                <div><span class="label-small">Placa</span><strong><?= $equipo['placa_ur'] ?></strong></div>
                <div><span class="label-small">Serial</span><strong><?= $equipo['serial'] ?></strong></div>
                <div><span class="label-small">Estado Actual</span><strong style="color:var(--primary)"><?= $equipo['estado_maestro'] ?></strong></div>
            </div>

            <form method="POST">
                <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
                
                <div class="form-grid">
                    <div>
                        <label class="label-small">Hostname</label>
                        <input type="text" name="hostname" required placeholder="Nombre de red del equipo">
                    </div>
                    <div>
                        <label class="label-small">Tipo de Evento</label>
                        <select name="tipo_evento">
                            <option value="Asignación">Asignación Directa</option>
                            <option value="Traslado">Traslado Interno</option>
                            <option value="Préstamo">Préstamo Temporal</option>
                        </select>
                    </div>
                    <div>
                        <label class="label-small">Sede Principal</label>
                        <select id="selectSede" required onchange="filtrarLugares()">
                            <option value="">-- Seleccionar Sede --</option>
                            <?php 
                            $sedes = array_unique(array_column($lugares, 'sede'));
                            foreach($sedes as $s) echo "<option value='$s'>$s</option>";
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="label-small">Edificio / Ubicación</label>
                        <select id="selectLugar" name="id_lugar" required disabled>
                            <option value="">-- Elija primero la sede --</option>
                        </select>
                    </div>

                    <div class="full-width">
                        <label class="label-small">Responsable (Correo Institucional)</label>
                        <div class="ldap-group">
                            <input type="email" id="correo_resp" name="correo_resp" required placeholder="usuario@urosario.edu.co">
                            <button type="button" class="btn-verify" onclick="verificarUsuario()">🔍 Verificar Usuario</button>
                        </div>
                        
                        <div id="userCard" class="user-card">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <h4 id="ldap_nombre" style="margin:0; color:var(--primary); font-size: 1.1rem;"></h4>
                                    <p id="ldap_cargo" style="margin:4px 0 0 0; color:var(--secondary); font-size: 0.85rem;"></p>
                                </div>
                                <span style="background:#dcfce7; color:#166534; padding:6px 12px; border-radius:20px; font-size:0.7rem; font-weight:800; border: 1px solid #bbf7d0;">LDAP OK</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>CONFIRMAR Y GENERAR ACTA PDF</button>
            </form>

            

            <script>
                // 1. Filtrado de Lugares
                const lugaresData = <?= json_encode($lugares) ?>;
                function filtrarLugares() {
                    const sede = document.getElementById('selectSede').value;
                    const el = document.getElementById('selectLugar');
                    el.innerHTML = '<option value="">-- Seleccionar Ubicación --</option>';
                    
                    const filtrados = lugaresData.filter(l => l.sede === sede);
                    filtrados.forEach(l => {
                        el.innerHTML += `<option value="${l.id}">${l.nombre}</option>`;
                    });
                    el.disabled = false;
                }

                // 2. Verificación LDAP Proactiva
                function verificarUsuario() {
                    const email = document.getElementById('correo_resp').value;
                    const card = document.getElementById('userCard');
                    const btnSubmit = document.getElementById('btnSubmit');

                    if (!email.includes('@urosario.edu.co')) {
                        alert("Por favor ingrese un correo institucional válido (@urosario.edu.co).");
                        return;
                    }

                    // Feedback de carga
                    card.style.display = 'block';
                    document.getElementById('ldap_nombre').innerText = "Consultando...";
                    document.getElementById('ldap_cargo').innerText = "Buscando en Directorio Activo...";

                    fetch(`../core/validar_usuario_ldap.php?email=${email}`)
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === 'success') {
                                document.getElementById('ldap_nombre').innerText = data.nombre;
                                document.getElementById('ldap_cargo').innerText = data.departamento;
                                btnSubmit.disabled = false;
                                btnSubmit.style.cursor = "pointer";
                            } else {
                                alert("❌ Usuario no encontrado. Verifique la dirección de correo.");
                                card.style.display = 'none';
                                btnSubmit.disabled = true;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert("Error crítico de conectividad con LDAP.");
                        });
                }
            </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>