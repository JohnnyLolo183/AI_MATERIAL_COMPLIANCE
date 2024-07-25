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
function callOpenAI($certificateContent, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // Prepare a prompt with certificate content and ask the AI to provide a concise compliance check
    $prompt = "You are a Steel certificate compliance checker. Analyze the uploaded steel certificate, identify the NZ standard mentioned, search for it online, and compare the certificate content with the standard. If the certificate is compliant, return 'Compliant', otherwise return 'Not Compliant' and provide a brief summary of the non-compliance.\n\nCertificate Content:\n$certificateContent";

    $data = [
        'model' => 'gpt-4o-mini',
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
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
        exit;
    }
    curl_close($ch);

    if ($httpcode != 200) {
        echo "HTTP error code: $httpcode";
        echo "Response: " . $response;
        exit;
    }

    $response_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg();
        exit;
    }
    
    // Extracting the required fields from the response (assuming the response format)
    $extractedData = [
        'response' => $response_data['choices'][0]['message']['content'] ?? 'No response from OpenAI API',
        'manufacturer' => '', // Extract from the response
        'certificate_number' => '', // Extract from the response
        'heat_number' => '', // Extract from the response
        'material_standard' => '', // Extract from the response
        'material_grade' => '', // Extract from the response
        'material_description' => '', // Extract from the response
    ];

    // Assuming the response content contains relevant data, populate these fields
    // This should be modified based on the actual response format from OpenAI

    return json_encode($extractedData);
}

// Function to extract text from PDF using pdftotext
function extractTextFromPDF($pdfPath) {
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return $text;
}

// Handle OpenAI API call
if (isset($_GET['pdf'])) {
    $pdfPath = urldecode($_GET['pdf']);
    $pdfContent = extractTextFromPDF($pdfPath);

    // Check if content was extracted successfully
    if (!$pdfContent) {
        echo "Failed to extract text from the certificate PDF.";
        exit;
    }

    // Retrieve API key directly from environment variables
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI($pdfContent, $apiKey);

    // Redirect to export.html with the result as a query parameter
    header('Location: export.html?result=' . urlencode($result) . '&pdf=' . urlencode($pdfPath));
    exit;
} else {
    echo "No PDF provided.";
}
?>
