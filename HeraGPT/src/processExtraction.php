<?php
require '../vendor/autoload.php'; // Include Composer's autoload file
require 'load_env.php'; // Include your custom environment loader

use PhpOffice\PhpSpreadsheet\IOFactory;

// Load the environment variables
$envFilePath = __DIR__ . '/.env';
if (file_exists($envFilePath)) {
    try {
        loadEnv($envFilePath);
    } catch (Exception $e) {
        echo "Error loading .env file: " . $e->getMessage();
        exit;
    }
} else {
    echo "Error loading .env file: Environment file not found at $envFilePath.";
    exit;
}

// Function to call OpenAI API using cURL
function callOpenAI($certificateContent, $standardContent, $certificateFileName, $standardFileName, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';

    // Prepare a concise prompt with both files' content and ask the AI to provide a compliance check
    $prompt = "Analyze the uploaded steel certificate and NZ Standard file. 
        Mention the uploaded file names and whether the certificate complies with the standard as shown:
        'Result: (Compliant/Non-Compliant) Certificate $certificateFileName 'complies/does not comply' with $standardFileName.' 
        Only provide required information.
        \nCertificate File Name: $certificateFileName
        \nCertificate Content: $certificateContent
        \nStandard File Name: $standardFileName
        \nStandard Content: $standardContent";

    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a Steel certificate compliance checker.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 4000, // Adjust this as needed
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
function extractTextFromPDF($pdfPath, $maxLength = 5000) {
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return substr($text, 0, $maxLength);
}

// Function to extract text from Excel using PhpSpreadsheet
function extractTextFromExcel($excelPath, $maxLength = 5000) {
    $spreadsheet = IOFactory::load($excelPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $text = '';
    foreach ($worksheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $text .= $cell->getValue() . ' ';
        }
        $text .= "\n";
        if (strlen($text) > $maxLength) {
            break;
        }
    }
    return substr($text, 0, $maxLength);
}

// Function to read the specific standard file and extract its text
function readStandardFile($standardName, $directory, $maxLength = 5000) {
    $files = glob("$directory/*.xlsx");
    foreach ($files as $file) {
        if (stripos(basename($file, '.xlsx'), str_replace('/', '-', strtolower($standardName))) !== false) {
            return [
                'content' => extractTextFromExcel($file, $maxLength),
                'filename' => basename($file),
            ];
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

    // Read and extract text from the specific standard file
    $standardsDirectory = __DIR__ . '/NzStandards/Excel';
    $standardFile = readStandardFile($standardName, $standardsDirectory);
    if (!$standardFile) {
        echo "Standard not found in the local directory.";
        exit;
    }

    // Retrieve API key directly from environment variables
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI($pdfContent, $standardFile['content'], basename($pdfPath), $standardFile['filename'], $apiKey);

    // Redirect to export.html with the result as a query parameter
    header('Location: export.html?result=' . urlencode($result) . '&pdf=' . urlencode($pdfPath));
    exit;
} else {
    echo "No PDF provided.";
}
