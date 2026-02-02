<?php
/**
 * public/admin_usuarios.php
 * Gestión de usuarios autorizados (CRUD Simple)
 */
require_once '../core/session.php';
require_once '../core/db.php';

// 1. SEGURIDAD: Solo Administradores pueden ver esto
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    // Si es Soporte o Auditor, lo devolvemos al Dashboard
    header("Location: dashboard.php?error=acceso_no_autorizado");
    exit;
}

$mensaje = "";

// 2. LÓGICA: Agregar o Eliminar Usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Agregar Usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $correo = strtolower(trim($_POST['correo']));
        $nombre = trim($_POST['nombre']);
        $rol    = $_POST['rol'];

        try {
            $sql = "INSERT INTO usuarios_sistema (correo_ldap, nombre_completo, rol) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$correo, $nombre, $rol]);
            $mensaje = "✅ Usuario agregado correctamente.";
        } catch (PDOException $e) {
            $mensaje = "❌ Error: El usuario ya existe o hubo un fallo técnico.";
        }
    }
    
    // B. Eliminar (Inactivar) Usuario
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $id = $_POST['id_eliminar'];
        // Evitar auto-eliminación
        if ($_SESSION['usuario_id'] !== $_POST['correo_eliminar']) {
            $sql = "DELETE FROM usuarios_sistema WHERE id_usuario = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $mensaje = "🗑️ Usuario eliminado.";
        } else {
            $mensaje = "⚠️ No puedes eliminarte a ti mismo.";
        }
    }
}

// 3. CONSULTA: Traer lista de usuarios
$usuarios = $pdo->query("SELECT * FROM usuarios_sistema ORDER BY fecha_registro DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Usuarios</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f6f9; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .btn-danger { background-color: #dc3545; padding: 5px 10px; font-size: 0.8rem; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; background-color: #d1ecf1; color: #0c5460; }
        .header-nav { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .back-link { text-decoration: none; color: #6c757d; font-weight: bold; }
    </style>
    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    <div class="header-nav">
        <h2>👥 Gestión de Usuarios Autorizados</h2>
        <a href="dashboard.php" class="back-link">⬅ Volver al Dashboard</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3>Nuevo Usuario</h3>
        <form method="POST">
            <input type="hidden" name="accion" value="crear">
            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <input type="text" name="correo" placeholder="Usuario LDAP (ej: juan.perez)" required>
                </div>
                <div style="flex: 1;">
                    <input type="text" name="nombre" placeholder="Nombre Completo" required>
                </div>
                <div style="width: 150px;">
                    <select name="rol">
                        <option value="Soporte">Soporte</option>
                        <option value="Administrador">Administrador</option>
                        <option value="Auditor">Auditor (Solo ver)</option>
                    </select>
                </div>
                <button type="submit">Agregar</button>
            </div>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Usuario LDAP</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usr): ?>
            <tr>
                <td><?php echo htmlspecialchars($usr['correo_ldap']); ?></td>
                <td><?php echo htmlspecialchars($usr['nombre_completo']); ?></td>
                <td>
                    <span style="font-weight: bold; color: <?php echo $usr['rol'] == 'Administrador' ? '#007bff' : '#666'; ?>">
                        <?php echo htmlspecialchars($usr['rol']); ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($usr['estado']); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este acceso?');">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id_eliminar" value="<?php echo $usr['id_usuario']; ?>">
                        <input type="hidden" name="correo_eliminar" value="<?php echo $usr['correo_ldap']; ?>">
                        <button type="submit" class="btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>