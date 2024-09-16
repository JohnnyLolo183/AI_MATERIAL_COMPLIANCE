<?php
require '../vendor/autoload.php'; // Include Composer's autoload file
require 'load_env.php'; // Include your custom environment loader

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start(); // Already starting session

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

// Retrieve API key directly from environment variables
$apiKey = getenv('OPENAI_API_KEY');

if (!$apiKey) {
    echo "API key is not set.";
    exit;
}

// Store API key in session for later use
$_SESSION['apiKey'] = $apiKey;

// Function to call OpenAI API using cURL
function callOpenAI($certificateContent, $standardContent, $certificateFileName, $standardFileName, $apiKey, $userMessage = null)
{
    $url = 'https://api.openai.com/v1/chat/completions';

    // Initialize chat history in session if not already set
    if (!isset($_SESSION['chatHistory'])) {
        $_SESSION['chatHistory'] = [];
        // Add initial system prompt for context
        $_SESSION['chatHistory'][] = ['role' => 'system', 'content' => 'You are a Steel certificate compliance checker.'];
    }

    // If the user provides a new message, append it to the chat history
    if ($userMessage) {
        $_SESSION['chatHistory'][] = ['role' => 'user', 'content' => $userMessage];
    } else {
        // If no user message, start with a compliance check prompt
        $initialPrompt = "Analyze the uploaded steel certificate and NZ Standard file. 
        Onlyention the uploaded file names and whether the certificate complies with the standard as shown:
        'Result: (Compliant/Non-Compliant) Certificate $certificateFileName 'complies/does not comply' with $standardFileName.' 
        Only provide required information, nothing more.
        \nCertificate File Name: $certificateFileName
        \nCertificate Content: $certificateContent
        \nStandard File Name: $standardFileName
        \nStandard Content: $standardContent";

        $_SESSION['chatHistory'][] = ['role' => 'user', 'content' => $initialPrompt];
    }

    $data = [
        'model' => 'chatgpt-4o-latest',
        'messages' => $_SESSION['chatHistory'],  // Use the entire chat history
        'max_tokens' => 4000, // Adjust this as needed
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,  // Keep your original format
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

    // Extracting the AI response from the response
    $aiResponse = $response_data['choices'][0]['message']['content'] ?? 'No response from OpenAI API';

    // Append AI response to chat history
    $_SESSION['chatHistory'][] = ['role' => 'assistant', 'content' => $aiResponse];

    // Return the AI response as JSON
    return json_encode(['response' => $aiResponse]);
}

// Function to extract text from PDF using pdftotext
function extractTextFromPDF($pdfPath, $maxLength = 5000)
{
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return substr($text, 0, $maxLength);
}

// Function to extract text from Excel using PhpSpreadsheet
function extractTextFromExcel($excelPath, $maxLength = 5000)
{
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
function readStandardFile($standardName, $directory, $maxLength = 5000)
{
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

    // Store the extracted certificate and standard content for the chat
    $_SESSION['certificateContent'] = $pdfContent;
    $_SESSION['standardContent'] = $standardFile['content'];
    $_SESSION['certificateFileName'] = basename($pdfPath);
    $_SESSION['standardFileName'] = $standardFile['filename'];

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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle OpenAI Chat interaction
    $requestData = json_decode(file_get_contents('php://input'), true);
    $userMessage = $requestData['message'] ?? 'No message provided';

    // Call OpenAI for the chat
    $chatResult = callOpenAI($_SESSION['certificateContent'], $_SESSION['standardContent'], $_SESSION['certificateFileName'], $_SESSION['standardFileName'], $apiKey, $userMessage);

    // Return the chat result as JSON
    echo $chatResult;
    exit;
} else {
    echo "No PDF provided or invalid request.";
}
