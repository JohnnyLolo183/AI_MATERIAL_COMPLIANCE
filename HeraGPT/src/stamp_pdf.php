<?php
require '../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class stamp_pdf extends Fpdi
{
}

function stampPDF($filePath, $stampType, $signatureData = null, $isSigned = false) {
    $pdf = new stamp_pdf();
    $pageCount = $pdf->setSourceFile($filePath);

    // Only apply the stamp if the document is not being signed
    if (!$isSigned) {
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

        // Only apply the stamp if the document is not being signed
        if (!$isSigned) {
            // Center the compliant/non-compliant stamp
            $stampWidth = $size['width'] / 2; // Adjust size as needed
            $stampHeight = $size['height'] / 2; // Adjust size as needed
            $x = ($size['width'] - $stampWidth) / 2;
            $y = ($size['height'] - $stampHeight) / 2;
            $pdf->Image($stampImagePath, $x, $y, $stampWidth, $stampHeight);
        }

        // Add the signature image if provided
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

    // Determine the correct prefix based on whether it's signed
    $prefix = ($stampType == 'compliant') ? 'compliant_' : 'noncompliant_';
    if ($isSigned) {
        $prefix = 'signed_';
        // Remove any existing compliant or noncompliant prefix from the filename
        $fileName = basename($filePath);
        $fileName = preg_replace('/^(compliant_|noncompliant_)/', '', $fileName);
    } else {
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

    try {
        $outputPath = stampPDF($filePath, $stampType, $signatureData, $isSigned);
        echo $outputPath;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo "Error: PDF, stamp type, or signature not provided.";
}
