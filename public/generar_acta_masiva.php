<?php
/**
 * public/generar_acta_masiva.php
 * Generación de Acta Masiva (Manifiesto de Entrega)
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDACIÓN DE ENTRADA
if (!isset($_GET['serials'])) {
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Error: No se han especificado equipos.</div>");
}

$serials_input = explode(',', $_GET['serials']);
$action = $_GET['action'] ?? 'view';

// Limpiar y preparar seriales para la query IN (?,?,?)
$serials = array_map('trim', $serials_input);
$placeholders = str_repeat('?,', count($serials) - 1) . '?';

// 2. OBTENER DATOS MASIVOS
// Obtenemos el último movimiento de cada serial solicitado
$sql = "SELECT e.serial, e.placa_ur, e.marca, e.modelo, 
               b.hostname, b.fecha_evento, b.tipo_evento, b.sede, b.ubicacion, 
               b.correo_responsable, b.responsable_secundario, b.tecnico_responsable,
               l.nombre as nombre_lugar
        FROM equipos e
        JOIN bitacora b ON e.serial = b.serial_equipo
        LEFT JOIN lugares l ON b.id_lugar = l.id
        WHERE e.serial IN ($placeholders)
        AND b.id_evento = (SELECT MAX(id_evento) FROM bitacora b2 WHERE b2.serial_equipo = e.serial)
        ORDER BY e.placa_ur ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($serials);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die("<div style='color:red; text-align:center; margin-top:50px; font-family:sans-serif;'>Error: No se encontraron datos para los seriales suministrados.</div>");
}

// Extraemos datos comunes del primer registro (Asumimos que en carga masiva comparten responsable y lugar)
$head = $rows[0];

// 3. CLASE PDF EXTENDIDA
class PDF_Masivo extends \FPDF {
    function Header() {
        if(file_exists('img/logo_ur.png')) { 
            $this->Image('img/logo_ur.png', 10, 8, 33);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'MANIFIESTO DE ENTREGA MASIVA', 0, 0, 'C');
        $this->Ln(20);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('URTRACK - Control de Inventarios - Hoja ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Cabecera de la tabla
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

// 4. FUNCIÓN CONSTRUCTORA
function construirPDFMasivo($rows, $head) {
    $pdf = new PDF_Masivo();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // --- BLOQUE 1: DATOS GENERALES ---
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(0, 8, utf8_decode('1. DATOS DE LA TRANSACCIÓN Y UBICACIÓN'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, 'Fecha:', 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(65, 6, $head['fecha_evento'], 1);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, 'Tipo Evento:', 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(65, 6, 'ASIGNACION MASIVA', 1, 1); // Forzamos el texto

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, 'Sede:', 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(65, 6, utf8_decode($head['sede']), 1);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 6, utf8_decode('Ubicación:'), 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(65, 6, utf8_decode($head['ubicacion']), 1, 1);
    
    $pdf->Ln(5);

    // --- BLOQUE 2: RESPONSABLES ---
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode('2. RESPONSABILIDAD'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(45, 6, 'Responsable Principal:', 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(145, 6, $head['correo_responsable'], 1, 1);

    if (!empty($head['responsable_secundario'])) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 6, 'Responsable Secundario:', 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(145, 6, $head['responsable_secundario'], 1, 1);
    }
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(45, 6, utf8_decode('Técnico que entrega:'), 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(145, 6, utf8_decode($head['tecnico_responsable']), 1, 1);
    
    $pdf->Ln(5);

    // --- BLOQUE 3: LISTADO DE EQUIPOS ---
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, utf8_decode('3. DETALLE DE ACTIVOS ASIGNADOS (' . count($rows) . ' Equipos)'), 1, 1, 'L', true);
    
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 8);

    $fill = false;
    $count = 1;
    foreach ($rows as $row) {
        $pdf->Cell(10, 6, $count++, 1, 0, 'C', $fill);
        $pdf->Cell(25, 6, $row['placa_ur'], 1, 0, 'C', $fill);
        $pdf->Cell(35, 6, $row['serial'], 1, 0, 'C', $fill);
        $pdf->Cell(60, 6, utf8_decode(substr($row['marca'] . ' ' . $row['modelo'], 0, 38)), 1, 0, 'L', $fill);
        $pdf->Cell(60, 6, utf8_decode(substr($row['hostname'], 0, 38)), 1, 1, 'L', $fill);
        //$fill = !$fill; // Alternar color si se desea
    }
    $pdf->Ln(10);

    // --- BLOQUE LEGAL Y FIRMAS ---
    $texto_legal = file_get_contents('../core/acta_legal.txt');
    if(!$texto_legal) $texto_legal = "El usuario responsable declara recibir los equipos listados en condiciones funcionales y asume la responsabilidad de su custodia..."; 
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 4, utf8_decode($texto_legal), 0, 'J');
    $pdf->Ln(15);

    // Firmas
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 0, '', 'T'); 
    $pdf->Cell(10, 0, '', 0);
    $pdf->Cell(90, 0, '', 'T');
    $pdf->Ln(2);
    $pdf->Cell(90, 5, 'Firma Responsable', 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0);
    $pdf->Cell(90, 5, utf8_decode('Firma Dirección de Tecnología'), 0, 0, 'C');

    return $pdf;
}

// 5. LÓGICA DE SALIDA
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
        // Configuración SMTP (Igual que el original)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($head['correo_responsable']);
        
        $mail->addStringAttachment($pdfContent, 'Acta_Masiva_URTRACK.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Entrega Masiva - ' . count($rows) . ' Activos';
        $mail->Body    = 'Buen día,<br><br>Adjunto encontrará el <b>Acta de Entrega Masiva</b> correspondiente a la asignación de ' . count($rows) . ' equipos en la ubicación <b>' . $head['ubicacion'] . '</b>.<br><br>Atentamente,<br>Dirección de Tecnología - UR';

        $mail->send();
        echo "OK"; 
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error Mailer: {$mail->ErrorInfo}";
    }
    exit;
}

// 6. VISTA PREVIA HTML
if ($action == 'view') {
    $pdf = construirPDFMasivo($rows, $head);
    $pdfBase64 = base64_encode($pdf->Output('S'));
    $serials_str = $_GET['serials']; // Mantener la cadena para pasarla
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vista Previa Masiva</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 40px; }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .btn-send { background: #22c55e; color: white; }
        .btn-send:hover { background: #16a34a; }
        .btn-back { background: #64748b; color: white; }
        .btn-down { background: #0ea5e9; color: white; }
        iframe { width: 100%; height: calc(100vh - 60px); border: none; display: block; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <a href="asignacion_masiva.php" class="btn btn-back">⬅ Volver</a>
            <span style="margin-left: 20px; color:#cbd5e1;">Manifiesto Masivo (<?= count($rows) ?> Equipos)</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <span id="statusMsg" style="color: white; font-weight: bold;"></span>
            <a href="generar_acta_masiva.php?serials=<?= $serials_str ?>&action=download" class="btn btn-down">⬇ PDF</a>
            <button id="btnSend" class="btn btn-send">📧 Enviar a <?= $head['correo_responsable'] ?></button>
        </div>
    </div>
    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>#toolbar=0&navpanes=0&view=FitH"></iframe>

    <script>
        document.getElementById('btnSend').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Enviando...';
            btn.disabled = true;

            fetch('generar_acta_masiva.php?serials=<?= $serials_str ?>&action=send_mail')
            .then(response => response.text())
            .then(data => {
                if (data === 'OK') {
                    btn.style.background = '#15803d'; // Verde oscuro
                    btn.innerHTML = '✅ Enviado';
                    document.getElementById('statusMsg').innerText = 'Correo enviado con éxito.';
                } else {
                    throw new Error(data);
                }
            })
            .catch(error => {
                alert('Error al enviar: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
<?php } ?>