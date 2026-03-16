<?php
// Create a simple default avatar image
header('Content-Type: image/png');

// Create a 200x200 image
$image = imagecreatetruecolor(200, 200);

// Colors
$bg = imagecolorallocate($image, 240, 240, 240);
$circle = imagecolorallocate($image, 76, 175, 80);
$text = imagecolorallocate($image, 255, 255, 255);

// Fill background
imagefill($image, 0, 0, $bg);

// Draw circle
imagefilledellipse($image, 100, 100, 160, 160, $circle);

// Add text
imagestring($image, 4, 75, 95, 'USER', $text);

imagepng($image);
imagedestroy($image);
?>
