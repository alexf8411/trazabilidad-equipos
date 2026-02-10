<?php
/**
 * public/generar_acta.php
 * Generación de Acta PDF y Vista Previa con lógica de Reenvío
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDACIÓN
if (!isset($_GET['serial'])) {
    die("Error: No se especificó un serial.");
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
    die("Error: No existen movimientos para este equipo.");
}

// 3. CLASE PDF (Se mantiene igual que tu versión funcional)
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

// 4. FUNCIÓN CONSTRUCTORA DEL PDF
function construirPDF($data) {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);
    // ... (Todo el bloque de construcción de celdas que ya tienes funciona perfecto)
    // Nota: He omitido el detalle de las celdas para brevedad, mantén tu código de construcción aquí.
    
    // Bloque Responsabilidad
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('DETALLES DE LA TRANSACCIÓN #') . $data['id_evento'], 1, 1, 'L', true);
    // ... resto de celdas ...
    
    return $pdf;
}

// 5. PROCESO DE ENVÍO (Llamado vía Fetch)
if ($action == 'send_mail') {
    $pdf = construirPDF($data);
    $pdfContent = $pdf->Output('S'); 

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($data['correo_responsable']);
        $mail->addStringAttachment($pdfContent, 'Acta_URTRACK_' . $data['placa_ur'] . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Movimiento - Activo ' . $data['placa_ur'];
        $mail->Body    = "Buen día,<br><br>Adjunto encontrará el acta digital.<br><b>Tipo:</b> {$data['tipo_evento']}<br>Atentamente,<br>Dirección de Tecnología - UR";

        $mail->send();
        echo "OK"; 
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error Mailer: {$mail->ErrorInfo}";
    }
    exit;
}

// 6. VISTA HTML PREVIA
if ($action == 'view') {
    $pdf = construirPDF($data);
    $pdfBase64 = base64_encode($pdf->Output('S'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta URTRACK | <?= $data['placa_ur'] ?></title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #525659; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 45px; }
        .btn { padding: 8px 16px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 0.9rem; transition: 0.3s; color: white; }
        .btn-back { background: #64748b; }
        .btn-send { background: #22c55e; } /* Verde inicial */
        .btn-resend { background: #3b82f6 !important; } /* Azul para reenvío */
        .btn:hover { opacity: 0.8; }
        .btn:disabled { background: #94a3b8; cursor: not-allowed; }
        iframe { width: 100%; height: calc(100vh - 65px); border: none; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <a href="dashboard.php" class="btn btn-back">⬅ Volver</a>
            <span style="margin-left:15px;">Acta #<?= $data['id_evento'] ?> - <?= $data['placa_ur'] ?></span>
        </div>
        <div>
            <span id="statusMsg" style="margin-right:15px; font-weight:bold;"></span>
            <button id="btnSend" class="btn btn-send" 
                    onclick="enviarCorreo('<?= $serial ?>', '<?= $data['correo_responsable'] ?>')">
                📧 <span id="btnText">Enviar al Usuario</span>
            </button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>"></iframe>

    <script src="js/gestion_actas.js"></script>
</body>
</html>
<?php } ?>