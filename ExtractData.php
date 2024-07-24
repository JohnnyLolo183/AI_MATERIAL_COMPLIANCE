<?php
// Function to extract text from PDF using pdftotext
function extractTextFromPDF($pdfPath) {
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return $text;
}

// Function to extract relevant data from the PDF content
function extractRelevantData($pdfContent) {
    $data = [];

    // Dummy extraction logic - replace with actual parsing logic
    $data['manufacturer'] = 'Extracted Manufacturer';
    $data['certificate_number'] = 'Extracted Certificate Number';
    $data['heat_number'] = 'Extracted Heat Number';
    $data['material_standard'] = 'Extracted Material Standard';
    $data['material_grade'] = 'Extracted Material Grade';
    $data['material_description'] = 'Extracted Material Description';

    return $data;
}

if (isset($_GET['pdf'])) {
    $pdfPath = urldecode($_GET['pdf']);

    if (file_exists($pdfPath)) {
        $pdfContent = extractTextFromPDF($pdfPath);

        if ($pdfContent) {
            $data = extractRelevantData($pdfContent);
            echo json_encode($data);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to extract text from the certificate PDF.']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'PDF file not found.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No PDF URL provided.']);
}
?>
