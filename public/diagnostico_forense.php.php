<?php
/**
 * public/diagnostico_forense.php
 * Script de depuración paso a paso para SMTP Office 365
 */
header('Content-Type: text/html; charset=utf-8');
echo "<h2>🕵️ Diagnóstico Forense SMTP - URTRACK</h2>";

$configFile = '../core/config.json';
if (!file_exists($configFile)) die("No existe config.json");
$config = json_decode(file_get_contents($configFile), true);

$smtp_user = $config['mail']['smtp_user'];
$smtp_pass = $config['mail']['smtp_pass'];

// 1. ANÁLISIS DE DATOS
echo "<h3>1. Análisis de Variables</h3>";
echo "Usuario: [" . htmlspecialchars($smtp_user) . "] <br>";
echo "Longitud Usuario: " . strlen($smtp_user) . " caracteres (Verifica que no haya espacios extra)<br>";
echo "Contraseña cargada: " . (empty($smtp_pass) ? "NO ❌" : "SÍ ✅") . "<br>";

// 2. CONEXIÓN
echo "<h3>2. Intento de Conexión</h3>";
$host = 'smtp.office365.com';
$port = 587;

$socket = fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    die("❌ Error de conexión: $errstr ($errno)");
}
echo "✅ Conectado a $host:$port<br>";
echo "S: " . fgets($socket, 515) . "<br>"; // Bienvenida

// HELO
fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
echo "C: EHLO " . $_SERVER['SERVER_NAME'] . "<br>";
echo "S: " . fread_response($socket);

// STARTTLS
fputs($socket, "STARTTLS\r\n");
echo "C: STARTTLS<br>";
$resp = fgets($socket, 515);
echo "S: " . $resp . "<br>";

if (strpos($resp, '220') === false) {
    die("❌ El servidor no aceptó STARTTLS.");
}

// ENCRIPTACIÓN
if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
    die("❌ Error al establecer encriptación TLS.");
}
echo "✅ Encriptación TLS establecida.<br>";

// EHLO (De nuevo, requerido tras TLS)
fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
echo "C: EHLO (Post-TLS)<br>";
echo "S: " . fread_response($socket);

// AUTH LOGIN
fputs($socket, "AUTH LOGIN\r\n");
echo "C: AUTH LOGIN<br>";
$resp = fgets($socket, 515);
echo "S: " . $resp . "<br>";

if (strpos($resp, '334') === false) {
    die("❌ El servidor no aceptó iniciar AUTH LOGIN. Posiblemente SMTP Auth deshabilitado en el tenant.");
}

// ENVÍO DE USUARIO
echo "C: [Usuario en Base64] (" . base64_encode($smtp_user) . ")<br>";
fputs($socket, base64_encode($smtp_user) . "\r\n");
$resp = fgets($socket, 515);
echo "S: " . $resp . "<br>"; // <--- AQUÍ ESTÁ LA CLAVE

if (strpos($resp, '334') === false) {
    echo "<h1 style='color:red'>🛑 DETENIDO AQUÍ</h1>";
    echo "El servidor rechazó el USUARIO. No tiene sentido enviar la contraseña.<br>";
    echo "Código esperado: 334 (Password:). Código recibido: $resp";
    fclose($socket);
    exit;
}

// ENVÍO DE PASSWORD
echo "C: [Password en Base64]<br>";
fputs($socket, base64_encode($smtp_pass) . "\r\n");
$resp = fgets($socket, 515);
echo "S: " . $resp . "<br>";

if (strpos($resp, '235') !== false) {
    echo "<h2 style='color:green'>🎉 ÉXITO TOTAL. CREDENCIALES OK.</h2>";
} else {
    echo "<h2 style='color:red'>❌ FALLÓ EL PASSWORD.</h2>";
}

fclose($socket);

// Función auxiliar para leer respuestas multilínea
function fread_response($socket) {
    $response = "";
    while($str = fgets($socket, 515)) {
        $response .= $str;
        if(substr($str, 3, 1) == " ") { break; }
    }
    return $response;
}
?>