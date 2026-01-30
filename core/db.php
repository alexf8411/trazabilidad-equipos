<?php
/**
 * core/db.php
 * Conexión a la base de datos 'trazabilidad_local'
 * Usuario: appadmdb
 */

$host = '127.0.0.1';
$db   = 'trazabilidad_local';
$user = 'appadmdb';
$pass = 'DBAPPFEo5POJeGW!'; // La contraseña de appadmdb
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
     //error_log("Error de conexión BD: " . $e->getMessage());
     //die("Error de conexión al sistema de trazabilidad.");
    
     // Esto nos dirá el código de error (ej: 1045, 1049, etc)
     echo "Cod. Error: " . $e->getCode() . "<br>";
     echo "Mensaje: " . $e->getMessage();
     exit;

}
?>