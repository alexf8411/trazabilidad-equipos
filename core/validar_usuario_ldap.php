<?php
require_once 'config_ldap.php';
header('Content-Type: application/json');

$email = $_GET['email'] ?? '';
if (empty($email)) die(json_encode(['status' => 'error']));

try {
    $ldap_conn = ldap_connect(LDAP_HOST, LDAP_PORT);
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Bind con cuenta de servicio (ajusta según tu config_ldap.php)
    $bind = @ldap_bind($ldap_conn, LDAP_ADMIN_USER, LDAP_ADMIN_PASS);

    if ($bind) {
        $filtro = "(mail=$email)";
        $busqueda = ldap_search($ldap_conn, LDAP_DN, $filtro, ['cn', 'department']);
        $info = ldap_get_entries($ldap_conn, $busqueda);

        if ($info['count'] > 0) {
            echo json_encode([
                'status' => 'success',
                'nombre' => $info[0]['cn'][0],
                'departamento' => $info[0]['department'][0] ?? 'N/A'
            ]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }
    ldap_unbind($ldap_conn);
} catch (Exception $e) {
    echo json_encode(['status' => 'error']);
}