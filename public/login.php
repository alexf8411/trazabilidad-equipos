<?php
// --- LÓGICA DE CONTROLADOR (BACKEND) ---
require_once '../core/session.php';
require_once '../core/auth.php';

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];

    if (!empty($user) && !empty($pass)) {
        
        $ip_cliente = $_SERVER['REMOTE_ADDR'];
        
        // PASO 1: Verificar si el usuario está en la lista blanca ANTES de intentar LDAP
        require_once '../core/db.php';
        
        $sqlRol = "SELECT id_usuario, rol, nombre_completo FROM usuarios_sistema 
                   WHERE correo_ldap = ? AND estado = 'Activo' LIMIT 1";
        $stmtRol = $pdo->prepare($sqlRol);
        $stmtRol->execute([$user]); 
        $usuarioLocal = $stmtRol->fetch();
        
        if (!$usuarioLocal) {
            // Usuario NO está en la lista blanca de URTRACK
            // Rechazar inmediatamente sin intentar LDAP ni registrar en auditoría
            $error_msg = "Acceso denegado: El usuario '$user' no está autorizado.";
        } else {
            // Usuario SÍ está autorizado en URTRACK → validar contra LDAP
            $resultado = autenticar_usuario($user, $pass);

            if ($resultado['success']) {
                // CASO A: LDAP OK + RBAC OK → Login exitoso
                $nombre_ldap = $resultado['data']['nombre'] ?? $user;
                
                regenerar_sesion_segura();
                
                $_SESSION['usuario_id'] = $user; 
                $_SESSION['nombre']     = $nombre_ldap; 
                $_SESSION['rol']        = $usuarioLocal['rol']; 
                $_SESSION['depto']      = $resultado['data']['departamento'];
                $_SESSION['logged_in']  = true;

                // AUDITORÍA — Login exitoso
                try {
                    $pdo->prepare("INSERT INTO auditoria_acceso 
                        (fecha_hora, usuario_ldap, usuario_nombre, ip_acceso, resultado)
                        VALUES (NOW(), ?, ?, ?, 'Login_Exitoso')")
                        ->execute([$user, $nombre_ldap, $ip_cliente]);
                } catch (Exception $e) {
                    error_log("Fallo auditoría login exitoso: " . $e->getMessage());
                }

                header("Location: dashboard.php");
                exit;

            } else {
                // CASO B: Usuario autorizado pero falló LDAP
                // Estos SÍ son relevantes porque son usuarios reales
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
                    // Error de conexión u otro → registrar como fallo genérico
                    $resultado_auditoria = 'Login_Fallido';
                }

                // AUDITORÍA — Fallo de usuario autorizado (SIEMPRE registrar)
                try {
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