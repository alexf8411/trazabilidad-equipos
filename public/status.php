<?php
// public/status.php
// Este archivo solo sirve para que JavaScript pregunte el estado de la sesión
require_once '../core/session.php';

header('Content-Type: application/json');

// Devolvemos un JSON simple
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode(['status' => 'active']);
} else {
    echo json_encode(['status' => 'inactive']);
}
exit;
?>