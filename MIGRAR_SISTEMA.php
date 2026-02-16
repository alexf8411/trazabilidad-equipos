<?php
/**
 * MIGRAR_SISTEMA.php
 * Script de Migración Automática v1.0
 * Ejecutar UNA SOLA VEZ después de subir los archivos nuevos
 * 
 * INSTRUCCIONES:
 * 1. Subir todos los archivos nuevos a /core/
 * 2. Acceder a: http://tuservidor/MIGRAR_SISTEMA.php
 * 3. Revisar que todo está OK
 * 4. ELIMINAR este archivo por seguridad
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migración URTRACK</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;border:1px solid #c3e6cb;}";
echo ".error{background:#f8d7da;padding:15px;border-radius:5px;margin:10px 0;border:1px solid #f5c6cb;}";
echo ".info{background:#d1ecf1;padding:15px;border-radius:5px;margin:10px 0;border:1px solid #bee5eb;}";
echo "pre{background:#f4f4f4;padding:10px;border-radius:5px;overflow-x:auto;}</style></head><body>";

echo "<h1>🚀 URTRACK - Migración a Sistema Cifrado</h1>";

$errors = [];
$success = [];

// PASO 1: Verificar archivos críticos
echo "<h2>📋 Paso 1: Verificación de Archivos</h2>";

$requiredFiles = [
    'core/config_crypto.php' => 'Módulo de Cifrado',
    'core/auth.php' => 'Autenticación LDAP',
    'core/db.php' => 'Conexión Base de Datos',
    'core/config.json' => 'Configuración Principal'
];

foreach ($requiredFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $desc: <code>$file</code> encontrado</div>";
    } else {
        echo "<div class='error'>❌ $desc: <code>$file</code> NO ENCONTRADO</div>";
        $errors[] = "$desc faltante";
    }
}

if (count($errors) > 0) {
    echo "<div class='error'><strong>⚠️ MIGRACIÓN DETENIDA</strong><br>";
    echo "Corrija los errores y vuelva a ejecutar este script.</div>";
    echo "</body></html>";
    exit;
}

// PASO 2: Crear .env si no existe
echo "<h2>🔐 Paso 2: Inicialización de Clave de Cifrado</h2>";

if (!file_exists('core/.env')) {
    require_once 'core/config_crypto.php';
    try {
        // Forzar inicialización de clave
        ConfigCrypto::encrypt('test');
        echo "<div class='success'>✅ Archivo <code>core/.env</code> creado con clave aleatoria segura</div>";
        echo "<div class='info'><strong>⚠️ IMPORTANTE:</strong> Haga backup de <code>core/.env</code> inmediatamente. Sin este archivo, NO podrá descifrar las contraseñas.</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando .env: " . $e->getMessage() . "</div>";
        $errors[] = "No se pudo crear .env";
    }
} else {
    echo "<div class='info'>ℹ️ Archivo <code>core/.env</code> ya existe (no se modificó)</div>";
}

// PASO 3: Migrar config.json
echo "<h2>🔄 Paso 3: Migración de Contraseñas a Formato Cifrado</h2>";

$configFile = 'core/config.json';
$config = json_decode(file_get_contents($configFile), true);

if (!$config) {
    echo "<div class='error'>❌ Error: config.json corrupto</div>";
    exit;
}

// Agregar parámetros faltantes
$modified = false;

if (!isset($config['ldap']['domain_suffix'])) {
    $config['ldap']['domain_suffix'] = '@lab.urosario.edu.co';
    $modified = true;
    echo "<div class='success'>✅ Agregado: ldap.domain_suffix</div>";
}

if (!isset($config['mail']['smtp_host'])) {
    $config['mail']['smtp_host'] = 'smtp.office365.com';
    $modified = true;
    echo "<div class='success'>✅ Agregado: mail.smtp_host</div>";
}

if (!isset($config['mail']['smtp_port'])) {
    $config['mail']['smtp_port'] = 587;
    $modified = true;
    echo "<div class='success'>✅ Agregado: mail.smtp_port</div>";
}

if (!isset($config['db']['port'])) {
    $config['db']['port'] = 3306;
    $modified = true;
    echo "<div class='success'>✅ Agregado: db.port</div>";
}

// Cifrar contraseñas si están en texto plano
require_once 'core/config_crypto.php';

if (!empty($config['mail']['smtp_pass']) && !ConfigCrypto::isEncrypted($config['mail']['smtp_pass'])) {
    $config['mail']['smtp_pass'] = ConfigCrypto::encrypt($config['mail']['smtp_pass']);
    $modified = true;
    echo "<div class='success'>✅ Contraseña SMTP cifrada</div>";
}

if (!empty($config['ldap']['bind_pass']) && !ConfigCrypto::isEncrypted($config['ldap']['bind_pass'])) {
    $config['ldap']['bind_pass'] = ConfigCrypto::encrypt($config['ldap']['bind_pass']);
    $modified = true;
    echo "<div class='success'>✅ Contraseña LDAP cifrada</div>";
}

if (!empty($config['db']['pass']) && !ConfigCrypto::isEncrypted($config['db']['pass'])) {
    $config['db']['pass'] = ConfigCrypto::encrypt($config['db']['pass']);
    $modified = true;
    echo "<div class='success'>✅ Contraseña DB cifrada</div>";
}

if ($modified) {
    // Backup del original
    copy($configFile, $configFile . '.backup_' . date('Ymd_His'));
    echo "<div class='info'>📦 Backup creado: <code>config.json.backup_" . date('Ymd_His') . "</code></div>";
    
    // Guardar nueva configuración
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "<div class='success'>✅ Configuración actualizada y guardada</div>";
} else {
    echo "<div class='info'>ℹ️ No se requirieron cambios en config.json</div>";
}

// PASO 4: Pruebas de conexión
echo "<h2>🧪 Paso 4: Pruebas de Conectividad</h2>";

// Prueba de BD
try {
    require_once 'core/db.php';
    echo "<div class='success'>✅ Conexión a Base de Datos: OK</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error de BD: " . $e->getMessage() . "</div>";
    $errors[] = "DB no conecta";
}

// RESULTADO FINAL
echo "<h2>📊 Resultado de la Migración</h2>";

if (count($errors) == 0) {
    echo "<div class='success'>";
    echo "<h3>🎉 ¡MIGRACIÓN EXITOSA!</h3>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li>Hacer backup de <code>core/.env</code> (guardarlo en un lugar seguro fuera del servidor)</li>";
    echo "<li>Verificar que el login funciona correctamente</li>";
    echo "<li>Probar generar un acta</li>";
    echo "<li>Acceder a <code>configuracion.php</code> y verificar que los valores se muestran correctamente</li>";
    echo "<li><strong>ELIMINAR</strong> este archivo (MIGRAR_SISTEMA.php) por seguridad</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ Migración con Errores</h3>";
    echo "<p>Se encontraron los siguientes problemas:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "<p>Corrija estos errores antes de usar el sistema.</p>";
    echo "</div>";
}

echo "<hr><p style='text-align:center;color:#666;'>URTRACK v4.0 | Universidad del Rosario</p>";
echo "</body></html>";
?>