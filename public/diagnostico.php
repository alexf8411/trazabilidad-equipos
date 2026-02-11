<?php
/**
 * public/diagnostico.php
 * Script de Pruebas de Conectividad (LDAP, SMTP, DB)
 * Muestra errores reales ocultos por la interfaz de usuario.
 */

// 1. Cargar Configuración
$configFile = '../core/config.json';
echo "<h1>🕵️ Diagnóstico de Conectividad URTRACK</h1>";

if (!file_exists($configFile)) {
    die("<h3 style='color:red'>❌ Error Crítico: No existe core/config.json</h3>");
}

$config = json_decode(file_get_contents($configFile), true);
echo "<div style='background:#efefef; padding:10px; border-radius:5px;'>";
echo "<strong>📂 Configuración Cargada:</strong><br>";
echo "DB Host: " . ($config['db']['host'] ?? 'No definido') . "<br>";
echo "LDAP Host: " . ($config['ldap']['host'] ?? 'No definido') . "<br>";
echo "LDAP User: " . ($config['ldap']['bind_user'] ? '✅ Definido' : '❌ VACÍO') . "<br>";
echo "SMTP User: " . ($config['mail']['smtp_user'] ? '✅ Definido' : '❌ VACÍO') . "<br>";
echo "</div>";

echo "<hr>";

// ---------------------------------------------------------
// 2. PRUEBA LDAP (Directorio Activo)
// ---------------------------------------------------------
echo "<h3>1. Prueba LDAP (Directorio Activo)</h3>";

$ldap_host = $config['ldap']['host'];
$ldap_port = $config['ldap']['port'];
$ldap_user = $config['ldap']['bind_user'];
$ldap_pass = $config['ldap']['bind_pass'];

// Forzar opciones para debug y certificados auto-firmados
putenv('LDAPTLS_REQCERT=NEVER'); 

echo "Intentando conectar a <code>$ldap_host:$ldap_port</code>...<br>";

$conn = @ldap_connect($ldap_host, $ldap_port);

if ($conn) {
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5); 

    // Intentar Bind (Login)
    $bind = @ldap_bind($conn, $ldap_user, $ldap_pass);

    if ($bind) {
        echo "<h4 style='color:green'>✅ ÉXITO: Conexión y Autenticación LDAP correctas.</h4>";
        echo "El usuario de servicio funciona.";
    } else {
        echo "<h4 style='color:red'>❌ FALLÓ EL LOGIN (BIND)</h4>";
        echo "<strong>Error LDAP:</strong> " . ldap_error($conn) . "<br>";
        echo "<strong>Posibles causas:</strong><br>";
        echo "1. La contraseña es incorrecta.<br>";
        echo "2. El formato del usuario no es un DN o UPN válido (ej: usuario@dominio.com o CN=... DC=...).<br>";
    }
    ldap_close($conn);
} else {
    echo "<h4 style='color:red'>❌ FALLÓ LA CONEXIÓN AL SERVIDOR</h4>";
    echo "No se pudo alcanzar la IP $ldap_host. Verifique Firewall o VPN.";
}

echo "<hr>";

// ---------------------------------------------------------
// 3. PRUEBA SMTP (Correo)
// ---------------------------------------------------------
echo "<h3>2. Prueba SMTP (Office 365)</h3>";
$smtp_host = 'smtp.office365.com';
$smtp_port = 587;
$smtp_user = $config['mail']['smtp_user'];
$smtp_pass = $config['mail']['smtp_pass'];

echo "Conectando a $smtp_host:$smtp_port...<br>";

$socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 5);

if (!$socket) {
    echo "<h4 style='color:red'>❌ Error de Red SMTP: $errstr ($errno)</h4>";
} else {
    echo "✅ Puerto 587 abierto. Iniciando handshake...<br>";
    
    // Leer bienvenida
    $response = fgets($socket, 515);
    
    // Enviar HELO
    fputs($socket, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
    $response = fgets($socket, 515);

    // Iniciar TLS
    fputs($socket, "STARTTLS\r\n");
    $response = fgets($socket, 515);
    
    if (strpos($response, '220') === false) {
         echo "<span style='color:orange'>⚠️ El servidor no respondió OK a STARTTLS. Respuesta: $response</span><br>";
    }

    // Encriptar canal
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
    
    // Autenticar (LOGIN plain)
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);

    fputs($socket, base64_encode($smtp_user) . "\r\n");
    fgets($socket, 515);

    fputs($socket, base64_encode($smtp_pass) . "\r\n");
    $auth_result = fgets($socket, 515);

    if (strpos($auth_result, '235') !== false) {
        echo "<h4 style='color:green'>✅ ÉXITO: Credenciales SMTP válidas.</h4>";
    } else {
        echo "<h4 style='color:red'>❌ FALLÓ AUTENTICACIÓN SMTP</h4>";
        echo "<strong>Respuesta Servidor:</strong> $auth_result<br>";
        echo "<strong>Solución:</strong> Si usas Office 365, asegúrate de que 'Authenticated SMTP' esté habilitado en el usuario y estés usando una App Password si tienes MFA.";
    }
    fclose($socket);
}
?>