<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get year filter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$startDate = "$year-01-01 00:00:00";
$endDate = "$year-12-31 23:59:59";

// Get attendance data - LEFT JOIN agar tetap muncul walau event dihapus
$stmt = $conn->prepare("
    SELECT a.*, 
           COALESCE(e.nama_event, 'Event Telah Dihapus') as nama_event, 
           COALESCE(e.waktu_mulai, a.waktu) as waktu_mulai,
           e.lokasi
    FROM absen a
    LEFT JOIN events e ON a.event_id = e.id
    WHERE a.user_id = ? AND YEAR(a.waktu) = ?
    ORDER BY a.waktu ASC
");
$stmt->bind_param("ii", $user_id, $year);
$stmt->execute();
$attendances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$totalAttendance = count($attendances);

// Get total events in year (yang masih ada)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE YEAR(waktu_mulai) = ?");
$stmt->bind_param("i", $year);
$stmt->execute();
$totalEventsYear = $stmt->get_result()->fetch_assoc()['total'];

$attendanceRate = $totalEventsYear > 0 ? round(($totalAttendance / $totalEventsYear) * 100, 1) : 0;

// Indonesian month names
$months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kehadiran - <?= htmlspecialchars($user['nama']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #fff; padding: 20px; color: #000; font-size: 11pt; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { text-align: center; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 16pt; font-weight: bold; margin-bottom: 3px; }
        .header p { font-size: 10pt; }
        
        .section-title { font-weight: bold; margin: 20px 0 10px 0; font-size: 11pt; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; font-size: 10pt; }
        th { background: #eee; font-weight: bold; }
        
        .info-table td { border: none; padding: 3px 8px; }
        .info-table td:first-child { width: 120px; font-weight: bold; }
        
        .summary-table { margin-top: 10px; }
        .summary-table th, .summary-table td { text-align: center; }
        
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #999; font-size: 9pt; color: #666; text-align: center; }
        
        .print-btn { 
            position: fixed; bottom: 20px; right: 20px; 
            background: #333; color: white; border: none; 
            padding: 10px 20px; cursor: pointer; font-size: 11pt;
        }
        .print-btn:hover { background: #000; }
        
        .empty { text-align: center; padding: 20px; color: #666; font-style: italic; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAPORAN KEHADIRAN</h1>
            <p>Periode Tahun <?= $year ?></p>
        </div>
        
        <!-- Data Diri -->
        <div class="section-title">DATA DIRI</div>
        <table class="info-table">
            <tr>
                <td>Nama</td>
                <td>: <?= htmlspecialchars($user['nama']) ?></td>
            </tr>
            <tr>
                <td>Email</td>
                <td>: <?= htmlspecialchars($user['email']) ?></td>
            </tr>
            <?php if (!empty($user['nim'])): ?>
            <tr>
                <td>NIM</td>
                <td>: <?= htmlspecialchars($user['nim']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($user['kelas'])): ?>
            <tr>
                <td>Kelas</td>
                <td>: <?= htmlspecialchars($user['kelas']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Tanggal Cetak</td>
                <td>: <?= date('d') ?> <?= $months[date('n') - 1] ?> <?= date('Y, H:i') ?> WIB</td>
            </tr>
        </table>
        
        <!-- Ringkasan -->
        <div class="section-title">RINGKASAN KEHADIRAN</div>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Total Event</th>
                    <th>Hadir</th>
                    <th>Tidak Hadir</th>
                    <th>Persentase Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $totalEventsYear ?></td>
                    <td><?= $totalAttendance ?></td>
                    <td><?= max(0, $totalEventsYear - $totalAttendance) ?></td>
                    <td><?= $attendanceRate ?>%</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Riwayat -->
        <div class="section-title">RIWAYAT KEHADIRAN</div>
        <?php if (empty($attendances)): ?>
            <p class="empty">Belum ada data kehadiran untuk tahun <?= $year ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width:35px">No</th>
                        <th style="width:90px">Tanggal</th>
                        <th>Nama Event</th>
                        <th class="text-center" style="width:70px">Jam Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($attendances as $a): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= date('d-m-Y', strtotime($a['waktu_mulai'])) ?></td>
                        <td><?= htmlspecialchars($a['nama_event']) ?></td>
                        <td class="text-center"><?= date('H:i', strtotime($a['waktu'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="footer">
            Dokumen ini digenerate otomatis oleh sistem pada <?= date('d-m-Y H:i:s') ?>
        </div>
    </div>
    
    <button class="print-btn no-print" onclick="window.print()">Cetak PDF</button>
</body>
</html>
