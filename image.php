<?php
$assetsDir = __DIR__ . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR;
$file = $_GET["file"] ?? "";
$format = $_GET["format"] ?? "";

$file = basename((string)$file);
if ($file === "" || !preg_match('/\A[\w.\-]+\z/', $file)) {
  http_response_code(400);
  exit;
}

$path = $assetsDir . $file;
if (!is_file($path)) {
  http_response_code(404);
  exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$wantWebp = $format === "webp" && isset($_SERVER["HTTP_ACCEPT"]) && str_contains($_SERVER["HTTP_ACCEPT"], "image/webp");

header("Cache-Control: public, max-age=604800, immutable");
header("X-Content-Type-Options: nosniff");

if ($wantWebp && function_exists("imagecreatefromjpeg") && function_exists("imagewebp") && ($ext === "jpg" || $ext === "jpeg")) {
  $img = @imagecreatefromjpeg($path);
  if ($img !== false) {
    header("Content-Type: image/webp");
    imagewebp($img, null, 82);
    imagedestroy($img);
    exit;
  }
}

if ($ext === "jpg" || $ext === "jpeg") {
  header("Content-Type: image/jpeg");
} elseif ($ext === "png") {
  header("Content-Type: image/png");
} elseif ($ext === "webp") {
  header("Content-Type: image/webp");
} else {
  header("Content-Type: application/octet-stream");
}

readfile($path);
