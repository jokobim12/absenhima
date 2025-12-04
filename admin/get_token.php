<?php
/**
 * API endpoint untuk get/generate token baru
 * Dipanggil via AJAX setiap 5 detik
 */
include "auth.php";
include "../config/koneksi.php";
include "../config/cleanup.php";
include "../config/ratelimit.php";

header('Content-Type: application/json');

// Rate limit: max 30 requests per 10 detik (untuk polling setiap 5 detik)
rateLimitOrDie('get_token', 30, 10);

// Cleanup expired tokens (1% probability per request)
maybeCleanup($conn, 1);

if(!isset($_GET['id'])){
    echo json_encode(['error' => 'Event ID required']);
    exit;
}

$event_id = intval($_GET['id']);

// Prepared statement untuk cek status event
$stmt = mysqli_prepare($conn, "SELECT status FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$event){
    echo json_encode(['error' => 'Event not found']);
    exit;
}

if($event['status'] != 'open'){
    echo json_encode(['status' => 'closed']);
    exit;
}

// Prepared statement untuk cek token yang masih valid
$stmt = mysqli_prepare($conn, "SELECT token FROM tokens WHERE event_id = ? AND expired_at > NOW() ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$token_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$token_row){
    // Generate token baru (expired dalam 5 detik)
    $token = bin2hex(random_bytes(16));
    $expired_at = date('Y-m-d H:i:s', strtotime('+5 seconds'));
    
    $stmt = mysqli_prepare($conn, "INSERT INTO tokens(event_id, token, expired_at) VALUES(?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $event_id, $token, $expired_at);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    $token = $token_row['token'];
}

// Prepared statement untuk hitung peserta
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM absen WHERE event_id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$peserta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
mysqli_stmt_close($stmt);

echo json_encode([
    'token' => $token,
    'peserta' => intval($peserta),
    'status' => 'open'
]);
