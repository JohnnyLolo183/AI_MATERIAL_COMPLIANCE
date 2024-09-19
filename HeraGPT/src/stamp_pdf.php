<?php
require '../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

class stamp_pdf extends Fpdi {}

// Function to digitally sign the PDF
function signPDF($pdfContent, $privateKeyPath) {
    $pdfHash = hash('sha256', $pdfContent);

    if (!file_exists($privateKeyPath)) {
        throw new Exception("Error: Private key file not found at: " . $privateKeyPath);
    }

    $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));

    if (!$privateKey) {
        throw new Exception("Error: Could not load private key from: " . $privateKeyPath);
    }

    $signature = '';
    if (!openssl_sign($pdfHash, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new Exception("Error: Could not sign the PDF content using OpenSSL.");
    }

    return base64_encode($signature);
}

// Function to set metadata
function setPDFMetadata($pdf, $metadata) {
    if (isset($metadata['title'])) {
        $pdf->SetTitle($metadata['title']);
    }
    if (isset($metadata['author'])) {
        $pdf->SetAuthor($metadata['author']);
    }
    if (isset($metadata['subject'])) {
        $pdf->SetSubject($metadata['subject']);
    }
    if (isset($metadata['keywords'])) {
        $pdf->SetKeywords($metadata['keywords']);
    }
}

// Function to stamp the PDF as compliant or noncompliant
// Function to stamp the PDF as compliant or noncompliant
function stampPDF($filePath, $stampType, $comment = null, $metadata = null) {
    $pdf = new stamp_pdf();

    if (!file_exists($filePath)) {
        throw new Exception("Error: PDF file not found at: " . $filePath);
    }

    $pageCount = $pdf->setSourceFile($filePath);
    $applyStamp = $stampType !== null;

    // Set metadata if provided
    if ($metadata) {
        setPDFMetadata($pdf, $metadata);
    }

    if ($applyStamp) {
        $stampImagePath = ($stampType == 'compliant') ? '../images/compliant.png' : '../images/noncompliant.png';
        if (!file_exists($stampImagePath) || mime_content_type($stampImagePath) !== 'image/png') {
            throw new Exception('Invalid PNG file: ' . $stampImagePath);
        }
    }

    // Step 1: Create the stamped PDF file
    for ($i = 1; $i <= $pageCount; $i++) {
        $templateId = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($templateId);

        $pdf->addPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);

        if ($applyStamp) {
            $stampWidth = $size['width'] / 4; // Smaller size
            $stampHeight = $size['height'] / 4; // Maintain aspect ratio
            $x = ($size['width'] - $stampWidth) / 2;
            $y = ($size['height'] - $stampHeight) / 2;
            $pdf->Image($stampImagePath, $x, $y, $stampWidth, $stampHeight);
        }
    }

    if ($comment) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 10, $comment);
    }

    // Check if the file already has a `signed_`, `compliant_`, or `noncompliant_` prefix
    $fileName = basename($filePath);
    if (strpos($fileName, 'signed_') === false && strpos($fileName, 'compliant_') === false && strpos($fileName, 'noncompliant_') === false) {
        // If no prefix exists, add one based on the `stampType`
        $prefix = ($stampType == 'compliant') ? 'compliant_' : 'noncompliant_';
        $fileName = $prefix . $fileName;
    }

    $outputPath = 'uploads/' . $fileName;
    $pdf->Output($outputPath, 'F'); // Save to the uploads folder

    return $outputPath;
}


// Function to add the signature to the stamped PDF
function addSignatureToPDF($filePath, $signatureData, $metadata = null) {
    $pdf = new stamp_pdf();
    $pageCount = $pdf->setSourceFile($filePath);

    // Set metadata if provided
    if ($metadata) {
        setPDFMetadata($pdf, $metadata);
    }

    for ($i = 1; $i <= $pageCount; $i++) {
        $templateId = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($templateId);

        $pdf->addPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId);
    }

    if ($signatureData) {
        $signatureFilePath = 'uploads/signature.png';
        $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
        $signatureData = str_replace(' ', '+', $signatureData);
        $signatureImageData = base64_decode($signatureData);

        file_put_contents($signatureFilePath, $signatureImageData);

        // Adjust the size and position of the signature
        $sigWidth = $size['width'] / 6;
        $sigHeight = $sigWidth * 0.5;
        $sigX = $size['width'] - $sigWidth - 10;
        $sigY = $size['height'] - $sigHeight - 10;
        $pdf->Image($signatureFilePath, $sigX, $sigY, $sigWidth, $sigHeight);

        unlink($signatureFilePath); // Clean up temporary signature image
    }

    // Ensure the correct prefix is maintained and only add `signed_`
    $signedFilePath = 'signed_' . basename($filePath);

    // Save the signed PDF with the correct file name
    $signedOutputPath = 'uploads/' . $signedFilePath;
    $pdf->Output($signedOutputPath, 'F');

    return $signedOutputPath;
}

// Handle request for stamping and signing PDF
if (isset($_GET['pdf'])) {
    $filePath = urldecode($_GET['pdf']);
    $stampType = isset($_GET['type']) ? $_GET['type'] : null;
    $signatureData = isset($_GET['signature']) ? $_GET['signature'] : null;
    $isSigned = isset($_GET['signed']) && $_GET['signed'] === 'true';
    $comment = isset($_GET['comment']) ? $_GET['comment'] : null;

    // Correctly preserve the compliance status for keywords
if (strpos($filePath, 'noncompliant_') !== false) {
    $complianceStatus = 'noncompliant';
} else {
    // If 'noncompliant_' is not found, assume 'compliant'
    $complianceStatus = 'compliant';
}

    // Set metadata dynamically based on compliance status
    $metadata = [
        'title' => 'Signed Steel Certificate',
        'author' => 'Hera',
        'subject' => 'Steel Compliance Check',
        'keywords' => $complianceStatus
    ];

    try {
        if (!$isSigned) {
            // Step 1: Stamp the PDF as compliant or non-compliant
            $outputPath = stampPDF($filePath, $stampType, $comment, $metadata);
        } else {
            // Step 2: Add signature to the stamped PDF
            $outputPath = addSignatureToPDF($filePath, $signatureData, $metadata);
        }
        echo $outputPath;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo "Error: PDF, stamp type, or signature not provided.";
}
