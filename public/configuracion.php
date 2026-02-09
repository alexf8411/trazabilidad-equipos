<?php
/**
 * public/configuracion.php
 * V2.1 - Multi-Actas
 * Gestión de credenciales y textos legales (Asignación y Baja)
 */
require_once '../core/session.php';

// 1. SEGURIDAD ESTRICTA
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$files = [
    'mail'      => '../core/config_mail.php',
    'ldap'      => '../core/config_ldap.php',
    'db'        => '../core/db.php',
    'txt_asign' => '../core/acta_legal.txt', // Texto original
    'txt_baja'  => '../core/acta_baja.txt'   // NUEVO: Texto de baja
];

// Asegurar que existan los archivos de texto
if(!file_exists($files['txt_asign'])) file_put_contents($files['txt_asign'], "Texto por defecto asignación...");
if(!file_exists($files['txt_baja']))  file_put_contents($files['txt_baja'], "CERTIFICACIÓN: Por medio del presente documento se certifica que los equipos listados han sido retirados del inventario activo por obsolescencia técnica o falla irreparable.");

// 2. PROCESAR GUARDADO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        function s($input) { return addslashes($input); }

        // A. MAIL
        $content = file_get_contents($files['mail']);
        $content = preg_replace("/define\('SMTP_USER',\s*'([^']*)'\);/", "define('SMTP_USER', '" . s($_POST['smtp_user']) . "');", $content);
        $content = preg_replace("/define\('SMTP_PASS',\s*'([^']*)'\);/", "define('SMTP_PASS', '" . s($_POST['smtp_pass']) . "');", $content);
        if($content) file_put_contents($files['mail'], $content);

        // B. LDAP
        $content = file_get_contents($files['ldap']);
        $content = preg_replace("/define\('LDAP_BIND_USER',\s*'([^']*)'\);/", "define('LDAP_BIND_USER', '" . s($_POST['ldap_user']) . "');", $content);
        $content = preg_replace("/define\('LDAP_BIND_PASS',\s*'([^']*)'\);/", "define('LDAP_BIND_PASS', '" . s($_POST['ldap_pass']) . "');", $content);
        if($content) file_put_contents($files['ldap'], $content);

        // C. DB
        $content = file_get_contents($files['db']);
        $content = preg_replace("/\\\$user\s*=\s*'([^']*)';/", "\$user = '" . s($_POST['db_user']) . "';", $content);
        $content = preg_replace("/\\\$pass\s*=\s*'([^']*)';/", "\$pass = '" . s($_POST['db_pass']) . "';", $content);
        if($content) file_put_contents($files['db'], $content);

        // D. TEXTOS LEGALES
        file_put_contents($files['txt_asign'], $_POST['texto_asign']);
        file_put_contents($files['txt_baja'],  $_POST['texto_baja']);

        $msg = "<div class='alert success'>✅ Configuración guardada correctamente.</div>";

    } catch (Exception $e) {
        $msg = "<div class='alert error'>❌ Error al escribir archivos.</div>";
    }
}

// 3. LEER VALORES
function clean($str) { return htmlspecialchars(stripslashes($str)); }
$vals = [];

// Mail
$c = file_get_contents($files['mail']);
preg_match("/define\('SMTP_USER',\s*'([^']*)'\);/", $c, $m); $vals['smtp_user'] = $m[1]??'';
preg_match("/define\('SMTP_PASS',\s*'([^']*)'\);/", $c, $m); $vals['smtp_pass'] = $m[1]??'';

// LDAP
$c = file_get_contents($files['ldap']);
preg_match("/define\('LDAP_BIND_USER',\s*'([^']*)'\);/", $c, $m); $vals['ldap_user'] = $m[1]??'';
preg_match("/define\('LDAP_BIND_PASS',\s*'([^']*)'\);/", $c, $m); $vals['ldap_pass'] = $m[1]??'';

// DB
$c = file_get_contents($files['db']);
preg_match("/\\\$user\s*=\s*'([^']*)';/", $c, $m); $vals['db_user'] = $m[1]??'';
preg_match("/\\\$pass\s*=\s*'([^']*)';/", $c, $m); $vals['db_pass'] = $m[1]??'';

// Textos
$vals['txt_asign'] = file_get_contents($files['txt_asign']);
$vals['txt_baja']  = file_get_contents($files['txt_baja']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración | URTRACK</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { color: #002D72; margin-top: 0; border-bottom: 2px solid #002D72; padding-bottom: 10px; }
        h3 { color: #666; font-size: 1.1rem; margin-top: 25px; margin-bottom: 10px; border-left: 4px solid #ffc107; padding-left: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: 1 / -1; }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; color: #333; }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        textarea { resize: vertical; min-height: 100px; font-family: sans-serif; }
        .btn-save { background: #002D72; color: white; padding: 15px; border: none; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 20px; }
        .btn-save:hover { background: #001f52; }
        .btn-back { text-decoration: none; color: #666; display: inline-block; margin-bottom: 20px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
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
                <div><label>Usuario</label><input type="text" name="smtp_user" value="<?= clean($vals['smtp_user']) ?>"></div>
                <div><label>Password</label><input type="password" name="smtp_pass" value="<?= clean($vals['smtp_pass']) ?>"></div>
            </div>

            <h3>🔑 Credenciales LDAP (Bind User)</h3>
            <div class="form-grid">
                <div><label>Usuario</label><input type="text" name="ldap_user" value="<?= clean($vals['ldap_user']) ?>"></div>
                <div><label>Password</label><input type="password" name="ldap_pass" value="<?= clean($vals['ldap_pass']) ?>"></div>
            </div>

            <h3>💾 Base de Datos (MySQL)</h3>
            <div class="form-grid">
                <div><label>Usuario BD</label><input type="text" name="db_user" value="<?= clean($vals['db_user']) ?>"></div>
                <div><label>Password BD</label><input type="password" name="db_pass" value="<?= clean($vals['db_pass']) ?>"></div>
            </div>

            <h3>⚖️ Textos Legales (PDFs)</h3>
            <div class="form-grid">
                <div class="full-width">
                    <label>📝 Cláusula para Acta de ASIGNACIÓN / DEVOLUCIÓN</label>
                    <textarea name="texto_asign"><?= htmlspecialchars($vals['txt_asign']) ?></textarea>
                </div>
                <div class="full-width">
                    <label>🗑️ Cláusula para Acta de BAJA DE ACTIVOS</label>
                    <textarea name="texto_baja" style="border-color: #dc3545;"><?= htmlspecialchars($vals['txt_baja']) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-save" onclick="return confirm('¿Guardar cambios?');">GUARDAR CONFIGURACIÓN</button>
        </div>
    </form>
</div>
</body>
</html>