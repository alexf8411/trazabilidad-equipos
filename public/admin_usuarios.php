<?php
/**
 * public/admin_usuarios.php
 * Gestión de usuarios con protección de auto-eliminación
 */
require_once '../core/session.php';
require_once '../core/db.php';

// 1. SEGURIDAD: Solo Administradores
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php?error=acceso_no_autorizado");
    exit;
}

$mensaje = "";

// 2. LÓGICA DE PROCESAMIENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. CREAR USUARIO
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $correo = strtolower(trim($_POST['correo']));
        $nombre = trim($_POST['nombre']); // Nombre descriptivo inicial
        $rol    = $_POST['rol'];

        try {
        $sql = "INSERT INTO usuarios_sistema (correo_ldap, nombre_completo, rol, estado) VALUES (?, ?, ?, 'Activo')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$correo, $nombre, $rol]);
        
        // AUDITORÍA — Nuevo usuario autorizado
        try {
            $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
            $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
            $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
            $ip_cliente     = $_SERVER['REMOTE_ADDR'];
            
            $pdo->prepare("INSERT INTO auditoria_cambios 
                (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                VALUES (NOW(), ?, ?, ?, ?, 'CAMBIO_USUARIO', 'usuarios_sistema', ?, NULL, ?)")
                ->execute([
                    $usuario_ldap,
                    $usuario_nombre,
                    $usuario_rol,
                    $ip_cliente,
                    "Usuario: $correo",
                    "Nuevo usuario autorizado - Rol: $rol"
                ]);
        } catch (Exception $e) {
            error_log("Fallo auditoría crear usuario: " . $e->getMessage());
        }
        
        $mensaje = "<div class='alert success'>✅ Usuario <strong>$correo</strong> autorizado.</div>";
        
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $mensaje = "<div class='alert error'>⚠️ El usuario ya existe.</div>";
            } else {
                $mensaje = "<div class='alert error'>❌ Error técnico: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // B. ELIMINAR USUARIO (CON PROTECCIÓN)
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $id_a_borrar = $_POST['id_eliminar'];
        $correo_target = $_POST['correo_eliminar'];

        // --- 🛡️ PROTECCIÓN CRÍTICA ---
        // Verificamos si el usuario que intentan borrar es el mismo que está logueado
        if ($correo_target === $_SESSION['usuario_id']) {
            $mensaje = "<div class='alert error'>⛔ <strong>ACCIÓN DENEGADA:</strong> No puedes eliminar tu propio usuario administrador.</div>";
        } else {
            // Si no eres tú, procedemos
            $sql = "DELETE FROM usuarios_sistema WHERE id_usuario = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_a_borrar]);
            $mensaje = "<div class='alert success'>🗑️ Acceso revocado para <strong>$correo_target</strong>.</div>";
        }
    }
}

// 3. CONSULTA DE LISTADO
$usuarios = $pdo->query("SELECT * FROM usuarios_sistema ORDER BY fecha_registro DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f6f9; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Alertas */
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* Formulario y Tablas */
        .card { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ddd; }
        input, select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; margin-right: 5px; }
        button { cursor: pointer; padding: 8px 12px; border: none; border-radius: 4px; color: white; font-weight: bold; }
        .btn-add { background-color: #28a745; }
        .btn-del { background-color: #dc3545; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #007bff; color: white; }
    </style>
    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>👥 Usuarios Autorizados</h2>
        <a href="dashboard.php" style="text-decoration:none; color:#666;">⬅ Volver</a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card">
        <h3>Nueva Autorización</h3>
        <form method="POST" style="display:flex; gap:10px;">
            <input type="hidden" name="accion" value="crear">
            <input type="text" name="correo" placeholder="Usuario LDAP (ej. j.perez)" required style="flex:1;">
            <input type="text" name="nombre" placeholder="Nombre descriptivo" required style="flex:1;">
            <select name="rol" required>
                <option value="" disabled selected>-- Seleccionar Rol --</option>
                <option value="Soporte">Soporte (Operativo)</option>
                <option value="Administrador">Administrador (Total)</option>
                <option value="Auditor">Auditor (Lectura)</option>
                <option value="Recursos">Recursos (Dueño del Dato)</option>
            </select>
            <button type="submit" class="btn-add">Autorizar</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usr): ?>
            <tr>
                <td><?php echo htmlspecialchars($usr['correo_ldap']); ?></td>
                <td><?php echo htmlspecialchars($usr['nombre_completo']); ?></td>
                <td><?php echo htmlspecialchars($usr['rol']); ?></td>
                <td>
                    <?php 
                    // --- CAPA VISUAL DE PROTECCIÓN ---
                    // Si el usuario de la fila es IGUAL al usuario logueado...
                    if ($usr['correo_ldap'] === $_SESSION['usuario_id']): 
                    ?>
                        <span style="color:#aaa; font-style:italic; font-size:0.9em;">(Sesión actual)</span>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('¿Revocar acceso a <?php echo $usr['nombre_completo']; ?>?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_eliminar" value="<?php echo $usr['id_usuario']; ?>">
                            <input type="hidden" name="correo_eliminar" value="<?php echo $usr['correo_ldap']; ?>">
                            <button type="submit" class="btn-del">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>