<?php
/**
 * public/configuracion.php
 * Versión Final 3.3 - Gestión Centralizada, Responsive y Segura
 */
require_once '../core/session.php';

// 1. SEGURIDAD: Bloqueo estricto (Solo Administrador)
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$configFile = '../core/config.json';

// Definición de archivos de texto legal
$filesTxt = [
    'txt_asign'  => '../core/acta_legal.txt',   // Para acta individual
    'txt_baja'   => '../core/acta_baja.txt',    // Para acta de baja
    'txt_masiva' => '../core/acta_masiva.txt'   // NUEVO: Para manifiesto masivo
];

// 2. INICIALIZACIÓN DE ARCHIVOS (Si no existen)
if (!file_exists($configFile)) {
    $initialConfig = [
        "mail" => ["smtp_user" => "", "smtp_pass" => ""],
        "ldap" => ["bind_user" => "", "bind_pass" => "", "host" => "ldaps://10.194.194.142", "port" => 636, "base_dn" => "DC=urosario,DC=loc"],
        "db"   => ["host" => "127.0.0.1", "name" => "trazabilidad_local", "user" => "appadmdb", "pass" => ""]
    ];
    // Intentar crear el archivo config
    @file_put_contents($configFile, json_encode($initialConfig, JSON_PRETTY_PRINT));
}

// Crear textos por defecto si están vacíos o no existen
if(!file_exists($filesTxt['txt_asign'])) @file_put_contents($filesTxt['txt_asign'], "El usuario declara recibir el activo en custodia...");
if(!file_exists($filesTxt['txt_baja']))  @file_put_contents($filesTxt['txt_baja'], "CERTIFICACIÓN DE BAJA: El activo ha sido retirado del inventario...");
if(!file_exists($filesTxt['txt_masiva'])) @file_put_contents($filesTxt['txt_masiva'], "MANIFIESTO DE ENTREGA: El responsable recibe a entera satisfacción los equipos detallados...");


// 3. PROCESAMIENTO DEL FORMULARIO (Guardar Cambios)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Cargar configuración actual
        $jsonContent = @file_get_contents($configFile);
        if ($jsonContent === false) throw new Exception("No se pudo leer core/config.json");
        
        $currentConfig = json_decode($jsonContent, true);
        if (!$currentConfig) $currentConfig = []; // Fallback si el JSON estaba corrupto

        // A. Configuración de Correo
        $currentConfig['mail']['smtp_user'] = trim($_POST['smtp_user']);
        if (!empty($_POST['smtp_pass'])) {
            $currentConfig['mail']['smtp_pass'] = trim($_POST['smtp_pass']);
        }

        // B. Configuración LDAP
        $currentConfig['ldap']['bind_user'] = trim($_POST['ldap_user']);
        if (!empty($_POST['ldap_pass'])) {
            $currentConfig['ldap']['bind_pass'] = trim($_POST['ldap_pass']);
        }

        // C. Configuración Base de Datos
        $currentConfig['db']['user'] = trim($_POST['db_user']);
        if (!empty($_POST['db_pass'])) {
            $currentConfig['db']['pass'] = trim($_POST['db_pass']);
        }

        // GUARDADO DE DATOS (Validando escritura)
        
        // 1. Guardar JSON
        $jsonSaved = file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT));
        
        // 2. Guardar Textos (Usando trim para evitar espacios vacíos)
        $txt1 = file_put_contents($filesTxt['txt_asign'], trim($_POST['texto_asign']));
        $txt2 = file_put_contents($filesTxt['txt_baja'],  trim($_POST['texto_baja']));
        $txt3 = file_put_contents($filesTxt['txt_masiva'], trim($_POST['texto_masiva']));

        // Verificar si hubo errores de escritura
        if ($jsonSaved === false || $txt1 === false || $txt2 === false || $txt3 === false) {
            throw new Exception("Error de permisos: No se pudieron escribir los archivos en /core/. Ejecute: sudo chown -R www-data ../core");
        }

        $msg = "<div class='alert success'>✅ Configuración y textos actualizados correctamente.</div>";

    } catch (Exception $e) {
        $msg = "<div class='alert error'>❌ Error al guardar: " . $e->getMessage() . "</div>";
    }
}

// 4. LECTURA DE VALORES ACTUALES (Para mostrar en los inputs)
$data = json_decode(@file_get_contents($configFile), true);
// Fallback visual si falla la lectura
if (!$data) $data = ['mail'=>[], 'ldap'=>[], 'db'=>[]];

