<?php
include "auth.php";
include "../config/koneksi.php";

if(!isset($_GET['id'])){
    header("Location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

mysqli_query($conn, "UPDATE events SET status='closed' WHERE id='$event_id'");

echo "<script>alert('Event berhasil ditutup'); window.location='events.php';</script>";
?>
