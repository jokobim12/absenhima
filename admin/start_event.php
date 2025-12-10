<?php
include "auth.php";
include "../config/koneksi.php";
include "../config/notification_helper.php";

if(!isset($_GET['id'])){
    header("Location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

// Tutup semua event lain dulu
mysqli_query($conn, "UPDATE events SET status='closed' WHERE status='open'");

// Buka event ini
$stmt = mysqli_prepare($conn, "UPDATE events SET status='open' WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Get event name for notification
$stmt = mysqli_prepare($conn, "SELECT nama_event FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Generate token baru untuk event ini (expired 5 detik)
$token = bin2hex(random_bytes(16));
$expired_at = date('Y-m-d H:i:s', strtotime('+5 seconds'));

$stmt = mysqli_prepare($conn, "INSERT INTO tokens(event_id, token, expired_at) VALUES(?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iss", $event_id, $token, $expired_at);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Kirim notifikasi ke semua user
if ($event) {
    notifyEventStarted($conn, $event_id, $event['nama_event']);
}

// Send push notification (dengan error handling)
// Dinonaktifkan sementara karena compatibility issue dengan PHP version
// try {
//     if (file_exists("../config/push_helper.php")) {
//         include_once "../config/push_helper.php";
//         if ($event && function_exists('sendNotificationToAllUsers')) {
//             @sendNotificationToAllUsers(
//                 $conn,
//                 'Event Absen Dimulai!',
//                 'Event "' . $event['nama_event'] . '" sudah dibuka. Segera lakukan absensi!',
//                 '/user/dashboard.php'
//             );
//         }
//     }
// } catch (Throwable $e) {
//     // Abaikan error push notification, event tetap jalan
// }

header("Location: generate_qr.php?id=$event_id");
exit;
