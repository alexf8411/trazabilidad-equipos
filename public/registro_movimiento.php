<?php
require_once '../core/db.php';
require_once '../core/session.php';

if (!in_array($_SESSION['rol'], ['Administrador', 'Recursos', 'Soporte'])) {
    header('Location: dashboard.php'); exit;
}

$equipo = null;
$msg = "";

// Cargar catálogo de lugares
$stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE estado = 1 ORDER BY sede, nombre");
$lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['buscar']) && !empty($_GET['criterio'])) {
    $criterio = trim($_GET['criterio']);
    $stmt = $pdo->prepare("SELECT * FROM equipos WHERE placa_ur = ? OR serial = ? LIMIT 1");
    $stmt->execute([$criterio, $criterio]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equipo) $msg = "<div class='alert error'>Equipo no localizado. Verifique los datos.</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $pdo->beginTransaction();
        $stmt_l = $pdo->prepare("SELECT sede, nombre FROM lugares WHERE id = ?");
        $stmt_l->execute([$_POST['id_lugar']]);
        $l = $stmt_l->fetch();

        $sql = "INSERT INTO bitacora (serial_equipo, id_lugar, sede, ubicacion, tipo_evento, correo_responsable, tecnico_responsable, hostname, fecha_evento) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['serial'], $_POST['id_lugar'], $l['sede'], $l['nombre'],
            $_POST['tipo_evento'], $_POST['correo_resp'], $_SESSION['nombre'], strtoupper($_POST['hostname'])
        ]);

        $pdo->commit();
        header("Location: generar_acta.php?serial=" . $_POST['serial']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación | URTRACK</title>
    <style>
        :root {
            --primary: #002D72;
            --secondary: #64748b;
            --success: #22c55e;
            --error: #ef4444;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: #1e293b;
            margin: 0;
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }

        .container {
            width: 100%;
            max-width: 800px;
        }

        .card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
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
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .btn-back:hover { color: var(--primary); }

        .search-section {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }

        input, select {
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            outline: none;
            width: 100%;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 45, 114, 0.1);
        }

        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .info-pill {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 0.9rem;
            border-left: 4px solid var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width { grid-column: span 2; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .btn-submit {
            background: var(--success);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            width: 100%;
            margin-top: 30px;
            transition: transform 0.2s, opacity 0.2s;
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
        }

        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-submit:hover:not(:disabled) { transform: translateY(-1px); }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }

        .error { background: #fee2e2; color: #b91c1c; }
        .ldap-status { font-size: 0.8rem; margin-top: 6px; font-weight: 500; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <h2>Asignación de Activo</h2>
            <a href="dashboard.php" class="btn-back">← Volver</a>
        </div>

        <?= $msg ?>

        <form method="GET" class="search-section">
            <input type="text" name="criterio" placeholder="Placa UR o Serial..." value="<?= $_GET['criterio'] ?? '' ?>" required autofocus>
            <button type="submit" name="buscar" class="btn-search">Buscar</button>
        </form>

        <?php if ($equipo): ?>
            <div class="info-pill">
                <div><label>Activo</label><strong><?= $equipo['marca'] ?> <?= $equipo['modelo'] ?></strong></div>
                <div><label>Placa</label><strong><?= $equipo['placa_ur'] ?></strong></div>
                <div><label>Serial</label><strong><?= $equipo['serial'] ?></strong></div>
                <div><label>Estado</label><strong style="color:var(--primary)"><?= $equipo['estado_maestro'] ?></strong></div>
            </div>

            <form method="POST">
                <input type="hidden" name="serial" value="<?= $equipo['serial'] ?>">
                
                <div class="form-grid">
                    <div>
                        <label>Hostname</label>
                        <input type="text" name="hostname" required placeholder="Nombre de red">
                    </div>
                    <div>
                        <label>Tipo de Evento</label>
                        <select name="tipo_evento">
                            <option value="Asignación">Asignación</option>
                            <option value="Traslado">Traslado</option>
                        </select>
                    </div>
                    <div>
                        <label>Sede</label>
                        <select id="selectSede" required onchange="filtrarLugares()">
                            <option value="">-- Seleccionar --</option>
                            <?php 
                            $sedes = array_unique(array_column($lugares, 'sede'));
                            foreach($sedes as $s) echo "<option value='$s'>$s</option>";
                            ?>
                        </select>
                    </div>
                    <div>
                        <label>Ubicación / Edificio</label>
                        <select id="selectLugar" name="id_lugar" required disabled>
                            <option value="">-- Elija sede --</option>
                        </select>
                    </div>
                    <div class="full-width">
                        <label>Correo Responsable (Validación LDAP)</label>
                        <input type="email" id="correo_resp" name="correo_resp" required placeholder="usuario@urosario.edu.co">
                        <div id="ldap_msg" class="ldap-status"></div>
                    </div>
                </div>

                <button type="submit" name="confirmar" id="btnSubmit" class="btn-submit" disabled>Registrar y Generar Acta PDF</button>
            </form>

            <script>
                const lugaresData = <?= json_encode($lugares) ?>;
                function filtrarLugares() {
                    const sede = document.getElementById('selectSede').value;
                    const el = document.getElementById('selectLugar');
                    el.innerHTML = '<option value="">-- Seleccionar Edificio --</option>';
                    lugaresData.filter(l => l.sede === sede).forEach(l => {
                        el.innerHTML += `<option value="${l.id}">${l.nombre}</option>`;
                    });
                    el.disabled = false;
                }

                document.getElementById('correo_resp').addEventListener('blur', function() {
                    const email = this.value;
                    const msg = document.getElementById('ldap_msg');
                    const btn = document.getElementById('btnSubmit');
                    if(email.includes('@')) {
                        msg.innerHTML = "🔍 Validando identidad...";
                        msg.style.color = "var(--secondary)";
                        fetch(`../core/validar_usuario_ldap.php?email=${email}`)
                            .then(res => res.json())
                            .then(data => {
                                if(data.status === 'success') {
                                    msg.innerHTML = `✅ ${data.nombre} [${data.departamento}]`;
                                    msg.style.color = "var(--success)";
                                    btn.disabled = false;
                                } else {
                                    msg.innerHTML = "❌ Usuario no encontrado en LDAP";
                                    msg.style.color = "var(--error)";
                                    btn.disabled = true;
                                }
                            });
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>