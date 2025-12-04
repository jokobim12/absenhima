<?php
/**
 * API endpoint untuk get/generate token baru
 * Dipanggil via AJAX setiap 5 detik
 */
include "auth.php";
include "../config/koneksi.php";

header('Content-Type: application/json');

if(!isset($_GET['id'])){
    echo json_encode(['error' => 'Event ID required']);
    exit;
}

$event_id = intval($_GET['id']);

// Cek status event
$event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM events WHERE id='$event_id'"));

if(!$event){
    echo json_encode(['error' => 'Event not found']);
    exit;
}

if($event['status'] != 'open'){
    echo json_encode(['status' => 'closed']);
    exit;
}

// Cek token yang masih valid
$token_row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT token FROM tokens 
    WHERE event_id='$event_id' AND expired_at > NOW() 
    ORDER BY id DESC LIMIT 1
"));

if(!$token_row){
    // Generate token baru (expired dalam 5 detik)
    $token = bin2hex(random_bytes(16));
    $expired_at = date('Y-m-d H:i:s', strtotime('+5 seconds'));
    mysqli_query($conn, "INSERT INTO tokens(event_id, token, expired_at) VALUES('$event_id', '$token', '$expired_at')");
} else {
    $token = $token_row['token'];
}

// Hitung peserta
$peserta = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM absen WHERE event_id='$event_id'"))['c'];

echo json_encode([
    'token' => $token,
    'peserta' => intval($peserta),
    'status' => 'open'
]);
