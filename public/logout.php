<?php
require_once '../core/session.php'; 

// 1. Borrar variables
$_SESSION = array();

// 2. MATAR LA COOKIE DEL NAVEGADOR (Crucial para que el JS funcione)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // Ponemos la fecha de expiración en el pasado (ayer)
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir sesión en servidor
session_destroy();

// 4. HEADER NUCLEAR (Opcional pero recomendado para Chrome moderno)
// Le dice al navegador: "Borra todo lo que sepas de este sitio"
header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\"");

header("Location: login.php");
exit;
?>