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
// Modo 1: DSN completa (para clústeres avanzados)
if (isset($dbConf['dsn']) && !empty($dbConf['dsn'])) {
    $dsn = $dbConf['dsn'];
    
// Modo 2: Host + Nombre (tradicional o clúster con VIP)
} else {
    $host = $dbConf['host'] ?? '127.0.0.1';
    $db   = $dbConf['name'] ?? 'trazabilidad_local';
    $port = $dbConf['port'] ?? 3306; // Nuevo: puerto configurable
    $charset = 'utf8mb4';
    
    // Soportar múltiples hosts separados por coma (failover)
    if (strpos($host, ',') !== false) {
        // Clúster con múltiples nodos: mysql:host=node1,node2,node3
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    } else {
        // Host único
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    }
}

// Descifrar credenciales (prefijo db_ para evitar colisión)
$db_user = $dbConf['user'] ?? 'appadmdb';
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