<?php
/**
 * public/generar_acta.php
 * Generación de Acta PDF con Observaciones y Responsable Secundario
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDACIÓN
if (!isset($_GET['serial'])) {
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Error: No se especificó un serial.</div>");
}
$serial = $_GET['serial'];
$action = $_GET['action'] ?? 'view';

// 2. OBTENER DATOS
$sql = "SELECT e.*, b.*, l.nombre as nombre_lugar 
        FROM equipos e
        JOIN bitacora b ON e.serial = b.serial_equipo
        LEFT JOIN lugares l ON b.id_lugar = l.id
        WHERE e.serial = ? 
        ORDER BY b.id_evento DESC LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$serial]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Error: No existen movimientos.</div>");
}

/* -------------------------------------------------------------------------
   [OPCIONAL] LÓGICA DE GUARDADO DE FIRMA DIGITAL
   Descomentar si se desea reactivar la firma en pantalla.
   -------------------------------------------------------------------------
if ($action == 'save_signature' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_json = json_decode(file_get_contents('php://input'), true);
    if (isset($data_json['imagen'])) {
        // Asegúrate de tener la columna 'firma_digital' en tu tabla bitacora
        $stmt = $pdo->prepare("UPDATE bitacora SET firma_digital = ? WHERE id_evento = ?");
        $stmt->execute([$data_json['imagen'], $data['id_evento']]);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'msg' => 'No image data']);
    }
    exit;
}
*/

// 3. CLASE PDF
class PDF extends \FPDF {
    function Header() {
        if(file_exists('img/logo_ur.png')) { 
            $this->Image('img/logo_ur.png', 10, 8, 33);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'ACTA DE MOVIMIENTO DE ACTIVO', 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Generado por URTRACK - Universidad del Rosario - Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

// 4. FUNCIÓN CONSTRUCTORA
function construirPDF($data) {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);

    // Bloque 1: Datos del Evento
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('DETALLES DE LA TRANSACCIÓN #') . $data['id_evento'], 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Fecha:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, $data['fecha_evento'], 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Tipo Evento:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, strtoupper(utf8_decode($data['tipo_evento'])), 1, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Sede:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, utf8_decode($data['sede']), 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, utf8_decode('Ubicación:'), 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, utf8_decode($data['ubicacion']), 1, 1);
    $pdf->Ln(5);

    // Bloque 2: Datos del Equipo
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('INFORMACIÓN DEL ACTIVO'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Placa UR:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, $data['placa_ur'], 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Serial:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, $data['serial'], 1, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Marca/Modelo:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(155, 8, utf8_decode($data['marca'] . ' ' . $data['modelo']), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Hostname:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(155, 8, $data['hostname'], 1, 1);
    $pdf->Ln(5);

    // Bloque Observaciones
    if (!empty($data['campo_adic1']) || !empty($data['campo_adic2'])) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode('OBSERVACIONES ADICIONALES'), 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);
        $observaciones = trim(($data['campo_adic1'] ?? '') . " " . ($data['campo_adic2'] ?? ''));
        $pdf->MultiCell(0, 8, utf8_decode($observaciones), 1, 'L');
        $pdf->Ln(5);
    }

    // Bloque 3: Responsabilidad
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('CONSTANCIA DE RESPONSABILIDAD'), 1, 1, 'L', true);
    
