<?php
/**
 * core/config_mail.php
 * Configuración de credenciales SMTP.
 * Estos valores son actualizados dinámicamente desde public/configuracion.php
 * <?php
* core/config_mail.php
*define('SMTP_HOST', 'smtp.office365.com');
*define('SMTP_PORT', 587);
*define('SMTP_USER', 'recursos.tecnologicos@lab.urosario.edu.co'); // Poner el correo real aquí
*define('SMTP_PASS', 'vpvdpwyqzmjxncgf'); // Contraseña de la cuenta
*define('SMTP_FROM_NAME', 'URTRACK - Activos TI');
 
 */
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);

// Valores iniciales vacíos (se llenan vía interfaz web)
define('SMTP_USER', ''); 
define('SMTP_PASS', ''); 

define('SMTP_FROM_NAME', 'URTRACK - Activos TI');
?>



