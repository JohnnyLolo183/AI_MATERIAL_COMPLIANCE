<?php
$filename = 'uploads/stamped_100 x 100 x 5.0 NOPC SHS x 8 Mtr.pdf';
if (file_exists($filename)) {
    echo "File exists and is accessible.";
} else {
    echo "File does not exist or is not accessible.";
}
?>
