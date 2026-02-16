<?php
/**
 * core/db.php
 * Conector BD - Lee credenciales desde config.json
 */
$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$dbConf = $config['db'] ?? [];

$host = $dbConf['host'] ?? '127.0.0.1';
$db   = $dbConf['name'] ?? 'trazabilidad_local';
$user = $dbConf['user'] ?? 'appadmdb';
$pass = $dbConf['pass'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Error genérico para no exponer datos
     die("Error de conexión a la base de datos. Verifique configuración.");
}
?>