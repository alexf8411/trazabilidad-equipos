<?php
/**
 * ACTIVIDAD 2: SISTEMA DE AUTENTICACIÓN (BIND) CON MANEJO DE EXCEPCIONES
 * Objetivo: Validar credenciales contra el AD institucional de forma segura.
 */

// 1. Definición de parámetros institucionales
$ldap_config = [
    'host'   => 'ldaps://10.194.194.142',
    'port'   => 636,
    'suffix' => '@lab.urosario.edu.co' // Confirmar si es este o @urosario.edu.co
];

// 2. Datos capturados (Simulación de formulario)
$user_input = "guillermo.fonseca"; 
$pass_input = "PASSWORD_PLACEHOLDER"; // Cambiar para pruebas

try {
    // 3. Fase de Conexión y Configuración
    $ldap_conn = ldap_connect($ldap_config['host'], $ldap_config['port']);
    
    if (!$ldap_conn) {
        throw new Exception("Error crítico: No se pudo inicializar el recurso LDAP.");
    }

    // Configuramos el comportamiento del cliente LDAP
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, 5); // Timeout de 5s para evitar bloqueos

    // 4. Fase de Autenticación (Bind)
    $dn = $user_input . $ldap_config['suffix'];
    
    // El operador @ suprime el Warning nativo de PHP para que el Catch tome el control
    $is_authenticated = @ldap_bind($ldap_conn, $dn, $pass_input);

    if ($is_authenticated) {
        echo "✅ [SUCCESS] Autenticación validada. Bind exitoso para: " . $dn . "\n";
        // Aquí se procedería a la Actividad 3 (Autorización/Sesión)
    } else {
        // Obtenemos el código de error específico del Directorio Activo
        $errno = ldap_errno($ldap_conn);
        $error = ldap_error($ldap_conn);
        
        // El error 49 suele ser "Invalid Credentials"
        throw new Exception("Fallo de autenticación en el Directorio Activo. Código: $errno. Detalle: $error");
    }

} catch (Exception $e) {
    // 5. Gestión de Errores y Seguridad
    // En producción, aquí loguearías el error en un archivo privado y mostrarías algo genérico
    echo "❌ [ERROR DE SEGURIDAD] " . $e->getMessage() . "\n";

} finally {
    // 6. Cierre Limpio del Socket
    if (isset($ldap_conn)) {
        ldap_close($ldap_conn);
    }
}
