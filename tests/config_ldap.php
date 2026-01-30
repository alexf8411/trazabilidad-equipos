<?php
// /var/www/html/trazabilidad/config_ldap.php

/**
 * CONFIGURACIÓN CENTRAL DE DIRECTORIO ACTIVO
 * Datos extraídos del Controlador de Dominio: SRVCBPBAD.urosario.loc
 */

define('LDAP_HOST', 'ldaps://10.194.194.142'); // IP Real detectada + Protocolo Seguro
define('LDAP_PORT', 636);                      // Puerto verificado en estado Listen
define('LDAP_DN', 'DC=urosario,DC=loc');       // Base DN extraída

// DOMINIO PARA LOGIN
// Esto se usa para que el usuario solo escriba "guillermo.fonseca" y el sistema agregue el resto
define('LDAP_DOMAIN_PREFIX', 'urosario\\');    // Formato NetBIOS (urosario\usuario)
// O alternativamente formato UPN: define('LDAP_DOMAIN_SUFFIX', '@lab.urosario.edu.co');

/**
 * AJUSTE DE SEGURIDAD SSL
 * Como estamos en pruebas y usamos una IP (no un nombre DNS con certificado válido),
 * debemos decirle a PHP que confíe en el certificado aunque sea auto-firmado.
 * EN PRODUCCIÓN ESTO DEBE CAMBIARSE.
 */
putenv('LDAPTLS_REQCERT=NEVER');
?>
