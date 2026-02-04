<?php
/**
 * public/api_ldap.php
 * Este archivo actúa como puente seguro entre el JS y el servidor LDAP
 */
// Subimos un nivel (..) para buscar la configuración en core
require_once '../core/config_ldap.php'; 

header('Content-Type: application/json');

// 1. Capturar usuario
$usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';

if (empty($usuario)) {
    echo json_encode(['status' => 'error', 'msg' => 'Usuario vacío']);
    exit;
}

// 2. Conexión LDAP
$ldap_conn = ldap_connect(LDAP_HOST, LDAP_PORT);
if (!$ldap_conn) {
    echo json_encode(['status' => 'error', 'msg' => 'Error de conexión LDAP']);
    exit;
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

// 3. Autenticación (Bind) con usuario de servicio
$bind = @ldap_bind($ldap_conn, LDAP_ADMIN_USER, LDAP_ADMIN_PASS);

if ($bind) {
    // Buscamos por sAMAccountName (el usuario corto, ej: guillermo.fonseca)
    $filtro = "(sAMAccountName=$usuario)";
    $busqueda = ldap_search($ldap_conn, LDAP_DN, $filtro, ['cn', 'mail', 'department', 'title']);
    $info = ldap_get_entries($ldap_conn, $busqueda);

    if ($info['count'] > 0) {
        echo json_encode([
            'status' => 'success',
            'nombre' => $info[0]['cn'][0],
            'correo' => $info[0]['mail'][0] ?? 'Sin correo',
            'departamento' => ($info[0]['department'][0] ?? 'N/A') . ' - ' . ($info[0]['title'][0] ?? '')
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Fallo al autenticar en AD']);
}
ldap_unbind($ldap_conn);
?>