<?php
include "auth.php";
include "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

// Tutup semua event lain dulu
mysqli_query($conn, "UPDATE events SET status='closed' WHERE status='open'");

// Buka event ini
mysqli_query($conn, "UPDATE events SET status='open' WHERE id='$event_id'");

// Generate token baru untuk event ini (expired 5 detik)
$token = bin2hex(random_bytes(16));
$expired_at = date('Y-m-d H:i:s', strtotime('+5 seconds'));

mysqli_query($conn, "INSERT INTO tokens(event_id, token, expired_at) VALUES('$event_id', '$token', '$expired_at')");

header("Location: generate_qr.php?id=$event_id");
exit;
