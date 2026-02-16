<?php
/**
 * auth_bind.php - Prueba de Bind con credenciales
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Bind</title>";
echo "<style>body{font-family:Arial;max-width:600px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".warning{background:#fff3cd;color:#856404;padding:15px;border-radius:5px;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🔑 Prueba de Bind LDAP</h1>";

// Cargar configuración
$configFile = '../core/config.json';
$config = json_decode(file_get_contents($configFile), true);
$ldapConf = $config['ldap'] ?? [];

$host = $ldapConf['host'] ?? 'ldaps://10.194.194.142';
$port = $ldapConf['port'] ?? 636;

require_once '../core/config_crypto.php';
$bind_user = !empty($ldapConf['bind_user']) ? ConfigCrypto::decrypt($ldapConf['bind_user']) : '';
$bind_pass = !empty($ldapConf['bind_pass']) ? ConfigCrypto::decrypt($ldapConf['bind_pass']) : '';

if (empty($bind_user) || empty($bind_pass)) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Sin Credenciales de Bind</h3>";
    echo "<p>No hay usuario y contraseña de bind configurados.</p>";
    echo "<p>Esto es <strong>NORMAL</strong> si usas bind directo (sin cuenta de servicio).</p>";
    echo "</div>";
} else {
    try {
        $ds = @ldap_connect($host, $port);
        ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
        
        $bind = @ldap_bind($ds, $bind_user, $bind_pass);
        
        if ($bind) {
            echo "<div class='success'>";
            echo "<h3>✅ Bind Exitoso</h3>";
            echo "<p>La cuenta de servicio puede conectarse correctamente.</p>";
            echo "</div>";
        } else {
            $error = "";
            ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $error);
            throw new Exception("Bind falló: " . $error);
        }
        
        ldap_close($ds);
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Error de Bind</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
}

echo "<p><a href='configuracion.php'>← Volver a Configuración</a></p>";
echo "</body></html>";
?>
