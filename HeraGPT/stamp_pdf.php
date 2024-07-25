<?php
require 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class PdfWithStamp extends Fpdi
{
}

function stampPDF($filePath, $stampType) {
    $pdf = new PdfWithStamp();
    $pageCount = $pdf->setSourceFile($filePath);

    $stampImagePath = ($stampType == 'compliant') ? 'images/compliant.png' : 'images/noncompliant.png';

    if (!file_exists($stampImagePath) || mime_content_type($stampImagePath) !== 'image/png') {
        throw new Exception('Invalid PNG file: ' . $stampImagePath);
    }

    for ($i = 1; $i <= $pageCount; $i++) {
        $templateId = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($templateId);

        $pdf->addPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        // Center the stamp and make it large
        $stampWidth = $size['width'] / 2; // Adjust the size as needed
        $stampHeight = $size['height'] / 2; // Adjust the size as needed
        $x = ($size['width'] - $stampWidth) / 2;
        $y = ($size['height'] - $stampHeight) / 2;

        $pdf->Image($stampImagePath, $x, $y, $stampWidth, $stampHeight);
    }

    $prefix = ($stampType == 'compliant') ? 'compliant_' : 'noncompliant_';
    $outputPath = 'uploads/' . $prefix . basename($filePath);
    $pdf->Output($outputPath, 'F');

    return $outputPath;
}

if (isset($_GET['pdf']) && isset($_GET['type'])) {
    $filePath = urldecode($_GET['pdf']);
    $stampType = $_GET['type'];

    try {
        $outputPath = stampPDF($filePath, $stampType);
        // Return the output path instead of redirecting immediately
        echo $outputPath;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo "PDF or stamp type not provided.<br>";
    echo "PDF: " . isset($_GET['pdf']) . "<br>";
    echo "Type: " . isset($_GET['type']) . "<br>";
}
?>
