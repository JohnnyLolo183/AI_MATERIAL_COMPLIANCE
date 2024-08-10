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

// Function to extract text from PDF using pdftotext
function extractTextFromPDF($pdfPath, $maxLength = 5000) {
    $outputFile = tempnam(sys_get_temp_dir(), 'pdftotext');
    shell_exec("pdftotext -q -nopgbrk -enc UTF-8 '$pdfPath' '$outputFile'");
    $text = file_get_contents($outputFile);
    unlink($outputFile);
    return substr($text, 0, $maxLength);
}

// Function to find all PNG files in the subfolder of the standards directory that matches the standard name
function findPngFiles($directory, $standardName) {
    $standardFolderName = str_replace('/', '-', $standardName);
    $standardFolderName = preg_replace('/AS-NZS/', 'AS-NZS ', $standardFolderName);

    $standardFolderPath = "$directory/$standardFolderName";

    echo "Searching for folder: $standardFolderPath<br>";

    // Search for PNG files in both lowercase and uppercase extensions
    $files = array_merge(glob("$standardFolderPath/*.png"), glob("$standardFolderPath/*.PNG"));

    // Check if glob found any files
    if (empty($files)) {
        echo "No files found in: $standardFolderPath. Checking variations...<br>";
        $possibleFolders = glob("$directory/{$standardFolderName}*", GLOB_ONLYDIR);
        foreach ($possibleFolders as $folder) {
            echo "Checking possible folder: $folder<br>";
            $files = array_merge($files, glob("$folder/*.png"), glob("$folder/*.PNG"));
            if (!empty($files)) {
                echo "Files found in $folder:<br>";
                foreach ($files as $file) {
                    echo " - $file<br>";
                }
            }
        }
    } else {
        echo "Files found in $standardFolderPath:<br>";
        foreach ($files as $file) {
            echo " - $file<br>";
        }
    }

    if (empty($files)) {
        echo "No PNG files found in any variations.<br>";
    } else {
        echo "PNG files successfully identified.<br>";
    }

    $fileData = [];
    foreach ($files as $file) {
        $fileData[] = [
            'path' => $file,
            'filename' => basename($file),
        ];
    }

    return $fileData;
}



// Function to call OpenAI API using cURL with PNG files in the prompt
function callOpenAI($certificateContent, $pngFiles, $certificateFileName, $standardName, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';

    // Prepare a concise prompt that includes the certificate content, the names of the PNG files, and the standard name
    $prompt = "Analyze the uploaded steel certificate and the following NZ standard images (PNG files) based on the standard: $standardName. 
        Mention the uploaded file names and whether the certificate complies with the standard as shown:
        'Result: (Compliant/Non-Compliant) Certificate (certificate file name) 'complies/does not comply' with $standardName.' 
        If fails, provide 'Reason: (reason of failure)', just a short paragraph stating where and what failed, no need to display the number value.
        Also if fails, state 'User should check for the following and any other unlisted values: (list of failed items sorted by '- item' (space) '- item')'.
        Only provide required information and follow my prompt format exactly.
        \nCertificate File Name: $certificateFileName
        \nCertificate Content: $certificateContent
        \nStandard Name: $standardName
        \nStandard Image Files: " . implode(", ", array_column($pngFiles, 'filename'));

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
    preg_match('/AS\s*\/?\s*NZS\s*\d{4}(\.\d+)?/', $pdfContent, $matches);
    if (!isset($matches[0])) {
        echo "No standard mentioned in the certificate.";
        exit;
    }
    $standardName = $matches[0];

    // Find PNG files in the subfolder of the standards directory
    $standardsDirectory = __DIR__ . '/NzStandards';
    $pngFiles = findPngFiles($standardsDirectory, $standardName);
    if (empty($pngFiles)) {
        echo "No PNG files found in the standards directory for $standardName.";
        exit;
    }

    // Retrieve API key directly from environment variables
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI($pdfContent, $pngFiles, basename($pdfPath), $standardName, $apiKey);

    // Redirect to export.html with the result as a query parameter
    header('Location: export.html?result=' . urlencode($result) . '&pdf=' . urlencode($pdfPath));
    exit;
} else {
    echo "No PDF provided.";
}
