<?php
/**
 * ACTIVIDAD 4: CONSULTA DE ATRIBUTOS (PERFIL DE USUARIO)
 * Objetivo: Extraer sAMAccountName, Department y Roles institucionales.
 */

// --- CONFIGURACIÓN ---
$host = "ldaps://10.194.194.142";
$port = 636;
$domain_suffix = "@lab.urosario.edu.co"; 

// IMPORTANTE: La ruta raíz donde buscaremos al usuario
// Basado en tu dominio, esto se desglosa así:
//$base_dn = "DC=lab,DC=urosario,DC=edu,DC=co";  //<-Anterior 

// Opción A (La más probable según tu certificado):
$base_dn = "DC=urosario,DC=loc";


// Credenciales para la prueba
$user_input = "guillermo.fonseca"; 
$pass_input = "PASSWORD_PLACEHOLDER"; // <--- CAMBIAR AQUÍ

echo "--- INICIANDO CONSULTA DE PERFIL LDAP ---\n";

try {
    // 1. Conexión
    $ds = ldap_connect($host, $port);
    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    if (!$ds) throw new Exception("No hay conexión con el servidor.");

    // 2. Autenticación (Bind)
    $upn = $user_input . $domain_suffix;
    $bind = @ldap_bind($ds, $upn, $pass_input);

    if (!$bind) {
        throw new Exception("Login fallido. No se puede buscar si no estás autenticado.");
    }
    
    echo "✅ Autenticación correcta. Iniciando búsqueda de atributos...\n";

    // 3. Definición del Filtro de Búsqueda
    // Buscamos un objeto que sea 'persona' Y que su 'userPrincipalName' coincida con el email
    $filter = "(&(objectClass=user)(userPrincipalName=$upn))";

    // 4. Proyección (¿Qué datos queremos traer?)
    // Pedimos SOLO lo necesario para cumplir la norma de "No duplicar datos"
    $attributes = [
        'samaccountname',       // ID único (ej: gfonseca)
        'cn',                   // Nombre completo
        'mail',                 // Correo
        'department',           // Departamento/Área
        'extensionattribute4'   // Roles de trazabilidad
    ];

    // 5. Ejecución de la Búsqueda
    $search = ldap_search($ds, $base_dn, $filter, $attributes);
    
    // Obtenemos los datos en formato array
    $info = ldap_get_entries($ds, $search);

    if ($info['count'] == 0) {
        throw new Exception("Usuario autenticado, pero no se encontró su objeto en BaseDN: $base_dn");
    }

    // 6. Procesamiento y Limpieza de Datos
    // LDAP devuelve arrays anidados, simplificamos aquí:
    $entry = $info[0];

    $data = [
        'Nombre'       => get_attr($entry, 'cn'),
        'Usuario (ID)' => get_attr($entry, 'samaccountname'),
        'Correo'       => get_attr($entry, 'mail'),
        'Departamento' => get_attr($entry, 'department'),
        'Roles (RBAC)' => get_attr($entry, 'extensionattribute4')
    ];

    // 7. Salida de Resultados
    echo "\n📊 PERFIL RECUPERADO DE LA FUENTE DE VERDAD:\n";
    echo "============================================\n";
    foreach ($data as $key => $val) {
        echo str_pad($key, 15) . ": " . $val . "\n";
    }
    echo "============================================\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
} finally {
    if (isset($ds)) ldap_close($ds);
}

// Función auxiliar para evitar errores si el campo está vacío
function get_attr($entry, $attr_name) {
    // LDAP devuelve las claves en minúscula
    $attr_name = strtolower($attr_name);
    if (isset($entry[$attr_name][0])) {
        return $entry[$attr_name][0];
    }
    return "--- NO ASIGNADO ---";
}
?>
