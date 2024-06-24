<?php
require 'vendor/autoload.php';

// Function to call OpenAI API using cURL
function callOpenAI($certificateContent, $standardContents, $apiKey) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // Prepare prompt with certificate and all standards
    $prompt = "You are a Steel certificate compliance checker. Analyze the following steel certificate and compare it with the NZ standards provided. If the certificate is compliant, return 'Compliant', otherwise return 'Not Compliant' and explain why. Provide details of the comparison with each standard.\n\nCertificate Content (base64 encoded):\n$certificateContent\n\nNZ Standards (base64 encoded):\n";
    foreach ($standardContents as $standardName => $standardContent) {
        $prompt .= "Standard: $standardName\n$standardContent\n\n";
    }

    $data = [
        'model' => 'gpt-4-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a Steel certificate compliance checker.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 2000,
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
    return $response_data['choices'][0]['message']['content'];
}

// Function to fetch NZ standards from the database
function getNZStandards($pdo) {
    $stmt = $pdo->query("SELECT standard_name, standard_file_path FROM nz_standards"); // Fetch all standards with file paths
    $standards = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Read each standard PDF as binary
        $standardContent = file_get_contents($row['standard_file_path']);
        if ($standardContent) {
            $standards[$row['standard_name']] = base64_encode($standardContent);
        }
    }
    return $standards;
}

// Handle file upload and OpenAI API call
if ($_FILES['pdfFile']['error'] == UPLOAD_ERR_OK) {
    $pdfPath = $_FILES['pdfFile']['tmp_name'];
    $pdfContent = file_get_contents($pdfPath);

    // Check if content was read successfully
    if (!$pdfContent) {
        echo "Failed to read the certificate PDF.";
        exit;
    }

    // Get the standard texts from the database
    $pdo = new PDO('mysql:host=localhost;dbname=your_db', 'username', 'password'); // Update with your database credentials
    if (!$pdo) {
        echo "Failed to connect to the database.";
        exit;
    }

    $standardContents = getNZStandards($pdo);
    if (empty($standardContents)) {
        echo "Failed to fetch the standard PDFs.";
        exit;
    }

    // Call OpenAI to analyze the certificate and compare with standards
    $apiKey = getenv('OPENAI_API_KEY'); // Use environment variable for API key
    if (!$apiKey) {
        echo "API key is not set.";
        exit;
    }

    $result = callOpenAI(base64_encode($pdfContent), $standardContents, $apiKey);

    // Output the result
    echo "<h2>Compliance Check Result</h2>";
    echo "<p>$result</p>";
} else {
    echo "File upload failed!";
}
?>
