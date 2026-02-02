<?php
// --- LÓGICA DE CONTROLADOR (BACKEND) ---
require_once '../core/session.php'; // Inicia la seguridad de sesión (Actividad 7)
require_once '../core/auth.php';    // Trae la función de LDAP (Paso 1)

$error_msg = "";

// Detectamos si el usuario presionó "Iniciar Sesión"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitización básica
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (!empty($user) && !empty($pass)) {
        // 2. Llamada al Motor de Autenticación
        $resultado = autenticar_usuario($user, $pass);

        if ($resultado['success']) {

           // 1. SALVAMOS EL USUARIO LDAP (Antes de que db.php lo sobrescriba)
            $usuario_ldap_real = $user;
        
            // --- PASO 1: VERIFICACIÓN DE LISTA BLANCA (RBAC) ---
            require_once '../core/db.php'; // Conectamos a BD inmediatamente

            // Buscamos si el usuario LDAP está en nuestra tabla de permisos y está Activo
            
            // 1. DEFINIMOS LA CONSULTA (¡Esto faltaba!)
            $sqlRol = "SELECT id_usuario, rol, nombre_completo FROM usuarios_sistema 
                       WHERE correo_ldap = ? AND estado = 'Activo' LIMIT 1";

            // 2. PREPARAMOS (Con un solo $)
            $stmtRol = $pdo->prepare($sqlRol);
            
            // 3. EJECUTAMOS
            $stmtRol->execute([$usuario_ldap_real]); 
            $usuarioLocal = $stmtRol->fetch();
            
            if (!$usuarioLocal) {
                // CASO A: Autenticó en LDAP pero NO está en la lista blanca
                // No regeneramos sesión, no auditamos acceso exitoso, solo mostramos error.
                $error_msg = "Acceso denegado: El usuario '$usuario_ldap_real' no está autorizado.";
                
            } else {
                // CASO B: ¡AUTORIZADO TOTALMENTE! (LDAP + RBAC)
                
                // 1. Configuración de Sesión
                regenerar_sesion_segura(); // Anti-hijacking
                
                // 1. USUARIO: Usamos la variable blindada (corrección del bug appadmdb)
                $_SESSION['usuario_id'] = $usuario_ldap_real; 
                
                // 2. NOMBRE: ¡CAMBIO REALIZADO! Usamos el dato directo del LDAP
                // Esto garantiza que siempre veas el nombre oficial de la Universidad
                $_SESSION['nombre']     = $resultado['data']['nombre']; 
                
                // 3. ROL: Este SÍ debe venir de la BD local
                // (Es lo que define si ves el botón de "Gestionar Usuarios" o no)
                $_SESSION['rol']        = $usuarioLocal['rol']; 
                
                // 4. DEPTO: También lo traemos del LDAP
                $_SESSION['depto']      = $resultado['data']['departamento'];
                
                $_SESSION['logged_in']  = true;

                // 2. --- TU AUDITORÍA ORIGINAL (INTEGRADA AQUÍ) ---
                try {
                    // Nota: Ya no hacemos require_once '../core/db.php' porque lo hicimos arriba
                    
                    $sqlAudit = "INSERT INTO auditoria_acceso (fecha, hora, usuario_ldap, ip_acceso) 
                                 VALUES (CURDATE(), CURTIME(), ?, ?)";
                    
                    $stmtAudit = $pdo->prepare($sqlAudit);
                    
                    $stmtAudit->execute([
                        $_SESSION['usuario_id'],
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                } catch (Exception $e) {
                    // Log de error silencioso
                    error_log("Fallo al registrar auditoría de acceso: " . $e->getMessage());
                }
                // --- FIN AUDITORÍA ---

                // 3. Redirección
                header("Location: dashboard.php");
                exit;
            }

        } else {
            // Fallo LDAP (Contraseña incorrecta, usuario no existe, etc.)
            $error_msg = $resultado['message'];
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
    <link rel="stylesheet" href="../css/style.css">
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