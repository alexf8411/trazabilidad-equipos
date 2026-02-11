<?php
/**
 * public/configuracion.php
 * V3.1 - Gestión Centralizada (Incluye Textos Masivos)
 */
require_once '../core/session.php';

// 1. SEGURIDAD: Solo Administrador
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php"); exit;
}

$msg = "";
$configFile = '../core/config.json';
// Definimos los 3 archivos de texto legal
$filesTxt = [
    'txt_asign' => '../core/acta_legal.txt',
    'txt_baja'  => '../core/acta_baja.txt',
    'txt_masiva'=> '../core/acta_masiva.txt' // NUEVO
];

// Asegurar existencia de archivos base
if (!file_exists($configFile)) {
    $initialConfig = [
        "mail" => ["smtp_user" => "", "smtp_pass" => ""],
        "ldap" => ["bind_user" => "", "bind_pass" => "", "host" => "ldaps://10.194.194.142", "port" => 636, "base_dn" => "DC=urosario,DC=loc"],
        "db"   => ["host" => "127.0.0.1", "name" => "trazabilidad_local", "user" => "appadmdb", "pass" => ""]
    ];
    file_put_contents($configFile, json_encode($initialConfig, JSON_PRETTY_PRINT));
}

// Crear archivos de texto si no existen
if(!file_exists($filesTxt['txt_asign'])) file_put_contents($filesTxt['txt_asign'], "El usuario declara recibir el equipo...");
if(!file_exists($filesTxt['txt_baja']))  file_put_contents($filesTxt['txt_baja'], "CERTIFICACIÓN DE BAJA DE ACTIVOS...");
if(!file_exists($filesTxt['txt_masiva'])) file_put_contents($filesTxt['txt_masiva'], "MANIFIESTO DE ENTREGA: El responsable recibe los equipos listados...");

// 2. PROCESAR GUARDADO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $currentConfig = json_decode(file_get_contents($configFile), true);

        // MAIL
        $currentConfig['mail']['smtp_user'] = $_POST['smtp_user'];
        if (!empty($_POST['smtp_pass'])) $currentConfig['mail']['smtp_pass'] = $_POST['smtp_pass'];

        // LDAP
        $currentConfig['ldap']['bind_user'] = $_POST['ldap_user'];
        if (!empty($_POST['ldap_pass'])) $currentConfig['ldap']['bind_pass'] = $_POST['ldap_pass'];

        // DB
        $currentConfig['db']['user'] = $_POST['db_user'];
        if (!empty($_POST['db_pass'])) $currentConfig['db']['pass'] = $_POST['db_pass'];

        if (file_put_contents($configFile, json_encode($currentConfig, JSON_PRETTY_PRINT))) {
            // Guardar Textos Legales
            file_put_contents($filesTxt['txt_asign'], $_POST['texto_asign']);
            file_put_contents($filesTxt['txt_baja'],  $_POST['texto_baja']);
            file_put_contents($filesTxt['txt_masiva'], $_POST['texto_masiva']); // Guardar nuevo texto
            
            $msg = "<div class='alert success'>✅ Configuración guardada correctamente.</div>";
        } else {
            throw new Exception("No se pudo escribir en core/config.json");
        }
    } catch (Exception $e) {
        $msg = "<div class='alert error'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

// 3. LEER VALORES
$data = json_decode(file_get_contents($configFile), true);
$txt_asign = file_get_contents($filesTxt['txt_asign']);
$txt_baja  = file_get_contents($filesTxt['txt_baja']);
$txt_masiva= file_get_contents($filesTxt['txt_masiva']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | URTRACK</title>
    <style>
        :root { --ur-blue: #002D72; --ur-gold: #ffc107; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { color: var(--ur-blue); border-bottom: 2px solid var(--ur-blue); padding-bottom: 10px; }
        h3 { color: #555; margin-top: 25px; border-left: 5px solid var(--ur-gold); padding-left: 10px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .full-width { grid-column: 1 / -1; }
        label { display: block; font-weight: bold; font-size: 0.9rem; margin-bottom: 5px; color: #444; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        textarea { min-height: 100px; resize: vertical; font-family: monospace; font-size: 0.9rem; }
        .btn-save { background: var(--ur-blue); color: white; padding: 15px; width: 100%; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 20px; }
        .btn-save:hover { background: #001f52; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .help-text { font-size: 0.8rem; color: #666; background: #eee; padding: 5px; border-radius: 3px; display: block; margin-top: 5px; }
        .btn-back { text-decoration: none; color: #666; display: inline-block; margin-bottom: 15px; }
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
                    <input type="password" name="smtp_pass" placeholder="Dejar vacía para mantener">
                </div>
            </div>

            <h3>🔑 Directorio Activo (LDAP)</h3>
            <div class="form-grid">
                <div>
                    <label>Usuario Bind</label>
                    <input type="text" name="ldap_user" value="<?= htmlspecialchars($data['ldap']['bind_user'] ?? '') ?>" placeholder="CN=...">
                </div>
                <div>
                    <label>Contraseña LDAP</label>
                    <input type="password" name="ldap_pass" placeholder="Dejar vacía para mantener">
                </div>
            </div>

            <h3>💾 Base de Datos</h3>
            <div class="form-grid">
                <div>
                    <label>Usuario BD</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($data['db']['user'] ?? '') ?>">
                </div>
                <div>
                    <label>Contraseña BD</label>
                    <input type="password" name="db_pass" placeholder="Dejar vacía para mantener">
                </div>
            </div>

            <h3>⚖️ Textos Legales (Reportes)</h3>
            <div class="form-grid">
                <div class="full-width">
                    <label>📝 Cláusula Acta Individual (Asignación/Devolución)</label>
                    <textarea name="texto_asign"><?= htmlspecialchars($txt_asign) ?></textarea>
                </div>
                <div class="full-width">
                    <label>📦 Cláusula Manifiesto Masivo (Listado de Equipos)</label>
                    <textarea name="texto_masiva" style="border-left: 3px solid #4f46e5;"><?= htmlspecialchars($txt_masiva) ?></textarea>
                </div>
                <div class="full-width">
                    <label>🗑️ Cláusula Acta de Baja</label>
                    <textarea name="texto_baja" style="border-left: 3px solid #dc3545;"><?= htmlspecialchars($txt_baja) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-save" onclick="return confirm('¿Confirma guardar los cambios?');">GUARDAR CONFIGURACIÓN</button>
        </div>
    </form>
</div>
</body>
</html>