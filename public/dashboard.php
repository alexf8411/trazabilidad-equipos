<?php
require_once '../core/session.php';

// --- PROTECCIÓN DE CACHÉ (Evita el botón atrás) ---
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// --- PROTECCIÓN DE RUTA ---
// Si el usuario no tiene la marca de "logged_in", lo expulsamos al login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal</title>
   <script>
        // Función que pregunta al servidor el estado real
        function verificarSesion() {
            fetch('status.php')
                .then(response => response.json())
                .then(data => {
                    // Si el servidor dice que NO estamos activos, recargamos
                    // para que PHP nos expulse al Login.
                    if (data.status !== 'active') {
                        window.location.href = 'login.php';
                    }
                })
                .catch(error => console.error('Error verificando sesión:', error));
        }

        // Se ejecuta cuando la página se muestra (incluso desde el botón Atrás/Caché)
        window.addEventListener('pageshow', function(event) {
            verificarSesion();
        });
    </script>
    <link rel="stylesheet" href="../css/style.css"> <style>
        .dashboard-container { padding: 2rem; max-width: 800px; margin: 0 auto; }
        .welcome-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .data-table th, .data-table td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
        .logout-btn { background: #c62828; display: inline-block; margin-top: 20px; text-decoration: none; color: white; padding: 10px 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="welcome-card">
            <h1>👋 Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
            <p>Has ingresado correctamente al Sistema de Trazabilidad.</p>
            
            <h3>Tus Credenciales de Sesión:</h3>
            <table class="data-table">
                <tr><th>Usuario:</th><td><?php echo $_SESSION['usuario_id']; ?></td></tr>
                <tr><th>Departamento:</th><td><?php echo $_SESSION['depto']; ?></td></tr>
                <tr><th>Roles (LDAP):</th><td><code><?php echo $_SESSION['roles']; ?></code></td></tr>
            </table>

            <a href="logout.php" class="logout-btn">Cerrar Sesión Segura</a>
        </div>
    </div>
</body>
</html>