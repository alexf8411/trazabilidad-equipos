<?php
// Incluir la configuración
require_once 'config_ldap.php';

echo "<h1>Prueba de Conectividad LDAP (Identidad)</h1>";
echo "<hr>";

// --- ZONA DE PRUEBA ---
// Cambia esto temporalmente por tu contraseña real para probar y luego borra el archivo.
$usuario_prueba = 'guillermo.fonseca'; 
$password_prueba = 'PASSWORD_PLACEHOLDER'; // <--- ¡EDITA ESTO!
// ----------------------

echo "<p>Intentando conectar a: <strong>" . LDAP_HOST . "</strong></p>";

try {
    // 1. Conexión al Servidor
    $ldap_conn = ldap_connect(LDAP_HOST, LDAP_PORT);
    
    if (!$ldap_conn) {
        throw new Exception("No se pudo iniciar la conexión con el servidor LDAP.");
    }

    // Opciones obligatorias para AD moderno
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    echo "<p style='color:blue;'>✅ Conexión física establecida (Socket abierto).</p>";

    // 2. Intentar Autenticación (Bind)
    // El AD suele requerir formato: DOMINIO\usuario o usuario@dominio
    $ldap_user_full = $usuario_prueba . "@lab.urosario.edu.co"; // Usando UPN según tus datos
    
    echo "<p>Intentando autenticar como: <code>$ldap_user_full</code></p>";

    $bind = @ldap_bind($ldap_conn, $ldap_user_full, $password_prueba);

    if ($bind) {
        echo "<h2 style='color:green;'>🎉 ¡AUTENTICACIÓN EXITOSA!</h2>";
        echo "<p>El servidor 10.194.194.142 aceptó tus credenciales.</p>";
        
        // 3. Buscar tus propios datos (Prueba de Lectura)
        $filtro = "(sAMAccountName=$usuario_prueba)";
        $atributos = ['cn', 'mail', 'department', 'extensionAttribute4'];
        
        $busqueda = ldap_search($ldap_conn, LDAP_DN, $filtro, $atributos);
        $info = ldap_get_entries($ldap_conn, $busqueda);

        if ($info['count'] > 0) {
            echo "<h3>Datos recuperados del Directorio Activo:</h3>";
            echo "<ul>";
            echo "<li><strong>Nombre (CN):</strong> " . $info[0]['cn'][0] . "</li>";
            echo "<li><strong>Correo:</strong> " . $info[0]['mail'][0] . "</li>";
            echo "<li><strong>Departamento:</strong> " . $info[0]['department'][0] . "</li>";
            echo "<li><strong>Rol (ExtAttr4):</strong> " . $info[0]['extensionattribute4'][0] . "</li>";
            echo "</ul>";
        } else {
            echo "<p style='color:orange;'>Autenticó, pero no pudo leer los atributos.</p>";
        }

    } else {
        echo "<h2 style='color:red;'>❌ FALLÓ LA AUTENTICACIÓN</h2>";
        echo "<p>Error LDAP: " . ldap_error($ldap_conn) . "</p>";
        echo "<p>Verifica que la contraseña sea correcta en el código.</p>";
    }

    // Cerrar
    ldap_unbind($ldap_conn);

} catch (Exception $e) {
    echo "<p style='color:red;'>Error Crítico: " . $e->getMessage() . "</p>";
}
?>
