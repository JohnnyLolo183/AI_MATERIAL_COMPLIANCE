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
function callOpenAI($certificateContent, $standardContent, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // Prepare a prompt with certificate content and ask the AI to provide a concise compliance check
    $prompt = "Analyze the uploaded steel certificates chemical analysis and mechanical testing data values. 
        Analyze the uploaded NZ Standard and compare the specifications with the certificate data values.
        If the certificate meets standards specifications then return compliant else non-compliant.
        If result is compliant, return 'Compliant' and 'This certificate complies with (standard name)' only. 
        If result is non-compliant, return 'Non-Compliant', 'This certificate fails to comply with (standard name)', 
        and comparison of non-compliant data values only. 
        Ensure extracted numbers are correct and match.
        \n\nCertificate Content:\n$certificateContent
        \n\nStandard Content:\n$standardContent";

    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a Steel certificate compliance checker.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 4000,
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
    ];

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

// Function to read the specific standard from the local directory
function readStandard($standardName, $directory) {
    $files = glob("$directory/*.pdf");
    foreach ($files as $file) {
        if (stripos(basename($file, '.pdf'), str_replace('/', '-', strtolower($standardName))) !== false) {
            return extractTextFromPDF($file);
        }
    }
    return null;
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

    // Identify the mentioned standard
    preg_match('/AS\s*\/?\s*NZS\s*\d{4}/', $pdfContent, $matches);
    if (!isset($matches[0])) {
        echo "No standard mentioned in the certificate.";
        exit;
    }
    $standardName = $matches[0];

    // Read the specific standard from the local directory
    $standardsDirectory = __DIR__ . '/NzStandards';
    $standardContent = readStandard($standardName, $standardsDirectory);
    if (!$standardContent) {
        echo "Standard not found in the local directory.";
        exit;
    }

    // Retrieve API key directly from environment variables
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI($pdfContent, $standardContent, $apiKey);

    // Redirect to export.html with the result as a query parameter
    header('Location: export.html?result=' . urlencode($result) . '&pdf=' . urlencode($pdfPath));
    exit;
} else {
    echo "No PDF provided.";
}
