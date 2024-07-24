<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Function to extract text from PDF using pdftotext
    function extractTextFromPDF($pdfPath) {
        $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
        shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
        $text = file_get_contents($outputFile);
        unlink($outputFile);
        return $text;
    }

    // Handle file upload and data extraction
    if ($_FILES['pdfFile']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $uploadFile = $uploadDir . basename($_FILES['pdfFile']['name']);

        // Ensure the uploads directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['pdfFile']['tmp_name'], $uploadFile)) {
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $uploadFile);
            finfo_close($fileInfo);

            if ($mimeType !== 'application/pdf') {
                http_response_code(400);
                echo "Uploaded file is not a PDF.";
                exit;
            }

            // Redirect to DataExtract.html with the PDF URL as a query parameter
            header('Location: DataExtract.html?pdf=' . urlencode($uploadFile));
            exit;
        } else {
            http_response_code(500);
            echo "Failed to move uploaded file.";
        }
    } else {
        http_response_code(400);
        echo "File upload failed with error code: " . $_FILES['pdfFile']['error'];
    }
} else {
    http_response_code(405);
    echo "Method not allowed";
}
