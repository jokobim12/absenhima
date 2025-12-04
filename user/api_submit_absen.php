<?php
/**
 * API endpoint untuk submit absensi via AJAX
 */
include "auth.php";
include "../config/koneksi.php";
include "../config/ratelimit.php";

header('Content-Type: application/json');

// Rate limit: max 10 submit attempts per menit
if (!checkRateLimit('submit_absen', 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Terlalu banyak percobaan. Tunggu sebentar.'
    ]);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Token tidak ditemukan.'
    ]);
    exit;
}

// Cek token valid
$stmt = mysqli_prepare($conn, "SELECT * FROM tokens WHERE token = ? AND expired_at > NOW() ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tk = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$tk) {
    echo json_encode([
        'success' => false,
        'message' => 'Token tidak valid atau sudah kadaluarsa. Silakan scan ulang.'
    ]);
    exit;
}

$event_id = intval($tk['event_id']);

// Cek event status
$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ev = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$ev || $ev['status'] != 'open') {
    echo json_encode([
        'success' => false,
        'message' => 'Event sudah ditutup.'
    ]);
    exit;
}

// Cek sudah absen
$stmt = mysqli_prepare($conn, "SELECT * FROM absen WHERE user_id = ? AND event_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

if (mysqli_num_rows($result) > 0) {
    echo json_encode([
        'success' => false,
        'already_attended' => true,
        'message' => 'Kamu sudah absen untuk event ini.'
    ]);
    exit;
}

// Insert absen
$token_id = intval($tk['id']);
$stmt = mysqli_prepare($conn, "INSERT INTO absen(user_id, event_id, token_id) VALUES(?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iii", $user_id, $event_id, $token_id);
$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Absensi berhasil dicatat!',
        'event_name' => $ev['nama_event'],
        'timestamp' => date('d M Y, H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan absensi. Silakan coba lagi.'
    ]);
}
