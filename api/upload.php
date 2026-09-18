<?php
/**
 * Upload API
 * POST /api/upload/icon
 */

$method = $_SERVER['REQUEST_METHOD'];
$upload_endpoint = $parts[1] ?? '';

if ($method === 'DELETE') {
    if ($upload_endpoint !== 'icon') {
        sendResponse(['error' => 'Upload endpoint not found', 'upload_endpoint' => $upload_endpoint], 404);
    }

    $path = $_GET['path'] ?? null;
    if (!$path) {
        $body = json_decode(file_get_contents('php://input'), true);
        $path = $body['path'] ?? null;
    }

    if (!$path) {
        sendResponse(['error' => 'No file path specified'], 400);
    }

    $path = trim($path);
    if (strpos($path, '..') !== false) {
        sendResponse(['error' => 'Invalid file path'], 400);
    }

    $allowedPrefix = 'uploads/icons/';
    if (strpos($path, $allowedPrefix) !== 0) {
        sendResponse(['error' => 'File path not allowed'], 400);
    }

    $filePath = realpath(__DIR__ . '/../' . $path);
    $allowedDir = realpath(__DIR__ . '/../uploads/icons');
    if (!$filePath || strpos($filePath, $allowedDir) !== 0) {
        sendResponse(['error' => 'Invalid file path'], 400);
    }

    if (!file_exists($filePath)) {
        sendResponse(['error' => 'File not found'], 404);
    }

    if (!unlink($filePath)) {
        sendResponse(['error' => 'Failed to delete file'], 500);
    }

    sendResponse(['message' => 'File deleted']);
}

if ($method !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

if ($upload_endpoint !== 'icon') {
    sendResponse(['error' => 'Upload endpoint not found', 'upload_endpoint' => $upload_endpoint], 404);
}

if (!isset($_FILES['icon'])) {
    sendResponse(['error' => 'No file uploaded'], 400);
}

$file = $_FILES['icon'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(['error' => 'Upload error: ' . $file['error']], 400);
}

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($mimeType, $allowedMimeTypes)) {
    sendResponse(['error' => 'Invalid file type'], 400);
}

$uploadDir = __DIR__ . '/../uploads/icons';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    sendResponse(['error' => 'Failed to create upload directory'], 500);
}

$extension = $allowedMimeTypes[$mimeType];
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$originalName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $originalName);
$originalName = mb_substr($originalName, 0, 100);
$originalName = $originalName ?: 'icon';

$filename = $originalName . '.' . $extension;
$destination = $uploadDir . '/' . $filename;
$sequence = 1;
while (file_exists($destination)) {
    $filename = $originalName . '_' . $sequence . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;
    $sequence++;
}

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    sendResponse(['error' => 'Failed to move uploaded file'], 500);
}

$relativePath = 'uploads/icons/' . $filename;

// Return only the relative filename for database storage and the public URL for display
sendResponse([
    'filename' => $filename,
    'path' => $relativePath,
    'url' => '/' . $relativePath
]);