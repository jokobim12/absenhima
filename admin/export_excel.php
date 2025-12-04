<?php
include "auth.php";
include "../config/koneksi.php";

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if($event_id == 0){
    die("Event ID tidak valid. <a href='absen.php'>Kembali</a>");
}

$event = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id='$event_id'"));

if(!$event){
    die("Event tidak ditemukan. <a href='absen.php'>Kembali</a>");
}

$data = mysqli_query($conn, "
    SELECT u.nama, u.nim, u.kelas, u.semester, COALESCE(a.waktu, a.created_at) as waktu_absen
    FROM absen a
    JOIN users u ON a.user_id = u.id
    WHERE a.event_id = '$event_id'
    ORDER BY a.id ASC
");

$filename = "Absensi_" . preg_replace('/[^A-Za-z0-9]/', '_', $event['nama_event']) . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
<table border="1">
    <tr>
        <th colspan="6" style="font-size:16px; text-align:center;">
            Daftar Hadir: <?= htmlspecialchars($event['nama_event']) ?>
        </th>
    </tr>
    <tr>
        <th colspan="6">Tanggal Export: <?= date('d-m-Y H:i:s') ?></th>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr style="background:#007bff; color:white; font-weight:bold;">
        <th>No</th>
        <th>Nama</th>
        <th>NIM</th>
        <th>Kelas</th>
        <th>Semester</th>
        <th>Waktu Hadir</th>
    </tr>
    <?php 
    $no = 1;
    while($row = mysqli_fetch_assoc($data)): 
    ?>
    <tr>
        <td><?= $no++ ?></td>
        <td><?= htmlspecialchars($row['nama']) ?></td>
        <td><?= htmlspecialchars($row['nim']) ?></td>
        <td><?= htmlspecialchars($row['kelas']) ?></td>
        <td><?= htmlspecialchars($row['semester']) ?></td>
        <td><?= $row['waktu_absen'] ?></td>
    </tr>
    <?php endwhile; ?>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td colspan="6"><strong>Total Peserta: <?= $no - 1 ?> orang</strong></td>
    </tr>
</table>
</body>
</html>
