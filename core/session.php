<?php
/**
 * core/session.php
 * Módulo de Gestión de Sesiones Seguras
 * * Este script configura e inicia la sesión PHP aplicando las mejores prácticas
 * de seguridad (OWASP) para prevenir robo de identidad y ataques XSS/CSRF.
 */

// 1. Configuración de Seguridad de Cookies
// Definimos los parámetros estrictos antes de iniciar la sesión
$session_params = [
    'lifetime' => 0,            // 0 = La sesión muere al cerrar el navegador
    'path'     => '/',          // Disponible en todo el sitio
    'domain'   => '',           // Dominio actual (automático)
    'secure'   => true,         // IMPORTANTE: Solo envía la cookie si hay HTTPS
    'httponly' => true,         // IMPORTANTE: JavaScript no puede leer la cookie (Anti-XSS)
    'samesite' => 'Strict'      // La cookie solo viaja en peticiones del mismo origen (Anti-CSRF)
];

// 2. Nombre Personalizado de la Sesión
// Evitamos el nombre por defecto 'PHPSESSID' para no dar pistas al atacante
session_name('TRAZABILIDAD_ID');

// 3. Inicio de la Sesión con los parámetros
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => $session_params['lifetime'],
        'cookie_path'     => $session_params['path'],
        'cookie_secure'   => $session_params['secure'],
        'cookie_httponly' => $session_params['httponly'],
        'cookie_samesite' => $session_params['samesite'],
        'use_strict_mode' => 1  // Evita que un atacante fije el ID de sesión
    ]);
}

/**
 * Función auxiliar para login exitoso
 * Regenera el ID de sesión para prevenir "Session Fixation"
 */
function regenerar_sesion_segura() {
    session_regenerate_id(true); // true = borrar la sesión antigua
}
?>