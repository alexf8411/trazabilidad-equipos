<?php
/**
 * core/config_mail.php
 * Conector SMTP - Soporte para Contraseñas Cifradas
 */

// 1. CARGAR EL DESCIFRADOR
require_once __DIR__ . '/config_crypto.php';

$configFile = __DIR__ . '/config.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

// 2. CONFIGURACIONES BÁSICAS
define('SMTP_HOST', $config['mail']['smtp_host'] ?? 'smtp.office365.com');
define('SMTP_PORT', $config['mail']['smtp_port'] ?? 587);
define('SMTP_FROM_NAME', 'URTRACK - Activos TI');
define('SMTP_USER', $config['mail']['smtp_user'] ?? '');

// 3. LA CLAVE: DESCIFRAR EL PASSWORD ANTES DE DEFINIRLO
$pass_cifrada = $config['mail']['smtp_pass'] ?? '';
define('SMTP_PASS', ConfigCrypto::decrypt($pass_cifrada)); 
?>