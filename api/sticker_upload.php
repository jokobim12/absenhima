<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";
include "sticker_init.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

initStickerTables($conn);

$user_id = intval($_SESSION['user_id']);

if (!isset($_FILES['sticker']) || $_FILES['sticker']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File stiker tidak valid']);
    exit;
}

$file = $_FILES['sticker'];
$allowedTypes = ['image/png', 'image/webp', 'image/gif', 'image/jpeg'];
$maxSize = 1024 * 1024; // 1MB

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format file harus PNG, WEBP, GIF, atau JPG']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Ukuran file maksimal 1MB']);
    exit;
}

// Generate filename
$ext = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/webp' ? 'webp' : ($mimeType === 'image/jpeg' ? 'jpg' : 'gif'));
$filename = 'sticker_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
$uploadPath = '../uploads/stickers/' . $filename;
$dbPath = 'uploads/stickers/' . $filename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Insert sticker to database
    $stmt = mysqli_prepare($conn, "INSERT INTO stickers (file_url, uploaded_by) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "si", $dbPath, $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $sticker_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Auto-add to user's collection
        $stmt = mysqli_prepare($conn, "INSERT INTO user_stickers (user_id, sticker_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $sticker_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            'success' => true,
            'sticker' => [
                'id' => $sticker_id,
                'file_url' => $dbPath
            ]
        ]);
    } else {
        unlink($uploadPath);
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menyimpan stiker']);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal upload file']);
}
?>
