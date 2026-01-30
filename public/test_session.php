<?php
// Incluimos nuestro gestor de seguridad
require_once '../core/session.php';

// Simulamos que guardamos datos (como si acabáramos de loguearnos)
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = 'guillermo.fonseca';
    $_SESSION['rol'] = 'ADMINISTRADOR';
    $_SESSION['hora_login'] = time();
    $mensaje = "🆕 Sesión iniciada y datos guardados.";
} else {
    $mensaje = "🔄 Sesión existente recuperada correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de Sesión</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f4f4f4; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #003366; }
        code { background: #eee; padding: 2px 5px; border-radius: 3px; }
        .status { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Prueba de Backend: Sesiones</h1>
        <p class="status"><?php echo $mensaje; ?></p>
        <hr>
        <h3>Datos en Memoria del Servidor ($_SESSION):</h3>
        <pre><?php print_r($_SESSION); ?></pre>
        
        <h3>ID de Sesión (Cookie):</h3>
        <code><?php echo session_id(); ?></code>
        
        <p><small>Recarga la página. Si el ID se mantiene y los datos siguen ahí, la persistencia funciona.</small></p>
    </div>
</body>
</html>