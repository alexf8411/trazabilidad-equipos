<?php
/**
 * public/generar_acta.php
 * Generación de Acta PDF y Envío de Correo
 * CORRECCIÓN: Solución de carga de FPDF
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

// USAMOS SOLAMENTE PHPMAILER AQUÍ
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOTA: No usamos "use FPDF;" porque es una clase global.

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
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>
            Error: No existen movimientos en la bitácora para el equipo <b>$serial</b>.
         </div>");
}

// 3. CLASE PDF (Extendemos de \FPDF con barra invertida para indicar Global)
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
    // Instanciamos \FPDF explícitamente
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

    // Bloque 3: Responsabilidad
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('CONSTANCIA DE RESPONSABILIDAD'), 1, 1, 'L', true);
    $pdf->MultiCell(0, 8, utf8_decode("El usuario responsable declara recibir/entregar el equipo descrito en condiciones operativas. Este movimiento ha sido registrado y auditado por el sistema URTRACK."), 0, 'J');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 8, 'Usuario Responsable:', 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, $data['correo_responsable'], 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 8, utf8_decode('Técnico Responsable:'), 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode($data['tecnico_responsable']), 0, 1);
    $pdf->Ln(20);

    // Firmas
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
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($data['correo_responsable']);
        
        $mail->addStringAttachment($pdfContent, 'Acta_URTRACK_' . $data['placa_ur'] . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Movimiento - Activo ' . $data['placa_ur'];
        $mail->Body    = 'Buen día,<br><br>Adjunto encontrará el acta digital del movimiento realizado.<br>' .
                         '<b>Tipo:</b> ' . $data['tipo_evento'] . '<br>' .
                         '<b>Equipo:</b> ' . $data['marca'] . ' ' . $data['modelo'] . '<br><br>' .
                         'Atentamente,<br>Dirección de Tecnología - UR';

        $mail->send();
        echo "OK"; 
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error Mailer: {$mail->ErrorInfo}";
    }
    exit;
}

// 6. VISTA HTML
if ($action == 'view') {
    $pdf = construirPDF($data);
    $pdfBase64 = base64_encode($pdf->Output('S'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista Previa Acta</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: 0.2s; }
        .btn-send { background: #22c55e; color: white; }
        .btn-send:hover { background: #16a34a; }
        .btn-send:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-back { background: #64748b; color: white; }
        .btn-back:hover { background: #475569; }
        iframe { width: 100%; height: calc(100vh - 60px); border: none; }
        .status-msg { margin-right: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div style="display:flex; align-items:center;">
            <a href="dashboard.php" class="btn btn-back">⬅ Volver al Dashboard</a>
            <span style="margin-left: 20px; color:#cbd5e1;">Acta #<?= $data['id_evento'] ?> - <?= $data['placa_ur'] ?></span>
        </div>
        
        <div style="display:flex; align-items:center;">
            <span id="statusMsg" class="status-msg"></span>
            <button id="btnSend" onclick="enviarCorreo()" class="btn btn-send">
                📧 Enviar Copia al Usuario
            </button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>" type="application/pdf"></iframe>

    <script>
        function enviarCorreo() {
            const btn = document.getElementById('btnSend');
            const msg = document.getElementById('statusMsg');
            
            if(!confirm('¿Confirmar envío del acta a <?= $data['correo_responsable'] ?>?')) return;

            btn.disabled = true;
            btn.innerHTML = '⏳ Enviando...';
            msg.innerHTML = '';

            fetch('generar_acta.php?serial=<?= $serial ?>&action=send_mail')
                .then(response => {
                    if (response.ok) {
                        btn.innerHTML = '✅ Correo Enviado';
                        btn.style.background = '#0ea5e9';
                        msg.innerHTML = 'Notificación enviada exitosamente.';
                        msg.style.color = '#4ade80';
                    } else {
                        return response.text().then(text => { throw new Error(text) });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '❌ Reintentar';
                    btn.style.background = '#ef4444';
                    msg.innerHTML = 'Error de envío. Ver consola.';
                    msg.style.color = '#fca5a5';
                    console.error(error);
                    alert("Error detallado: " + error.message);
                });
        }
    </script>
</body>
</html>
<?php } ?>