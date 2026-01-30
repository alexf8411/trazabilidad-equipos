<?php
/**
 * Clase de Conexión a Base de Datos
 * Utiliza el patrón Singleton implícito y PDO para seguridad.
 */
class Conexion {
    // Credenciales (En fase producción esto debería ir en variables de entorno)
    private static $host = 'localhost';
    private static $db   = 'trazabilidad_local';
    private static $user = 'app_trazabilidad';
    private static $pass = 'Tr4zabilidad_UR_2026'; // La contraseña que definimos ayer
    private static $charset = 'utf8mb4';

    /**
     * Método estático para obtener la conexión
     * Retorna: Objeto PDO o termina la ejecución con error controlado.
     */
    public static function conectar() {
        try {
            // Definimos el DSN (Data Source Name). Es la cadena que dice "Qué y Dónde"
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db . ";charset=" . self::$charset;
            
            // Opciones de configuración del motor PDO
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones reales ante errores SQL
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos (['nombre' => 'PC01'])
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa sentencias preparadas nativas (Mayor seguridad)
            ];

            // Intentamos crear la instancia PDO
            $pdo = new PDO($dsn, self::$user, self::$pass, $opciones);
            
            return $pdo;

        } catch (PDOException $e) {
            // ERROR CONTROLADO:
            // Si falla, no mostramos el error real al usuario (podría revelar contraseñas).
            // Guardamos el error real en el log del servidor y mostramos un mensaje genérico.
            error_log("Error de conexión BD: " . $e->getMessage());
            die("Error crítico de sistema: No se pudo conectar a la base de datos.");
        }
    }
}
?>
