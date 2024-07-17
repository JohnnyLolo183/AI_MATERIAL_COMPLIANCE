<?php
require 'load_env.php'; // Include your custom environment loader

// Load the environment variables
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    echo "Error loading .env file: " . $e->getMessage();
    exit;
}

// Function to call OpenAI API using cURL
function callOpenAI($certificateContent, $standardContents, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // Prepare prompt with certificate and all standards
    $prompt = "You are a Steel certificate compliance checker. Analyze the following steel certificate and compare it with the NZ standards provided. If the certificate is compliant, return 'Compliant', otherwise return 'Not Compliant' and explain why. Provide details of the comparison with each standard.\n\nCertificate Content:\n$certificateContent\n\nNZ Standards:\n";
    foreach ($standardContents as $standardName => $standardContent) {
        $prompt .= "Standard: $standardName\n$standardContent\n\n";
    }

    $data = [
        'model' => 'gpt-4-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a Steel certificate compliance checker.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 3000,
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    return $response_data['choices'][0]['message']['content'] ?? 'No response from OpenAI API';
}

// Function to extract text from PDF using pdftotext
function extractTextFromPDF($pdfPath) {
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return $text;
}

// Function to read and extract text from all standard PDFs in a directory
function extractTextFromStandards($directory) {
    $standardTexts = [];
    $files = glob("$directory/*.pdf");

    foreach ($files as $file) {
        $text = extractTextFromPDF($file);
        if ($text) {
            $standardTexts[basename($file)] = $text;
        }
    }
    return $standardTexts;
}

// Handle file upload and OpenAI API call
if ($_FILES['pdfFile']['error'] == UPLOAD_ERR_OK) {
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['pdfFile']['tmp_name']);
    finfo_close($fileInfo);

    if ($mimeType !== 'application/pdf') {
        echo "Uploaded file is not a PDF.";
        exit;
    }

    $pdfPath = $_FILES['pdfFile']['tmp_name'];
    $pdfContent = extractTextFromPDF($pdfPath);

    // Check if content was extracted successfully
    if (!$pdfContent) {
        echo "Failed to extract text from the certificate PDF.";
        exit;
    }

    // Extract text from all standard PDFs in the NzStandards directory
    $standardContents = extractTextFromStandards('NzStandards');
    if (empty($standardContents)) {
        echo "Failed to extract text from the standard PDFs.";
        exit;
    }

    // Retrieve API key directly from environment variables
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI($pdfContent, $standardContents, $apiKey);

    // Output the result
    echo "<h2>Compliance Check Result</h2>";
    echo "<p>$result</p>";
} else {
    echo "File upload failed with error code: " . $_FILES['pdfFile']['error'];
}
?>
