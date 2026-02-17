<?php
/**
 * public/generar_acta_baja.php
 * Visor de Acta de Baja Masiva
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDACIÓN DE SESIÓN
if (empty($_SESSION['acta_baja_seriales'])) {
    die("<div style='color:white; background:#1e293b; padding:30px; text-align:center; font-family:sans-serif;'>
            <h3>⛔ No hay un lote de bajas activo.</h3>
            <a href='baja_equipos.php' style='color:#4ade80; margin-top:10px; display:inline-block;'>⬅ Volver al módulo de bajas</a>
         </div>");
}

$seriales   = $_SESSION['acta_baja_seriales'];
$motivo     = $_SESSION['acta_baja_motivo'];
$lote       = $_SESSION['acta_baja_lote'];
$tecnico    = $_SESSION['nombre'];
$action     = $_GET['action'] ?? 'view';

// 2. OBTENER DATOS
$placeholders = str_repeat('?,', count($seriales) - 1) . '?';
$stmt = $pdo->prepare("SELECT placa_ur, serial, marca, modelo FROM equipos WHERE serial IN ($placeholders)");
$stmt->execute($seriales);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. CLASE PDF
class PDF extends \FPDF {
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
        $this->Cell(0, 10, utf8_decode('Generado por URTRACK - Universidad del Rosario - Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

// 4. FUNCIÓN CONSTRUCTORA
function construirPDF($lote, $motivo, $tecnico, $equipos) {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 11);

    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('DETALLES DEL LOTE DE BAJA #') . $lote, 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Fecha:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, date('Y-m-d H:i:s'), 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, utf8_decode('Técnico:'), 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 8, utf8_decode($tecnico), 1, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Motivo:', 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(155, 8, utf8_decode($motivo), 1, 1);
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, utf8_decode('LISTADO DE ACTIVOS (' . count($equipos) . ' Unidades)'), 1, 1, 'L', true);
    
    $pdf->SetFillColor(220, 53, 69);
    $pdf->SetTextColor(255);
    $pdf->Cell(40, 7, 'Placa', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Serial', 1, 0, 'C', true);
    $pdf->Cell(100, 7, 'Marca / Modelo', 1, 0, 'C', true);
    
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($equipos as $eq) {
        $pdf->Ln();
        $pdf->Cell(40, 7, $eq['placa_ur'], 1);
        $pdf->Cell(50, 7, $eq['serial'], 1);
        $pdf->Cell(100, 7, utf8_decode(substr($eq['marca'].' '.$eq['modelo'], 0, 55)), 1);
    }
    $pdf->Ln(10);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('CERTIFICACIÓN DE DISPOSICIÓN FINAL'), 1, 1, 'L', true);
    
    $texto_legal = file_get_contents('../core/acta_baja.txt');
    if (empty($texto_legal)) {
        $texto_legal = "CERTIFICACIÓN: Los equipos listados han sido retirados del inventario.";
    }

    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 6, utf8_decode($texto_legal), 0, 'J');
    $pdf->Ln(20);

    $pdf->Cell(90, 0, '', 'T'); 
    $pdf->Cell(10, 0, '', 0);
    $pdf->Cell(90, 0, '', 'T');
    $pdf->Ln(2);
    $pdf->Cell(90, 5, utf8_decode('Firma Técnico Ejecutor'), 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0);
    $pdf->Cell(90, 5, utf8_decode('Firma Auditoría / Activos Fijos'), 0, 0, 'C');

    return $pdf;
}

// 5. ENVÍO DE CORREO
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
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom(SMTP_USER, 'URTRACK Bajas');
        $destinatario = $_SESSION['correo_ldap'] ?? SMTP_USER;
        $mail->addAddress($destinatario);
        $mail->addStringAttachment($pdfContent, 'Acta_Baja_Lote_' . $lote . '.pdf');
        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Baja Masiva #' . $lote;
        $mail->Body    = 'Buen día,<br><br>Adjunto acta técnica de la baja realizada.<br>' .
                         '<b>Lote:</b> ' . $lote . '<br>' .
                         '<b>Motivo:</b> ' . $motivo . '<br>' .
                         '<b>Cantidad:</b> ' . count($equipos) . ' equipos.<br><br>' .
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
    $pdf = construirPDF($lote, $motivo, $tecnico, $equipos);
    $pdfBase64 = base64_encode($pdf->Output('S'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Baja #<?= $lote ?> | URTRACK</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }

        .toolbar {
            background: #1e293b;
            color: white;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 56px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            gap: 10px;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-info {
            color: #94a3b8;
            font-size: 0.85rem;
            padding-left: 12px;
            border-left: 1px solid #334155;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-home  { background: #334155; color: #e2e8f0; }
        .btn-home:hover { background: #475569; }
        .btn-back  { background: #475569; color: #e2e8f0; }
        .btn-back:hover { background: #334155; }
        .btn-send  { background: #dc2626; color: white; }
        .btn-send:hover { background: #b91c1c; }
        .btn-send:disabled { background: #64748b; cursor: not-allowed; opacity: 0.7; }

        .status-msg {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            display: none;
        }
        .status-ok  { background: #166534; color: #bbf7d0; display: inline-flex; }
        .status-err { background: #7f1d1d; color: #fecaca; display: inline-flex; }

        iframe { width: 100%; height: calc(100vh - 56px); border: none; display: block; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="dashboard.php" class="btn btn-home">🏠 Dashboard</a>
            <a href="baja_equipos.php" class="btn btn-back">⬅ Nueva Baja</a>
            <span class="toolbar-info">
                Acta de Baja — Lote <strong>#<?= htmlspecialchars($lote) ?></strong>
                — <?= count($equipos) ?> equipo(s)
            </span>
        </div>

        <div class="toolbar-right">
            <span id="statusMsg" class="status-msg"></span>
            <button id="btnSend" class="btn btn-send">📧 Enviar Copia</button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>" type="application/pdf"></iframe>

    <script>
        document.getElementById('btnSend').addEventListener('click', function() {
            const btn = this;
            const msg = document.getElementById('statusMsg');
            const destino = '<?= htmlspecialchars($_SESSION['correo_ldap'] ?? 'su correo') ?>';

            if (!confirm('¿Enviar copia del Acta a ' + destino + '?')) return;

            btn.disabled = true;
            btn.innerHTML = '⏳ Enviando...';

            fetch('generar_acta_baja.php?action=send_mail')
                .then(r => r.text())
                .then(d => {
                    if (d === 'OK') {
                        btn.innerHTML = '✅ Enviado';
                        btn.style.background = '#15803d';
                        msg.className = 'status-msg status-ok';
                        msg.textContent = '✅ Correo enviado correctamente';
                        msg.style.display = 'inline-flex';
                    } else {
                        throw new Error(d);
                    }
                })
                .catch(e => {
                    btn.disabled = false;
                    btn.innerHTML = '❌ Reintentar';
                    msg.className = 'status-msg status-err';
                    msg.textContent = '❌ Error al enviar';
                    msg.style.display = 'inline-flex';
                    alert('Error: ' + e.message);
                });
        });
    </script>
</body>
</html>
<?php } ?>