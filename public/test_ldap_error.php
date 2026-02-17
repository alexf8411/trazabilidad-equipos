<?php
require_once '../core/auth.php';

$resultado = autenticar_usuario('appadmdb', 'cualquiercontraseña');

echo "<pre>";
echo "Success: " . ($resultado['success'] ? 'true' : 'false') . "\n";
echo "Message: " . $resultado['message'] . "\n";
echo "Error Code: " . ($resultado['error_code'] ?? 'NULL') . "\n";
echo "</pre>";
?>