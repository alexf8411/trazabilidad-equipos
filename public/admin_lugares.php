<?php
/**
 * public/admin_lugares.php
 * Gestión Maestra de Ubicaciones - UX Mejorada (Post-Redirect-Get)
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Seguridad: Solo Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$msg = "";
$editMode = false;
$dataEdit = ['id' => '', 'sede' => '', 'nombre' => ''];

// --- 1. DETECTOR DE MENSAJES (Feedback tras redirección) ---
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'created':
            $msg = "<div class='toast success'>✅ Ubicación agregada exitosamente.</div>";
            break;
        case 'updated':
            $msg = "<div class='toast success'>🔄 Ubicación actualizada y formulario limpio.</div>";
            break;
        case 'deleted':
            $msg = "<div class='toast success'>🗑️ Ubicación eliminada correctamente.</div>";
            break;
        case 'error_integrity':
            $msg = "<div class='toast warning'>⚠️ No se puede eliminar: El edificio tiene historial activo.<br>Se recomienda DESACTIVARLO en su lugar.</div>";
            break;
        case 'error_db':
            $msg = "<div class='toast error'>❌ Error general de base de datos.</div>";
            break;
    }
}

// --- 2. PROCESAR POST (Crear / Editar) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $sede = $_POST['sede'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');

    try {
        if ($action == 'add' && !empty($nombre)) {
            $stmt = $pdo->prepare("INSERT INTO lugares (sede, nombre, estado) VALUES (?, ?, 1)");
            $stmt->execute([$sede, $nombre]);

            // AUDITORÍA — Nuevo lugar creado
            try {
                $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
                $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
                $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
                $ip_cliente     = $_SERVER['REMOTE_ADDR'];
                
                $pdo->prepare("INSERT INTO auditoria_cambios 
                    (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                    tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                    VALUES (NOW(), ?, ?, ?, ?, 'CAMBIO_LUGAR', 'lugares', ?, NULL, ?)")
                    ->execute([
                        $usuario_ldap,
                        $usuario_nombre,
                        $usuario_rol,
                        $ip_cliente,
                        "Lugar: $nombre",
                        "Nuevo lugar creado - Sede: $sede"
                    ]);
            } catch (Exception $e) {
                error_log("Fallo auditoría crear lugar: " . $e->getMessage());
            }

            // REDIRECCIÓN: Limpia el formulario
            header("Location: admin_lugares.php?status=created");
            exit;
        } 
        elseif ($action == 'edit' && !empty($nombre)) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE lugares SET sede = ?, nombre = ? WHERE id = ?");
            $stmt->execute([$sede, $nombre, $id]);

            // AUDITORÍA — Lugar editado
            try {
                $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
                $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
                $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
                $ip_cliente     = $_SERVER['REMOTE_ADDR'];
                
                $pdo->prepare("INSERT INTO auditoria_cambios 
                    (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                    tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                    VALUES (NOW(), ?, ?, ?, ?, 'CAMBIO_LUGAR', 'lugares', ?, NULL, ?)")
                    ->execute([
                        $usuario_ldap,
                        $usuario_nombre,
                        $usuario_rol,
                        $ip_cliente,
                        "Lugar ID: $id",
                        "Lugar actualizado: $sede - $nombre"
                    ]);
            } catch (Exception $e) {
                error_log("Fallo auditoría editar lugar: " . $e->getMessage());
            }

            // REDIRECCIÓN: Saca al usuario del modo edición
            header("Location: admin_lugares.php?status=updated");
            exit;
        }
    } catch (PDOException $e) {
        $msg = "<div class='toast error'>Error DB: " . $e->getMessage() . "</div>";
    }
}

// --- 3. PROCESAR GET (Eliminar / Toggle / Cargar Edición) ---
if (isset($_GET['action'])) {
    $id = (int)$_GET['id'];
    
    // ELIMINAR
    if ($_GET['action'] == 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM lugares WHERE id = ?");
            $stmt->execute([$id]);

            // AUDITORÍA — Lugar eliminado
            try {
                $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
                $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
                $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
                $ip_cliente     = $_SERVER['REMOTE_ADDR'];
                
                $pdo->prepare("INSERT INTO auditoria_cambios 
                    (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                    tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                    VALUES (NOW(), ?, ?, ?, ?, 'CAMBIO_LUGAR', 'lugares', ?, ?, NULL)")
                    ->execute([
                        $usuario_ldap,
                        $usuario_nombre,
                        $usuario_rol,
                        $ip_cliente,
                        "Lugar ID: $id",
                        "Lugar eliminado del sistema"
                    ]);
            } catch (Exception $e) {
                error_log("Fallo auditoría eliminar lugar: " . $e->getMessage());
            }

            header("Location: admin_lugares.php?status=deleted");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Violación de llave foránea
                header("Location: admin_lugares.php?status=error_integrity");
            } else {
                header("Location: admin_lugares.php?status=error_db");
            }
            exit;
        }
    }
    
    // TOGGLE ESTADO
    if ($_GET['action'] == 'toggle') {
        $stmt = $pdo->prepare("UPDATE lugares SET estado = NOT estado WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_lugares.php"); // Recarga simple
        exit;
    }
    
    // CARGAR PARA EDICIÓN
    if ($_GET['action'] == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM lugares WHERE id = ?");
        $stmt->execute([$id]);
        $dataEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dataEdit) $editMode = true;
    }
}

// Consultar todos
$lugares = $pdo->query("SELECT * FROM lugares ORDER BY sede ASC, nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Ubicaciones</title>
    <style>
        :root {
            --primary: #002D72;
            --accent: #ffc107;
            --text: #333;
            --bg: #f0f2f5;
            --white: #ffffff;
            --border: #e1e4e8;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            font-size: 13px;
        }

        .main-wrapper {
            max-width: 1000px;
            margin: 20px auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 25px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        h1 { margin: 0; color: var(--primary); font-size: 1.5rem; font-weight: 600; }
        .btn-back { text-decoration: none; color: #666; font-weight: 500; display: flex; align-items: center; gap: 5px;}
        .btn-back:hover { color: var(--primary); }

        /* Formulario */
        .form-card {
            background: #fafbfc;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .form-card.editing { border-left: 4px solid var(--accent); background: #fffdf5; }
        
        select, input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }
        select { width: 180px; }
        input[type="text"] { flex-grow: 1; min-width: 200px; }
        select:focus, input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(0,45,114,0.1); }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #001f52; }
        .btn-success { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; text-decoration: none; display: inline-block;}

        .search-wrapper { position: relative; margin-bottom: 15px; }
        .search-box { width: 100%; padding: 8px 10px 8px 30px; border: 1px solid var(--border); border-radius: 4px; box-sizing: border-box;}
        .search-icon { position: absolute; left: 10px; top: 9px; color: #999; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 2px solid var(--border); color: #555; font-weight: 600; background: #f8f9fa;}
        td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background-color: #f8faff; }

        .actions { display: flex; gap: 5px; }
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid transparent;
            transition: background 0.2s;
        }
        .btn-edit { color: #0056b3; background: #e7f1ff; }
        .btn-edit:hover { background: #d0e4ff; }
        .btn-del { color: #dc3545; background: #ffebeb; }
        .btn-del:hover { background: #ffd1d1; }

        .btn-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold; text-decoration: none; }
        .active { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .inactive { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .toast { padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; border-left: 4px solid transparent; }
        .toast.success { background: #d4edda; color: #155724; border-color: #28a745; }
        .toast.error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .toast.warning { background: #fff3cd; color: #856404; border-color: #ffc107; }
    </style>
</head>
<body>

<div class="main-wrapper">
    
    <header>
        <h1>🏢 Sedes y Edificios</h1>
        <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    </header>

    <?= $msg ?>

    <form method="POST" class="form-card <?= $editMode ? 'editing' : '' ?>">
        <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
        <?php if ($editMode): ?>
            <input type="hidden" name="id" value="<?= $dataEdit['id'] ?>">
        <?php endif; ?>

        <div style="font-weight: bold; color: var(--primary); margin-right: 10px;">
            <?= $editMode ? 'EDITAR:' : 'NUEVO:' ?>
        </div>

        <select name="sede" required>
            <option value="" disabled <?= !$editMode ? 'selected' : '' ?>>-- Seleccionar Sede --</option>
            <?php 
            $opts = ['Centro', 'Quinta de Mutis', 'SEIC', 'Bodega tecnología'];
            foreach ($opts as $o) {
                $sel = ($editMode && $dataEdit['sede'] == $o) ? 'selected' : '';
                echo "<option value='$o' $sel>$o</option>";
            }
            ?>
        </select>

        <input type="text" name="nombre" placeholder="Nombre del edificio, bodega o espacio..." required 
               value="<?= htmlspecialchars($dataEdit['nombre']) ?>" autocomplete="off">

        <button type="submit" class="btn <?= $editMode ? 'btn-success' : 'btn-primary' ?>">
            <?= $editMode ? 'Guardar Cambios' : 'Agregar' ?>
        </button>

        <?php if ($editMode): ?>
            <a href="admin_lugares.php" class="btn btn-cancel">Cancelar</a>
        <?php endif; ?>
    </form>

    <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" class="search-box" onkeyup="filterTable()" placeholder="Filtrar por nombre o sede...">
    </div>

    <table id="lugaresTable">
        <thead>
            <tr>
                <th width="20%">Sede</th>
                <th width="45%">Nombre</th>
                <th width="15%">Estado</th>
                <th width="20%" style="text-align: right;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lugares as $l): ?>
            <tr>
                <td style="color: var(--primary); font-weight: 500;"><?= htmlspecialchars($l['sede']) ?></td>
                <td style="font-weight: 600;"><?= htmlspecialchars($l['nombre']) ?></td>
                <td>
                    <a href="?action=toggle&id=<?= $l['id'] ?>" class="btn-status <?= $l['estado'] ? 'active' : 'inactive' ?>" title="Clic para cambiar estado">
                        <?= $l['estado'] ? 'ACTIVO' : 'INACTIVO' ?>
                    </a>
                </td>
                <td style="text-align: right;">
                    <div class="actions" style="justify-content: flex-end;">
                        <a href="?action=edit&id=<?= $l['id'] ?>" class="action-btn btn-edit" title="Renombrar / Editar">✏️</a>
                        <a href="?action=delete&id=<?= $l['id'] ?>" class="action-btn btn-del" 
                           onclick="return confirm('⚠️ ¿Estás seguro de ELIMINAR este lugar?\n\nSi el lugar tiene historial, la operación se cancelará por seguridad.')" 
                           title="Eliminar permanentemente">🗑️</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

<script>
function filterTable() {
    let input = document.getElementById("searchInput");
    let filter = input.value.toUpperCase();
    let table = document.getElementById("lugaresTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let tdSede = tr[i].getElementsByTagName("td")[0];
        let tdNombre = tr[i].getElementsByTagName("td")[1];
        if (tdSede || tdNombre) {
            let txtValue = (tdSede.textContent || tdSede.innerText) + " " + (tdNombre.textContent || tdNombre.innerText);
            tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }
}
</script>

</body>
</html>