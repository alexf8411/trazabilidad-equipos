<?php
/**
 * public/generar_acta_baja.php
 * Genera un PDF consolidado con los equipos dados de baja en el lote actual.
 */
require_once '../core/db.php';
require_once '../core/session.php';
require_once '../vendor/autoload.php'; // Asegúrate de tener FPDF o usar la clase manual si no usas composer

// 1. VALIDAR DATOS EN SESIÓN
if (empty($_SESSION['acta_baja_seriales']) || empty($_SESSION['acta_baja_motivo'])) {
    die("Error: No hay datos de baja recientes en la sesión. <a href='baja_equipos.php'>Volver</a>");
}

$seriales = $_SESSION['acta_baja_seriales'];
$motivo = $_SESSION['acta_baja_motivo'];
$lote = $_SESSION['acta_baja_lote'] ?? date('Ymd');
$tecnico = $_SESSION['nombre'];

// 2. RECUPERAR DETALLES DE LOS EQUIPOS
// Creamos placeholders (?,?,?) para la consulta IN
$placeholders = str_repeat('?,', count($seriales) - 1) . '?';
$sql = "SELECT placa_ur, serial, marca, modelo, precio, vida_util 
        FROM equipos WHERE serial IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($seriales);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. CLASE PDF PERSONALIZADA
class PDF_Baja extends \FPDF {
    function Header() {
        if(file_exists('img/logo_ur.png')) { 
            $this->Image('img/logo_ur.png', 10, 8, 33);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, utf8_decode('ACTA DE BAJA DE ACTIVOS FIJOS'), 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Generado por URTRACK - Universidad del Rosario - Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

// 4. CONSTRUCCIÓN DEL PDF
$pdf = new PDF_Baja();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Encabezado del Acta
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, utf8_decode('DATOS GENERALES DE LA OPERACIÓN'), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(40, 8, 'Fecha:', 1);
$pdf->Cell(55, 8, date('Y-m-d H:i:s'), 1);
$pdf->Cell(40, 8, 'Ref. Lote:', 1);
$pdf->Cell(55, 8, $lote, 1, 1);

$pdf->Cell(40, 8, utf8_decode('Técnico Resp:'), 1);
$pdf->Cell(150, 8, utf8_decode($tecnico), 1, 1);

$pdf->Cell(40, 8, 'Concepto/Motivo:', 1);
$pdf->Cell(150, 8, utf8_decode($motivo), 1, 1);
$pdf->Ln(5);

// Tabla de Equipos
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, utf8_decode('DETALLE DE ACTIVOS DADOS DE BAJA (' . count($equipos) . ' Unidades)'), 0, 1, 'L');

$pdf->SetFillColor(220, 53, 69); // Rojo Baja
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(30, 8, 'Placa UR', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Serial', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'Marca / Modelo', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Valor Libros', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

$total_valor = 0;

foreach ($equipos as $eq) {
    $pdf->Cell(30, 7, utf8_decode($eq['placa_ur']), 1);
    $pdf->Cell(40, 7, utf8_decode($eq['serial']), 1);
    $pdf->Cell(80, 7, utf8_decode(substr($eq['marca'] . ' ' . $eq['modelo'], 0, 45)), 1);
    $pdf->Cell(40, 7, '$ ' . number_format($eq['precio'], 0, ',', '.'), 1, 1, 'R');
    $total_valor += $eq['precio'];
}

// Total
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(150, 8, 'TOTAL VALOR DADO DE BAJA:', 1, 0, 'R');
$pdf->Cell(40, 8, '$ ' . number_format($total_valor, 0, ',', '.'), 1, 1, 'R');

$pdf->Ln(10);

// Texto Legal
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, utf8_decode("CERTIFICACIÓN:\nPor medio del presente documento se certifica que los equipos listados anteriormente han sido retirados del inventario activo de la Universidad por encontrarse en estado de obsolescencia técnica, falla irreparable o hurto, según el concepto técnico adjunto. Estos activos quedan a disposición del área de Activos Fijos para su disposición final conforme a la normativa ambiental vigente."), 0, 'J');

$pdf->Ln(20);

// Firmas
$pdf->Cell(80, 0, '', 'T'); 
$pdf->Cell(30, 0, '', 0);
$pdf->Cell(80, 0, '', 'T');
$pdf->Ln(2);
$pdf->Cell(80, 5, utf8_decode('Firma Técnico Responsable'), 0, 0, 'C');
$pdf->Cell(30, 5, '', 0);
$pdf->Cell(80, 5, utf8_decode('Firma Dirección de Activos Fijos'), 0, 0, 'C');

$pdf->Output('I', 'Acta_Baja_Lote_' . $lote . '.pdf');
?>