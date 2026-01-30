<?php
/**
 * core/db.php
 * Conexión a la base de datos 'trazabilidad_local'
 * Usuario: appadmdb
 */

$host = 'localhost';
$db   = 'trazabilidad_local';
$user = 'appadmdb';
$pass = 'AQUI_TU_CONTRASEÑA_REAL'; // La contraseña de appadmdb
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
     // En producción es mejor no mostrar el error detallado al usuario final
     error_log("Error de conexión BD: " . $e->getMessage());
     die("Error de conexión al sistema de trazabilidad.");
}
?>