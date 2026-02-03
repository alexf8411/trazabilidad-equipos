<?php
/**
 * public/admin_lugares.php
 * Gestión dinámica de Sedes y Edificios (CRUD Completo)
 */
require_once '../core/db.php';
require_once '../core/session.php';

// 1. Verificación de seguridad: Solo Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$msg = "";
$editMode = false;
$lugarEditar = ['id' => '', 'sede' => '', 'nombre' => ''];

// 2. Procesar Formularios (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $sede = $_POST['sede'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');

    try {
        if ($action == 'add' && !empty($nombre)) {
            // CREAR NUEVO
            $stmt = $pdo->prepare("INSERT INTO lugares (sede, nombre, estado) VALUES (?, ?, 1)");
            $stmt->execute([$sede, $nombre]);
            $msg = "<div class='alert success'>✅ Ubicación '$nombre' creada correctamente.</div>";
        } 
        elseif ($action == 'edit' && !empty($nombre)) {
            // ACTUALIZAR (RENOMBRAR)
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE lugares SET sede = ?, nombre = ? WHERE id = ?");
            $stmt->execute([$sede, $nombre, $id]);
            $msg = "<div class='alert success'>🔄 Ubicación actualizada a '$nombre'.</div>";
        }
    } catch (PDOException $e) {
        $msg = "<div class='alert error'>❌ Error en base de datos: " . $e->getMessage() . "</div>";
    }
}

// 3. Procesar Acciones GET (Toggle Estado o Cargar Edición)
if (isset($_GET['action'])) {
    $id = (int)$_GET['id'];
    
    // Cambiar Estado (Activar/Desactivar)
    if ($_GET['action'] == 'toggle') {
        $stmt = $pdo->prepare("UPDATE lugares SET estado = NOT estado WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_lugares.php"); // Limpiar URL
        exit;
    }
    
    // Cargar datos para Editar
    if ($_GET['action'] == 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM lugares WHERE id = ?");
        $stmt->execute([$id]);
        $lugarEditar = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lugarEditar) {
            $editMode = true;
        }
    }
}

// 4. Consultar listado completo
$lugares = $pdo->query("SELECT * FROM lugares ORDER BY sede ASC, nombre ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ubicaciones - URTRACK</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Estilos específicos para esta vista */
        body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        h1 { color: #002D72; border-bottom: 2px solid #ffc107; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Formulario */
        .form-panel { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 25px; }
        .form-title { margin-top: 0; color: #002D72; font-size: 1.1em; margin-bottom: 15px; }
        
        .input-group { display: flex; gap: 10px; flex-wrap: wrap; }
        select, input[type="text"] { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex-grow: 1; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background: #002D72; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-cancel { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #333; font-size: 0.9em; padding: 5px 10px; }
        .btn-toggle { background: #fff; border: 1px solid #ccc; color: #333; font-size: 0.9em; padding: 5px 10px; }

        /* Tabla */
        .search-box { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #002D72; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #002D72; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background-color: #f1f1f1; }

        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="container">
        <h1>🏢 Sedes y Edificios</h1>
        
        <?= $msg; ?>

        <div class="form-panel" style="<?= $editMode ? 'border-left: 5px solid #ffc107;' : 'border-left: 5px solid #002D72;' ?>">
            <h3 class="form-title">
                <?= $editMode ? '✏️ Editando Ubicación' : '➕ Agregar Nueva Ubicación' ?>
            </h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editMode ? 'edit' : 'add' ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="id" value="<?= $lugarEditar['id'] ?>">
                <?php endif; ?>

                <div class="input-group">
                    <select name="sede" required style="max-width: 200px;">
                        <option value="Sede Única" <?= ($lugarEditar['sede'] == 'Sede Única') ? 'selected' : '' ?>>Sede Única</option>
                        <option value="Centro" <?= ($lugarEditar['sede'] == 'Centro') ? 'selected' : '' ?>>Sede Centro</option>
                        <option value="Quinta de Mutis" <?= ($lugarEditar['sede'] == 'Quinta de Mutis') ? 'selected' : '' ?>>Quinta de Mutis</option>
                        <option value="SEIC" <?= ($lugarEditar['sede'] == 'SEIC') ? 'selected' : '' ?>>Sede SEIC</option>
                    </select>

                    <input type="text" name="nombre" placeholder="Nombre del Edificio / Bodega" required 
                           value="<?= htmlspecialchars($lugarEditar['nombre']) ?>">

                    <button type="submit" class="btn <?= $editMode ? 'btn-success' : 'btn-primary' ?>">
                        <?= $editMode ? 'Guardar Cambios' : 'Registrar' ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="admin_lugares.php" class="btn btn-cancel">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <input type="text" id="searchInput" class="search-box" onkeyup="filterTable()" placeholder="🔍 Buscar sede o edificio...">

        <table id="lugaresTable">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th style="width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lugares as $l): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['sede']) ?></strong></td>
                    <td><?= htmlspecialchars($l['nombre']) ?></td>
                    <td>
                        <span class="<?= $l['estado'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $l['estado'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?action=edit&id=<?= $l['id'] ?>" class="btn btn-warning" title="Editar Nombre">✏️</a>
                        
                        <a href="?action=toggle&id=<?= $l['id'] ?>" class="btn btn-toggle" 
                           onclick="return confirm('¿Seguro que deseas cambiar el estado?')" title="Activar/Desactivar">
                           <?= $l['estado'] ? '⛔' : '✅' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <a href="dashboard.php" style="color: #002D72; font-weight: bold; text-decoration: none;">← Volver al Dashboard</a>
        </div>
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