$txt_asign  = file_exists($filesTxt['txt_asign']) ? file_get_contents($filesTxt['txt_asign']) : '';
$txt_baja   = file_exists($filesTxt['txt_baja'])  ? file_get_contents($filesTxt['txt_baja'])  : '';
$txt_masiva = file_exists($filesTxt['txt_masiva'])? file_get_contents($filesTxt['txt_masiva']) : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Configuración | URTRACK</title>
    <style>
        :root { --ur-blue: #002D72; --ur-gold: #ffc107; --bg: #f4f6f9; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); padding: 20px; margin: 0; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Card Styles */
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        h2 { color: var(--ur-blue); border-bottom: 2px solid #eee; padding-bottom: 15px; margin-top: 0; }
        h3 { color: #444; margin-top: 30px; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        h3::before { content: ''; display: block; width: 4px; height: 18px; background: var(--ur-gold); border-radius: 2px; }

        /* Grid Responsive System */
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
            margin-top: 15px;
        }
        .full-width { grid-column: 1 / -1; }

        /* Inputs */
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; color: #333; }
        input[type="text"], input[type="password"], textarea { 
            width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; 
            box-sizing: border-box; font-size: 0.95rem; transition: border 0.2s;
        }
        input:focus, textarea:focus { border-color: var(--ur-blue); outline: none; }
        textarea { min-height: 120px; resize: vertical; line-height: 1.5; font-family: inherit; }

        /* Buttons & Alerts */
        .btn-save { 
            background: var(--ur-blue); color: white; padding: 15px; width: 100%; border: none; 
            border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 30px; 
            transition: background 0.2s;
        }
        .btn-save:hover { background: #001a4d; }
        .btn-back { text-decoration: none; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .help-text { font-size: 0.8rem; color: #64748b; margin-top: 5px; display: block; }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .card { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    
    <?= $msg ?>
    
    <form method="POST">
        <div class="card">
            <h2>⚙️ Configuración del Sistema</h2>
            
            <h3>📧 Servidor de Correo (SMTP)</h3>
            <div class="form-grid">
                <div>
                    <label>Correo Remitente</label>
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($data['mail']['smtp_user'] ?? '') ?>" placeholder="usuario@universidad.edu.co">
                </div>
                <div>
                    <label>Contraseña (App Password)</label>
                    <input type="password" name="smtp_pass" placeholder="Dejar vacía para mantener actual">
                    <span class="help-text">Se recomienda usar App Password de Office365/Gmail.</span>
                </div>
            </div>

            <h3>🔑 Conexión Directorio Activo (LDAP)</h3>
            <div class="form-grid">
                <div>
                    <label>Usuario de Servicio (Bind User)</label>
                    <input type="text" name="ldap_user" value="<?= htmlspecialchars($data['ldap']['bind_user'] ?? '') ?>" placeholder="CN=...">
                </div>
                <div>
                    <label>Contraseña de Servicio</label>
                    <input type="password" name="ldap_pass" placeholder="Dejar vacía para mantener actual">
                </div>
            </div>

            <h3>💾 Base de Datos Local</h3>
            <div class="form-grid">
                <div>
                    <label>Usuario MySQL</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($data['db']['user'] ?? '') ?>">
                </div>
                <div>
                    <label>Contraseña MySQL</label>
                    <input type="password" name="db_pass" placeholder="Dejar vacía para mantener actual">
                </div>
            </div>

            <h3>⚖️ Textos Legales para Reportes</h3>
            <div class="form-grid">
                <div class="full-width">
                    <label>📝 Cláusula para Acta Individual (Movimientos)</label>
                    <textarea name="texto_asign"><?= htmlspecialchars($txt_asign) ?></textarea>
                </div>
                
                <div class="full-width">
                    <label style="color: #4f46e5;">📦 Cláusula para Manifiesto de Entrega Masiva</label>
                    <textarea name="texto_masiva" style="border-left: 4px solid #4f46e5; background-color: #fcfdff;"><?= htmlspecialchars($txt_masiva) ?></textarea>
                    <span class="help-text">Este texto aparecerá al final del PDF generado por el módulo de Asignación Masiva.</span>
                </div>

                <div class="full-width">
                    <label style="color: #dc3545;">🗑️ Cláusula para Certificación de Baja</label>
                    <textarea name="texto_baja" style="border-left: 4px solid #dc3545; background-color: #fff5f5;"><?= htmlspecialchars($txt_baja) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-save" onclick="return confirm('¿Está seguro de guardar estos cambios?');">GUARDAR CONFIGURACIÓN</button>
        </div>
    </form>
</div>

</body>
</html>