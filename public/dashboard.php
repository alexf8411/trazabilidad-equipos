<?php
/**
 * public/dashboard.php
 * Panel Principal
 */
require_once '../core/session.php'; // Inicia sesión y verifica inactividad

// Verificación de seguridad (Doble factor)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Trazabilidad</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .welcome-card {
            background: white; padding: 20px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex;
            justify-content: space-between; align-items: center;
        }
        
        .user-info h2 { margin: 0 0 5px 0; color: #333; }
        .user-info p { margin: 0; color: #666; }
        .badge { 
            background: #007bff; color: white; padding: 3px 8px; 
            border-radius: 12px; font-size: 0.8em; vertical-align: middle;
        }

        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px; margin-top: 30px;
        }
        
        .action-card {
            background: white; padding: 20px; border-radius: 8px;
            text-align: center; border: 1px solid #ddd; transition: transform 0.2s;
            text-decoration: none; color: #333;
        }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .action-card h3 { color: #0056b3; }
        
        /* Botón de Logout */
        .btn-logout {
            background-color: #dc3545; color: white; text-decoration: none;
            padding: 10px 20px; border-radius: 5px; font-weight: bold;
        }
        .btn-logout:hover { background-color: #c82333; }

        /* Estilo especial para Admin */
        .card-admin { border-top: 4px solid #ffc107; }

        .subtitle { color: #666; margin-top: 5px; font-size: 0.95em; }
    
        .badge-rol { padding: 4px 10px; border-radius: 15px; color: white; font-weight: bold; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px;}
        
        /* Colores según el rol */
        .badge-rol.Administrador { background-color: #6f42c1; /* Morado Admin */ }
        .badge-rol.Soporte { background-color: #17a2b8; /* Azul Técnico */ }
        .badge-rol.Auditor { background-color: #6c757d; /* Gris Observador */ }
    </style>

    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    <div class="welcome-card">
    <div class="user-info">
        <h2>
            Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </h2>
        <p class="subtitle">
            Rol actual: <span class="badge-rol <?php echo $_SESSION['rol']; ?>">
                <?php echo htmlspecialchars($_SESSION['rol']); ?>
            </span>
            | Depto: <?php echo htmlspecialchars($_SESSION['depto'] ?? 'General'); ?>
        </p>
    </div>
    <div>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</div>

    <div class="actions-grid">
        
        <a href="inventario.php" class="action-card">
            <h3>📦 Inventario General</h3>
            <p>Consultar listado maestro de equipos y ubicaciones.</p>
        </a>

        <?php if ($_SESSION['rol'] === 'Administrador'): ?>
            
            <a href="admin_usuarios.php" class="action-card card-admin">
                <h3>👥 Gestión de Usuarios</h3>
                <p>Autorizar accesos y asignar roles (RBAC).</p>
            </a>

            <a href="auditoria.php" class="action-card card-admin" style="border-top-color: #343a40;">
                <h3>📜 Auditoría de Accesos</h3>
                <p>Ver historial de conexiones e IPs.</p>
            </a>

        <?php endif; ?>

        <div class="action-card" style="opacity: 0.5; cursor: not-allowed;">
            <h3>📊 Reportes (Próximamente)</h3>
            <p>Estadísticas de uso y auditoría.</p>
        </div>

    </div>
</div>

</body>
</html>