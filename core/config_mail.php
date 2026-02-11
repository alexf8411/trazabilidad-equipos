<?php
/**
 * core/config_mail.php
 * Conector SMTP - Lee credenciales desde config.json
 */
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_FROM_NAME', 'URTRACK - Activos TI');

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

define('SMTP_USER', $config['mail']['smtp_user'] ?? '');
define('SMTP_PASS', $config['mail']['smtp_pass'] ?? '');
?>