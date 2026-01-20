<?php
// tools/generate_assets.php
// Usage: php tools/generate_assets.php "path/to/source_image.png"

if ($argc < 2) {
    die("Usage: php tools/generate_assets.php <source_image>\n");
}

$sourceFile = $argv[1];

if (!file_exists($sourceFile)) {
    die("Error: Source file not found: $sourceFile\n");
}

$info = getimagesize($sourceFile);
$mime = $info['mime'];

switch ($mime) {
    case 'image/jpeg': $srcImg = imagecreatefromjpeg($sourceFile); break;
    case 'image/png':  $srcImg = imagecreatefrompng($sourceFile); break;
    case 'image/webp': $srcImg = imagecreatefromwebp($sourceFile); break;
    default: die("Error: Unsupported image format ($mime). Use JPG, PNG or WEBP.\n");
}

// Preserve transparency for PNG/WEBP source
imagealphablending($srcImg, true);
imagesavealpha($srcImg, true);

$baseDir = dirname(__DIR__) . '/assets/img';
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

echo "Processing...\n";

// 1. Generate Main Logo (logo.png)
// Requirement: Header usage. Max width ~200px in CSS. 
// We will generate at 400px width for Retina, preserving aspect ratio.
$targetWidth = 400;
$width = imagesx($srcImg);
$height = imagesy($srcImg);
$aspect = $height / $width;
$targetHeight = (int)($targetWidth * $aspect);

$logoImg = imagecreatetruecolor($targetWidth, $targetHeight);
imagealphablending($logoImg, false);
imagesavealpha($logoImg, true);
$transparent = imagecolorallocatealpha($logoImg, 255, 255, 255, 127);
imagefilledrectangle($logoImg, 0, 0, $targetWidth, $targetHeight, $transparent);

imagecopyresampled($logoImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
$destLogo = $baseDir . '/logo.png';
imagepng($logoImg, $destLogo);
echo "✔ Generated: $destLogo ({$targetWidth}x{$targetHeight})\n";
imagedestroy($logoImg);


// 2. Generate Icon (logo_icon.png)
// Requirement: PWA Manifest calls for 512x512 (and uses it for 192x192).
// Must be SQUARE.
// Logic: Aspect Fit inside a 512x512 transparent square.
$iconSize = 512;
$iconImg = imagecreatetruecolor($iconSize, $iconSize);
imagealphablending($iconImg, false);
imagesavealpha($iconImg, true);
$transparent = imagecolorallocatealpha($iconImg, 255, 255, 255, 127);
imagefilledrectangle($iconImg, 0, 0, $iconSize, $iconSize, $transparent);

// Calculate fit dimensions
if ($width > $height) {
    // Landscape
    $newWidth = $iconSize;
    $newHeight = $iconSize * ($height / $width);
    $offX = 0;
    $offY = ($iconSize - $newHeight) / 2;
} else {
    // Portrait or Square
    $newHeight = $iconSize;
    $newWidth = $iconSize * ($width / $height);
    $offX = ($iconSize - $newWidth) / 2;
    $offY = 0;
}

imagecopyresampled($iconImg, $srcImg, (int)$offX, (int)$offY, 0, 0, (int)$newWidth, (int)$newHeight, $width, $height);
$destIcon = $baseDir . '/logo_icon.png';
imagepng($iconImg, $destIcon);
echo "✔ Generated: $destIcon ({$iconSize}x{$iconSize} Padded)\n";
imagedestroy($iconImg);

imagedestroy($srcImg);
echo "Done!\n";
