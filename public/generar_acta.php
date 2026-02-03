<?php
/**
 * public/generar_acta.php
 * Generación de Acta con trazabilidad estricta para Auditoría
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_ldap.php';
require_once '../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. CAPTURA DE SERIAL Y VALIDACIÓN DE SESIÓN
$serial = $_GET['serial'] ?? '';
if (empty($serial)) die("Acceso denegado: Serial ausente.");

// 2. CONSULTA SQL CRUZADA (EQUIPO + ÚLTIMO MOVIMIENTO REGISTRADO)
// Buscamos el correo real que el técnico ingresó en el formulario de movimiento
$sql = "SELECT e.marca, e.modelo, e.placa_ur, e.serial,
               b.sede, b.ubicacion, b.correo_responsable, b.hostname, 
               b.fecha_evento, b.tecnico_responsable 
        FROM equipos e 
        JOIN bitacora b ON e.serial = b.serial_equipo 
        WHERE e.serial = ? 
        ORDER BY b.id DESC LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$serial]);
$movimiento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movimiento) {
    die("Error de Auditoría: No existe un registro de movimiento para este serial.");
}

// 3. VALIDACIÓN LDAP PARA IDENTIDAD LEGAL
$nombre_legal = "No recuperado de LDAP";
$cargo_departamento = "No recuperado de LDAP";

$ldap_conn = ldap_connect(LDAP_HOST, LDAP_PORT);
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
    
    // Bind con cuenta de sistema para búsqueda
    $bind = @ldap_bind($ldap_conn, LDAP_ADMIN_USER, LDAP_ADMIN_PASS);
    
    if ($bind) {
        // Buscamos por el CORREO REAL que quedó guardado en la base de datos
        $filtro = "(mail=" . $movimiento['correo_responsable'] . ")";
        $busqueda = ldap_search($ldap_conn, LDAP_DN, $filtro, ['cn', 'department', 'title']);
        $info = ldap_get_entries($ldap_conn, $busqueda);
        
        if ($info['count'] > 0) {
            $nombre_legal = $info[0]['cn'][0];
            $cargo_departamento = ($info[0]['department'][0] ?? 'N/A') . " / " . ($info[0]['title'][0] ?? 'N/A');
        }
    }
    ldap_unbind($ldap_conn);
}

// 4. ESTRUCTURA DEL DOCUMENTO PARA ARCHIVO FÍSICO/DIGITAL
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: "Helvetica", sans-serif; font-size: 10pt; color: #333; }
        .header-table { width: 100%; border-bottom: 2px solid #002D72; margin-bottom: 20px; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; margin: 20px 0; color: #002D72; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .data-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .label { font-weight: bold; background-color: #f5f5f5; width: 30%; }
        .legal-box { border: 1px solid #999; padding: 15px; font-size: 8.5pt; text-align: justify; line-height: 1.4; background: #fafafa; }
        .sig-container { margin-top: 50px; width: 100%; }
        .sig-line { border-top: 1px solid #000; width: 200px; margin: 0 auto 5px; }
        .footer-info { font-size: 8pt; color: #777; text-align: center; margin-top: 30px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td><h2 style="margin:0; color:#002D72;">URTRACK</h2></td>
            <td style="text-align:right;">ID de Seguimiento: '.time().'-'.$movimiento['placa_ur'].'</td>
        </tr>
    </table>

    <div class="title">ACTA DE RESPONSABILIDAD Y ASIGNACIÓN DE ACTIVOS</div>

    <p>Se deja constancia de la entrega del siguiente activo bajo la política de uso de herramientas tecnológicas de la Institución.</p>

    <table class="data-table">
        <tr><td colspan="2" style="background:#002D72; color:white; font-weight:bold;">DATOS DEL RESPONSABLE (ASIGNADO)</td></tr>
        <tr><td class="label">Nombre Completo:</td><td>'.$nombre_legal.'</td></tr>
        <tr><td class="label">Correo Electrónico Real:</td><td><strong>'.$movimiento['correo_responsable'].'</strong></td></tr>
        <tr><td class="label">Unidad / Cargo:</td><td>'.$cargo_departamento.'</td></tr>
        <tr><td class="label">Sede / Ubicación:</td><td>'.$movimiento['sede'].' - '.$movimiento['ubicacion'].'</td></tr>
    </table>

    <table class="data-table">
        <tr><td colspan="2" style="background:#002D72; color:white; font-weight:bold;">ESPECIFICACIONES DEL ACTIVO</td></tr>
        <tr><td class="label">Placa Institucional:</td><td>'.$movimiento['placa_ur'].'</td></tr>
        <tr><td class="label">Serial de Fábrica:</td><td>'.$movimiento['serial'].'</td></tr>
        <tr><td class="label">Marca / Modelo:</td><td>'.$movimiento['marca'].' / '.$movimiento['modelo'].'</td></tr>
        <tr><td class="label">Hostname en Red:</td><td>'.$movimiento['hostname'].'</td></tr>
    </table>

    <div class="legal-box">
        <strong>CLÁUSULAS DE AUDITORÍA:</strong> El usuario acepta que el equipo es una herramienta de trabajo y su uso está sujeto a monitoreo institucional. La cuenta de correo <u>'.$movimiento['correo_responsable'].'</u> es la vinculada legalmente a este activo. Cualquier extravío debe ser reportado en un plazo no mayor a 24 horas adjuntando el denuncio respectivo. Este documento sirve como soporte para auditorías de inventario físico y digital.
    </div>

    <table class="sig-container">
        <tr>
            <td style="text-align:center; width:50%;">
                <div class="sig-line"></div>
                <strong>Firma del Usuario Responsable</strong><br>
                Identificación: _________________
            </td>
            <td style="text-align:center; width:50%;">
                <div class="sig-line"></div>
                <strong>Técnico que Entrega</strong><br>
                '.$movimiento['tecnico_responsable'].'
            </td>
        </tr>
    </table>

    <div class="footer-info">
        Documento generado automáticamente por el Sistema URTRACK el '.date("d/m/Y H:i:s").'<br>
        Servidor: '.$_SERVER['SERVER_NAME'].' | Registro de Auditoría: '.$movimiento['fecha_evento'].'
    </div>
</body>
</html>';

// 5. GENERACIÓN DEL PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Acta_Auditoria_".$movimiento['placa_ur'].".pdf", ["Attachment" => true]);