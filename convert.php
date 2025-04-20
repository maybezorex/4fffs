<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit("Only POST allowed");
}

if (!isset($_FILES['file']) || !isset($_POST['format'])) {
  http_response_code(400);
  exit("Missing file or format.");
}

$file = $_FILES['file'];
$format = strtolower($_POST['format']);
$allowedFormats = ['pdf', 'docx', 'txt', 'jpg', 'png', 'webp', 'mp3', 'wav', 'mp4', 'mov', 'avi', 'zip', 'rar', '7z'];

if (!in_array($format, $allowedFormats)) {
  http_response_code(400);
  exit("Format not supported.");
}

$inputPath = $file['tmp_name'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$outputFile = tempnam(sys_get_temp_dir(), 'convert_') . '.' . $format;

// Use ffmpeg for video/audio
if (in_array($format, ['mp3', 'wav', 'mp4', 'mov', 'avi'])) {
  $cmd = "ffmpeg -y -i " . escapeshellarg($inputPath) . " " . escapeshellarg($outputFile);
  exec($cmd, $out, $code);
}

// Use LibreOffice for document conversion
elseif (in_array($format, ['pdf', 'txt'])) {
  $tmpDir = sys_get_temp_dir() . '/' . uniqid('lo_');
  mkdir($tmpDir);
  copy($inputPath, $tmpDir . '/' . $file['name']);
  $cmd = "libreoffice --headless --convert-to $format --outdir " . escapeshellarg($tmpDir) . " " . escapeshellarg($tmpDir . '/' . $file['name']);
  exec($cmd);
  $outputFile = $tmpDir . '/' . pathinfo($file['name'], PATHINFO_FILENAME) . '.' . $format;
}

// Use ImageMagick for images
elseif (in_array($format, ['jpg', 'png', 'webp'])) {
  $cmd = "convert " . escapeshellarg($inputPath) . " " . escapeshellarg($outputFile);
  exec($cmd);
}

// Create ZIP archive
elseif ($format === 'zip') {
  $zip = new ZipArchive();
  if ($zip->open($outputFile, ZipArchive::CREATE) === TRUE) {
    $zip->addFile($inputPath, $file['name']);
    $zip->close();
  } else {
    exit("Failed to create zip.");
  }
}

// Deliver file
if (!file_exists($outputFile)) {
  http_response_code(500);
  exit("Conversion failed.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="converted.' . $format . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($outputFile));
readfile($outputFile);
unlink($outputFile);
exit;
