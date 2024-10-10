<?php
require '../vendor/autoload.php';
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

// Set metadata function
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

// Function to sign PDF with digital signature
function signPDFWithDigitalSignature($filePath, $certPath, $privateKeyPath, $password, $metadata = null, $signatureData = null) {
    $pdf = new \setasign\Fpdi\TcpdfFpdi();

    // Validate certificate and key paths
    if (!file_exists($certPath)) {
        die("Error: Certificate file not found at $certPath.");
    }
    if (!file_exists($privateKeyPath)) {
        die("Error: Private key file not found at $privateKeyPath.");
    }

    // Import the existing PDF file
    $pageCount = $pdf->setSourceFile($filePath);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tplIdx = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tplIdx);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tplIdx);
    }

    // Apply signature if data exists
    if ($signatureData) {
        $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
        $signatureImage = base64_decode($signatureData);
        $signaturePath = 'uploads/temp_signature.png';

        if (!file_put_contents($signaturePath, $signatureImage)) {
            die("Error: Signature image could not be saved.");
        }

        $xPos = $size['width'] - 60;
        $yPos = $size['height'] - 40;
        $pdf->Image($signaturePath, $xPos, $yPos, 50, 30);
        unlink($signaturePath); // Clean up temporary file
    }

    // Set metadata if provided
    if ($metadata) {
        setPDFMetadata($pdf, $metadata);
    }

    // Apply digital signature
    $info = [
        'Name' => 'Hera',
        'Location' => 'NZ',
        'Reason' => 'Document Verification',
        'ContactInfo' => 'Info@hera.org.nz'
    ];

    // Add signature appearance
    $pdf->setSignatureAppearance($size['width'] - 60, $size['height'] - 40, 50, 30);

    try {
        $pdf->setSignature("file://" . realpath($certPath), "file://" . realpath($privateKeyPath), $password, '', 2, $info);
    } catch (Exception $e) {
        die("Error applying signature: " . $e->getMessage());
    }

    // Save signed PDF
    $fileInfo = pathinfo($filePath);
    $newFilename = 'signed_' . $fileInfo['filename'] . '.' . $fileInfo['extension'];
    $outputPath = dirname($filePath) . '/' . $newFilename;

    if ($metadata) {
        setPDFMetadata($pdf, $metadata);
    }
    
    // After setting metadata, save the file
    $pdf->Output($outputPath, 'F');

    return $outputPath;
}


function stampPDF($filePath, $stampType, $comment = null, $metadata = null) {
    $pdf = new \setasign\Fpdi\TcpdfFpdi();

    // Import the existing PDF file
    $pageCount = $pdf->setSourceFile($filePath);

    for ($i = 1; $i <= $pageCount; $i++) {
        $tplIdx = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tplIdx);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tplIdx);

        // Apply stamp image based on compliance on the last page
        if ($stampType && $i === $pageCount) {
            $stampImagePath = ($stampType == 'noncompliant') ? '../images/noncompliant.png' : '../images/compliant.png';
            if (!file_exists($stampImagePath)) {
                throw new Exception('Error: Stamp image not found at: ' . $stampImagePath);
            }

            // Calculate center of the last page
            $centerX = ($size['width'] - 60) / 2;  // Adjust for stamp width
            $centerY = ($size['height'] - 60) / 2;  // Adjust for stamp height
            $pdf->Image($stampImagePath, $centerX, $centerY, 60, 60);  // Adjust size as needed
        }
    }

    // Add comment if present (on a new page)
    if ($comment) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(150, 10, $comment, 0, 'L');
    }

    // Output stamped PDF with modified filename
    $outputPath = $filePath; // Initialize outputPath
    $fileInfo = pathinfo($filePath);
    if (!strpos($fileInfo['filename'], '_compliant') && !strpos($fileInfo['filename'], '_noncompliant')) {
        $newFilename = $fileInfo['filename'] . '_' . (($metadata['keywords'] == 'noncompliant') ? 'noncompliant' : 'compliant') . '.' . $fileInfo['extension'];
        $outputPath = dirname($filePath) . '/' . $newFilename;
    }

    if ($metadata) {
    setPDFMetadata($pdf, $metadata);
}

// After setting metadata, save the file
$pdf->Output($outputPath, 'F');

    return $outputPath;
}



// Handle request for stamping and signing PDF
if (isset($_GET['pdf'])) {
    $filePath = urldecode($_GET['pdf']);
    $stampType = isset($_GET['type']) ? $_GET['type'] : null;
    $isSigned = isset($_GET['signed']) && $_GET['signed'] === 'true';
    $comment = isset($_GET['comment']) ? $_GET['comment'] : null;
    $signatureData = isset($_GET['signature']) ? $_GET['signature'] : null;

    if (!$filePath || !file_exists($filePath)) {
        die("Error: PDF file not found at $filePath");
    }

    // Set metadata dynamically based on compliance status
    $metadata = [
        'title' => 'Signed Steel Certificate',
        'author' => 'Hera',
        'subject' => 'Steel Compliance Check',
        'keywords' => ($stampType == 'noncompliant') ? 'noncompliant' : 'compliant'
    ];

    try {
        if (!$isSigned) {
            // Step 1: Stamp the PDF as compliant or non-compliant
            $outputPath = stampPDF($filePath, $stampType, $comment, $metadata);
        } else {
            // Step 2: Sign the PDF with a digital signature
            $certPath = realpath('../certificate.pem');  // Use realpath for absolute path
            $privateKeyPath = realpath('../private-key.pem');  // Use realpath for absolute path

            if (!file_exists($certPath)) {
                die("Error: Certificate file not found at $certPath");
            }
            if (!file_exists($privateKeyPath)) {
                die("Error: Private key file not found at $privateKeyPath");
            }

            $outputPath = signPDFWithDigitalSignature($filePath, $certPath, $privateKeyPath, 'your_password', $metadata, $signatureData);
        }

        if (!$outputPath || !file_exists($outputPath)) {
            die("Error: Output file not created at $outputPath");
        }


        echo $outputPath;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo "Error: PDF, stamp type, or signature not provided.";
}