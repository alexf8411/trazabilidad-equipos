<?php
/**
 * URTRACK - Dashboard Principal 
 * Versión 3.0 OPTIMIZADA
 * 
 * OPTIMIZACIONES:
 * ✅ CSS centralizado en urtrack-styles.css
 * ✅ Mantiene session-check.js sin cambios
 * ✅ Panel con RBAC estricto
 * ✅ Responsive completo
 */

require_once '../core/session.php';

// Verificación de seguridad
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$rol = $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - URTRACK</title>
    
    <!-- CSS EXTERNO -->
    <link rel="stylesheet" href="../css/urtrack-styles.css">
    
    <!-- JavaScript de verificación de sesión -->
    <script src="js/session-check.js"></script>
</head>
<body>

<div class="container">
    
    <!-- Tarjeta de bienvenida -->
    <div class="welcome-card">
        <div class="user-info">
            <h2>Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
            <p class="subtitle">
                Rol: <span class="badge-rol <?php echo htmlspecialchars($rol); ?>"><?php echo htmlspecialchars($rol); ?></span>
                | <?php echo htmlspecialchars($_SESSION['correo']); ?>
            </p>
        </div>
        <div>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <!-- Grid de acciones -->
    <div class="actions-grid">
        
        <!-- INVENTARIO GENERAL - Todos los roles -->
        <a href="inventario.php" class="action-card card-consult">
            <h3>📦 Inventario General</h3>
            <p>Consulta maestra de equipos, ubicaciones y estados actuales.</p>
        </a>

        <!-- ALTA DE EQUIPOS - Administrador, Recursos -->
        <?php if (in_array($rol, ['Administrador', 'Recursos'])): ?>
            <a href="alta_equipos.php" class="action-card card-input">
                <h3>➕ Alta de Equipos</h3>
                <p>Registro de nuevos activos (Hoja de Vida) e ingreso a Bodega.</p>
            </a>

            <!-- CONTROL DE BAJAS - Administrador, Recursos -->
            <a href="baja_equipos.php" class="action-card card-input" style="border-top-color: #dc3545;">
                <h3 style="color: #dc3545;">🗑️ Control de Bajas</h3>
                <p>Retiro definitivo de activos por obsolescencia o daño (Por Serial).</p>
            </a>
        <?php endif; ?>

        <!-- REGISTRO DE MOVIMIENTO - Administrador, Recursos, Soporte -->
        <?php if (in_array($rol, ['Administrador', 'Recursos', 'Soporte'])): ?>
            <a href="registro_movimiento.php" class="action-card card-move">
                <h3>🚚 Registro de Movimiento</h3>
                <p>Asignaciones, devoluciones y traslados entre sedes.</p>
            </a>

            <!-- ASIGNACIÓN MASIVA - Administrador, Recursos, Soporte -->
            <a href="asignacion_masiva.php" class="action-card card-move" style="border-top-color: #4f46e5;">
                <h3>🚀 Asignación Masiva</h3>
                <p>Carga CSV para múltiples equipos (Máx 100). Ideal para salas o laboratorios.</p>
            </a>
        <?php endif; ?>

        <!-- INFORMES Y REPORTES - Administrador, Recursos, Auditor -->
        <?php if (in_array($rol, ['Administrador', 'Recursos', 'Auditor'])): ?>
            <a href="reportes.php" class="action-card card-report">
                <h3>📊 Informes y Reportes</h3>
                <p>Descarga de Excel/PDF, actas y estadísticas de gestión.</p>
            </a>
        <?php endif; ?>

        <!-- AUDITORÍA - Administrador, Auditor -->
        <?php if (in_array($rol, ['Administrador', 'Auditor'])): ?>
            <a href="auditoria.php" class="action-card card-admin" style="border-top-color: #6c757d;">
                <h3>📜 Auditoría</h3>
                <p>Logs de seguridad, cambios e historial de logins.</p>
            </a>
        <?php endif; ?>

        <!-- GESTIÓN DE USUARIOS - Solo Administrador -->
        <?php if ($rol === 'Administrador'): ?>
            <a href="admin_usuarios.php" class="action-card card-admin">
                <h3>👥 Gestión de Usuarios</h3>
                <p>Control de acceso RBAC y aprobación de cuentas.</p>
            </a>

            <!-- SEDES Y EDIFICIOS - Solo Administrador -->
            <a href="admin_lugares.php" class="action-card card-admin">
                <h3>🏢 Sedes y Edificios</h3>
                <p>Administración del catálogo de ubicaciones.</p>
            </a>

            <!-- CONFIGURACIÓN SISTEMA - Solo Administrador -->
            <a href="configuracion.php" class="action-card card-admin" style="border-top-color: #343a40;">
                <h3>⚙️ Configuración Sistema</h3>
                <p>Credenciales SMTP, LDAP, DB y Textos Legales.</p>
            </a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>