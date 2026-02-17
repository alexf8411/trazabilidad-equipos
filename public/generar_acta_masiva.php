<?php
/**
 * public/generar_acta_masiva.php 
 * Genera el Manifiesto de Entrega usando texto configurable
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['serials'])) die("<div style='padding:20px; color:red; font-family:sans-serif'>Error: Faltan datos de equipos.</div>");

$serials_input = explode(',', $_GET['serials']);
$action = $_GET['action'] ?? 'view';
$serials = array_map('trim', $serials_input);
$placeholders = str_repeat('?,', count($serials) - 1) . '?';

// Consultar — sede y nombre vienen de tabla lugares via JOIN
$sql = "SELECT e.serial, e.placa_ur, e.marca, e.modelo, 
               b.hostname, b.fecha_evento, b.tipo_evento,
               b.correo_responsable, b.responsable_secundario, b.tecnico_responsable,
               l.sede AS sede_lugar,
               l.nombre AS nombre_lugar
        FROM equipos e
        JOIN bitacora b ON e.serial = b.serial_equipo
        LEFT JOIN lugares l ON b.id_lugar = l.id
        WHERE e.serial IN ($placeholders)
        AND b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = e.serial)
        ORDER BY e.placa_ur ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($serials);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) die("Error: No se encontraron registros.");
$head = $rows[0];

// Clase PDF
class PDF_Masivo extends \FPDF {
    function Header() {
        if(file_exists('img/logo_ur.png')) $this->Image('img/logo_ur.png', 10, 8, 33);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'MANIFIESTO DE ENTREGA MASIVA', 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('URTRACK - Control de Inventarios - Hoja ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    function TableHeader() {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(10, 7, '#', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Placa UR', 1, 0, 'C', true);
        $this->Cell(35, 7, 'Serial', 1, 0, 'C', true);
        $this->Cell(60, 7, 'Equipo (Marca/Modelo)', 1, 0, 'L', true);
        $this->Cell(60, 7, 'Hostname', 1, 1, 'L', true);
    }
}

function construirPDFMasivo($rows, $head) {
    $pdf = new PDF_Masivo();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // 1. Datos Transacción
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('1. DATOS DE LA TRANSACCIÓN'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 6, 'Fecha:', 1);
    $pdf->SetFont('Arial', '', 9);  $pdf->Cell(65, 6, $head['fecha_evento'], 1);
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 6, 'Tipo:', 1);
    $pdf->SetFont('Arial', '', 9);  $pdf->Cell(65, 6, 'ASIGNACION MASIVA', 1, 1);
    
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 6, 'Sede:', 1);
    $pdf->SetFont('Arial', '', 9);  $pdf->Cell(65, 6, utf8_decode($head['sede_lugar'] ?? ''), 1);
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(30, 6, utf8_decode('Ubicación:'), 1);
    $pdf->SetFont('Arial', '', 9);  $pdf->Cell(65, 6, utf8_decode($head['nombre_lugar'] ?? ''), 1, 1);
    $pdf->Ln(5);

    // 2. Responsables
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode('2. RESPONSABILIDAD'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'Responsable:', 1);
    $pdf->SetFont('Arial', '', 9);  $pdf->Cell(145, 6, $head['correo_responsable'], 1, 1);
    if (!empty($head['responsable_secundario'])) {
        $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'Resp. Secundario:', 1);
        $pdf->SetFont('Arial', '', 9);  $pdf->Cell(145, 6, $head['responsable_secundario'], 1, 1);
    }
    $pdf->Ln(5);

    // 3. Tabla de Equipos
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode('3. DETALLE DE ACTIVOS (' . count($rows) . ')'), 1, 1, 'L', true);
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 8);

    $count = 1;
    foreach ($rows as $row) {
        $pdf->Cell(10, 6, $count++, 1, 0, 'C');
        $pdf->Cell(25, 6, $row['placa_ur'], 1, 0, 'C');
        $pdf->Cell(35, 6, $row['serial'], 1, 0, 'C');
        $pdf->Cell(60, 6, utf8_decode(substr($row['marca'] . ' ' . $row['modelo'], 0, 38)), 1, 0, 'L');
        $pdf->Cell(60, 6, utf8_decode(substr($row['hostname'], 0, 38)), 1, 1, 'L');
    }
    $pdf->Ln(10);

    // 4. Texto Legal
    $texto_legal = file_get_contents('../core/acta_masiva.txt');
    if(!$texto_legal) $texto_legal = "El usuario declara recibir los equipos listados..."; 
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 4, utf8_decode($texto_legal), 0, 'J');
    $pdf->Ln(15);

    // Firmas
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 5, 'Firma Responsable', 'T', 0, 'C');
    $pdf->Cell(10, 5, '', 0);
    $pdf->Cell(90, 5, utf8_decode('Firma Dirección de Tecnología'), 'T', 0, 'C');

    return $pdf;
}

if ($action == 'download') {
    $pdf = construirPDFMasivo($rows, $head);
    $pdf->Output('D', 'Acta_Masiva_' . date('Ymd_His') . '.pdf');
    exit;
}

if ($action == 'send_mail') {
    $pdf = construirPDFMasivo($rows, $head);
    $pdfContent = $pdf->Output('S'); 
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = SMTP_PORT;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($head['correo_responsable']);
        $mail->addStringAttachment($pdfContent, 'Acta_Masiva_URTRACK.pdf');
        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta Masiva - ' . count($rows) . ' Activos';
        $mail->Body    = 'Buen día,<br><br>Adjunto Manifiesto de Entrega.<br><br>Atentamente,<br>URTRACK';
        $mail->send(); echo "OK"; 
    } catch (Exception $e) { http_response_code(500); echo $mail->ErrorInfo; }
    exit;
}

if ($action == 'view') {
    $pdf = construirPDFMasivo($rows, $head);
    $pdfBase64 = base64_encode($pdf->Output('S'));
    $serials_str = htmlspecialchars($_GET['serials']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Previa Masiva | URTRACK</title>
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
        .btn-down  { background: #0ea5e9; color: white; }
        .btn-down:hover { background: #0284c7; }
        .btn-send  { background: #22c55e; color: white; }
        .btn-send:hover { background: #16a34a; }
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
            <a href="asignacion_masiva.php" class="btn btn-back">⬅ Volver</a>
            <span class="toolbar-info">
                Manifiesto Masivo — <strong><?= count($rows) ?> equipos</strong>
            </span>
        </div>

        <div class="toolbar-right">
            <span id="statusMsg" class="status-msg"></span>
            <a href="generar_acta_masiva.php?serials=<?= $serials_str ?>&action=download" class="btn btn-down">
                ⬇ Descargar PDF
            </a>
            <button id="btnSend" class="btn btn-send">📧 Enviar</button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>#toolbar=0&navpanes=0&view=FitH"></iframe>

    <script>
        document.getElementById('btnSend').addEventListener('click', function() {
            const btn = this;
            const msg = document.getElementById('statusMsg');

            if (!confirm('¿Enviar el Manifiesto al responsable?')) return;

            btn.disabled = true;
            btn.innerHTML = '⏳ Enviando...';

            fetch('generar_acta_masiva.php?serials=<?= $serials_str ?>&action=send_mail')
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
                    btn.innerHTML = '📧 Reintentar';
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