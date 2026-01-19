<?php
// --- CONFIGURATION ---

// 1. Define the 16-Color Palette (0-15). You can customize these RGB values.
$palette = [
    '0' => [0, 0, 0],         // 0: Black
    '1' => [128, 0, 0],       // 1: Dark Red
    '2' => [0, 128, 0],       // 2: Dark Green
    '3' => [128, 128, 0],     // 3: Dark Yellow / Olive
    '4' => [0, 0, 128],       // 4: Dark Blue
    '5' => [128, 0, 128],     // 5: Dark Magenta / Purple
    '6' => [0, 128, 128],     // 6: Dark Cyan / Teal
    '7' => [192, 192, 192],   // 7: Silver
    '8' => [128, 128, 128],   // 8: Gray
    '9' => [255, 0, 0],       // 9: Red
    'a' => [0, 255, 0],       // a: Lime Green
    'b' => [255, 255, 0],     // b: Yellow
    'c' => [0, 0, 255],       // c: Blue
    'd' => [255, 0, 255],     // d: Magenta
    'e' => [0, 255, 255],     // e: Cyan
    'f' => [255, 255, 255]     // f: White
];

// --- SCRIPT LOGIC (No need to edit below) ---

// Get the hex data from the URL, or use your default example.
$hex_data = $_GET['data'] ?? '0x0000000000044000004440000444400004444000044400000000000000000000';

// Sanitize the input: remove '0x' prefix and convert to lowercase.
$hex_data = strtolower(str_replace('0x', '', $hex_data));
if (strlen($hex_data) !== 64) {
    die('Invalid emblem data. Must be 64 hex characters.');
}

// Create a blank 8x8 pixel image canvas.
$image_width = 8;
$image_height = 8;
$image = imagecreatetruecolor($image_width, $image_height);

// Make the background transparent (using color index 0 - Black as transparent).
$black = imagecolorallocate($image, 0, 0, 0);
imagecolortransparent($image, $black);

// Loop through each of the 64 hex characters. Each character is one pixel.
for ($i = 0; $i < 64; $i++) {
    // Get the hex character for the current pixel.
    $pixel_char = $hex_data[$i];
    
    // Calculate the (x, y) position of the pixel.
    $x = $i % $image_width;
    $y = floor($i / $image_width);
    
    // Get the RGB color from our palette.
    if (isset($palette[$pixel_char])) {
        list($r, $g, $b) = $palette[$pixel_char];
        $color = imagecolorallocate($image, $r, $g, $b);
        
        // Draw the pixel.
        imagesetpixel($image, $x, $y, $color);
    }
}

// --- OUTPUT THE IMAGE ---

// Tell the browser that this is a PNG image.
header('Content-Type: image/png');

// Output the final image as a PNG.
imagepng($image);

// Clean up the memory.
imagedestroy($image);
?>