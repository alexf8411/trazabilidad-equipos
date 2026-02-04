<?php
/**
 * core/config_ldap.php
 * Configuración exclusiva para el Buscador de Usuarios
 * NO afecta al login actual.
 */

define('LDAP_HOST', 'ldaps://10.194.194.142');
define('LDAP_PORT', 636);
define('LDAP_BASE_DN', 'DC=urosario,DC=loc');

// CUENTA DE SERVICIO (Service Account)
// NO uses una cuenta Administradora. Usa una cuenta normal de dominio.
// Esta cuenta es necesaria porque el script necesita "permiso" para entrar a buscar.
define('LDAP_BIND_USER', 'recursos.tecnologicos@lab.urosario.edu.co'); // <--- Pon aquí tu usuario o el de servicio
define('LDAP_BIND_PASS', 'bKeDYq3uAuCdZJU6GKEL');                  // <--- Pon aquí la contraseña
?>