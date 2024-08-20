<?php
require '../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class stamp_pdf extends Fpdi
{
}

function stampPDF($filePath, $stampType, $signatureData = null, $isSigned = false, $comment = null) {
    $pdf = new stamp_pdf();
    $pageCount = $pdf->setSourceFile($filePath);

    $applyStamp = !$isSigned && $stampType; // Only apply stamp if not signed and type is provided

    if ($applyStamp) {
        $stampImagePath = ($stampType == 'compliant') ? '../images/compliant.png' : '../images/noncompliant.png';

        if (!file_exists($stampImagePath) || mime_content_type($stampImagePath) !== 'image/png') {
            throw new Exception('Invalid PNG file: ' . $stampImagePath);
        }
    }

    for ($i = 1; $i <= $pageCount; $i++) {
        $templateId = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($templateId);

        $pdf->addPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        if ($applyStamp) {
            // Center the compliant/non-compliant stamp
            $stampWidth = $size['width'] / 2; // Adjust size as needed
            $stampHeight = $size['height'] / 2; // Adjust size as needed
            $x = ($size['width'] - $stampWidth) / 2;
            $y = ($size['height'] - $stampHeight) / 2;
            $pdf->Image($stampImagePath, $x, $y, $stampWidth, $stampHeight);
        }

        if ($signatureData) {
            $signatureFilePath = 'uploads/signature.png';
            $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
            $signatureData = str_replace(' ', '+', $signatureData);
            $signatureImageData = base64_decode($signatureData);

            file_put_contents($signatureFilePath, $signatureImageData);

            // Adjust the size and position of the signature
            $sigWidth = $size['width'] / 6; // Smaller size (1/6th of page width)
            $sigHeight = $sigWidth * 0.5; // Maintain aspect ratio (adjust as necessary)
            $sigX = $size['width'] - $sigWidth - 10; // 10 units from the right edge
            $sigY = $size['height'] - $sigHeight - 10; // 10 units from the bottom edge
            $pdf->Image($signatureFilePath, $sigX, $sigY, $sigWidth, $sigHeight);

            // Clean up the temporary signature image file
            unlink($signatureFilePath);
        }
    }

    // Add a new page with the comment text if provided
    if ($comment) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $comment);
    }

    // Determine the correct prefix based on whether it's signed
    if ($isSigned) {
        $prefix = 'signed_';
        $fileName = basename($filePath);
        // Remove any existing compliant or noncompliant prefix from the filename
        $fileName = preg_replace('/^(compliant_|noncompliant_)/', '', $fileName);
    } else {
        $prefix = ($stampType == 'compliant') ? 'compliant_' : 'noncompliant_';
        $fileName = basename($filePath);
    }
    

    $outputPath = 'uploads/' . $prefix . $fileName;
    $pdf->Output($outputPath, 'F');

    return $outputPath;
}

if (isset($_GET['pdf'])) {
    $filePath = urldecode($_GET['pdf']);
    $stampType = isset($_GET['type']) ? $_GET['type'] : null;
    $signatureData = isset($_GET['signature']) ? $_GET['signature'] : null;
    $isSigned = isset($_GET['signed']) && $_GET['signed'] === 'true';
    $comment = isset($_GET['comment']) ? $_GET['comment'] : null;

    try {
        $outputPath = stampPDF($filePath, $stampType, $signatureData, $isSigned, $comment);
        echo $outputPath;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo "Error: PDF, stamp type, or signature not provided.";
}
