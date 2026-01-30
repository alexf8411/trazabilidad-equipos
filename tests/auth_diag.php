<?php
/**
 * ACTIVIDAD 3: MANEJO AVANZADO DE ERRORES LDAP (DIAGNÓSTICO)
 * Objetivo: Traducir códigos hexadecimales de AD en mensajes humanos.
 */

// Configuración (Igual que en Actividad 2)
$conf = [
    'host'   => 'ldaps://10.194.194.142',
    'port'   => 636,
    'suffix' => '@lab.urosario.edu.co' 
];

// --- SIMULACIÓN DE ENTRADA ---
// Cambia esto para probar distintos escenarios:
$user_input = "guillermo.fonseca";
$pass_input = "PASSWORD_PLACEHOLDER"; // Prueba con una clave errónea intencionalmente

echo "--- PRUEBA DE DIAGNÓSTICO DE IDENTIDAD ---\n";
echo "Intentando autenticar a: $user_input\n\n";

$ds = ldap_connect($conf['host'], $conf['port']);
ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

if (!$ds) {
    die("❌ Error Crítico: No hay conexión con el servidor.");
}

// Intentamos el Bind silenciando errores nativos (@)
$user_dn = $user_input . $conf['suffix'];
$bind = @ldap_bind($ds, $user_dn, $pass_input);

if ($bind) {
    echo "✅ ÉXITO: Credenciales válidas. Acceso concedido.\n";
} else {
    // AQUÍ OCURRE LA MAGIA DE LA ACTIVIDAD 3
    
    // 1. Capturamos el error extendido del servidor
    $extended_error = "";
    ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
    
    // 2. Si no hay mensaje extendido, usamos el estándar
    if (empty($extended_error)) {
        $extended_error = ldap_error($ds);
    }

    // 3. Procesamos el error para hacerlo legible
    $human_message = analyze_ad_error($extended_error);

    echo "⛔ ACCESO DENEGADO\n";
    echo "🔍 Diagnóstico Técnico: $extended_error\n";
    echo "📢 Mensaje al Usuario:  $human_message\n";
}

ldap_close($ds);

// --- FUNCIÓN DE ANÁLISIS DE ERRORES ---
function analyze_ad_error($diagnostic_string) {
    // Buscamos el patrón "data XXX" donde XXX es un número hexadecimal
    if (preg_match('/data ([0-9a-f]{3})/i', $diagnostic_string, $matches)) {
        $code = $matches[1];
        
        switch ($code) {
            case '525': return "El usuario no existe en el directorio.";
            case '52e': return "Credenciales inválidas (Contraseña o usuario incorrecto).";
            case '530': return "Restricción de horario: No puedes iniciar sesión ahora.";
            case '532': return "Tu contraseña ha expirado. Debes cambiarla.";
            case '533': return "Esta cuenta ha sido deshabilitada administrativamente.";
            case '701': return "La cuenta ha expirado.";
            case '773': return "Debes cambiar tu contraseña antes de ingresar.";
            case '775': return "¡CUENTA BLOQUEADA! Demasiados intentos fallidos.";
            default:    return "Error de cuenta desconocido (Código: $code).";
        }
    }
    
    // Si no encontramos código 'data', devolvemos error genérico
    return "Error de conexión o credenciales (Sin código específico).";
}
?>
