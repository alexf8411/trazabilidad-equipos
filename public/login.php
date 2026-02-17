<?php
// --- LÓGICA DE CONTROLADOR (BACKEND) ---
require_once '../core/session.php';
require_once '../core/auth.php';

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Variables con prefijo login_ para evitar colisión con db.php
    $login_user = trim($_POST['username']);
    $login_pass = $_POST['password'];

    if (!empty($login_user) && !empty($login_pass)) {
        
        $ip_cliente = $_SERVER['REMOTE_ADDR'];
        
        // PASO 1: Verificar si el usuario está en la lista blanca ANTES de intentar LDAP
        require_once '../core/db.php';
        
        $sqlRol = "SELECT id_usuario, rol, nombre_completo FROM usuarios_sistema 
                   WHERE correo_ldap = ? AND estado = 'Activo' LIMIT 1";
        $stmtRol = $pdo->prepare($sqlRol);
        $stmtRol->execute([$login_user]); 
        $usuarioLocal = $stmtRol->fetch();
        
        if (!$usuarioLocal) {
            // Usuario NO está en la lista blanca de URTRACK
            // Rechazar inmediatamente sin intentar LDAP ni registrar en auditoría
            $error_msg = "Acceso denegado: El usuario '$login_user' no está autorizado.";
        } else {
            // Usuario SÍ está autorizado en URTRACK → validar contra LDAP
            $resultado = autenticar_usuario($login_user, $login_pass);

            if ($resultado['success']) {
                // CASO A: LDAP OK + RBAC OK → Login exitoso
                $nombre_ldap = $resultado['data']['nombre'] ?? $login_user;
                
                regenerar_sesion_segura();
                
                $_SESSION['usuario_id'] = $login_user; 
                $_SESSION['nombre']     = $nombre_ldap; 
                $_SESSION['rol']        = $usuarioLocal['rol']; 
                $_SESSION['depto']      = $resultado['data']['departamento'];
                $_SESSION['logged_in']  = true;

                // AUDITORÍA — Login exitoso
                try {
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (GETDATE(), ?, ?, ?, 'Login_Exitoso')")
                        ->execute([$login_user, $nombre_ldap, $ip_cliente]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría login exitoso: " . $e->getMessage());
                }

                header("Location: dashboard.php");
                exit;

            } else {
                // CASO B: Usuario autorizado pero falló LDAP
                $error_msg  = $resultado['message'];
                $error_code = $resultado['error_code'] ?? 'OTRO';

                $resultado_auditoria = '';

                if ($error_code === '775') {
                    $resultado_auditoria = 'Cuenta_Bloqueada';
                } elseif ($error_code === '532') {
                    $resultado_auditoria = 'Password_Expirado';
                } elseif ($error_code === '52e') {
                    $resultado_auditoria = 'Login_Fallido';
                } else {
                    $resultado_auditoria = 'Login_Fallido';
                }

                // AUDITORÍA — Fallo de usuario autorizado
                try {
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (GETDATE(), ?, NULL, ?, ?)")
                        ->execute([$login_user, $ip_cliente, $resultado_auditoria]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría login fallido: " . $e->getMessage());
                }
            }
        }

    } else {
        $error_msg = "Por favor completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Trazabilidad de Equipos</title>
    <link rel="stylesheet" href="../css/style_login.css">
</head>
<body>

    <main class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Bienvenido</h2>
                <p>Sistema de Trazabilidad de Equipos</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div style="background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem; text-align: center; border: 1px solid #ef9a9a;">
                    ⚠️ <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username">Usuario Institucional</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           placeholder="ej. guillermo.fonseca" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-primary">Iniciar Sesión Segura</button>
            </form>

            <div class="login-footer">
                <small>&copy; 2026 Universidad del Rosario</small>
            </div>
        </div>
    </main>

</body>
</html>