<?php
/**
 * core/config_ldap.php
 * Conector LDAP - Lee credenciales desde config.json
 */
$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$ldap = $config['ldap'] ?? [];

define('LDAP_HOST', $ldap['host'] ?? 'ldaps://10.194.194.142');
define('LDAP_PORT', $ldap['port'] ?? 636);
define('LDAP_BASE_DN', $ldap['base_dn'] ?? 'DC=urosario,DC=loc');
define('LDAP_BIND_USER', $ldap['bind_user'] ?? '');
define('LDAP_BIND_PASS', $ldap['bind_pass'] ?? '');
?>