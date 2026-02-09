<?php
/**
 * public/generar_acta_baja.php
 * Visor de Acta de Baja Masiva + Envío de Correo
 * Estilo unificado con generar_acta.php
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php'; // Asegúrate de tener esto configurado
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDAR SESIÓN DE BAJA
if (empty($_SESSION['acta_baja_seriales'])) {
    die("<h3>⛔ No hay datos de baja recientes para procesar.</h3><a href='baja_equipos.php'>Volver al módulo de bajas</a>");
}

$seriales = $_SESSION['acta_baja_seriales'];
$motivo = $_SESSION['acta_baja_motivo'];
$lote = $_SESSION['acta_baja_lote'];
$tecnico = $_SESSION['nombre'];
$action = $_GET['action'] ?? 'view';

// 2. RECUPERAR DATOS DE LOS EQUIPOS
$placeholders = str_repeat('?,', count($seriales) - 1) . '?';
$stmt = $pdo->prepare("SELECT placa_ur, serial, marca, modelo, precio FROM equipos WHERE serial IN ($placeholders)");
$stmt->execute($seriales);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. CLASE PDF
class PDF_Baja extends \FPDF {
    function Header() {
        if(file_exists('img/logo_ur.png')) { 
            $this->Image('img/logo_ur.png', 10, 8, 33);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('ACTA DE BAJA Y DISPOSICIÓN FINAL'), 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Sistema URTRACK - Universidad del Rosario - Pág ') . $this->PageNo(), 0, 0, 'C');
    }
}

// 4. CONSTRUCTOR PDF
function construirPDF($lote, $motivo, $tecnico, $equipos) {
    $pdf = new PDF_Baja();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);

    // Bloque 1: Info General
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('DETALLES DEL LOTE DE BAJA #' . $lote), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 8, 'Fecha:', 1); $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, date('Y-m-d H:i:s'), 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 8, utf8_decode('Técnico:'), 1); $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(70, 8, utf8_decode($tecnico), 1, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 8, 'Motivo:', 1); $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(160, 8, utf8_decode($motivo), 1, 1);
    $pdf->Ln(5);

    // Bloque 2: Tabla de Equipos
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 53, 69); // Rojo Baja
    $pdf->SetTextColor(255);
    $pdf->Cell(30, 8, 'PLACA', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'SERIAL', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'MARCA / MODELO', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'VALOR LIBROS', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 8);
    $total = 0;

    foreach ($equipos as $eq) {
        $pdf->Cell(30, 6, $eq['placa_ur'], 1);
        $pdf->Cell(40, 6, $eq['serial'], 1);
        $pdf->Cell(80, 6, utf8_decode(substr($eq['marca'].' '.$eq['modelo'], 0, 45)), 1);
        $pdf->Cell(40, 6, '$ '.number_format($eq['precio'],0,',','.'), 1, 1, 'R');
        $total += $eq['precio'];
    }

    // Totales
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(150, 8, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell(40, 8, '$ '.number_format($total,0,',','.'), 1, 1, 'R');
    $pdf->Ln(10);

    // Bloque 3: Texto Legal (Leído de Archivo)
    $pdf->SetFont('Arial', '', 10);
    
    $texto_legal = @file_get_contents('../core/acta_baja.txt');
    if(!$texto_legal) {
        $texto_legal = "CERTIFICACIÓN:\nPor medio del presente documento se certifica que los equipos listados anteriormente han sido retirados del inventario activo por encontrarse en estado de obsolescencia técnica, falla irreparable o hurto. Estos activos quedan a disposición del área de Activos Fijos para su disposición final conforme a la normativa vigente.";
    }

    $pdf->MultiCell(0, 5, utf8_decode($texto_legal), 0, 'J');
    
    // Bloque 4: Firmas
    $pdf->Ln(25);
    $pdf->Cell(80, 0, '', 'T'); $pdf->Cell(30, 0, '', 0); $pdf->Cell(80, 0, '', 'T');
    $pdf->Ln(2);
    $pdf->Cell(80, 5, utf8_decode('Firma Técnico Responsable'), 0, 0, 'C');
    $pdf->Cell(30, 5, '', 0);
    $pdf->Cell(80, 5, utf8_decode('Firma Auditoria / Activos Fijos'), 0, 0, 'C');

    return $pdf;
}

// 5. ENVÍO DE CORREO (AJAX)
if ($action == 'send_mail') {
    $pdf = construirPDF($lote, $motivo, $tecnico, $equipos);
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

        $mail->setFrom(SMTP_USER, 'URTRACK Bajas');
        
        // En Bajas, se envía copia al técnico logueado (que es quien ejecuta)
        // Opcional: Agregar una cuenta fija de auditoría con $mail->addCC('auditoria@universidad.edu.co');
        $correo_destino = $_SESSION['correo_ldap'] ?? SMTP_USER;
        $mail->addAddress($correo_destino); 
        
        $mail->addStringAttachment($pdfContent, 'Acta_Baja_Lote_'.$lote.'.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Baja Masiva #' . $lote;
        $mail->Body    = "Buen día,<br><br>Se ha generado el acta de baja correspondiente al lote <b>$lote</b>.<br>" .
                         "<b>Motivo:</b> $motivo<br>" .
                         "<b>Equipos procesados:</b> " . count($equipos) . "<br><br>" .
                         "Atentamente,<br>Sistema de Inventarios URTRACK";

        $mail->send();
        echo "OK"; 
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error Mailer: {$mail->ErrorInfo}";
    }
    exit;
}

// 6. VISUALIZACIÓN (HTML + IFRAME) - Estilo idéntico a generar_acta.php
if ($action == 'view') {
    $pdf = construirPDF($lote, $motivo, $tecnico, $equipos);
    $pdfBase64 = base64_encode($pdf->Output('S'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista Previa Acta Baja</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: 0.2s; }
        .btn-send { background: #dc3545; color: white; } /* Rojo distintivo de Bajas */
        .btn-send:hover { background: #b02a37; }
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
            <a href="baja_equipos.php" class="btn btn-back">⬅ Cerrar / Volver</a>
            <span style="margin-left: 20px; color:#cbd5e1; font-weight:bold;">ACTA DE BAJA #<?= $lote ?></span>
        </div>
        
        <div style="display:flex; align-items:center;">
            <span id="statusMsg" class="status-msg"></span>
            <button id="btnSend" onclick="enviarCorreo()" class="btn btn-send">
                📧 Enviar Copia al Correo
            </button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>#toolbar=0" type="application/pdf"></iframe>

    <script>
        function enviarCorreo() {
            const btn = document.getElementById('btnSend');
            const msg = document.getElementById('statusMsg');
            
            if(!confirm('¿Desea enviar copia de esta acta a su correo institucional?')) return;

            btn.disabled = true;
            btn.innerHTML = '⏳ Enviando...';
            msg.innerHTML = '';

            fetch('generar_acta_baja.php?action=send_mail')
                .then(response => {
                    if (response.ok) {
                        btn.innerHTML = '✅ Correo Enviado';
                        btn.style.background = '#198754'; // Verde éxito
                        msg.innerHTML = 'Notificación enviada exitosamente.';
                        msg.style.color = '#4ade80';
                    } else {
                        return response.text().then(text => { throw new Error(text) });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '❌ Reintentar';
                    msg.innerHTML = 'Error de envío.';
                    console.error(error);
                    alert("Error detallado: " + error.message);
                });
        }
    </script>
</body>
</html>
<?php } ?>