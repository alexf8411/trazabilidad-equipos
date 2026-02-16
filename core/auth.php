<?php
/**
 * core/auth.php
 * Motor de Lógica de Autenticación LDAP
 * Versión 2.0 - 100% Configurable desde config.json
 */

function autenticar_usuario($usuario, $password) {
    // --- 1. CARGA DE CONFIGURACIÓN DINÁMICA ---
    $configFile = __DIR__ . '/config.json';
    
    if (!file_exists($configFile)) {
        return [
            'success' => false, 
            'message' => 'Error crítico: Archivo de configuración no encontrado.',
            'data' => []
        ];
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    $ldapConf = $config['ldap'] ?? [];
    
    // Cargar parámetros con fallbacks seguros
    $host = $ldapConf['host'] ?? 'ldaps://10.194.194.142';
    $port = $ldapConf['port'] ?? 636;
    $base_dn = $ldapConf['base_dn'] ?? 'DC=urosario,DC=loc';
    $domain_suffix = $ldapConf['domain_suffix'] ?? '@lab.urosario.edu.co';
    
    // Descifrar credenciales si existen (para búsquedas privilegiadas)
    require_once __DIR__ . '/config_crypto.php';
    $bind_user = !empty($ldapConf['bind_user']) ? ConfigCrypto::decrypt($ldapConf['bind_user']) : '';
    $bind_pass = !empty($ldapConf['bind_pass']) ? ConfigCrypto::decrypt($ldapConf['bind_pass']) : '';

    // Respuesta por defecto
    $respuesta = ['success' => false, 'message' => '', 'data' => []];

    // --- 2. CONEXIÓN ---
    $ds = @ldap_connect($host, $port);
    if (!$ds) {
        $respuesta['message'] = "Error crítico: No se pudo conectar al servidor de identidad.";
        return $respuesta;
    }
    
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    // --- 3. AUTENTICACIÓN (BIND) ---
    $ldap_user = $usuario . $domain_suffix;
    $bind = @ldap_bind($ds, $ldap_user, $password);

    if ($bind) {
        // --- 4. EXTRACCIÓN DE DATOS (Si el login es exitoso) ---
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
            $respuesta['data'] = ['nombre' => $usuario, 'departamento' => 'Desconocido'];
        }

    } else {
        // --- 5. DIAGNÓSTICO DE ERROR ---
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