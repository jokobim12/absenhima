<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Endpoint ini bisa diakses publik untuk mendapatkan public key
$result = mysqli_query($conn, "SELECT public_key FROM vapid_keys ORDER BY id DESC LIMIT 1");

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['publicKey' => $row['public_key']]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'VAPID keys not configured']);
}
?>
