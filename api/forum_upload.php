<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check for image or file upload
$file = null;
$uploadType = 'image';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $uploadType = 'image';
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $uploadType = 'file';
}

if (!$file) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

// Allowed types
$imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$fileTypes = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx'
];
$max_size = 10 * 1024 * 1024; // 10MB

// Detect MIME type properly
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$isImage = in_array($mimeType, $imageTypes);
$isFile = array_key_exists($mimeType, $fileTypes);

if (!$isImage && !$isFile) {
    http_response_code(400);
    echo json_encode(['error' => 'Format tidak didukung. Gunakan JPG, PNG, GIF, WebP, PDF, Word, Excel, atau PPT']);
    exit;
}

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'Ukuran file maksimal 10MB']);
    exit;
}

$upload_dir = '../uploads/forum/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get proper extension
if ($isImage) {
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext = $extMap[$mimeType] ?? 'jpg';
} else {
    $ext = $fileTypes[$mimeType];
}

$filename = uniqid('forum_') . '_' . time() . '.' . $ext;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $response = [
        'success' => true,
        'type' => $isImage ? 'image' : 'file',
        'file_url' => 'uploads/forum/' . $filename,
        'file_name' => $file['name'],
        'file_ext' => $ext
    ];
    if ($isImage) {
        $response['image_url'] = 'uploads/forum/' . $filename;
    }
    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengupload file']);
}
?>
