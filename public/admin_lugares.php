<?php
/**
 * public/admin_lugares.php
 * Gestión dinámica de Sedes y Edificios
 */
require_once '../core/db.php';
require_once '../core/session.php';

// Verificación de seguridad: Solo Administradores
// Nota: Usamos 'rol' que es como está en tu dashboard.php
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit;
}

$msg = "";

// Lógica para Agregar Lugar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $sede = $_POST['sede'];
    $nombre = trim($_POST['nombre']);
    
    if (!empty($nombre)) {
        $stmt = $pdo->prepare("INSERT INTO lugares (sede, nombre, estado) VALUES (?, ?, 1)");
        $stmt->execute([$sede, $nombre]);
        $msg = "✅ Ubicación '$nombre' agregada correctamente.";
    }
}

// Lógica para Cambiar Estado (Activar/Desactivar)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE lugares SET estado = NOT estado WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_lugares.php"); // Limpiar la URL
    exit;
}

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
        /* Estilos rápidos para complementar tu style.css */
        body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #002D72; border-bottom: 2px solid #ffc107; padding-bottom: 10px; }
        
        .form-group { background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px; }
        input[type="text"], select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-right: 10px; }
        
        .btn-primary { background: #002D72; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary:hover { background: #0056b3; }
        
        .search-box { margin-bottom: 15px; width: 100%; padding: 12px; border: 1px solid #002D72; border-radius: 4px; box-sizing: border-box; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        tr:hover { background-color: #f1f1f1; }
        
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        
        .btn-toggle { text-decoration: none; font-size: 0.85em; padding: 5px 10px; border-radius: 4px; border: 1px solid #ccc; color: #333; background: #fff; }
        .btn-toggle:hover { background: #eee; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

    <div class="container">
        <h1>🏢 Gestión de Sedes y Edificios</h1>
        
        <?php if ($msg): ?>
            <div class="alert"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="form-group">
            <h3>Agregar Nueva Ubicación</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <select name="sede" required>
                    <option value="Sede Única">Sede Única (Bodegas)</option>
                    <option value="Centro">Sede Centro</option>
                    <option value="Quinta de Mutis">Sede Quinta de Mutis</option>
                    <option value="SEIC">Sede SEIC</option>
                </select>
                <input type="text" name="nombre" placeholder="Nombre (Ej: Claustro, Fase 8...)" required style="width: 250px;">
                <button type="submit" class="btn-primary">Registrar</button>
            </form>
        </div>

        <hr>

        <h3>Listado de Ubicaciones Actuales</h3>
        <input type="text" id="searchInput" class="search-box" onkeyup="filterTable()" placeholder="🔍 Buscar por sede o nombre de edificio...">

        <table id="lugaresTable">
            <thead>
                <tr style="background: #002D72; color: white;">
                    <th>Sede</th>
                    <th>Nombre del Edificio / Espacio</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lugares as $l): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['sede']) ?></strong></td>
                    <td><?= htmlspecialchars($l['nombre']) ?></td>
                    <td>
                        <span class="<?= $l['estado'] ? 'status-active' : 'status-inactive' ?>">
                            <?= $l['estado'] ? '● Activo' : '○ Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?toggle=<?= $l['id'] ?>" class="btn-toggle" onclick="return confirm('¿Cambiar estado de esta ubicación?')">
                            <?= $l['estado'] ? 'Desactivar' : 'Activar' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px;">
            <a href="dashboard.php" style="text-decoration: none; color: #002D72; font-weight: bold;">← Volver al Dashboard</a>
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