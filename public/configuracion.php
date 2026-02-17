<?php
/**
 * public/configuracion.php
 * Versión 4.0 - Panel de Administración Autónomo
 * Cambios: Cifrado AES-256, Validaciones, Links de Diagnóstico
 */
require_once '../core/session.php';

// SEGURIDAD: Solo Administrador
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$configFile = '../core/config.json';

// Inicialización de archivos de texto legal
$filesTxt = [
    'txt_asign'  => '../core/acta_legal.txt',
    'txt_baja'   => '../core/acta_baja.txt',
    'txt_masiva' => '../core/acta_masiva.txt'
];

if (!file_exists($filesTxt['txt_asign'])) @file_put_contents($filesTxt['txt_asign'], "El usuario declara recibir el activo en custodia...");
if (!file_exists($filesTxt['txt_baja']))  @file_put_contents($filesTxt['txt_baja'], "CERTIFICACIÓN DE BAJA: El activo ha sido retirado del inventario...");
if (!file_exists($filesTxt['txt_masiva'])) @file_put_contents($filesTxt['txt_masiva'], "MANIFIESTO DE ENTREGA: El responsable recibe a entera satisfacción los equipos detallados...");

// PROCESAMIENTO DEL FORMULARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once '../core/config_crypto.php';
        
        $jsonContent = @file_get_contents($configFile);
        if ($jsonContent === false) throw new Exception("No se pudo leer config.json");
        
        $currentConfig = json_decode($jsonContent, true);
        if (!$currentConfig) $currentConfig = ['mail'=>[], 'ldap'=>[], 'db'=>[]];

        // === SMTP ===
        $currentConfig['mail']['smtp_host'] = trim($_POST['smtp_host']);
        $currentConfig['mail']['smtp_port'] = (int)$_POST['smtp_port'];
        $currentConfig['mail']['smtp_user'] = trim($_POST['smtp_user']);
        if (!empty($_POST['smtp_pass'])) {
            $currentConfig['mail']['smtp_pass'] = ConfigCrypto::encrypt(trim($_POST['smtp_pass']));
        }

        // === LDAP ===
        $currentConfig['ldap']['host'] = trim($_POST['ldap_host']);
        $currentConfig['ldap']['port'] = (int)$_POST['ldap_port'];
        $currentConfig['ldap']['base_dn'] = trim($_POST['ldap_base_dn']);
        $currentConfig['ldap']['domain_suffix'] = trim($_POST['ldap_domain_suffix']);
        $currentConfig['ldap']['bind_user'] = trim($_POST['ldap_bind_user']);
        if (!empty($_POST['ldap_bind_pass'])) {
            $currentConfig['ldap']['bind_pass'] = ConfigCrypto::encrypt(trim($_POST['ldap_bind_pass']));
        }

        // === BASE DE DATOS ===
        $currentConfig['db']['host'] = trim($_POST['db_host']);
        $currentConfig['db']['port'] = (int)$_POST['db_port'];
        $currentConfig['db']['name'] = trim($_POST['db_name']);
        $currentConfig['db']['user'] = trim($_POST['db_user']);
        if (!empty($_POST['db_pass'])) {
            $currentConfig['db']['pass'] = ConfigCrypto::encrypt(trim($_POST['db_pass']));
        }

        // Guardar archivos
        $jsonSaved = file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $txt1 = file_put_contents($filesTxt['txt_asign'], trim($_POST['texto_asign']));
        $txt2 = file_put_contents($filesTxt['txt_baja'], trim($_POST['texto_baja']));
        $txt3 = file_put_contents($filesTxt['txt_masiva'], trim($_POST['texto_masiva']));

        if ($jsonSaved === false || $txt1 === false || $txt2 === false || $txt3 === false) {
            throw new Exception("Error de permisos al escribir archivos");
        }

        // Auditoría
        // AUDITORÍA — Registrar cambio de configuración
        try {
            require_once '../core/db.php';
            $usuario_ldap   = $_SESSION['usuario_id'] ?? 'desconocido';
            $usuario_nombre = $_SESSION['nombre']     ?? 'Usuario sin nombre';
            $usuario_rol    = $_SESSION['rol']        ?? 'Administrador';
            $ip_cliente     = $_SERVER['REMOTE_ADDR'];
            
            // Determinar qué sección se modificó (si tienes botones específicos)
            $seccion = 'Sistema general';
            
            $pdo->prepare("INSERT INTO auditoria_cambios 
                (fecha, usuario_ldap, usuario_nombre, usuario_rol, ip_origen, 
                tipo_accion, tabla_afectada, referencia, valor_anterior, valor_nuevo) 
                VALUES (NOW(), ?, ?, ?, ?, 'CAMBIO_CONFIGURACION', 'config', ?, NULL, ?)")
                ->execute([
                    $usuario_ldap,
                    $usuario_nombre,
                    $usuario_rol,
                    $ip_cliente,
                    "Configuración: $seccion",
                    "Configuración modificada"
                ]);
        } catch (Exception $e) {
            error_log("Fallo auditoría config: " . $e->getMessage());
        }

        $msg = "<div class='alert success'>✅ Configuración actualizada y cifrada correctamente.</div>";

    } catch (Exception $e) {
        $msg = "<div class='alert error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// LECTURA DE VALORES ACTUALES
require_once '../core/config_crypto.php';
$data = json_decode(@file_get_contents($configFile), true);
if (!$data) $data = ['mail'=>[], 'ldap'=>[], 'db'=>[]];

// Descifrar contraseñas para mostrar (enmascaradas)
$smtp_pass_display = !empty($data['mail']['smtp_pass']) ? '********' : '';
$ldap_pass_display = !empty($data['ldap']['bind_pass']) ? '********' : '';
$db_pass_display = !empty($data['db']['pass']) ? '********' : '';

$txt_asign  = file_exists($filesTxt['txt_asign']) ? file_get_contents($filesTxt['txt_asign']) : '';
$txt_baja   = file_exists($filesTxt['txt_baja']) ? file_get_contents($filesTxt['txt_baja']) : '';
$txt_masiva = file_exists($filesTxt['txt_masiva']) ? file_get_contents($filesTxt['txt_masiva']) : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - URTRACK</title>
    <link rel="stylesheet" href="css/urtrack-styles.css">
    <style>
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .config-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .config-section h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-grid.full {
            grid-template-columns: 1fr;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: monospace;
        }
        .btn-save {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-save:hover {
            background: #229954;
        }
        .btn-test {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            margin-left: 10px;
        }
        .btn-test:hover {
            background: #2980b9;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .diagnostic-links {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .diagnostic-links a {
            display: inline-block;
            padding: 8px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .diagnostic-links a:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>

<div class="config-container">
    <h1>⚙️ Configuración del Sistema</h1>
    <p style="color: #666; margin-bottom: 20px;">
        Panel de administración autónomo. Todas las contraseñas se cifran automáticamente.
    </p>

    <?php echo $msg; ?>

    <form method="POST">
        
        <!-- SMTP/Office 365 -->
        <div class="config-section">
            <h3>📧 Configuración SMTP (Office 365)</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Host SMTP</label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($data['mail']['smtp_host'] ?? 'smtp.office365.com'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Puerto</label>
                    <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($data['mail']['smtp_port'] ?? 587); ?>" required>
                </div>
                <div class="form-group">
                    <label>Usuario (Email)</label>
                    <input type="email" name="smtp_user" value="<?php echo htmlspecialchars($data['mail']['smtp_user'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Contraseña (dejar vacío para mantener)</label>
                    <input type="password" name="smtp_pass" placeholder="<?php echo $smtp_pass_display; ?>">
                </div>
            </div>
        </div>

        <!-- LDAP/Active Directory -->
        <div class="config-section">
            <h3>🔐 Configuración LDAP/Active Directory</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Host LDAP</label>
                    <input type="text" name="ldap_host" value="<?php echo htmlspecialchars($data['ldap']['host'] ?? 'ldaps://10.194.194.142'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Puerto</label>
                    <input type="number" name="ldap_port" value="<?php echo htmlspecialchars($data['ldap']['port'] ?? 636); ?>" required>
                </div>
                <div class="form-group">
                    <label>Base DN</label>
                    <input type="text" name="ldap_base_dn" value="<?php echo htmlspecialchars($data['ldap']['base_dn'] ?? 'DC=urosario,DC=loc'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Sufijo de Dominio (UPN)</label>
                    <input type="text" name="ldap_domain_suffix" value="<?php echo htmlspecialchars($data['ldap']['domain_suffix'] ?? '@lab.urosario.edu.co'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Usuario Bind (opcional)</label>
                    <input type="text" name="ldap_bind_user" value="<?php echo htmlspecialchars($data['ldap']['bind_user'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Contraseña Bind (opcional)</label>
                    <input type="password" name="ldap_bind_pass" placeholder="<?php echo $ldap_pass_display; ?>">
                </div>
            </div>
            <div class="diagnostic-links">
                <a href="test_ldap.php" target="_blank">🧪 Probar Conexión LDAP</a>
                <a href="auth_bind.php" target="_blank">🔑 Verificar Bind</a>
                <a href="auth_query.php" target="_blank">👤 Consultar Usuario</a>
            </div>
        </div>

        <!-- BASE DE DATOS -->
        <div class="config-section">
            <h3>🗄️ Configuración de Base de Datos</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Host / IP / VIP Clúster</label>
                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($data['db']['host'] ?? '127.0.0.1'); ?>" required>
                    <small style="color:#666;">Ejemplos: 127.0.0.1 | cluster-mysql.urosario.loc | 10.10.10.101,10.10.10.102</small>
                </div>
                <div class="form-group">
                    <label>Puerto</label>
                    <input type="number" name="db_port" value="<?php echo htmlspecialchars($data['db']['port'] ?? 3306); ?>" required>
                </div>
                <div class="form-group">
                    <label>Nombre de la Base de Datos</label>
                    <input type="text" name="db_name" value="<?php echo htmlspecialchars($data['db']['name'] ?? 'trazabilidad_local'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" name="db_user" value="<?php echo htmlspecialchars($data['db']['user'] ?? 'appadmdb'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Contraseña (dejar vacío para mantener)</label>
                    <input type="password" name="db_pass" placeholder="<?php echo $db_pass_display; ?>">
                </div>
            </div>
            <div class="diagnostic-links">
                <a href="test_db.php" target="_blank">🧪 Probar Conexión a BD</a>
            </div>
        </div>

        <!-- TEXTOS LEGALES -->
        <div class="config-section">
            <h3>📄 Textos Legales de Actas</h3>
            <div class="form-grid full">
                <div class="form-group">
                    <label>Texto Acta Individual (Asignación)</label>
                    <textarea name="texto_asign"><?php echo htmlspecialchars($txt_asign); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Texto Acta de Baja</label>
                    <textarea name="texto_baja"><?php echo htmlspecialchars($txt_baja); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Texto Manifiesto Masivo</label>
                    <textarea name="texto_masiva"><?php echo htmlspecialchars($txt_masiva); ?></textarea>
                </div>
            </div>
        </div>

        <!-- HERRAMIENTAS AVANZADAS -->
        <div class="config-section">
            <h3>🛠️ Herramientas de Diagnóstico Avanzado</h3>
            <div class="diagnostic-links">
                <a href="diagnostico.php">📊 Panel Completo de Diagnóstico</a>
                <a href="syscheck.php">🔍 Revisión del Sistema</a>
                <a href="escaner_db.php">🗂️ Análisis de Integridad DB</a>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn-save">💾 Guardar Cambios</button>
            <a href="dashboard.php" style="margin-left: 15px; color: #666; text-decoration: none;">← Volver al Dashboard</a>
        </div>
    </form>
</div>

</body>
</html>