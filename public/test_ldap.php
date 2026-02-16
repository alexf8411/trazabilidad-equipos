<?php
/**
 * test_ldap.php - Prueba de conexión LDAP
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test LDAP</title>";
echo "<style>body{font-family:Arial;max-width:600px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:5px;margin:10px 0;}";
echo "</style></head><body>";

echo "<h1>🧪 Prueba de Conexión LDAP</h1>";

// Cargar configuración
$configFile = '../core/config.json';
$config = json_decode(file_get_contents($configFile), true);
$ldapConf = $config['ldap'] ?? [];

$host = $ldapConf['host'] ?? 'ldaps://10.194.194.142';
$port = $ldapConf['port'] ?? 636;
$base_dn = $ldapConf['base_dn'] ?? 'DC=urosario,DC=loc';

echo "<div class='info'>";
echo "<strong>Configuración actual:</strong><br>";
echo "Host: $host<br>";
echo "Puerto: $port<br>";
echo "Base DN: $base_dn";
echo "</div>";

try {
    $ds = @ldap_connect($host, $port);
    
    if (!$ds) {
        throw new Exception("No se pudo crear la conexión LDAP");
    }
    
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
    
    echo "<div class='success'>";
    echo "<h3>✅ Conexión LDAP Exitosa</h3>";
    echo "<p>El servidor LDAP responde correctamente.</p>";
    echo "</div>";
    
    ldap_close($ds);
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error de Conexión LDAP</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<p><a href='configuracion.php'>← Volver a Configuración</a></p>";
echo "</body></html>";
?>
