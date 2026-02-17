<?php
/**
 * core/db.php
 * Conector BD - Lee credenciales desde config.json
 * Versión 2.0 - Soporte para Clústeres y Cifrado
 * 
 * NOTA: Variables de credenciales usan prefijo db_ para evitar
 * colisión con $user/$pass de login.php y otros módulos.
 */

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$dbConf = $config['db'] ?? [];

// Cargar módulo de cifrado
require_once __DIR__ . '/config_crypto.php';

// --- SOPORTE PARA CLÚSTERES ---
// --- CONEXIÓN PARA MICROSOFT SQL SERVER ---
$host = $dbConf['host'] ?? '10.194.194.190';
$db   = $dbConf['name'] ?? 'Trazabilidad_Equipos';
$port = $dbConf['port'] ?? 1433; // Puerto por defecto MS SQL

// Soportar el formato Host,Puerto si viene así desde la configuración
if (strpos($host, ',') === false) {
    $serverString = "$host,$port";
} else {
    $serverString = $host; // Si el usuario ya puso la coma en el panel
}

// Formato DSN para el driver pdo_sqlsrv de Microsoft
// TrustServerCertificate=true es vital para redes internas sin SSL comercial en la BD
$dsn = "sqlsrv:Server=$serverString;Database=$db;TrustServerCertificate=true";

// Descifrar credenciales (prefijo db_ para evitar colisión)
$db_user = $dbConf['user'] ?? 'Usr_Trazabilidad_Equipos';
$db_pass = !empty($dbConf['pass']) ? ConfigCrypto::decrypt($dbConf['pass']) : '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Error genérico para no exponer datos
    error_log("Error de conexión DB: " . $e->getMessage());
    die("Error de conexión a la base de datos. Verifique configuración en config.json");
}
?>