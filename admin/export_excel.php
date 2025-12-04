<?php
include "auth.php";
include "../config/koneksi.php";

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if($event_id == 0){
    die("Event ID tidak valid. <a href='absen.php'>Kembali</a>");
}

// Prepared statement untuk get event
$stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if(!$event){
    die("Event tidak ditemukan. <a href='absen.php'>Kembali</a>");
}

// Prepared statement untuk get data absen
$stmt = mysqli_prepare($conn, "
    SELECT u.nama, u.nim, u.kelas, u.semester, COALESCE(a.waktu, a.created_at) as waktu_absen
    FROM absen a
    JOIN users u ON a.user_id = u.id
    WHERE a.event_id = ?
    ORDER BY a.id ASC
");
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$data = mysqli_stmt_get_result($stmt);
$total = mysqli_num_rows($data);

$filename = "Absensi_" . preg_replace('/[^A-Za-z0-9]/', '_', $event['nama_event']) . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="UTF-8">
    <style>
        /* Style untuk Excel */
        .title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            background-color: #1e293b;
            color: #ffffff;
            padding: 12px;
        }
        .subtitle {
            font-size: 11pt;
            text-align: center;
            background-color: #f1f5f9;
            color: #475569;
            padding: 8px;
        }
        .header {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            background-color: #334155;
            color: #ffffff;
            padding: 10px;
            border: 1px solid #1e293b;
        }
        .data {
            font-size: 10pt;
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .data-center {
            font-size: 10pt;
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .row-even {
            background-color: #f8fafc;
        }
        .row-odd {
            background-color: #ffffff;
        }
        .footer {
            font-size: 11pt;
            font-weight: bold;
            background-color: #f1f5f9;
            padding: 10px;
            text-align: left;
        }
        .info {
            font-size: 9pt;
            color: #64748b;
            text-align: left;
            padding: 6px;
        }
    </style>
</head>
<body>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <!-- Header Title -->
    <tr>
        <td colspan="6" class="title">
            DAFTAR HADIR
        </td>
    </tr>
    <tr>
        <td colspan="6" class="subtitle">
            <?= htmlspecialchars($event['nama_event']) ?>
        </td>
    </tr>
    
    <!-- Info -->
    <tr>
        <td colspan="6" class="info">
            Tanggal Export: <?= date('d F Y, H:i:s') ?> WIB
        </td>
    </tr>
    <tr>
        <td colspan="6" class="info">
            Total Peserta: <?= $total ?> orang
        </td>
    </tr>
    
    <!-- Spacer -->
    <tr><td colspan="6" height="10"></td></tr>
    
    <!-- Table Header -->
    <tr>
        <td class="header" width="5%">No</td>
        <td class="header" width="30%">Nama Lengkap</td>
        <td class="header" width="15%">NIM</td>
        <td class="header" width="12%">Kelas</td>
        <td class="header" width="10%">Semester</td>
        <td class="header" width="28%">Waktu Hadir</td>
    </tr>
    
    <!-- Data Rows -->
    <?php 
    $no = 1;
    while($row = mysqli_fetch_assoc($data)): 
        $rowClass = ($no % 2 == 0) ? 'row-even' : 'row-odd';
    ?>
    <tr class="<?= $rowClass ?>">
        <td class="data-center"><?= $no++ ?></td>
        <td class="data"><?= htmlspecialchars($row['nama']) ?></td>
        <td class="data-center"><?= htmlspecialchars($row['nim']) ?></td>
        <td class="data-center"><?= htmlspecialchars($row['kelas']) ?></td>
        <td class="data-center"><?= htmlspecialchars($row['semester']) ?></td>
        <td class="data-center"><?= date('d-m-Y H:i:s', strtotime($row['waktu_absen'])) ?></td>
    </tr>
    <?php endwhile; ?>
    
    <!-- Spacer -->
    <tr><td colspan="6" height="10"></td></tr>
    
    <!-- Footer -->
    <tr>
        <td colspan="6" class="footer">
            Total Peserta: <?= $no - 1 ?> orang
        </td>
    </tr>
    
    <!-- Signature Area -->
    <tr><td colspan="6" height="30"></td></tr>
    <tr>
        <td colspan="3" class="info"></td>
        <td colspan="3" class="info" style="text-align:center;">
            Mengetahui,<br><br><br><br>
            (_______________________)
        </td>
    </tr>
</table>
</body>
</html>
