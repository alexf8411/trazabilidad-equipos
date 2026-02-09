<?php
/**
 * public/generar_acta_baja.php
 * Visor de Acta de Baja Masiva (Sin Precios)
 * Versión Final corregida
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../core/config_mail.php';
require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VALIDACIÓN DE SESIÓN
if (empty($_SESSION['acta_baja_seriales'])) {
    die("<div style='color:white; background:#333; padding:20px; text-align:center; font-family:sans-serif;'>
            <h3>⛔ No hay un lote de bajas activo.</h3>
            <a href='baja_equipos.php' style='color:#4ade80;'>Volver al módulo de bajas</a>
         </div>");
}

$seriales = $_SESSION['acta_baja_seriales'];
$motivo = $_SESSION['acta_baja_motivo'];
$lote = $_SESSION['acta_baja_lote'];
$tecnico = $_SESSION['nombre'];
$action = $_GET['action'] ?? 'view';

// 2. OBTENER DATOS (Sin columna precio)
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

    // Bloque 1: Datos del Lote
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

    // Bloque 2: Tabla de Equipos (SIN PRECIOS)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, utf8_decode('LISTADO DE ACTIVOS (' . count($equipos) . ' Unidades)'), 1, 1, 'L', true);
    
    // Encabezados de tabla (Anchos ajustados: 40+50+100 = 190mm)
    $pdf->SetFillColor(220, 53, 69); // Rojo para Bajas
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
    $pdf->Ln(10); // Espacio después de la tabla

    // Bloque 3: Texto Legal (Lectura Robusta)
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('CERTIFICACIÓN DE DISPOSICIÓN FINAL'), 1, 1, 'L', true);
    
    // Ruta del archivo de texto
    $archivo_texto = '../core/acta_baja.txt';
    $texto_legal = "";

    if (file_exists($archivo_texto)) {
        $texto_legal = file_get_contents($archivo_texto);
    }

    // Si el archivo está vacío o no existe, usamos fallback
    if (empty($texto_legal)) {
        $texto_legal = "CERTIFICACIÓN: Los equipos listados han sido retirados del inventario por obsolescencia o falla. (Nota: Configure este texto en el módulo de Configuración).";
    }

    $pdf->MultiCell(0, 6, utf8_decode($texto_legal), 0, 'J');
    $pdf->Ln(20);

    // Firmas
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

        $mail->setFrom(SMTP_USER, 'URTRACK Bajas');
        
        // En Bajas, el correo va al usuario logueado
        $destinatario = $_SESSION['correo_ldap'] ?? SMTP_USER;
        $mail->addAddress($destinatario);
        
        $mail->addStringAttachment($pdfContent, 'Acta_Baja_Lote_' . $lote . '.pdf');

        $mail->isHTML(true);
        $mail->Subject = 'URTRACK: Acta de Baja Masiva #' . $lote;
        $mail->Body    = 'Buen día,<br><br>Adjunto encontrará el acta técnica de la baja realizada.<br>' .
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
    <title>Vista Previa Acta Baja</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background: #525659; overflow: hidden; }
        .toolbar { background: #323639; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: 0.2s; }
        .btn-send { background: #dc3545; color: white; }
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
            <a href="baja_equipos.php" class="btn btn-back">⬅ Cerrar / Nueva Baja</a>
            <span style="margin-left: 20px; color:#cbd5e1; font-weight:bold;">ACTA DE BAJA #<?= $lote ?></span>
        </div>
        
        <div style="display:flex; align-items:center;">
            <span id="statusMsg" class="status-msg"></span>
            <button id="btnSend" onclick="enviarCorreo()" class="btn btn-send">
                📧 Enviar Copia
            </button>
        </div>
    </div>

    <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>" type="application/pdf"></iframe>

    <script>
        function enviarCorreo() {
            const btn = document.getElementById('btnSend');
            const msg = document.getElementById('statusMsg');
            
            if(!confirm('¿Enviar copia del Acta a <?= $_SESSION['correo_ldap'] ?? 'su correo' ?>?')) return;

            btn.disabled = true;
            btn.innerHTML = '⏳ Enviando...';
            msg.innerHTML = '';

            fetch('generar_acta_baja.php?action=send_mail')
                .then(response => {
                    if (response.ok) {
                        btn.innerHTML = '✅ Enviado';
                        btn.style.background = '#198754';
                        msg.innerHTML = 'Acta enviada correctamente.';
                        msg.style.color = '#4ade80';
                    } else {
                        return response.text().then(text => { throw new Error(text) });
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '❌ Reintentar';
                    btn.style.background = '#ef4444';
                    msg.innerHTML = 'Error de envío.';
                    console.error(error);
                    alert("Error: " + error.message);
                });
        }
    </script>
</body>
</html>
<?php } ?>