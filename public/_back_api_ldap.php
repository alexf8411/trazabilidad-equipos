<?php
/**
 * public/api_ldap.php
 */
require_once '../core/config_ldap.php'; // Usa la config nueva

header('Content-Type: application/json');

// Evitar errores si no hay input
$usuario_buscado = isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
if (empty($usuario_buscado)) { echo json_encode(['status' => 'error', 'msg' => 'Vacio']); exit; }

// Conexión
$ds = ldap_connect(LDAP_HOST, LDAP_PORT);
if (!$ds) { echo json_encode(['status' => 'error', 'msg' => 'Error Conexión']); exit; }

ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

// Autenticación con la cuenta de servicio (NO con la del usuario logueado)
$bind = @ldap_bind($ds, LDAP_BIND_USER, LDAP_BIND_PASS);

if ($bind) {
    // Buscar por sAMAccountName
    $search = ldap_search($ds, LDAP_BASE_DN, "(sAMAccountName=$usuario_buscado)", ['cn', 'mail', 'department', 'title']);
    $info = ldap_get_entries($ds, $search);

    if ($info['count'] > 0) {
        echo json_encode([
            'status' => 'success',
            'nombre' => $info[0]['cn'][0],
            'correo' => $info[0]['mail'][0] ?? 'Sin correo',
            'departamento' => ($info[0]['department'][0] ?? '') . ' - ' . ($info[0]['title'][0] ?? '')
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Credenciales de servicio incorrectas']);
}
ldap_close($ds);
?>