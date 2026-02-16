<?php
// public/test_mail.php
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "<h2>🕵️ Diagnóstico SMTP - URTRACK</h2>";
echo "<b>Host:</b> " . SMTP_HOST . "<br>";
echo "<b>Puerto:</b> " . SMTP_PORT . "<br>";
echo "<b>Usuario:</b> " . SMTP_USER . "<br>";
echo "<b>Longitud de Password Descifrado:</b> " . strlen(SMTP_PASS) . " caracteres<br><hr>";

$mail = new PHPMailer(true);
try {
    // Activar el log detallado de conexión
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
    $mail->Debugoutput = 'html';

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_USER, 'Diagnóstico URTRACK');
    $mail->addAddress(SMTP_USER); // Se enviará un correo a sí mismo

    $mail->isHTML(true);
    $mail->Subject = 'Prueba de Conexión URTRACK';
    $mail->Body    = 'Si ves esto, el SMTP funciona perfectamente.';

    $mail->send();
    echo "<br><br><h3 style='color:green;'>✅ CORREO ENVIADO CON ÉXITO</h3>";
} catch (Exception $e) {
    echo "<br><br><h3 style='color:red;'>❌ FALLÓ EL ENVÍO</h3>";
    echo "<b>Error exacto de PHPMailer:</b> " . $mail->ErrorInfo;
}
?>