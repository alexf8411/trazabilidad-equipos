<?php
/**
 * public/configuracion.php
 * V2 - BLINDADO
 * Módulo de Configuración con Protección de Sintaxis
 */
require_once '../core/session.php';

// 1. SEGURIDAD ESTRICTA
if (!isset($_SESSION['logged_in']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php");
    exit;
}

$msg = "";
$files = [
    'mail' => '../core/config_mail.php',
    'ldap' => '../core/config_ldap.php',
    'db'   => '../core/db.php',
    'txt'  => '../core/acta_legal.txt'
];

// 2. PROCESAR GUARDADO
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // --- FUNCIÓN DE SEGURIDAD ---
        // Escapa comillas simples (') para que no rompan el PHP
        function s($input) {
            return addslashes($input);
        }

        // A. ACTUALIZAR CORREO
        $content = file_get_contents($files['mail']);
        // Guardamos copia de seguridad en memoria por si el regex falla
        $backup = $content;
        
        $content = preg_replace("/define\('SMTP_USER',\s*'([^']*)'\);/", "define('SMTP_USER', '" . s($_POST['smtp_user']) . "');", $content);
        $content = preg_replace("/define\('SMTP_PASS',\s*'([^']*)'\);/", "define('SMTP_PASS', '" . s($_POST['smtp_pass']) . "');", $content);
        
        if($content !== null) file_put_contents($files['mail'], $content);

        // B. ACTUALIZAR LDAP
        $content = file_get_contents($files['ldap']);
        $content = preg_replace("/define\('LDAP_BIND_USER',\s*'([^']*)'\);/", "define('LDAP_BIND_USER', '" . s($_POST['ldap_user']) . "');", $content);
        $content = preg_replace("/define\('LDAP_BIND_PASS',\s*'([^']*)'\);/", "define('LDAP_BIND_PASS', '" . s($_POST['ldap_pass']) . "');", $content);
        
        if($content !== null) file_put_contents($files['ldap'], $content);

        // C. ACTUALIZAR DB
        $content = file_get_contents($files['db']);
        $content = preg_replace("/\\\$user\s*=\s*'([^']*)';/", "\$user = '" . s($_POST['db_user']) . "';", $content);
        $content = preg_replace("/\\\$pass\s*=\s*'([^']*)';/", "\$pass = '" . s($_POST['db_pass']) . "';", $content);
        
        if($content !== null) file_put_contents($files['db'], $content);

        // D. ACTUALIZAR TEXTO LEGAL (Este no es código PHP, no requiere addslashes)
        file_put_contents($files['txt'], $_POST['texto_legal']);

        $msg = "<div class='alert success'>✅ Configuración guardada y protegida.</div>";

    } catch (Exception $e) {
        $msg = "<div class='alert error'>❌ Error al escribir archivos.</div>";
    }
}

// 3. LEER VALORES (Usamos stripslashes para mostrar el dato limpio en el input)
$vals = [];

// Función para limpiar la visualización
function clean($str) {
    return htmlspecialchars(stripslashes($str));
}

// Mail
$c_mail = file_get_contents($files['mail']);
preg_match("/define\('SMTP_USER',\s*'([^']*)'\);/", $c_mail, $m); $vals['smtp_user'] = $m[1] ?? '';
preg_match("/define\('SMTP_PASS',\s*'([^']*)'\);/", $c_mail, $m); $vals['smtp_pass'] = $m[1] ?? '';

// LDAP
$c_ldap = file_get_contents($files['ldap']);
preg_match("/define\('LDAP_BIND_USER',\s*'([^']*)'\);/", $c_ldap, $m); $vals['ldap_user'] = $m[1] ?? '';
preg_match("/define\('LDAP_BIND_PASS',\s*'([^']*)'\);/", $c_ldap, $m); $vals['ldap_pass'] = $m[1] ?? '';

// DB
$c_db = file_get_contents($files['db']);
preg_match("/\\\$user\s*=\s*'([^']*)';/", $c_db, $m); $vals['db_user'] = $m[1] ?? '';
preg_match("/\\\$pass\s*=\s*'([^']*)';/", $c_db, $m); $vals['db_pass'] = $m[1] ?? '';

// Texto
$vals['texto'] = file_get_contents($files['txt']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema | URTRACK</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { color: #002D72; margin-top: 0; border-bottom: 2px solid #002D72; padding-bottom: 10px; }
        h3 { color: #666; font-size: 1.1rem; margin-top: 25px; margin-bottom: 10px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: 1 / -1; }
        
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; color: #333; }
        input[type="text"], input[type="password"], textarea { 
            width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-family: monospace;
        }
        textarea { resize: vertical; min-height: 80px; }
        
        .btn-save { background: #002D72; color: white; padding: 15px; border: none; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 20px; }
        .btn-save:hover { background: #001f52; }
        .btn-back { text-decoration: none; color: #666; display: inline-block; margin-bottom: 20px; }

        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .warning-box { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px; font-size: 0.85rem; border-radius: 4px; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-back">⬅ Volver al Dashboard</a>
    
    <?= $msg ?>

    <form method="POST">
        <div class="card">
            <h2>⚙️ Configuración del Sistema</h2>
            <p>Gestión de credenciales de servicios y textos legales.</p>

            <h3>📧 Servidor de Correo (SMTP)</h3>
            <div class="form-grid">
                <div>
                    <label>Usuario / Cuenta de Servicio</label>
                    <input type="text" name="smtp_user" value="<?= clean($vals['smtp_user']) ?>" required>
                </div>
                <div>
                    <label>Contraseña / App Password</label>
                    <input type="password" name="smtp_pass" value="<?= clean($vals['smtp_pass']) ?>" required>
                </div>
            </div>

            <h3>🔑 Conexión Directorio Activo (LDAP)</h3>
            <div class="warning-box">⚠️ Esta cuenta se usa para buscar nombres y departamentos de otros usuarios.</div>
            <div class="form-grid">
                <div>
                    <label>Bind User (Principal Name)</label>
                    <input type="text" name="ldap_user" value="<?= clean($vals['ldap_user']) ?>" required>
                </div>
                <div>
                    <label>Bind Password</label>
                    <input type="password" name="ldap_pass" value="<?= clean($vals['ldap_pass']) ?>" required>
                </div>
            </div>

            <h3>💾 Base de Datos Local</h3>
            <div class="warning-box" style="background:#f8d7da; color:#721c24; border-color:#f5c6cb;">
                🛑 <b>PELIGRO:</b> Si cambia estos datos por unos incorrectos, el sistema dejará de funcionar inmediatamente.
            </div>
            <div class="form-grid">
                <div>
                    <label>Usuario BD</label>
                    <input type="text" name="db_user" value="<?= clean($vals['db_user']) ?>" required>
                </div>
                <div>
                    <label>Contraseña BD</label>
                    <input type="password" name="db_pass" value="<?= clean($vals['db_pass']) ?>" required>
                </div>
            </div>

            <h3>⚖️ Texto Legal (Acta de Entrega)</h3>
            <div class="full-width">
                <label>Cláusula de Responsabilidad (Aparece en el PDF)</label>
                <textarea name="texto_legal" required><?= htmlspecialchars($vals['texto']) ?></textarea>
            </div>

            <button type="submit" class="btn-save" onclick="return confirm('¿Está seguro de guardar estos cambios? Si las credenciales son incorrectas, los servicios fallarán.');">GUARDAR CONFIGURACIÓN</button>
        </div>
    </form>
</div>

</body>
</html>