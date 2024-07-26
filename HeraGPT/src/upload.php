<?php
require 'load_env.php'; // Include your custom environment loader

// Load the environment variables
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    echo "Error loading .env file: " . $e->getMessage();
    exit;
}

// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for file upload errors
    if (isset($_FILES['pdfFile'])) {
        $fileError = $_FILES['pdfFile']['error'];

        if ($fileError == UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            $uploadFile = $uploadDir . basename($_FILES['pdfFile']['name']);
            $maxFileSize = 10 * 1024 * 1024; // 10 MB

            // Ensure the uploads directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Check file size
            if ($_FILES['pdfFile']['size'] > $maxFileSize) {
                echo "File exceeds the maximum allowed size of 10 MB.";
                exit;
            }

            // Move the uploaded file
            if (move_uploaded_file($_FILES['pdfFile']['tmp_name'], $uploadFile)) {
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($fileInfo, $uploadFile);
                finfo_close($fileInfo);

                // Verify the file is a PDF
                $allowedMimeTypes = ['application/pdf'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    echo "Uploaded file is not a PDF.";
                    exit;
                }

                // Redirect to extract.html with the PDF URL as a query parameter
                header('Location: extract.html?pdf=' . urlencode($uploadFile));
                exit;
            } else {
                echo "Failed to move uploaded file.";
                exit;
            }
        } else {
            // Handle different file upload errors
            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    echo "File is too large.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    echo "File was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    echo "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    echo "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    echo "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    echo "File upload stopped by extension.";
                    break;
                default:
                    echo "Unknown upload error.";
                    break;
            }
            exit;
        }
    } else {
        echo "No file uploaded.";
        exit;
    }
} else {
    echo "Invalid request method.";
    exit;
}
?>
