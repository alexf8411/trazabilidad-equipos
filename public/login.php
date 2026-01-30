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
            // 3. ¡ÉXITO! Guardamos datos en sesión
            regenerar_sesion_segura(); // Anti-hijacking (Actividad 7)
            
            $_SESSION['usuario_id'] = $user;
            $_SESSION['nombre']     = $resultado['data']['nombre'];
            $_SESSION['depto']      = $resultado['data']['departamento'];
            $_SESSION['roles']      = $resultado['data']['roles'];
            $_SESSION['logged_in']  = true;

            // --- INICIO AUDITORÍA (Actividad 9) ---
            try {
                // 1. Incluimos la conexión
                require_once '../core/db.php';
                
                // 2. Preparamos la inserción en la NUEVA tabla
                $sqlAudit = "INSERT INTO auditoria_acceso (fecha, hora, usuario_ldap, ip_acceso) 
                             VALUES (CURDATE(), CURTIME(), ?, ?)";
                
                $stmtAudit = $pdo->prepare($sqlAudit);
                
                // 3. Ejecutamos con los datos reales
                $stmtAudit->execute([
                    //$user,                   // usuario_ldap (ej. guillermo.fonseca)
                    $_SESSION['usuario_id'],
                    $_SERVER['REMOTE_ADDR']  // ip_acceso

                ]);
                
            } catch (Exception $e) {
                // Si falla el log, guardamos el error en el log de errores de Apache/PHP
                // pero NO detenemos el acceso al usuario.
                error_log("Fallo al registrar auditoría de acceso: " . $e->getMessage());
            }
            // --- FIN AUDITORÍA ---

            // 4. Redirección al Dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            // Fallo: Guardamos el mensaje para mostrarlo en el HTML
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