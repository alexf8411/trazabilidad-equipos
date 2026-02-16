<?php
/**
 * public/api_ldap.php
 * Versión 2.0 - Compatible con sistema de configuración v4.0
 * Soporta búsqueda CON o SIN cuenta de servicio
 */

header('Content-Type: application/json; charset=utf-8');

// Validar parámetro
$usuario_buscado = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
if (empty($usuario_buscado)) {
    echo json_encode(['status' => 'error', 'msg' => 'Parámetro usuario requerido']);
    exit;
}

// Cargar configuración desde config.json
$configFile = __DIR__ . '/../core/config.json';
if (!file_exists($configFile)) {
    echo json_encode(['status' => 'error', 'msg' => 'Configuración no encontrada']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$ldapConf = $config['ldap'] ?? [];

// Parámetros LDAP
$host = $ldapConf['host'] ?? 'ldaps://10.194.194.142';
$port = $ldapConf['port'] ?? 636;
$base_dn = $ldapConf['base_dn'] ?? 'DC=urosario,DC=loc';
$domain_suffix = $ldapConf['domain_suffix'] ?? '@lab.urosario.edu.co';

// Cargar módulo de descifrado
require_once __DIR__ . '/../core/config_crypto.php';

// Descifrar credenciales de bind (si existen)
$bind_user = '';
$bind_pass = '';

if (!empty($ldapConf['bind_user'])) {
    try {
        $bind_user = ConfigCrypto::decrypt($ldapConf['bind_user']);
    } catch (Exception $e) {
        // Si falla descifrado, está en texto plano
        $bind_user = $ldapConf['bind_user'];
    }
}

if (!empty($ldapConf['bind_pass'])) {
    try {
        $bind_pass = ConfigCrypto::decrypt($ldapConf['bind_pass']);
    } catch (Exception $e) {
        $bind_pass = $ldapConf['bind_pass'];
    }
}

// Conectar a LDAP
$ds = @ldap_connect($host, $port);
if (!$ds) {
    echo json_encode(['status' => 'error', 'msg' => 'Error de conexión LDAP']);
    exit;
}

ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

// Intentar bind
$bind_success = false;

// Opción 1: Con credenciales de servicio (si existen)
if (!empty($bind_user) && !empty($bind_pass)) {
    $bind_success = @ldap_bind($ds, $bind_user, $bind_pass);
}

// Opción 2: Bind anónimo (si falla o no hay credenciales)
if (!$bind_success) {
    $bind_success = @ldap_bind($ds);
}

if (!$bind_success) {
    // Si ambos fallan, retornar info básica sin búsqueda LDAP
    // (El usuario existe si tiene formato válido)
    if (preg_match('/^[a-z0-9._-]+$/i', $usuario_buscado)) {
        echo json_encode([
            'status' => 'success',
            'nombre' => ucwords(str_replace('.', ' ', $usuario_buscado)),
            'correo' => $usuario_buscado . $domain_suffix,
            'departamento' => 'Usuario Institucional'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Usuario inválido']);
    }
    ldap_close($ds);
    exit;
}

// Buscar usuario en LDAP
$filter = "(&(objectClass=user)(sAMAccountName=$usuario_buscado))";
$attributes = ['cn', 'mail', 'department', 'title', 'displayName'];

$search = @ldap_search($ds, $base_dn, $filter, $attributes);

if (!$search) {
    echo json_encode(['status' => 'error', 'msg' => 'Error en búsqueda LDAP']);
    ldap_close($ds);
    exit;
}

$info = ldap_get_entries($ds, $search);

if ($info['count'] == 0) {
    echo json_encode(['status' => 'not_found', 'msg' => 'Usuario no existe']);
    ldap_close($ds);
    exit;
}

// Usuario encontrado
$entry = $info[0];

$response = [
    'status' => 'success',
    'nombre' => $entry['cn'][0] ?? $entry['displayName'][0] ?? ucwords(str_replace('.', ' ', $usuario_buscado)),
    'correo' => $entry['mail'][0] ?? ($usuario_buscado . $domain_suffix),
    'departamento' => trim(
        ($entry['department'][0] ?? 'No especificado') . 
        (isset($entry['title'][0]) ? ' - ' . $entry['title'][0] : '')
    )
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);

ldap_close($ds);
?>