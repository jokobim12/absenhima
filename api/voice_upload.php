<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Check auth
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if voice file uploaded
if (!isset($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No voice file uploaded']);
    exit;
}

$file = $_FILES['voice'];
$maxSize = 5 * 1024 * 1024; // 5MB max

// Validate size
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Ukuran file maksimal 5MB']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/x-m4a', 'video/webm'];
if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format audio tidak didukung: ' . $mimeType]);
    exit;
}

// Create upload directory
$uploadDir = '../uploads/voice/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate filename
$ext = 'webm'; // Default extension
if ($mimeType === 'audio/mpeg') $ext = 'mp3';
elseif ($mimeType === 'audio/wav') $ext = 'wav';
elseif ($mimeType === 'audio/ogg') $ext = 'ogg';
elseif ($mimeType === 'audio/mp4' || $mimeType === 'audio/x-m4a') $ext = 'm4a';

$filename = 'voice_' . uniqid() . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;
$dbPath = 'uploads/voice/' . $filename;

// Get duration from POST if available
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode([
        'success' => true,
        'voice_url' => $dbPath,
        'duration' => $duration
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan file']);
}
?>
