<?php
require '../vendor/autoload.php';
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

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

function signPDFWithDigitalSignature($filePath, $certPath, $privateKeyPath, $password, $metadata = null, $signatureData = null) {
    $pdf = new \setasign\Fpdi\TcpdfFpdi();

    // Check paths and permissions
    if (!file_exists($certPath)) {
        die("Error: Certificate file not found at $certPath.");
    }
    if (!file_exists($privateKeyPath)) {
        die("Error: Private key file not found at $privateKeyPath.");
    }

    // Import the existing PDF file using FPDI
    $pageCount = $pdf->setSourceFile($filePath);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tplIdx = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tplIdx);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tplIdx);
    }

    // Apply signature image at bottom-right corner on the last page (no new page)
    if ($signatureData) {
        $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
        $signatureImage = base64_decode($signatureData);
        $signaturePath = 'uploads/temp_signature.png';

        if (!file_put_contents($signaturePath, $signatureImage)) {
            die("Error: Signature image could not be saved.");
        }

        if (!file_exists($signaturePath)) {
            die("Error: Signature image file not found.");
        }

        // Apply signature to the last page only
        $xPos = $size['width'] - 60;  // Adjust to fit within the right margin
        $yPos = $size['height'] - 40; // Adjust to fit just above the bottom margin
        $pdf->Image($signaturePath, $xPos, $yPos, 50, 30);  // Adjust size as necessary
    }

    // Apply metadata if present
    if ($metadata) {
        setPDFMetadata($pdf, $metadata);
    }

    // Set document signature information (TCPDF features)
    $info = [
        'Name' => 'Hera',  // Signer's name
        'Location' => 'NZ',
        'Reason' => 'Document Verification',
        'ContactInfo' => 'Info@hera.org.nz'
    ];

    // Set signature appearance (position - bottom-right on the last page)
    $pdf->setSignatureAppearance($size['width'] - 60, $size['height'] - 40, 50, 30);  // Adjust coordinates

    // **Important: Move metadata setting after signature application**
    // This ensures the metadata is embedded within the signed PDF
    try {
        $pdf->setSignature("file://" . realpath($certPath), "file://" . realpath($privateKeyPath), $password, '', 2, $info);
    } catch (Exception $e) {
        die("Error applying signature: " . $e->getMessage());
    }

    // Output signed PDF with modified filename
    $fileInfo = pathinfo($filePath);
    $newFilename = 'signed_' . $fileInfo['filename'] . '.' . $fileInfo['extension'];
    $outputPath = dirname($filePath) . '/' . $newFilename;

    $pdf->Output($outputPath, 'F');

    return $outputPath; // Return the correct signed PDF path only
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

    // Save the stamped PDF
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