<?php
/**
 * core/auth.php
 * Motor de Lógica de Autenticación LDAP
 */

function autenticar_usuario($usuario, $password) {
    // --- 1. CONFIGURACIÓN ---
    $host = "ldaps://10.194.194.142";
    $port = 636;
    $base_dn = "DC=urosario,DC=loc";
    $domain_suffix = "@lab.urosario.edu.co";

    // Respuesta por defecto
    $respuesta = ['success' => false, 'message' => '', 'error_code' => '', 'data' => []];

    // --- 2. CONEXIÓN ---
    $ds = ldap_connect($host, $port);
    if (!$ds) {
        $respuesta['message'] = "Error crítico: No se pudo conectar al servidor de identidad.";
        $respuesta['error_code'] = 'CONN_FAIL';
        return $respuesta;
    }

    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    // --- 3. AUTENTICACIÓN (BIND) ---
    $ldap_user = $usuario . $domain_suffix; 
    $bind = @ldap_bind($ds, $ldap_user, $password);

    if ($bind) {
        // --- 4. EXTRACCIÓN DE DATOS (Login exitoso) ---
        $respuesta['success'] = true;
        
        $filter = "(&(objectClass=user)(sAMAccountName=$usuario))";
        $attributes = ['cn', 'mail', 'department', 'extensionattribute4'];
        
        $search = @ldap_search($ds, $base_dn, $filter, $attributes);
        
        if ($search) {
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
                $respuesta['data'] = ['nombre' => $usuario, 'departamento' => 'Desconocido'];
            }
        } else {
            // Bind OK pero búsqueda falló (permisos insuficientes, etc.)
            $respuesta['data'] = ['nombre' => $usuario, 'departamento' => 'Desconocido'];
        }

    } else {
        // --- 5. DIAGNÓSTICO DE ERROR ---
        $extended_error = "";
        ldap_get_option($ds, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
        
        if (strpos($extended_error, '52e') !== false) {
            $respuesta['message'] = "Credenciales incorrectas. Verifica tu usuario y contraseña.";
            $respuesta['error_code'] = '52e';
        } elseif (strpos($extended_error, '532') !== false) {
            $respuesta['message'] = "Tu contraseña ha expirado.";
            $respuesta['error_code'] = '532';
        } elseif (strpos($extended_error, '775') !== false) {
            $respuesta['message'] = "Cuenta bloqueada por seguridad.";
            $respuesta['error_code'] = '775';
        } else {
            $respuesta['message'] = "Error de acceso. Código técnico: " . $extended_error;
            $respuesta['error_code'] = 'OTRO';
        }
    }

    ldap_close($ds);
    return $respuesta;
}
?>