<?php
/**
 * core/auth.php
 * Motor de Lógica de Autenticación LDAP
 */

function autenticar_usuario($usuario, $password) {
    // --- 1. CONFIGURACIÓN (Basada en tus pruebas exitosas) ---
    $host = "ldaps://10.194.194.142";
    $port = 636;
    $base_dn = "DC=urosario,DC=loc"; // Ajustado según tu Actividad 4
    $domain_suffix = "@lab.urosario.edu.co"; // Para el UPN si fuera necesario, pero usaremos búsqueda

    // Respuesta por defecto
    $respuesta = ['success' => false, 'message' => '', 'data' => []];

    // --- 2. CONEXIÓN ---
    $ds = ldap_connect($host, $port);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    if (!$ds) {
        $respuesta['message'] = "Error crítico: No se pudo conectar al servidor de identidad.";
        return $respuesta;
    }

    // --- 3. AUTENTICACIÓN (BIND) ---
    // Intentamos loguear con el usuario
    // Nota: A veces AD prefiere 'usuario@dominio' o solo 'usuario'. 
    // Usaremos el formato UPN que funcionó en Actividad 2.
    $ldap_user = $usuario . $domain_suffix; 
    
    // Silenciamos errores (@) para capturarlos nosotros
    $bind = @ldap_bind($ds, $ldap_user, $password);

    if ($bind) {
        // --- 4. EXTRACCIÓN DE DATOS (Si el login es exitoso) ---
        $respuesta['success'] = true;
        
        // Buscamos los detalles del usuario
        $filter = "(&(objectClass=user)(sAMAccountName=$usuario))";
        $attributes = ['cn', 'mail', 'department', 'extensionattribute4'];
        
        $search = ldap_search($ds, $base_dn, $filter, $attributes);
        $info = ldap_get_entries($ds, $search);

        if ($info['count'] > 0) {
            $entry = $info[0];
            $respuesta['data'] = [
                'nombre'       => $entry['cn'][0] ?? $usuario,
                'email'        => $entry['mail'][0] ?? 'Sin correo',
                'departamento' => $entry['department'][0] ?? 'No asignado',
                'roles'        => $entry['extensionattribute4'][0] ?? ''
            ];
        } else {
            // Login ok, pero no se pudo leer el perfil (caso raro)
            $respuesta['data'] = ['nombre' => $usuario, 'departamento' => 'Desconocido'];
        }

    } else {
        // --- 5. DIAGNÓSTICO DE ERROR (Si falla) ---
        $extended_error = "";
        ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
        
        if (strpos($extended_error, '52e') !== false) {
            $respuesta['message'] = "Credenciales incorrectas. Verifica tu usuario y contraseña.";
        } elseif (strpos($extended_error, '532') !== false) {
            $respuesta['message'] = "Tu contraseña ha expirado.";
        } elseif (strpos($extended_error, '775') !== false) {
            $respuesta['message'] = "Cuenta bloqueada por seguridad.";
        } else {
            $respuesta['message'] = "Error de acceso. Código técnico: " . $extended_error;
        }
    }

    ldap_close($ds);
    return $respuesta;
}
?>