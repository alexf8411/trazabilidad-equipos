<?php
/**
 * public/dashboard.php
 * Panel Principal con RBAC Estricto (Matriz de Accesos)
 */
require_once '../core/session.php';

// Verificación de seguridad básica
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$rol = $_SESSION['rol']; // Atajo para escribir menos código
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - URTRACK</title>
    <style>
        /* Estilos Base */
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f4f6f9; padding: 20px; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Tarjeta de Bienvenida */
        .welcome-card {
            background: white; padding: 25px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex;
            justify-content: space-between; align-items: center; margin-bottom: 30px;
            border-left: 5px solid #002D72;
        }
        .user-info h2 { margin: 0 0 5px 0; color: #002D72; font-size: 1.5rem; }
        .subtitle { color: #666; margin: 0; font-size: 0.95rem; }
        
        /* Badges de Rol */
        .badge-rol { padding: 5px 12px; border-radius: 20px; color: white; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
        .badge-rol.Administrador { background-color: #6f42c1; } /* Morado */
        .badge-rol.Recursos { background-color: #28a745; }      /* Verde */
        .badge-rol.Soporte { background-color: #17a2b8; }       /* Azul */
        .badge-rol.Auditor { background-color: #6c757d; }       /* Gris */

        /* Grid de Acciones */
        .actions-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }
        
        /* Tarjetas de Acción */
        .action-card {
            background: white; padding: 25px; border-radius: 10px;
            text-align: left; border: 1px solid #e1e4e8; transition: all 0.2s ease;
            text-decoration: none; color: #333; display: flex; flex-direction: column;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: relative; overflow: hidden;
        }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); border-color: #002D72; }
        .action-card h3 { color: #002D72; margin: 0 0 10px 0; font-size: 1.2rem; }
        .action-card p { margin: 0; color: #666; font-size: 0.9rem; line-height: 1.4; }
        
        /* Bordes superiores por tipo de acción */
        .card-consult { border-top: 4px solid #17a2b8; } /* Inventario */
        .card-input { border-top: 4px solid #28a745; }   /* Alta */
        .card-move { border-top: 4px solid #007bff; }    /* Movimiento */
        .card-report { border-top: 4px solid #fd7e14; }  /* Reportes */
        .card-admin { border-top: 4px solid #ffc107; }   /* Admin */

        .btn-logout { background-color: #dc3545; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: 600; font-size: 0.9rem; transition: background 0.2s; }
        .btn-logout:hover { background-color: #c82333; }
    </style>
    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    
    <div class="welcome-card">
        <div class="user-info">
            <h2>Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
            <p class="subtitle">
                Rol: <span class="badge-rol <?php echo $rol; ?>"><?php echo $rol; ?></span>
                | <?php echo htmlspecialchars($_SESSION['correo']); ?>
            </p>
        </div>
        <div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="actions-grid">
        
        <a href="inventario.php" class="action-card card-consult">
            <h3>📦 Inventario General</h3>
            <p>Consulta maestra de equipos, ubicaciones y estados actuales.</p>
        </a>

        <?php if (in_array($rol, ['Administrador', 'Recursos'])): ?>
            <a href="alta_equipos.php" class="action-card card-input">
                <h3>➕ Alta de Equipos</h3>
                <p>Registro de nuevos activos (Hoja de Vida) e ingreso a Bodega.</p>
            </a>
        <?php endif; ?>

        <?php if (in_array($rol, ['Administrador', 'Recursos', 'Soporte'])): ?>
            <a href="registro_movimiento.php" class="action-card card-move">
                <h3>🚚 Registro de Movimiento</h3>
                <p>Asignaciones, devoluciones y traslados entre sedes.</p>
            </a>
        <?php endif; ?>

        <?php if (in_array($rol, ['Administrador', 'Recursos', 'Auditor'])): ?>
            <a href="reportes.php" class="action-card card-report">
                <h3>📊 Informes y Reportes</h3>
                <p>Descarga de Excel/PDF, actas y estadísticas de gestión.</p>
            </a>
        <?php endif; ?>

        <?php if (in_array($rol, ['Administrador', 'Auditor'])): ?>
            <a href="auditoria.php" class="action-card card-admin" style="border-top-color: #6c757d;">
                <h3>📜 Auditoría</h3>
                <p>Logs de seguridad, cambios e historial de logins.</p>
            </a>
        <?php endif; ?>

        <?php if ($rol === 'Administrador'): ?>
            <a href="admin_usuarios.php" class="action-card card-admin">
                <h3>👥 Gestión de Usuarios</h3>
                <p>Control de acceso RBAC y aprobación de cuentas.</p>
            </a>

            <a href="admin_lugares.php" class="action-card card-admin">
                <h3>🏢 Sedes y Edificios</h3>
                <p>Administración del catálogo de ubicaciones.</p>
            </a>
            <a href="configuracion.php" class="action-card card-admin" style="border-top-color: #343a40;">
                <h3>⚙️ Configuración Sistema</h3>
                <p>Credenciales SMTP, LDAP, DB y Textos Legales.</p>
            </a>
        <?php endif; ?>

        

    </div>
</div>

</body>
</html>