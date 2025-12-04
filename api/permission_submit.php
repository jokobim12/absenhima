<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Handle file upload
$attachment = null;
if (!empty($_FILES['attachment']['name'])) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['attachment']['type'], $allowed)) {
        echo json_encode(['error' => 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau PDF.']);
        exit;
    }
    
    if ($_FILES['attachment']['size'] > $max_size) {
        echo json_encode(['error' => 'Ukuran file maksimal 5MB.']);
        exit;
    }
    
    $upload_dir = '../uploads/permissions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
    $filename = 'perm_' . $user_id . '_' . time() . '.' . $ext;
    
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) {
        $attachment = 'uploads/permissions/' . $filename;
    }
}

$type = $_POST['type'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;

if (!in_array($type, ['izin', 'sakit'])) {
    echo json_encode(['error' => 'Tipe harus izin atau sakit']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['error' => 'Alasan harus diisi']);
    exit;
}

if (strlen($reason) > 1000) {
    echo json_encode(['error' => 'Alasan maksimal 1000 karakter']);
    exit;
}

// Check if already submitted for this event
if ($event_id) {
    $check = mysqli_prepare($conn, "SELECT id FROM permissions WHERE user_id = ? AND event_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($check, "ii", $user_id, $event_id);
    mysqli_stmt_execute($check);
    if (mysqli_stmt_get_result($check)->num_rows > 0) {
        echo json_encode(['error' => 'Anda sudah mengajukan izin untuk event ini']);
        exit;
    }
}

$stmt = mysqli_prepare($conn, "INSERT INTO permissions (user_id, event_id, type, reason, attachment) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iisss", $user_id, $event_id, $type, $reason, $attachment);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil dikirim']);
} else {
    echo json_encode(['error' => 'Gagal mengirim pengajuan']);
}
?>