    $texto_legal = file_get_contents('../core/acta_legal.txt');
    if(!$texto_legal) $texto_legal = "El usuario responsable declara recibir/entregar el equipo..."; 
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, utf8_decode($texto_legal), 0, 'J');
    $pdf->Ln(4);

    // Firmantes
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 7, 'Usuario Responsable:', 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, $data['correo_responsable'], 0, 1);

    if (!empty($data['responsable_secundario'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(50, 7, 'Responsable Secundario:', 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, $data['responsable_secundario'], 0, 1);
    }

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 7, 'Entregado por:', 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, utf8_decode($data['tecnico_responsable']), 0, 1);
    $pdf->Ln(20);

    /* -------------------------------------------------------------------------
       [OPCIONAL] INCRUSTAR FIRMA DIGITAL EN EL PDF
       Descomentar si se usa la firma en pantalla.
       -------------------------------------------------------------------------
    $y_firmas = $pdf->GetY();
    if (!empty($data['firma_digital'])) {
        $img_parts = explode(',', $data['firma_digital']);
        $img_data = base64_decode($img_parts[1]);
        $tmp_file = sys_get_temp_dir() . '/firma_' . $data['id_evento'] . '.png';
        file_put_contents($tmp_file, $img_data);
        $pdf->Image($tmp_file, 15, $y_firmas - 15, 40, 0); 
        unlink($tmp_file); 
    }
    */

    // Líneas de Firmas
    $pdf->Cell(90, 0, '', 'T'); 
    $pdf->Cell(10, 0, '', 0);
    $pdf->Cell(90, 0, '', 'T');
    $pdf->Ln(2);
    $pdf->Cell(90, 5, 'Firma Responsable', 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0);
    $pdf->Cell(90, 5, utf8_decode('Firma Dirección de Tecnología'), 0, 0, 'C');

    return $pdf;
}

// 5. ENVÍO DE CORREO
if ($action == 'send_mail') {
    $pdf = construirPDF($data);
    $pdfContent = $pdf->Output('S'); 

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2; 
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($data['correo_responsable']);
        
        $mail->addStringAttachment($pdfContent, 'Acta_URTRACK_' . $data['placa_ur'] . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Movimiento - Activo ' . $data['placa_ur'];
        
        $body = 'Buen día,<br><br>Adjunto encontrará el acta digital del movimiento realizado.<br>' .
                '<b>Tipo:</b> ' . $data['tipo_evento'] . '<br>' .
                '<b>Equipo:</b> ' . $data['marca'] . ' ' . $data['modelo'] . '<br>' .
                '<b>Hostname:</b> ' . $data['hostname'] . '<br>';
        
        if(!empty($data['responsable_secundario'])) {
            $body .= '<b>Responsable Secundario:</b> ' . $data['responsable_secundario'] . '<br>';
        }

        $body .= '<br>Atentamente,<br>Dirección de Tecnología - UR';
        $mail->Body = $body;

        $mail->send();
        echo "OK"; 
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error Mailer: {$mail->ErrorInfo}";
    }
    exit;
}

// 6. VISTA HTML CON VISOR LIMPIO
if ($action == 'view') {
    $pdf = construirPDF($data);
    $pdfBase64 = base64_encode($pdf->Output('S'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista Previa Acta | <?= $data['placa_ur'] ?></title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: 0.2s; }
        .btn-send { background: #22c55e; color: white; }
        .btn-send:hover { background: #16a34a; }
        .btn-back { background: #64748b; color: white; }
        /* iframe full screen menos toolbar */
        iframe { width: 100%; height: calc(100vh - 60px); border: none; display: block; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <a href="dashboard.php" class="btn btn-back">⬅ Dashboard</a>
            <span style="margin-left: 20px; color:#cbd5e1;">Acta #<?= $data['id_evento'] ?> - Activo: <?= $data['placa_ur'] ?></span>
        </div>
        <div>
            <span id="statusMsg" style="margin-right:15px; font-size:0.9rem;"></span>
            <button id="btnSend" 
                    class="btn btn-send"
                    data-serial="<?= $serial ?>" 
                    data-email="<?= htmlspecialchars($data['correo_responsable']) ?>"
                    data-placa="<?= htmlspecialchars($data['placa_ur']) ?>">
                📧 Enviar al Usuario
            </button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>#toolbar=0&navpanes=0&view=FitH"></iframe>

    <script src="js/acta-mail.js"></script>
</body>
</html>
<?php } ?>