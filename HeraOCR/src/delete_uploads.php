<?php
function deleteFiles($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}

// Specify the uploads directory
$uploadsDir = 'uploads';

// Delete files in the uploads directory
deleteFiles($uploadsDir);

// Redirect to home page
header('Location: index.html');
exit;
?>
