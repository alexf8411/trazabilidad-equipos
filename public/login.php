<?php
// --- LÓGICA DE CONTROLADOR (BACKEND) ---
require_once '../core/session.php';
require_once '../core/auth.php';

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (!empty($user) && !empty($pass)) {
        
        $resultado = autenticar_usuario($user, $pass);
        $ip_cliente = $_SERVER['REMOTE_ADDR'];

        if ($resultado['success']) {

            $usuario_ldap_real = $user;
            $nombre_ldap       = $resultado['data']['nombre'] ?? $user;
        
            // VERIFICACIÓN DE LISTA BLANCA (RBAC)
            require_once '../core/db.php';

            $sqlRol = "SELECT id_usuario, rol, nombre_completo FROM usuarios_sistema 
                       WHERE correo_ldap = ? AND estado = 'Activo' LIMIT 1";
            $stmtRol = $pdo->prepare($sqlRol);
            $stmtRol->execute([$usuario_ldap_real]); 
            $usuarioLocal = $stmtRol->fetch();
            
            if (!$usuarioLocal) {
                // CASO A: LDAP ok pero NO está autorizado en URTRACK
                $error_msg = "Acceso denegado: El usuario '$usuario_ldap_real' no está autorizado.";
                
                // AUDITORÍA — Acceso denegado
                try {
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (NOW(), ?, ?, ?, 'Acceso_Denegado')")
                        ->execute([$usuario_ldap_real, $nombre_ldap, $ip_cliente]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría acceso denegado: " . $e->getMessage());
                }

            } else {
                // CASO B: AUTORIZADO TOTALMENTE (LDAP + RBAC)
                regenerar_sesion_segura();
                
                $_SESSION['usuario_id'] = $usuario_ldap_real; 
                $_SESSION['nombre']     = $nombre_ldap; 
                $_SESSION['rol']        = $usuarioLocal['rol']; 
                $_SESSION['depto']      = $resultado['data']['departamento'];
                $_SESSION['logged_in']  = true;

                // AUDITORÍA — Login exitoso
                try {
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (NOW(), ?, ?, ?, 'Login_Exitoso')")
                        ->execute([$usuario_ldap_real, $nombre_ldap, $ip_cliente]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría login exitoso: " . $e->getMessage());
                }

                header("Location: dashboard.php");
                exit;
            }

        } else {
            // CASO C: Fallo en LDAP — clasificar por tipo
            $error_msg  = $resultado['message'];
            $error_code = $resultado['error_code'] ?? 'OTRO';

            // Solo registrar en auditoría si el error es relevante para seguridad
            // (usuario real con contraseña incorrecta, cuenta bloqueada, password expirado)
            $registrar_en_auditoria = false;
            $resultado_auditoria = '';

            if ($error_code === '775') {
                $resultado_auditoria = 'Cuenta_Bloqueada';
                $registrar_en_auditoria = true;
            } elseif ($error_code === '532') {
                $resultado_auditoria = 'Password_Expirado';
                $registrar_en_auditoria = true;
            } elseif ($error_code === '52e') {
                // 52e = contraseña incorrecta para usuario REAL (esto sí importa)
                $resultado_auditoria = 'Login_Fallido';
                $registrar_en_auditoria = true;
            }
            // Si error_code es 'OTRO' o 'ERROR_CONEXION' → NO registrar

            // AUDITORÍA — Solo fallos relevantes
            if ($registrar_en_auditoria) {
                try {
                    require_once '../core/db.php';
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (NOW(), ?, NULL, ?, ?)")
                        ->execute([$user, $ip_cliente, $resultado_auditoria]);
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