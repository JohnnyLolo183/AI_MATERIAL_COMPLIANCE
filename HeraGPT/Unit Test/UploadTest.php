<?php

use PHPUnit\Framework\TestCase;

class UploadTest extends TestCase
{
    protected function setUp(): void
    {
        // Mock the $_FILES superglobal
        $_FILES = [
            'pdfFile' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/phpYzdqkD',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024 * 1024, // 1 MB
            ]
        ];

        // Mock the $_SERVER superglobal
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testSuccessfulUpload()
    {
        // Mock file system functions
        $this->mockIsDir(true);
        $this->mockMoveUploadedFile(true);
        $this->mockFinfoFile('application/pdf');

        // Start output buffering to capture echo statements
        ob_start();

        // Include the upload script from the src directory
        include __DIR__ . '/../src/upload.php';

        // Get the output
        $output = ob_get_clean();

        // Assert that the user is redirected to the extract page
        $this->assertStringContainsString('Location: extract.html?pdf=', xdebug_get_headers()[0]);
    }

    public function testFileTooLarge()
    {
        // Set file size to exceed the limit
        $_FILES['pdfFile']['size'] = 11 * 1024 * 1024; // 11 MB

        // Start output buffering to capture echo statements
        ob_start();

        // Include the upload script from the src directory
        include __DIR__ . '/../src/upload.php';

        // Get the output
        $output = ob_get_clean();

        // Assert that the correct error message is displayed
        $this->assertStringContainsString('File exceeds the maximum allowed size of 10 MB.', $output);
    }

    public function testInvalidFileType()
    {
        // Mock file system functions
        $this->mockIsDir(true);
        $this->mockMoveUploadedFile(true);
        $this->mockFinfoFile('image/jpeg'); // Invalid MIME type

        // Start output buffering to capture echo statements
        ob_start();

        // Include the upload script from the src directory
        include __DIR__ . '/../src/upload.php';

        // Get the output
        $output = ob_get_clean();

        // Assert that the correct error message is displayed
        $this->assertStringContainsString('Uploaded file is not a PDF.', $output);
    }

    public function testNoFileUploaded()
    {
        // Unset the $_FILES array to simulate no file uploaded
        unset($_FILES['pdfFile']);

        // Start output buffering to capture echo statements
        ob_start();

        // Include the upload script from the src directory
        include __DIR__ . '/../src/upload.php';

        // Get the output
        $output = ob_get_clean();

        // Assert that the correct error message is displayed
        $this->assertStringContainsString('No file uploaded.', $output);
    }

    // Helper function to mock is_dir
    private function mockIsDir($returnValue)
    {
        $this->mockFunction('is_dir', $returnValue);
    }

    // Helper function to mock move_uploaded_file
    private function mockMoveUploadedFile($returnValue)
    {
        $this->mockFunction('move_uploaded_file', $returnValue);
    }

    // Helper function to mock finfo_file
    private function mockFinfoFile($mimeType)
    {
        $this->mockFunction('finfo_file', $mimeType);
    }

    // Generic function to mock PHP functions
    private function mockFunction($functionName, $returnValue)
    {
        $mock = $this->getMockBuilder(stdClass::class)
            ->setMethods([$functionName])
            ->getMock();

        $mock->method($functionName)
            ->willReturn($returnValue);

        // Override the global function
        runkit_function_redefine($functionName, '', 'return ' . var_export($returnValue, true) . ';');
    }
}

// run test:
// vendor/bin/phpunit Unit\ Test/UploadTest.php