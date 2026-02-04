<?php
// public/api_ldap.php
require_once '../core/config_ldap.php'; // Subimos un nivel para buscar la config
header('Content-Type: application/json');

$usuario = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
if (empty($usuario)) { echo json_encode(['status' => 'error']); exit; }

$ldap_conn = ldap_connect(LDAP_HOST, LDAP_PORT);
if (!$ldap_conn) { echo json_encode(['status' => 'error', 'msg' => 'No conecta']); exit; }

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

$bind = @ldap_bind($ldap_conn, LDAP_ADMIN_USER, LDAP_ADMIN_PASS);

if ($bind) {
    $busqueda = ldap_search($ldap_conn, LDAP_DN, "(sAMAccountName=$usuario)", ['cn', 'mail', 'department']);
    $info = ldap_get_entries($ldap_conn, $busqueda);

    if ($info['count'] > 0) {
        echo json_encode([
            'status' => 'success',
            'nombre' => $info[0]['cn'][0],
            'correo' => $info[0]['mail'][0],
            'departamento' => $info[0]['department'][0] ?? 'N/A'
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Bind failed']);
}
ldap_unbind($ldap_conn);