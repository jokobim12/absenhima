<?php
include "auth.php";
include "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

$stmt = mysqli_prepare($conn, "UPDATE events SET status='closed' WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo "<script>alert('Event berhasil ditutup'); window.location='events.php';</script>";
?>
