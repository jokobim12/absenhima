<?php
$host = "localhost";
$user = "root";
$pass = "1234";
$db   = "absenhima";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
