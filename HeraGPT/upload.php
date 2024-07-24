<?php
require 'load_env.php'; // Include your custom environment loader

// Load the environment variables
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    echo "Error loading .env file: " . $e->getMessage();
    exit;
}

// Handle file upload
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
            echo "Uploaded file is not a PDF.";
            exit;
        }

        // Redirect to DataExtract.html with the PDF URL as a query parameter
        header('Location: DataExtract.html?pdf=' . urlencode($uploadFile));
        exit;
    } else {
        echo "Failed to move uploaded file.";
    }
} else {
    echo "File upload failed with error code: " . $_FILES['pdfFile']['error'];
}
?>
